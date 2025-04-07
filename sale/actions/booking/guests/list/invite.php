<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use core\Mail;
use equal\email\Email;
use sale\booking\Booking;
use sale\booking\GuestList;

list($params, $providers) = eQual::announce([
    'description'   => "Invite the customer to complete the guests list. If the guests list does not exist, it will be created.",
    'params'        => [
        'id' => [
            'description'       => 'Identifier of the targeted booking.',
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\GuestList',
            'required'          => true
        ],
        'email' => [
            'description'       => "(optional) The email address to which the invitation will be sent.",
            'help'              => "The email address must match one of the booking contact addresses.",
            'type'              => 'string',
            'usage'             => 'email'
        ],
        'lang' =>  [
            'description'   => 'Language of the reminder which is defined by the responsible.',
            'type'          => 'string',
            // #memo - lang impact the template selection and is set to DEFAULT_LANG if not provided andno lang is set on customer
            // 'default'       => constant('DEFAULT_LANG')
        ],
    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'constants'     => ['BACKEND_URL'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context' , 'cron']
]);

/**
 * @var \equal\php\Context      $context
 * @var \equal\cron\Scheduler   $cron
 */
['context' => $context , 'cron' => $cron] = $providers;


/**
 * Methods
 */

$getBookingContactByEmail = function($contacts, $email) {
    foreach($contacts as $contact) {
        if(strtolower($contact['email']) === $email) {
            return $contact;
        }
    }

    return null;
};

$getBookingContact = function($contacts) {
    $contact = null;
    foreach($contacts as $c) {
        if(strlen($c['email'] ?? '') <= 0) {
            continue;
        }

        if(in_array($c['type'], ['guest_list', 'booking']) || is_null($contact)) {
            $contact = $c;
            if($c['type'] === 'guest_list') {
                break;
            }
        }
    }

    return $contact;
};

$getTemplatePartValueByName = function($parts, $part_name) {
    foreach($parts as $part) {
        if($part['name'] === $part_name) {
            return $part['value'];
        }
    }
    return null;
};


$booking = Booking::id($params['id'])
    ->read([
        'id',
        'name', 'date_from', 'date_to',
        'guest_list_id',
        'customer_id'  => ['partner_identity_id' => ['lang_id' => ['code']]],
        'contacts_ids' => ['type', 'email'],
        'center_id'    => ['id', 'name', 'template_category_id']
    ])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if(!$booking['guest_list_id']) {
    $guest_list = GuestList::create(['booking_id' =>  $booking['id']])->read(['id'])->first(true);
    $guest_list_id = $guest_list['id'];
}
else {
    $guest_list_id = $booking['guest_list_id'];
}

$guest_list = GuestList::id($guest_list_id)
    ->read([
        'id',
        'status'
    ])
    ->first(true);

if(!$guest_list) {
    throw new Exception("unknown_guest_list", QN_ERROR_UNKNOWN_OBJECT);
}

$contact = null;
if(isset($params['email'])) {
    $params['email'] = strtolower(trim($params['email']));
    if(!preg_match('/^([_a-z0-9-]+)(\.[_a-z0-9-]+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,13})$/', $params['email'])) {
        throw new Exception('invalid_email_address', QN_ERROR_INVALID_PARAM);
    }

    $contact = $getBookingContactByEmail($booking['contacts_ids'], $params['email']);
    if(is_null($contact)) {
        throw new Exception('invalid_email_address', QN_ERROR_INVALID_PARAM);
    }
}
else {
    $contact = $getBookingContact($booking['contacts_ids']);
    if(is_null($contact)) {
        throw new Exception('missing_booking_contact_with_email', QN_ERROR_INVALID_PARAM);
    }
}

if(!isset($params['lang']) || !$params['lang']) {
    $params['lang'] = constant('DEFAULT_LANG');
    if(isset($booking['customer_id']['partner_identity_id']['lang_id']['code'])) {
        $params['lang'] = $booking['customer_id']['partner_identity_id']['lang_id']['code'];
    }
}

$template = Template::search([
        // common category [KA - 6]
        ['category_id', '=', 6],
        ['type', '=', 'contract'],
        ['code', '=', 'guestslist_invite']
    ])
    ->read(['parts_ids' => ['name', 'value']], $params['lang'])
    ->first(true);

if(is_null($template)) {
    throw new Exception("unknown_template", QN_ERROR_UNKNOWN_OBJECT);
}

$subject = $getTemplatePartValueByName($template['parts_ids'], 'subject');
$body = $getTemplatePartValueByName($template['parts_ids'], 'body');

if(is_null($subject) || is_null($body)) {
    throw new Exception('invalid_template', QN_ERROR_INVALID_PARAM);
}

// remove paragraph html tags, if any
$subject = strip_tags($subject);

$data = [
    'booking'   => $booking['name'],
    'center'    => $booking['center_id']['name'],
    'date_from' => date('d/m/Y', $booking['date_from']),
    'date_to'   => date('d/m/Y', $booking['date_to'])
];
foreach($data as $key => $val) {
    $subject = str_replace('{'.$key.'}', $val, $subject);
}

$url = constant('BACKEND_URL').'/guests/#/request/'.$booking['id'];
$body = str_replace('{link}', "<p><a href=\"$url\">$url</a></p>", $body);

try {
    $data = eQual::run('get', 'identity_center-signature', [
        'center_id' => $booking['center_id']['id'],
        'lang'      => $params['lang']
    ]);
    $body .= $data['signature'] ?? '';
}
catch(Exception $e) {}

$message = new Email();
$message->setTo($contact['email'])
    ->setSubject($subject)
    ->setContentType('text/html')
    ->setBody($body);

Mail::queue($message, 'sale\booking\Booking', $booking['id']);

// schedule a task in 10 days to check that composition has been received
$cron->schedule(
        "booking.guest.email.send.{$booking['id']}",
        time() + 10 * 86400,
        'sale_booking_guests_list_check-sent',
        [ 'id' => $booking['id']]
    );

$context->httpResponse()
        ->status(204)
        ->send();

<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;
use core\Mail;
use equal\email\Email;
use communication\Template;

list($params, $providers) = eQual::announce([
    'description'   => "Send an email to remind them to send the guest list.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking for the send reminder.',
            'type'          => 'integer',
            'required'      => true
        ],
        'email' =>  [
            'description'   => 'Email of the person who is responsible for the guest List.',
            'type'          => 'string',
            'required'      => true
        ],
        'lang' =>  [
            'description'   => 'Language of the reminder which is defined by the responsible.',
            'type'          => 'string',
            'default'       => constant('DEFAULT_LANG')
        ],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch']];

$booking = Booking::id($params['id'])
    ->read([
        'id',
        'name',
        'center_office_id' => ['id','email', 'email_bcc'],
        'status',
        'date_from',
        'date_to',
        'center_id' => ['name', 'template_category_id']
    ])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$template = Template::search([
        // common category [KA - 6]
        ['category_id', '=', 6],
        ['type', '=', 'contract'],
        ['code', '=', 'guestslist_reminder']
    ])
    ->read(['parts_ids' => ['name', 'value']], $params['lang'])
    ->first(true);

$result = [];

if(!$template){
    throw new Exception('missing_mandatory_template', EQ_ERROR_INVALID_CONFIG);
}

$body = $title = '';
foreach($template['parts_ids'] as $part) {
    if($part['name'] == 'subject') {
        $title = strip_tags($part['value']);
        $data = [
            'booking'   => $booking['name'],
            'center'    => $booking['center_id']['name'],
            'date_from' => date('d/m/Y', $booking['date_from']),
            'date_to'   => date('d/m/Y', $booking['date_to'])
        ];
        foreach($data as $key => $val) {
            $title = str_replace('{'.$key.'}', $val, $title);
        }
    }
    elseif($part['name'] == 'body') {
        $url = constant('BACKEND_URL').'/guests/#/request/'.$booking['id'];
        $body = str_replace('{link}', "<a href=\"$url\">$url</a>", $part['value']);
    }
}

try {
    $data = eQual::run('get', 'identity_center-signature', [
        'center_id' => $booking['center_id']['id'],
        'lang'      => $params['lang']
    ]);
    $body .= $data['signature'] ?? '';
}
catch(Exception $e) {}

$message = new Email();
$message->setTo($params['email'])
    ->setReplyTo($booking['center_office_id']['email'])
    ->setSubject($title)
    ->setContentType('text/html')
    ->setBody($body);

$bcc = isset($booking['center_office_id']['email_bcc'])?$booking['center_office_id']['email_bcc']:'';
if(strlen($bcc)) {
    $message->addBcc($bcc);
}

Mail::queue($message, 'sale\booking\Booking', $booking['id']);
$result = $booking['id'];


$httpResponse->body($result)
             ->send();

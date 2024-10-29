<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\GuestList;

list($params, $providers) = eQual::announce([
    'description'   => "Submit the guest list.",
    'params'        => [
        'id' => [
            'description'       => "Identifier of the targeted Guest List.",
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\GuestList',
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility'    => 'public'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

// validate and retrieve guest user info
try {
    $guest_user = eQual::run('get','sale_booking_guests_userinfo');
}
catch(Exception $e) {
    if(php_sapi_name() != 'cli') {
        throw $e;
    }
    $guest_user = null;
}


// switch to root user (to avoid permissions restrictions)
$auth->su();

$guest_list = GuestList::id($params['id'])
    ->read([
        'id',
        'status',
        'booking_id',
        'guest_list_items_ids' => [
            'booking_line_group_id',
            'firstname', 'lastname', 'gender', 'date_of_birth', 'citizen_identification', 'is_coordinator',
            'address_street', 'address_zip', 'address_city', 'address_country'
    ]])
    ->first(true);

if(!$guest_list) {
    throw new Exception("unknown_guest_list", QN_ERROR_UNKNOWN_OBJECT);
}

if($guest_list['status'] != 'pending') {
    throw new Exception('not_a_pending_guest_list', QN_ERROR_INVALID_PARAM);
}

if(!is_null($guest_user) && $guest_user['booking_id'] !== $guest_list['booking_id']) {
    throw new Exception("not_allowed", QN_ERROR_NOT_ALLOWED);
}

// generate items for Composition
$items = [];

foreach($guest_list['guest_list_items_ids'] as $item) {
    $address_parts = [];
    foreach(['address_street', 'address_zip', 'address_city'] as $part) {
        if(!empty($item[$part])) {
            $address_parts[] = $item[$part];
        }
    }

    $items[] = [
            'firstname'              => $item['firstname'],
            'lastname'               => $item['lastname'],
            'gender'                 => $item['gender'],
            'date_of_birth'          => $item['date_of_birth'],
            'is_coordinator'         => $item['is_coordinator'],
            'citizen_identification' => $item['citizen_identification'],
            'email'                  => '',
            'phone'                  => '',
            'address'                => implode(' ', $address_parts),
            'country'                => $item['address_country']
        ];
}

try {
    eQual::run('do', 'sale_booking_composition_generate', [
            'booking_id'    => $guest_list['booking_id'],
            'data'          => $items
        ]);
}
catch(Exception $e) {
    // ignore errors at this stage
    trigger_error('APP::error at composition generation'.$e->getMessage(), QN_REPORT_WARNING);
}

// mark Guest List as sent (cannot be modified anymore)
GuestList::id($params['id'])
    ->update(['status' => 'sent']);

$context->httpResponse()
        ->status(204)
        ->send();

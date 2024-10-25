<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;
use sale\booking\GuestList;

list($params, $providers) = eQual::announce([
    'description'   => "Return the booking with the booking line groups and guest line. If the guests list does not exist, it will be created.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted booking.',
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\Booking',
            'required'          => true
        ]
    ],
    'access' => [
        'visibility'        => 'public'
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
list('context' => $context, 'auth' => $auth) = $providers;

$current_user_id = $auth->userId();
if($current_user_id != QN_ROOT_USER_ID) {
    $guest_user = eQual::run('get','sale_booking_guests_userinfo');

    if(!$guest_user) {
        throw new Exception("unknown_guest_user", QN_ERROR_UNKNOWN_OBJECT);
    }

    if($guest_user['booking_id'] !== $params['id']) {
        throw new Exception("not_allowed_guest_user_access_to_booking", QN_ERROR_NOT_ALLOWED);
    }
    // switch to root user (to avoid permissions restrictions)
    $auth->su();
}

$booking = Booking::id($params['id'])
    ->read(['id', 'guest_list_id'])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if(!$booking['guest_list_id']) {
    GuestList::create(['booking_id' =>  $booking['id']]);
}

$booking = Booking::id($params['id'])
        ->read([
            'id',
            'status',
            'name',
            'center_id' => [
                'id', 'name', 'email', 'phone', 'address_street', 'address_city', 'address_zip', 'address_country'
            ],
            'booking_lines_groups_ids' => [
                'id', 'name', 'date_from', 'date_to', 'group_type', 'nb_pers', 'nb_children'
            ],
            'guest_list_id' => [
                'id',
                'status',
                'guest_list_items_ids' => [
                    'booking_line_group_id',
                    'is_coordinator',
                    'firstname', 'lastname', 'gender', 'date_of_birth', 'citizen_identification',
                    'address_street', 'address_zip', 'address_city', 'address_country'
                ]
            ]
        ])
        ->adapt('json')
        ->first(true);

$booking['booking_lines_groups_ids'] = array_values(
        array_filter($booking['booking_lines_groups_ids'], function($group) {
            return $group['group_type'] === 'sojourn';
        })
    );

$context->httpResponse()
        ->body($booking)
        ->send();

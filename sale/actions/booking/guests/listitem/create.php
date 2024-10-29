<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BookingLineGroup;
use sale\booking\GuestList;

list($params, $providers) = eQual::announce([
    'description'   => "Create an empty guest list item based on the guest list ID and booking line group ID.",
    'params'        => [
        'entity' =>  [
            'description'   => 'Full name (including namespace) of the class to look into.',
            'type'          => 'string',
            'default'       => 'sale\booking\GuestListItem'
        ],
        'guest_list_id' => [
            'description'   => 'Guest List id.',
            'type'          => 'integer',
            'required'      => true
        ],
        'booking_line_group_id' => [
            'description'   => 'Booking Line Group id.',
            'type'          => 'integer',
            'required'      => true
        ],
        'fields' => [
            'description'   => 'Other values to assign to new Guest List Item.',
            'type'          => 'array',
            'default'       => []
        ],
        'lang' => [
            'description '  => 'Specific language for multilang field.',
            'type'          => 'string',
            'default'       => constant('DEFAULT_LANG')
        ]
    ],
    'constants'     => ['DEFAULT_LANG'],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'access'        => [
        'visibility'    => 'public'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
list('context' => $context, 'auth' => $auth) = $providers;

$guest_user = null;

$current_user_id = $auth->userId();
if($current_user_id != QN_ROOT_USER_ID) {
    $guest_user = eQual::run('get','sale_booking_guests_userinfo');
    if(!$guest_user) {
        throw new Exception("unknown_guest_user", QN_ERROR_UNKNOWN_OBJECT);
    }
}

$guest_list = GuestList::id($params['guest_list_id'])->read(['id', 'booking_id', 'guest_list_items_ids' => ['booking_line_group_id']])->first(true);
if(!$guest_list) {
    throw new Exception("unknown_guest_list", QN_ERROR_UNKNOWN_OBJECT);
}

$booking_line_group = BookingLineGroup::id($params['booking_line_group_id'])->read(['id', 'booking_id', 'nb_pers'])->first(true);
if(!$booking_line_group) {
    throw new Exception("unknown_booking_line_group_id", QN_ERROR_UNKNOWN_OBJECT);
}

if($guest_list['booking_id'] !== $booking_line_group['booking_id']) {
    throw new Exception("booking_mismatch", QN_ERROR_INVALID_PARAM);
}

if( !is_null($guest_user)
    && (
        $guest_user['booking_id'] !== $guest_list['booking_id']
        || $guest_user['booking_id'] !== $booking_line_group['booking_id']
    )
) {
    throw new Exception("not_allowed", QN_ERROR_NOT_ALLOWED);
}

$group_guest_list_item_qty = 0;
foreach($guest_list['guest_list_items_ids'] as $guest_list_item) {
    if($guest_list_item['booking_line_group_id'] === $booking_line_group['id']) {
        $group_guest_list_item_qty++;
    }
}

if($group_guest_list_item_qty >= $booking_line_group['nb_pers']) {
    throw new Exception("booking_line_group_enough_guest_list_items", QN_ERROR_INVALID_PARAM);
}

$params['fields']['guest_list_id'] = $params['guest_list_id'];
$params['fields']['booking_line_group_id'] = $params['booking_line_group_id'];

if($params['entity'] !== 'sale\booking\GuestListItem') {
    throw new Exception("invalid_entity", QN_ERROR_INVALID_PARAM);
}

$auth->su();
$result = eQual::run('do', 'model_create', $params);
$auth->su(0);

$context->httpResponse()
        ->body($result)
        ->send();

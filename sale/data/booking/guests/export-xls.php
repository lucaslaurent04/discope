<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => 'Returns descriptor of current User, based on received guest_access_token (no user_id).',
    'response'      => [
        'content-type'          => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'content-disposition'   => 'inline; filename="export.xlsx"',
        'accept-origin'         => '*',
        'errors'                => ['invalid_token', 'malformed_token', 'expired_token']
    ],
    'constants'     => ['AUTH_SECRET_KEY'],
    'access'        => [
        'visibility'        => 'public'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;

$user_id = $auth->userId();
if($user_id != QN_ROOT_USER_ID) {
    $guest_user = eQual::run('get','sale_booking_guests_userinfo');

    if(!$guest_user) {
        throw new Exception("unknown_guest_user", EQ_ERROR_NOT_ALLOWED);
    }
}

$auth->su();

$booking = Booking::id($guest_user['booking_id'])
    ->read([
        'id',
        'date_to',
        'guest_list_id'
    ])
    ->first();

if($booking['date_to'] < time()) {
    throw new Exception('expired_booking', EQ_ERROR_NOT_ALLOWED);
}

$result = eQual::run('get','model_export-xls', [
    'entity'    => 'sale\booking\GuestListItem',
    'view_id'   => 'list.default',
    'domain'    => ['guest_list_id', '=', $booking['guest_list_id']],
    'controller'=> 'model_collect',
    'lang'      => 'fr',
    'params'    => ['limit' => 500]
    ]);

$context->httpResponse()
        ->body($result)
        ->send();

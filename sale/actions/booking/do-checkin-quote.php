<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;
use sale\booking\Contract;

[$params, $providers] = eQual::announce([
    'description'   => "Update the status of given booking from 'quote' to its previous 'checkedin' status.",
    'params'        => [

        'id' =>  [
            'description'       => 'Identifier of the targeted booking.',
            'type'              => 'integer',
            'min'               => 1,
            'required'          => true
        ]

    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$booking = Booking::id($params['id'])
    ->read(['status', 'status_before_revert_to_quote'])
    ->first();

if(is_null($booking)) {
    throw new Exception("unknown_booking", EQ_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] !== 'quote') {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

if($booking['status_before_revert_to_quote'] !== 'checkedin') {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

eQual::run('do', 'sale_booking_do-option-confirm', [
    'id'                => $params['id'],
    'instant_payment'   => false
]);

$pending_contract = Contract::search([
    ['booking_id', '=', $booking['id']],
    ['status', '=','pending']
])
    ->read(['id'])
    ->first();

eQual::run('do', 'sale_contract_signed', ['id' => $pending_contract['id']]);

eQual::run('do', 'sale_booking_do-checkin', [
    'id'                        => $params['id'],
    'no_payment'                => true,
    'no_composition'            => true,
    'no_rental_unit_cleaned'    => true
]);

Booking::id($booking['id'])->update(['status_before_revert_to_quote' => null]);

$context->httpResponse()
        ->status(204)
        ->send();

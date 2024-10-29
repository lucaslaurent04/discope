<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;
use sale\booking\Consumption;

[$params, $providers] = eQual::announce([
    'description'   => "Release any previously assigned rental unit, by removing all consumptions.",
    'params'        => [
        'id' =>  [
            'type'          => 'integer',
            'description'   => "Identifier of the targeted booking.",
            'min'           => 1,
            'required'      => true
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
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$booking = Booking::id($params['id'])
    ->read(['id', 'status'])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] != 'quote') {
    throw new Exception("invalid_status", QN_ERROR_INVALID_PARAM);
}

Consumption::search(['booking_id', '=', $params['id']])
    ->delete(true);

$context->httpResponse()
        ->status(204)
        ->send();

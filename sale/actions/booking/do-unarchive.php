<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;

[$params, $providers] = eQual::announce([
    'description'   => "This unarchives the archived reservation.",
    'params'        => [

        'id' =>  [
            'type'          => 'integer',
            'description'   => "Identifier of the booking the check against emptiness.",
            'required'      => true
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

$booking = Booking::id($params['id'])
    ->read(['state'])
    ->first();

if(is_null($booking)) {
    throw new Exception("unknown_booking", EQ_ERROR_UNKNOWN_OBJECT);
}

if($booking['state'] !== 'archive') {
    throw new Exception("invalid_state", EQ_ERROR_INVALID_PARAM);
}

Booking::id($booking['id'])->update(['state' => 'instance']);

$context->httpResponse()
        ->status(204)
        ->send();

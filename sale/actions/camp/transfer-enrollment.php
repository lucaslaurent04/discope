<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\Camp;
use sale\camp\Enrollment;

[$params, $providers] = eQual::announce([
    'description'   => "Transfer an enrollment from one camp to another.",
    'help'          => "Usually used when a camp is canceled.",
    'params'        => [

        'id' => [
            'type'              => 'integer',
            'description'       => "Identifier of the enrollment to transfer.",
            'min'               => 1,
            'required'          => true
        ],

        'camp_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Camp',
            'description'       => "The transfer targeted camp.",
            'required'          => true
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

['context' => $context, 'orm' => $orm] = $providers;

$enrollment = Enrollment::id($params['id'])
    ->read(['camp_id'])
    ->first();

if(is_null($enrollment)) {
    throw new Exception("unknown_enrollment", EQ_ERROR_UNKNOWN_OBJECT);
}

if($enrollment['camp_id'] === $params['camp_id']) {
    throw new Exception("same_camp", EQ_ERROR_INVALID_PARAM);
}

$camp = Camp::id($params['camp_id'])
    ->read(['status'])
    ->first();

if($camp['status'] === 'canceled') {
    throw new Exception("canceled_camp", EQ_ERROR_INVALID_PARAM);
}

// Checks are done in Enrollment::canupdate
Enrollment::id($enrollment['id'])
    ->update(['camp_id' => $camp['id']]);

$context->httpResponse()
        ->status(204)
        ->send();

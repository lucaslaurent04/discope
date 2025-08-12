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
    'help'          => "Usually used when a camp is cancelled.",
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
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['camp.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$enrollment = Enrollment::id($params['id'])
    ->read(['camp_id', 'is_locked'])
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

if($camp['status'] === 'cancelled') {
    throw new Exception("cancelled_camp", EQ_ERROR_INVALID_PARAM);
}

$was_locked = false;
if($enrollment['is_locked']) {
    $was_locked = true;
    Enrollment::id($enrollment['id'])->update(['is_locked' => false]);
}

try {
    // Checks are done in Enrollment::canupdate
    Enrollment::id($enrollment['id'])->update(['camp_id' => $camp['id']]);
}
catch(Exception $e) {
    if($was_locked) {
        Enrollment::id($enrollment['id'])->update(['is_locked' => true]);
    }
    throw $e;
}

if($was_locked) {
    Enrollment::id($enrollment['id'])->update(['is_locked' => true]);
}

$context->httpResponse()
        ->status(204)
        ->send();

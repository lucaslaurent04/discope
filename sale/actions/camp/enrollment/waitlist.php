<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\Enrollment;

[$params, $providers] = eQual::announce([
    'description'   => "Adds the pending enrollment to the waiting list.",
    'params'        => [

        'id' => [
            'type'          => 'integer',
            'description'   => "Identifier of the enrollment we want to add to the waiting list.",
            'required'      => true
        ]

    ],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['camp.default.administrator', 'camp.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$enrollment = Enrollment::id($params['id'])
    ->read(['status', 'camp_id' => ['date_from']])
    ->first();

if(is_null($enrollment)) {
    throw new Exception("unknown_enrollment", EQ_ERROR_UNKNOWN_OBJECT);
}

if($enrollment['status'] !== 'pending') {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

if($enrollment['camp_id']['date_from'] <= time()) {
    throw new Exception("camp_already_started", EQ_ERROR_INVALID_PARAM);
}

Enrollment::id($enrollment['id'])->transition('waitlist');

$context->httpResponse()
        ->status(200)
        ->send();

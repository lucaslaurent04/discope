<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\Enrollment;

[$params, $providers] = eQual::announce([
    'description'   => "Confirms the pending enrollment if the camp has an available spot, else adds it to the waiting list.",
    'params'        => [

        'id' => [
            'type'          => 'integer',
            'description'   => "Identifier of the enrollment we want to confirm.",
            'required'      => true
        ]

    ],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['camp.default.administrator', 'camp.default.user'],
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$enrollment = Enrollment::id($params['id'])
    ->read([
        'status',
        'is_ase',
        'camp_id' => [
            'max_children',
            'ase_quota',
            'enrollments_ids' => ['status', 'is_ase']
        ]
    ])
    ->first();

if($enrollment['status'] !== 'pending') {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

$confirmed_enrollments_qty = 0;
$confirmed_ase_enrollments = 0;
foreach($enrollment['camp_id']['enrollments_ids'] as $en) {
    if(in_array($en['status'], ['confirmed', 'validated'])) {
        $confirmed_enrollments_qty++;

        if($en['is_ase']) {
            $confirmed_ase_enrollments++;
        }
    }
}

$transition = 'confirm';
if($confirmed_enrollments_qty >= $enrollment['camp_id']['max_children']) {
    $status = 'waitlist';
}
if($enrollment['is_ase'] && $confirmed_ase_enrollments >= $enrollment['camp_id']['ase_quota']) {
    $status = 'waitlist';
}

Enrollment::id($enrollment['id'])->transition($transition);

$context->httpResponse()
        ->status(200)
        ->send();

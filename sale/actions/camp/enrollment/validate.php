<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\Enrollment;

[$params, $providers] = eQual::announce([
    'description'   => "Validates the confirmed enrollment when all documents have been received.",
    'params'        => [

        'id' => [
            'type'          => 'integer',
            'description'   => "Identifier of the enrollment we want to validate.",
            'required'      => true
        ],

        'do_not_check_payment' => [
            'type'          => 'boolean',
            'description'   => "Check enrollment's payment status before validation?",
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

// force check payment if param not given
if(!isset($params['do_not_check_payment'])) {
    $params['do_not_check_payment'] = false;
}

$enrollment = Enrollment::id($params['id'])
    ->read(['status', 'all_documents_received', 'payment_status'])
    ->first();

if(is_null($enrollment)) {
    throw new Exception("unknown_enrollment", EQ_ERROR_UNKNOWN_OBJECT);
}

if($enrollment['status'] !== 'confirmed') {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

if(!$enrollment['all_documents_received']) {
    throw new Exception("missing_document", EQ_ERROR_INVALID_PARAM);
}

if(!$params['do_not_check_payment'] && $enrollment['payment_status'] !== 'paid') {
    throw new Exception("not_paid", EQ_ERROR_INVALID_PARAM);
}

Enrollment::id($enrollment['id'])->transition('validate');

$context->httpResponse()
        ->status(200)
        ->send();

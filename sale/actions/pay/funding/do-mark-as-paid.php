<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\pay\Funding;

[$params, $providers] = eQual::announce([
    'description'   => "Mark a funding as paid even if not all payments received.",
    'params'        => [

        'id' =>  [
            'type'          => 'integer',
            'description'   => "Identifier of the Funding to mark as paid.",
            'min'           => 1,
            'required'      => true
        ]

    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['finance.default.user', 'sale.default.user'],
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

$funding = Funding::id($params['id'])
    ->read(['enrollment_id', 'is_paid', 'due_amount'])
    ->first();

if(is_null($funding)) {
    throw new Exception("unknown_funding", EQ_ERROR_UNKNOWN_OBJECT);
}

if(is_null($funding['enrollment_id'])) {
    throw new Exception("not_enrollment_funding", EQ_ERROR_INVALID_PARAM);
}

if($funding['is_paid']) {
    throw new Exception("funding_already_paid", EQ_ERROR_INVALID_PARAM);
}

Funding::id($funding['id'])->update([
    'is_paid'       => true,
    'paid_amount'   => $funding['due_amount']
]);

$context->httpResponse()
        ->status(204)
        ->send();

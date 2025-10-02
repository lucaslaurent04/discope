<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\pay\Funding;
use sale\pay\Payment;

[$params, $providers] = eQual::announce([
    'description'   => "Remove the manual payment attached to the funding, if any, and unmark funding as paid.",
    'help'          => "Manual payments can be undone while the enrollment is not validated (and invoiced).",
    'params'        => [

        'id' =>  [
            'type'          => 'integer',
            'min'           => 1,
            'description'   => "Identifier of the targeted funding.",
            'required'      => true
        ]

    ],
    'access'        => [
        'groups'        => ['finance.default.administrator', 'finance.default.user']
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
    ->read(['is_paid', 'enrollment_id' => ['status']])
    ->first(true);

if(is_null($funding)) {
    throw new Exception("unknown_funding", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$funding['is_paid']) {
    throw new Exception("funding_not_paid", EQ_ERROR_INVALID_PARAM);
}

if(is_null($funding['enrollment_id'])) {
    throw new Exception("not_enrollment_funding", EQ_ERROR_INVALID_PARAM);
}

if($funding['enrollment_id']['status'] === 'validate') {
    throw new Exception("booking_validated", EQ_ERROR_INVALID_PARAM);
}

if($funding['enrollment_id']['status'] === 'cancelled') {
    throw new Exception("enrollment_cancelled", EQ_ERROR_INVALID_PARAM);
}

// remove manual payments, if any
$payments = Payment::search([
    ['funding_id', '=', $funding['id']],
    ['is_manual', '=', true]
])
    ->delete(true);

Funding::id($params['id'])
    ->update(['status' => 'pending'])
    ->update(['paid_amount' => null])
    ->update(['is_paid' => null]);

$context->httpResponse()
        ->status(205)
        ->send();

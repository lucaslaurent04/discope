<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\pay\BankCheck;
use sale\pay\Funding;
use sale\pay\Payment;

[$params, $providers] = eQual::announce([
    'description'   => "Register a manual payment using a bank check to settle the outstanding balance of a funding.",
    'help'          => "This action processes a manual payment via bank check, linking it to the corresponding funding and marking it as paid.",
    'params'        => [

        'id' =>  [
            'type'              => 'integer',
            'description'       => "Identifier of the targeted bank check.",
            'required'          => true
        ],

        'confirm' =>  [
            'type'              => 'boolean',
            'description'       => "Manual confirmation.",
            'required'          => true
        ]

    ],
    'access'        => [
        'groups'        => ['booking.default.user', 'finance.default.administrator', 'finance.default.user']
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

if(!$params['confirm']) {
    throw new Exception("missing_confirmation", EQ_ERROR_MISSING_PARAM);
}

$bank_check = BankCheck::id($params['id'])
    ->read(['id', 'funding_id', 'payment_id', 'amount', 'emission_date'])
    ->first(true);

if(is_null($bank_check)){
    throw new Exception("unknown_bankcheck", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!is_null($bank_check['payment_id'])){
    throw new Exception("payment_already_associated", EQ_ERROR_INVALID_PARAM);
}

$funding = Funding::id($bank_check['funding_id'])
    ->read(['enrollment_id', 'center_office_id', 'paid_amount', 'due_amount'])
    ->first(true);

if(is_null($funding)) {
    throw new Exception("unknown_funding", EQ_ERROR_UNKNOWN_OBJECT);
}

$sign = ($funding['due_amount'] >= 0) ? 1 : -1;
$remaining_amount = abs($funding['due_amount']) - abs($funding['paid_amount']);

if($remaining_amount <= 0) {
    throw new Exception("nothing_to_pay", EQ_ERROR_INVALID_PARAM);
}

$payment = Payment::create([
        'enrollment_id'     => $funding['enrollment_id'],
        'center_office_id'  => $funding['center_office_id'],
        'bank_check_id'     => $bank_check['id'],
        'is_manual'         => true,
        'amount'            => $bank_check['amount'],
        'payment_origin'    => 'cashdesk',
        'payment_method'    => 'bank_check',
        'receipt_date'      => $bank_check['emission_date']
    ])
    // this updated funding paid status
    ->update([
        'funding_id'        => $funding['id']
    ])
    ->read(['id'])
    ->first(true);

BankCheck::id($bank_check['id'])
    ->update([
        'payment_id'    => $payment['id'],
        'status'        => 'paid'
    ]);

$context->httpResponse()
        ->status(205)
        ->send();

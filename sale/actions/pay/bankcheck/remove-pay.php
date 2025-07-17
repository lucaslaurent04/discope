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
    'description'   => "Detach the payment associated with the specified bank check, if present, and reset the corresponding funding status to 'pending'.",
    'params'        => [

        'id' =>  [
            'description'   => 'Unique identifier of the target bank check.',
            'type'          => 'integer',
            'required'      => true
        ],

        'confirm' =>  [
            'description'   => 'Explicit confirmation required to proceed.',
            'type'          => 'boolean',
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
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

if(!$params['confirm']) {
    throw new Exception("missing_confirmation", EQ_ERROR_MISSING_PARAM);
}

$bank_check = BankCheck::id($params['id'])
    ->read([
        'payment_id'    => ['is_manual'],
        'funding_id'    => ['is_paid', 'paid_amount', 'due_amount']
    ])
    ->first(true);

file_put_contents(QN_LOG_STORAGE_DIR.'/tmp.log', json_encode($bank_check).PHP_EOL, FILE_APPEND | LOCK_EX);

if(is_null($bank_check)) {
    throw new Exception("unknown_bankcheck", EQ_ERROR_UNKNOWN_OBJECT);
}

if(is_null($bank_check['funding_id'])) {
    throw new Exception("unknown_funding", EQ_ERROR_UNKNOWN_OBJECT);
}

if(is_null($bank_check['payment_id'])) {
    throw new Exception("unknown_payment", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$bank_check['payment_id']['is_manual']) {
    throw new Exception("payment_not_manual", EQ_ERROR_INVALID_PARAM);
}

Payment::id($bank_check['payment_id']['id'])
    ->delete(true);

BankCheck::id($bank_check['id'])
    ->update(['payment_id' => null, 'status' => 'pending']);

Funding::id($bank_check['funding_id']['id'])
    ->update(['status' => 'pending'])
    ->update(['paid_amount' => null])
    ->update(['is_paid' => null]);

$context->httpResponse()
        ->status(205)
        ->send();

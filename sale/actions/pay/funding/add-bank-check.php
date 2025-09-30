<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BankCheck;
use sale\booking\Funding;

list($params, $providers) = eQual::announce([
    'description'   => "Creates a bank check and associates it with a funding record.",
    'help'          => "This action generates a new bank check and links it to an existing funding record, updating its status accordingly.  
                    No actual payment transaction is processed. The association can be reversed as long as the booking has not been invoiced.",
    'params'        => [

        'id' =>  [
            'type'              => 'integer',
            'min'               => 1,
            'description'       => "Identifier of the targeted funding.",
            'required'          => true
        ],

        'has_signature' => [
            'type'              => 'boolean',
            'description'       => "Has the bank check  the signature?",
            'required'           => true,
        ],

        'amount' => [
            'type'              => 'float',
            'usage'             => 'amount/money:2',
            'description'       => "The monetary value of the bank check.",
            'default'           => function($id = 0){
                $funding = Funding::id($id)
                    ->read(['due_amount', 'paid_amount'])
                    ->first();

                return !is_null($funding) ? (abs($funding['due_amount']) - abs($funding['paid_amount'])) : 0;
            }
        ]

    ],
    'access'        => [
        'groups'        => ['booking.default.user', 'camp.default.administrator', 'camp.default.user', 'finance.default.administrator', 'finance.default.user']
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

if(!$params['has_signature']) {
    throw new Exception('missing_has_signature', EQ_ERROR_MISSING_PARAM);
}

if($params['amount'] < 0) {
    throw new Exception("invalidated_amount", EQ_ERROR_INVALID_PARAM);
}

$funding = Funding::id($params['id'])
    ->read(['paid_amount', 'due_amount'])
    ->first(true);

if(is_null($funding)) {
    throw new Exception("unknown_funding", EQ_ERROR_UNKNOWN_OBJECT);
}

$sign = $funding['due_amount'] >= 0 ? 1 : -1;
$remaining_amount = abs($funding['due_amount']) - abs($funding['paid_amount']);

if($remaining_amount <= 0) {
    throw new Exception("nothing_to_pay", EQ_ERROR_INVALID_PARAM);
}

BankCheck::create([
    'funding_id'    => $funding['id'],
    'has_signature' => $params['has_signature'],
    'amount'        => $params['amount']
])
    ->read(['id'])
    ->first(true);

$context->httpResponse()
        ->status(205)
        ->send();

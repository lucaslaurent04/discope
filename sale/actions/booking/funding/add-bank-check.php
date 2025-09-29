<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\BankCheck;
use sale\booking\Funding;

[$params, $providers] = eQual::announce([
    'description'   => "Creates a bank check and associates it with a funding record.",
    'help'          => "This action generates a new bank check and links it to an existing funding record, updating its status accordingly.  
                    No actual payment transaction is processed. The association can be reversed as long as the booking has not been invoiced.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted funding.',
            'type'              => 'integer',
            'min'               => 1,
            'required'          => true
        ],

        'has_signature' => [
            'type'              => 'boolean',
            'description'       => "Has the bank check  the signature?",
            'required'           => true,
        ],

        'bank_check_number' => [
            'type'              => 'string',
            'description'       => 'The official unique number assigned to the bank check by the issuing bank.',
        ],

        'amount' => [
            'type'              => 'float',
            'usage'             => 'amount/money:2',
            'description'       => 'The monetary value of the bank check.',
            'default'           => function($id = 0){
                $funding = Funding::id($id)->read(['due_amount', 'paid_amount'])->first(true);
                $remaining_amount = abs($funding['due_amount']) - abs($funding['paid_amount']);
                if(!$funding) {
                    return 0;
                }
                return  $remaining_amount ;}
        ],

    ],
    'access' => [
        'groups'            => ['booking.default.user', 'finance.default.administrator', 'finance.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $om
 */
list($context, $om) = [ $providers['context'], $providers['orm'] ];


if(!$params['has_signature']) {
    throw new Exception('missing_has_signature', EQ_ERROR_MISSING_PARAM);
}

if($params['amount'] < 0) {
    throw new Exception("invalidated_amount", EQ_ERROR_INVALID_PARAM);
}

$funding = Funding::id($params['id'])
            ->read(['paid_amount', 'due_amount'])
            ->first(true);

if(!$funding) {
    throw new Exception("unknown_funding", EQ_ERROR_UNKNOWN_OBJECT);
}

$sign = ($funding['due_amount'] >= 0) ? 1 : -1;
$remaining_amount = abs($funding['due_amount']) - abs($funding['paid_amount']);

if($remaining_amount <= 0) {
    throw new Exception("nothing_to_pay", EQ_ERROR_INVALID_PARAM);
}

BankCheck::create([
        'funding_id'        => $funding['id'],
        'has_signature'     => $params['has_signature'],
        'bank_check_number' => $params['bank_check_number'],
        'amount'            => $params['amount']
    ])
    ->read(['id'])
    ->first(true);

$context->httpResponse()
        ->status(205)
        ->send();

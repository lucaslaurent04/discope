<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\FinancialHelp;
use sale\booking\Funding;
use sale\booking\Payment;

[$params, $providers] = eQual::announce([
    'description'   => "Create a financial help payment for a specific funding.",
    'params'        => [
        'id' =>  [
            'type'              => 'integer',
            'description'       => "Identifier of the targeted funding.",
            'min'               => 1,
            'required'          => true
        ],

        'financial_help_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\FinancialHelp',
            'description'       => "The financial help that will take care of the payment.",
            'domain'            => [
                ['remaining_amount', '>', 0],
                ['date_to', '>', 'date.this.day'],
                ['status', '=', 'pending']
            ],
            'required'          => true
        ],

        'amount' => [
            'type'              => 'float',
            'usage'             => 'amount/money:2',
            'description'       => "The monetary value of the bank check."
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

if($params['amount'] <= 0) {
    throw new Exception("invalid_amount", EQ_ERROR_INVALID_PARAM);
}

$funding = Funding::id($params['id'])
    ->read([
        'center_office_id',
        'is_paid',
        'paid_amount',
        'due_amount',
        'status',
        'booking_id' => ['id', 'customer_id', 'date_from', 'date_to']
    ])
    ->first();

if(is_null($funding)) {
    throw new Exception("unknown_funding", EQ_ERROR_UNKNOWN_OBJECT);
}

if($funding['is_paid']) {
    throw new Exception("funding_already_paid", EQ_ERROR_INVALID_PARAM);
}

if($funding['status'] !== 'pending') {
    throw new Exception("funding_already_invoiced", EQ_ERROR_INVALID_PARAM);
}

if($funding['due_amount'] < 0 || $funding['paid_amount'] < 0) {
    throw new Exception("negative_funding", EQ_ERROR_INVALID_PARAM);
}

$remaining_amount = $funding['due_amount'] - $funding['paid_amount'];
if($remaining_amount <= 0) {
    throw new Exception("nothing_to_pay", EQ_ERROR_INVALID_PARAM);
}

if($params['amount'] > $remaining_amount) {
    throw new Exception("invalid_amount", EQ_ERROR_INVALID_PARAM);
}

$financial_help = FinancialHelp::id($params['financial_help_id'])
    ->read([
        'date_from',
        'date_to',
        'remaining_amount'
    ])
    ->first();

if (
    $financial_help['date_to'] < $funding['booking_id']['date_from']
    || $financial_help['date_from'] > $funding['booking_id']['date_to']
) {
    throw new Exception("invalid_financial_help_dates", EQ_ERROR_INVALID_PARAM);
}

if($params['amount'] > $financial_help['remaining_amount']) {
    throw new Exception("invalid_financial_help_amount", EQ_ERROR_INVALID_PARAM);
}

Payment::create([
    'booking_id'        => $funding['booking_id']['id'],
    'partner_id'        => $funding['booking_id']['customer_id'],
    'center_office_id'  => $funding['center_office_id'],
    'is_manual'         => true,
    'amount'            => $params['amount'],
    'payment_origin'    => 'cashdesk',
    'payment_method'    => 'financial_help',
    'financial_help_id' => $financial_help['id']
])
    ->update(['funding_id' => $funding['id']]);

// #memo - funding is modified in Payment::onupdateFundingId handler

// Sets remaining_amount to null trigger re-calc
FinancialHelp::id($financial_help['id'])
    ->update(['remaining_amount' => null]);

$context->httpResponse()
        ->status(205)
        ->send();

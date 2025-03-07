<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\BankCheck;
use sale\booking\Funding;
use sale\booking\Payment;

list($params, $providers) = eQual::announce([
    'description'   => "Register a manual payment using a bank check to settle the outstanding balance of a funding.",
    'help'          => "This action processes a manual payment via bank check, linking it to the corresponding funding and marking it as paid.",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted bankCheck.',
            'type'              => 'integer',
            'required'          => true
        ],
        'confirm' =>  [
            'description'   => 'Manual confirmation.',
            'type'          => 'boolean',
            'required'      => true
        ]

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

if(!$params['confirm']) {
    throw new Exception('missing_confirmation', EQ_ERROR_MISSING_PARAM);
}

$bankCheck = BankCheck::id($params['id'])->read(['id','funding_id','payment_id', 'amount'])->first(true);

if(!$bankCheck){
    throw new Exception("unknown_bankCheck", QN_ERROR_UNKNOWN_OBJECT);
}

if($bankCheck['payment_id']){
    throw new Exception("payment_already_associated", QN_ERROR_INVALID_PARAM);
}

$funding = Funding::id($bankCheck['funding_id'])
            ->read(['booking_id' => ['id', 'customer_id'], 'invoice_id', 'center_office_id', 'is_paid', 'paid_amount', 'due_amount'])
            ->first(true);

if(!$funding) {
    throw new Exception("unknown_funding", QN_ERROR_UNKNOWN_OBJECT);
}

$sign = ($funding['due_amount'] >= 0)? 1 : -1;
$remaining_amount = abs($funding['due_amount']) - abs($funding['paid_amount']);

if($remaining_amount <= 0) {
    throw new Exception("nothing_to_pay", QN_ERROR_INVALID_PARAM);
}

$payment = Payment::create([
        'booking_id'        => $funding['booking_id']['id'],
        'partner_id'        => $funding['booking_id']['customer_id'],
        'center_office_id'  => $funding['center_office_id'],
        'bank_check_id'     => $bankCheck['id'],
        'is_manual'         => true,
        'amount'            => $bankCheck['amount'],
        'payment_origin'    => 'cashdesk',
        'payment_method'    => 'bank_check'
    ])
    ->update([
        'funding_id'        => $funding['id']
    ])
    ->read(['id'])
    ->first(true);

BankCheck::id($bankCheck['id'])->update(['payment_id' => $payment['id']]);

Funding::id($bankCheck['funding_id'])->update(['is_paid' => true]);

$context->httpResponse()
        ->status(205)
        ->send();

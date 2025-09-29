<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Funding;
use sale\booking\Booking;
use sale\booking\Payment;

list($params, $providers) = eQual::announce([
    'description'   => "Create a manual payment to complete the payments of a funding and mark it as paid.",
    'help'          => "This action is intended for payment with bank card only. Manual payments can be undone while the booking is not fully balanced (and invoiced).",
    'params'        => [

        'id' =>  [
            'type'          => 'integer',
            'min'           => 1,
            'description'   => "Identifier of the targeted funding.",
            'required'      => true
        ]

    ],
    'access'        => [
        'groups'        => ['camp.default.user', 'camp.default.administrator', 'finance.default.administrator', 'finance.default.user']
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
    ->read([
        'is_paid',
        'due_amount',
        'paid_amount',
        'enrollment_id',
        'center_office_id'
    ])
    ->first(true);

if(is_null($funding)) {
    throw new Exception("unknown_funding", EQ_ERROR_UNKNOWN_OBJECT);
}

if($funding['is_paid']) {
    throw new Exception("funding_already_paid", EQ_ERROR_INVALID_PARAM);
}

$sign = ($funding['due_amount'] >= 0) ? 1 : -1;
$remaining_amount = abs($funding['due_amount']) - abs($funding['paid_amount']);

if($remaining_amount <= 0) {
    throw new Exception("nothing_to_pay", EQ_ERROR_INVALID_PARAM);
}

Payment::create([
        'enrollment_id'     => $funding['enrollment_id'],
        'center_office_id'  => $funding['center_office_id'],
        'is_manual'         => true,
        'amount'            => $sign * $remaining_amount,
        'payment_origin'    => 'cashdesk',
        'payment_method'    => 'bank_card'
    ])
    ->update([
        'funding_id'        => $funding['id']
    ]);

$context->httpResponse()
        ->status(205)
        ->send();

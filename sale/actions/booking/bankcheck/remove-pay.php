<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\BankCheck;
use sale\booking\Funding;
use sale\booking\Booking;
use sale\booking\Payment;

list($params, $providers) = eQual::announce([
    'description'   => "Detach the payment associated with the specified bank check, if present, and reset the corresponding funding status to 'pending'.",
    'help'          => "",
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
    'access' => [
        'groups'            => ['finance.default.administrator', 'finance.default.user']
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

$bankCheck = BankCheck::id($params['id'])
                ->read([
                    'id',
                    'payment_id' => ['id', 'is_manual'],
                    'funding_id' => ['id','is_paid', 'paid_amount', 'due_amount'],
                    'booking_id' => ['id', 'status']
                ])
                ->first(true);


if(!$bankCheck) {
    throw new Exception("unknown_bank_check", QN_ERROR_UNKNOWN_OBJECT);
}

if($bankCheck['booking_id']['status'] == 'balanced') {
    throw new Exception("booking_balanced", QN_ERROR_INVALID_PARAM);
}

if(!$bankCheck['funding_id']['id']) {
    throw new Exception("unknown_funding", QN_ERROR_UNKNOWN_OBJECT);
}

if(!$bankCheck['payment_id']['id']) {
    throw new Exception("unknown_payment", QN_ERROR_UNKNOWN_OBJECT);
}

if(!$bankCheck['payment_id']['is_manual']) {
    throw new Exception("payment_not_manual",QN_ERROR_INVALID_PARAM);
}

Payment::id($bankCheck['payment_id']['id'])->delete(true);

BankCheck::id($bankCheck['id'])->update(['payment_id' => null, 'status' => 'pending']);

Funding::id($bankCheck['funding_id']['id'])
    ->update(['status' => 'pending'])
    ->update(['paid_amount' => null])
    ->update(['is_paid' => null]);

Booking::updateStatusFromFundings($om, (array) $bankCheck['booking_id']['id'], [], 'en');

$context->httpResponse()
        ->status(205)
        ->send();

<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\BankCheck;
use sale\booking\Funding;

list($params, $providers) = eQual::announce([
    'description'   => "Associates a bank check with a funding and marks the funding as paid.",
    'help'          => "Allows you to associate a bank check with a funding and update its status.
                        No payment will be created. The association can be undone as long as the booking is not invoiced",
    'params'        => [
        'id' =>  [
            'description'       => 'Identifier of the targeted funding.',
            'type'              => 'integer',
            'min'               => 1,
            'required'          => true
        ],

        'bank_check_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\BankCheck',
            'description'       => 'The bank check associated with the funding.',
            'required'          => true
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

$funding = Funding::id($params['id'])
            ->read(['paid_amount', 'due_amount'])
            ->first(true);

if(!$funding) {
    throw new Exception("unknown_funding", QN_ERROR_UNKNOWN_OBJECT);
}

$bankCheck = BankCheck::id($params['bank_check_id'])->read(['id','funding_id'])->first(true);
if(!$bankCheck) {
    throw new Exception("unknown_bank_check", QN_ERROR_UNKNOWN_OBJECT);
}

if($bankCheck['funding_id']) {
    throw new Exception("funding_already_associated", QN_ERROR_UNKNOWN_OBJECT);
}

$sign = ($funding['due_amount'] >= 0)? 1 : -1;
$remaining_amount = abs($funding['due_amount']) - abs($funding['paid_amount']);

if($remaining_amount <= 0) {
    throw new Exception("nothing_to_pay", QN_ERROR_INVALID_PARAM);
}

Funding::id($funding['id'])
    ->update([
        'is_paid' => true
    ])
    ->update([
        'status' => 'in_process'
    ])
    ->read(['bank_check_ids']);

BankCheck::id($params['bank_check_id'])->update(['funding_id' => $funding['id']]);

$context->httpResponse()
        ->status(205)
        ->send();

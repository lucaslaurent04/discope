<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\BankCheck;

list($params, $providers) = eQual::announce([
    'description' => "Deletes the specified bank check. If it is associated with a payment, the payment will first be detached and then deleted before resetting the corresponding funding status to 'pending'. This operation requires explicit confirmation and cannot be performed if the related booking is already balanced.",
    'help'        => "This action removes a bank check from the system. If the check is linked to a payment, the payment will first be detached and then deleted before the check itself is removed. The funding status will be reset to 'pending' if applicable.
                     This operation requires explicit confirmation and cannot be executed if the related booking is already marked as balanced.",
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
                    'status',
                    'payment_id',
                    'booking_id' => ['id', 'status']
                ])
                ->first(true);


if(!$bankCheck) {
    throw new Exception("unknown_bankcheck", QN_ERROR_UNKNOWN_OBJECT);
}

if($bankCheck['booking_id']['status'] == 'balanced') {
    throw new Exception("booking_balanced", QN_ERROR_INVALID_PARAM);
}


if($bankCheck['payment_id']) {
    try {
        eQual::run('do', 'sale_booking_bankcheck_remove-pay', ['id' => $bankCheck['id'], 'confirm' => true]);
    }
    catch (Exception $e) {
        throw new Exception("failed_remove_payment", QN_ERROR_UNKNOWN_OBJECT);
    }
}

BankCheck::id($bankCheck['id'])->delete(true);

$context->httpResponse()
        ->status(205)
        ->send();

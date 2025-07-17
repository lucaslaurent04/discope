<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BankCheck;

[$params, $providers] = eQual::announce([
    'description' => "Deletes the specified bank check. If it is associated with a payment, the payment will first be detached and then deleted before resetting the corresponding funding status to 'pending'. This operation requires explicit confirmation and cannot be performed if the related booking is already balanced.",
    'help'        => "This action removes a bank check from the system. If the check is linked to a payment, the payment will first be detached and then deleted before the check itself is removed. The funding status will be reset to 'pending' if applicable.
                     This operation requires explicit confirmation and cannot be executed if the related booking is already marked as balanced.",
    'params'        => [

        'id' =>  [
            'type'          => 'integer',
            'description'   => "Unique identifier of the target bank check.",
            'required'      => true
        ],

        'confirm' =>  [
            'type'          => 'boolean',
            'description'   => "Explicit confirmation required to proceed.",
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
    ->read(['payment_id'])
    ->first(true);

if(is_null($bank_check)) {
    throw new Exception("unknown_bankcheck", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!is_null($bank_check['payment_id'])) {
    try {
        eQual::run('do', 'sale_pay_bankcheck_remove-pay', [
            'id'        => $bank_check['id'],
            'confirm'   => true
        ]);
    }
    catch (Exception $e) {
        throw new Exception("failed_remove_payment", EQ_ERROR_UNKNOWN_OBJECT);
    }
}

BankCheck::id($bank_check['id'])->delete(true);

$context->httpResponse()
        ->status(205)
        ->send();

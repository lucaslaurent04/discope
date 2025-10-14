<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BankStatement;

[$params, $providers] = eQual::announce([
    'description'   => "Un-reconcile a bank statement to allow the modification of its lines.",
    'params'        => [

        'id' =>  [
            'type'          => 'integer',
            'min'           => 1,
            'description'   => "Identifier of the BankStatement to un-reconcile.",
            'required'      => true
        ]

    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['finance.default.administrator'],
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

$bank_statement = BankStatement::id($params['id'])
    ->read(['status'])
    ->first();

if(is_null($bank_statement)) {
    throw new Exception("unknown_bank_statement", EQ_ERROR_UNKNOWN_OBJECT);
}

if($bank_statement['status'] !== 'reconciled') {
    throw new Exception("invalid_status", EQ_ERROR_INVALID_PARAM);
}

BankStatement::id($bank_statement['id'])
    ->update(['status' => 'pending']);

$context->httpResponse()
        ->status(204)
        ->send();

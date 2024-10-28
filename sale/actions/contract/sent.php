<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Contract;

[$params, $providers] = eQual::announce([
    'description'   => "Mark a contract as sent to the customer.",
    'params'        => [
        'id' =>  [
            'type'          => 'integer',
            'description'   => "Identifier of the targeted contract.",
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access'        => [
        'visibility'    => 'public',
        'groups'        => ['booking.default.user'],
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

$contract = Contract::id($params['id'])
    ->read(['id', 'name', 'status', 'valid_until'])
    ->first(true);

if(!$contract) {
    throw new Exception("unknown_contract", QN_ERROR_UNKNOWN_OBJECT);
}

if($contract['status'] != 'pending') {
    throw new Exception("invalid_status", QN_ERROR_NOT_ALLOWED);
}

Contract::id($params['id'])->update(['status' => 'sent']);

// #todo - check if required payment have been paid in the meantime

$context->httpResponse()
        ->status(200)
        ->body([])
        ->send();

<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\pos\CashdeskSession;

[$params, $providers] = eQual::announce([
    'description'   =>	"Provide a fully loaded tree for a given CashdeskSession.",
    'params'        =>	[
        'id' => [
            'description'   => "Identifier of the session for which the tree is requested.",
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['pos.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$tree = [
    'id',
    'amount_opening',
    'cashdesk_id',
    'status',
    'operations_ids' => [
        'id',
        'amount',
        'type'
    ],
    'orders_ids' => [
        'id',
        'name',
        'created',
        'status',
        'total',
        'price'
    ]
];

$session = CashdeskSession::id($params['id'])
    ->read($tree)
    ->adapt('json')
    ->first(true);

if(is_null($session)) {
    throw new Exception("unknown_session", EQ_ERROR_UNKNOWN_OBJECT);
}

$context->httpResponse()
        ->body($session)
        ->send();

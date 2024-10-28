<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BankStatementLine;

[$params, $providers] = eQual::announce([
    'description'	=>	"Mark a selection of BankStatementLine as ignored.",
    'params' 		=>	[
        'ids' => [
            'type'              => 'one2many',
            'description'       => "List of BankStatementLine identifiers  of the order for which the tree is requested.",
            'foreign_object'    => 'sale\booking\BankStatementLine',
            'required'          => true
        ]
    ],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['sale.default.user'],
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

BankStatementLine::ids($params['ids'])
    ->update([
        'message'   => 'test',
        'status'    => 'ignored'
    ]);

$context->httpResponse()
        ->status(204)
        ->send();

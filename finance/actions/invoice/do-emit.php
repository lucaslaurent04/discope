<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use finance\accounting\Invoice;

[$params, $providers] = eQual::announce([
    'description'   => "Sets booking as checked out.",
    'params'        => [
        'id' =>  [
            'type'          => 'integer',
            'description'   => 'Identifier of the booking for which the composition has to be generated.',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'    => 'protected',
        'groups'        => ['finance.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

// emit the invoice : changing status will trigger an invoice number assignation
Invoice::id($params['id'])->update(['status' => 'invoice']);

$context
    ->httpResponse()
    ->status(204)
    ->send();

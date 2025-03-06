<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2025
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;

list($params, $providers) = eQual::announce([
    'name'          => "Generate Exports",
    'description'   => "Creates export archives with newly available data from invoices and payments.",
    'help'          => "Creates either BOB or EBP exports depending on Setting 'finance.invoice.export_type'.",
    'params'        => [],
    'access'        => [
        'groups'        => ['finance.default.user'],
    ],
    'response'      => [
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$export_type = Setting::get_value('finance', 'invoice', 'export_type', 'bob');

eQual::run('do', sprintf('finance_payments_%s_export', $export_type));

$context->httpResponse()
        ->status(201)
        ->send();

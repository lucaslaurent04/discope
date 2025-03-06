<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2025
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\User;

list($params, $providers) = eQual::announce([
    'name'          => "Generate Exports",
    'description'   => "Creates export archives with newly available data from invoices and payments.",
    'params'        => [],
    'access' => [
        'groups' => ['finance.default.user'],
    ],
    'response'      => [
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

$auth_user = User::id($auth->userId())
    ->read(['center_offices_ids'])
    ->first();

foreach($auth_user['center_offices_ids'] as $center_office_id) {
    try {
        eQual::run('do', 'valrance_finance_payments_export-invoices', ['center_office_id' => $center_office_id]);

        # todo - check if payments export needed
        // eQual::run('do', 'finance_payments_export-payments', ['center_office_id' => $center_office_id]);
    }
    catch(Exception $e) {
        trigger_error("APP::error while processing center office $center_office_id", EQ_REPORT_WARNING);
    }
}

$context->httpResponse()
        ->status(201)
        ->send();

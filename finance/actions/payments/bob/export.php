<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use finance\accounting\AccountingJournal;
use identity\CenterOffice;
use identity\User;

[$params, $providers] = eQual::announce([
    'name'          => "Generate Exports",
    'description'   => "Creates BOB export archives with newly available data from invoices and payments.",
    'params'        => [],
    'access' => [
        'groups'        => ['finance.default.user'],
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

$center_office_ids = [];

$auth_user_id = $auth->userId();
if($auth_user_id === 1) {
    // All center offices if root
    $center_office_ids = CenterOffice::search()->ids();
}
else {
    $auth_user = User::id($auth->userId())
        ->read(['center_offices_ids'])
        ->first();

    $center_office_ids = $auth_user['center_offices_ids'];
}

foreach($center_office_ids as $center_office_id) {
    $accounting_journals = AccountingJournal::search([
        ['center_office_id', '=', $center_office_id],
        ['type', 'in', ['sales', 'sales_peppol']]
    ])
        ->read(['type'])
        ->get();

    foreach($accounting_journals as $accounting_journal) {
        $export_invoice_ctrl = 'finance_payments_bob_export-invoices';
        if($accounting_journal['type'] === 'sales_peppol') {
            $export_invoice_ctrl = 'finance_payments_bob_invoicing_export-invoices';
        }

        try {
            eQual::run('do', $export_invoice_ctrl, [
                'center_office_id'  => $center_office_id,
                'journal_type'      => $accounting_journal['type']
            ]);
        }
        catch(Exception $e) {
            trigger_error("APP::error while processing invoices (journal type: {$accounting_journal['type']}) for center office $center_office_id (action: $export_invoice_ctrl)", EQ_REPORT_WARNING);
        }
    }

    try {
        eQual::run('do', 'finance_payments_bob_export-payments', compact('center_office_id'));
    }
    catch(Exception $e) {
        trigger_error("APP::error while processing payments center office $center_office_id", EQ_REPORT_WARNING);
    }
}

$context
    ->httpResponse()
    ->status(201)
    ->send();

<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use documents\Export;
use finance\accounting\AccountingJournal;
use identity\CenterOffice;
use sale\booking\Invoice;

list($params, $providers) = eQual::announce([
    'description'   => "Creates an export archive containing all emitted invoices that haven't been exported yet (for external accounting software).",
    'params'        => [

        'center_office_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\CenterOffice',
            'description'       => "Management Group to which the center belongs.",
            'required'          => true
        ]

    ],
    'access'        => [
        'groups'        => ['finance.default.user']
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

/**
 * Methods
 */

$formatToCsv = function($data) {
    $csv_tmp_file = tempnam(sys_get_temp_dir(), 'csv');

    $fp = fopen($csv_tmp_file, 'w');
    foreach($data as $d) {
        fputcsv($fp, $d);
    }
    fclose($fp);

    $csv_data = file_get_contents($csv_tmp_file);
    unlink($csv_tmp_file);

    if($csv_data === false) {
        throw new Exception("Unable to retrieve CSV file content.", EQ_ERROR_UNKNOWN);
    }

    return $csv_data;
};

$generateZip = function($files) {
    $tmp_file = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive();
    if($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('Unable to create a ZIP file.', QN_ERROR_UNKNOWN);
    }

    foreach($files as $file_name => $file_data) {
        $zip->addFromString($file_name, $file_data);
    }

    $zip->close();

    $data = file_get_contents($tmp_file);
    unlink($tmp_file);

    if($data === false) {
        throw new Exception("Unable to retrieve ZIP file content.", QN_ERROR_UNKNOWN);
    }

    return $data;
};

/**
 * Action
 */

$office = CenterOffice::id($params['center_office_id'])
    ->read(['id'])
    ->first();

if(is_null($office)) {
    throw new Exception("unknown_center_office", EQ_ERROR_UNKNOWN_OBJECT);
}

$journal = AccountingJournal::search([['center_office_id', '=', $params['center_office_id']], ['type', '=', 'sales']])
    ->read(['id', 'code'])
    ->first();

if(is_null($journal)) {
    throw new Exception("unknown_accounting_journal", EQ_ERROR_UNKNOWN_OBJECT);
}

$invoices = Invoice::search(
    [
        [
            ['is_exported', '=', false],
            ['center_office_id', '=', $params['center_office_id']],
            ['booking_id', '>', 0],
            ['status', '<>', 'proforma'],
        ],
        [
            ['is_exported', '=', false],
            ['center_office_id', '=', $params['center_office_id']],
            ['has_orders', '=', true],
            ['status', '<>', 'proforma'],
        ]
    ],
    ['sort'  => ['number' => 'asc']]
)
    ->read([
        'id',
        'name',
        'date',
        'type',
        'partner_id' => [
            'id',
            'name'
        ],
        'invoice_lines_ids' => [
            'id',
            'name',
            'total',
            'price',
            'price_id' => [
                'id',
                'vat_rate',
                'accounting_rule_id' => [
                    'accounting_rule_line_ids' => [
                        'account_id' => [
                            'code'
                        ],
                        'share'
                    ]
                ]
            ]
        ]
    ])
    ->get(true);

if(!empty($invoices)) {
    $entry_num = 1;
    $data = [];
    foreach($invoices as $invoice) {
        $added = 0;
        foreach($invoice['invoice_lines_ids'] as $line) {
            foreach($line['price_id']['accounting_rule_id']['accounting_rule_line_ids'] as $rule_line) {
                $debit = false;

                $account_first = substr($rule_line['account_id']['code'], 0, 1);
                $account_2_first = substr($rule_line['account_id']['code'], 0, 2);
                $account_3_first = substr($rule_line['account_id']['code'], 0, 3);
                if(
                    in_array($account_2_first, ['60', '61', '62'])
                    || in_array($account_3_first, ['411', '512', '530'])
                    || $account_first === '2'
                ) {
                    $debit = true;
                }

                $data[] = [
                    $entry_num,
                    date('dmY', $invoice['date']),
                    'VE',
                    $rule_line['account_id']['code'],
                    'F',
                    $invoice['partner_id']['name'],
                    $invoice['name'],
                    number_format($line['price'], 2, '.', ''),
                    $debit ? 'D' : 'C',
                    '',
                    ''
                ];

                // #todo - Find out why other line for >210 or >220
                $data[] = [
                    '>210', // #todo - Find out why 210 or 220 (maybe related to VAT?)
                    number_format($rule_line['share'] * 100, 2, '.', ''),
                    number_format($line['price'] * $rule_line['share'], 2, '.', '')
                ];

                $added++;
            }
        }

        $entry_num += $added;
    }

    $csv_data = $formatToCsv($data);
    $file_name = sprintf('discope_extraction_%s.txt', date('Ymd', time()));

    $zip_data = $generateZip([$file_name => $csv_data]);

    // switch to root user
    $auth->su();

    // create the export archive
    Export::create([
        'center_office_id'  => $params['center_office_id'],
        'export_type'       => 'invoices',
        'data'              => $zip_data
    ]);

    // mark processed invoices as exported
    $invoices_ids = array_column($invoices, 'id');
    Invoice::ids($invoices_ids)->update(['is_exported' => true]);
}

$context->httpResponse()
        ->status(201)
        ->send();

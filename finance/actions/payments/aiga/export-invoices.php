<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2025
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use documents\Export;
use identity\CenterOffice;
use sale\booking\Invoice;

[$params, $providers] = eQual::announce([
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
        'groups'        => ['finance.default.user'],
    ],
    'response'      => [
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth', 'dispatch']
]);

['context' => $context, 'orm' => $orm, 'auth' => $auth, 'dispatch' => $dispatch] = $providers;

$office = CenterOffice::id($params['center_office_id'])
    ->read(['id'])
    ->first(true);

if(is_null($office)) {
    throw new Exception("unknown_center_office", EQ_ERROR_UNKNOWN_OBJECT);
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
        'number',
        'date',
        'total',
        'price',
        'has_orders',
        'booking_id',
        'partner_id'        => [
            'name',
            'partner_identity_id' => [
                'accounting_account'
            ]
        ],
        'invoice_lines_ids' => [
            'total',
            'product_id'        => [
                'analytic_section_id' => [
                    'code'
                ]
            ],
            'price_id'          => [
                'accounting_rule_id' => [
                    'accounting_rule_line_ids' => [
                        'account',
                        'share'
                    ]
                ]
            ]
        ]
    ])
    ->get(true);

if(empty($invoices)) {
    // exit without error
    throw new Exception("no_match", 0);
}

/*
    Check invoices consistency: discard invalid invoices and emit a warning.
*/

foreach($invoices as $index => $invoice) {
    if( !isset($invoice['partner_id']) ||
        !isset($invoice['partner_id']['partner_identity_id'])
    ) {
        ob_start();
        print_r($invoice);
        $out = ob_get_clean();
        trigger_error("APP::Ignoring invalid invoice : missing partner info for invoice {$invoice['name']} [{$invoice['id']}] - $out", QN_REPORT_WARNING);
        unset($invoices[$index]);
    }
    elseif(!$invoice['has_orders'] && !isset($invoice['booking_id'])) {
        trigger_error("APP::Ignoring invalid invoice : missing booking info for invoice {$invoice['name']} [{$invoice['id']}]", QN_REPORT_WARNING);
        unset($invoices[$index]);
    }
    // #memo - for cancelled invoices and orders invoices, it is ok not to have funding
}

/*
    Generate AIGA import file
 */

$writing_number = 1;

// create an array that contains the data needed for AIGA import
$lines = [];
foreach($invoices as $invoice) {
    $writing_line_number = 1;
    $invoice_date = date('dmY', $invoice['date']);

    // invoice total line
    $lines[] = [
        'writing_number'        => $writing_number,
        'writing_line_number'   => $writing_line_number,
        'date'                  => $invoice_date,
        'general_account'       => 'C'.$invoice['partner_id']['partner_identity_id']['accounting_account'],
        'analytic_account'      => '',
        'journal_account'       => 'VA',
        'file_type'             => '',
        'file_number'           => $invoice['number'],
        'amount'                => sprintf('%.02f', $invoice['total']),
        'writing_label'         => $invoice['partner_id']['name'],
        'due_date'              => '',
        'check_number'          => '',
        'invoice_number'        => $invoice['number'],
        'currency_type'         => 'E'
    ];

    foreach($invoice['invoice_lines_ids'] as $line) {
        // invoice product line
        foreach($line['price_id']['accounting_rule_id']['accounting_rule_line_ids'] as $rule_line) {
            $lines[] = [
                'writing_number'        => $writing_number,
                'writing_line_number'   => ++$writing_line_number,
                'date'                  => $invoice_date,
                'general_account'       => $rule_line['account'],
                'analytic_account'      => $line['product_id']['analytic_section_id']['code'] ?? '',
                'journal_account'       => 'VA',
                'file_type'             => '',
                'file_number'           => $invoice['number'],
                'amount'                => sprintf('%.02f', -1 * ($line['total'] * $rule_line['share'])),
                'writing_label'         => $invoice['partner_id']['name'],
                'due_date'              => '',
                'check_number'          => '',
                'invoice_number'        => $invoice['number'],
                'currency_type'         => 'E'
            ];
        }
    }

    $writing_number++;
}

// format each line to AIGA format (handle padding and substring)
$formatted_lines = [];
foreach($lines as $line) {
    $formatted_lines[] = [
        'writing_number'        => str_pad($line['writing_number'], 6, ' ', STR_PAD_LEFT),
        'writing_line_number'   => str_pad($line['writing_line_number'], 6, ' ', STR_PAD_LEFT),
        'date'                  => str_pad($line['date'], 8),
        'general_account'       => str_pad($line['general_account'], 11),
        'analytic_account'      => str_pad($line['analytic_account'], 10),
        'journal_account'       => str_pad($line['journal_account'], 3),
        'file_type'             => str_pad($line['file_type'], 3),
        'file_number'           => str_pad($line['file_number'], 15),
        'amount'                => str_pad($line['amount'], 15),
        'writing_label'         => str_pad(substr($line['writing_label'], 0, 50), 50),
        'due_date'              => str_pad($line['due_date'], 8),
        'check_number'          => str_pad($line['check_number'], 12),
        'invoice_number'        => str_pad($line['invoice_number'], 20),
        'currency_type'         => str_pad($line['currency_type'], 1)
    ];
}

$result = implode(
    PHP_EOL,
    array_map(
        function($line) {
            return $line['writing_number'].$line['writing_line_number'].$line['date'].$line['general_account']
                .$line['analytic_account'] .$line['journal_account'].$line['file_type'].$line['file_number']
                .$line['amount'].$line['writing_label'].$line['due_date'] .$line['check_number']
                .$line['invoice_number'].$line['currency_type'];
        },
        $formatted_lines
    )
);

/*
    Create export ZIP archive
 */

if(!empty($result)) {
    // generate the zip archive
    $tmp_file = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive();
    if($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        // could not create the ZIP archive
        throw new Exception("Unable to create a ZIP file.", EQ_ERROR_UNKNOWN);
    }

    $zip->addFromString('discope_invoices_export_for_AIGA.txt', $result);

    $zip->close();

    // read raw data
    $data = file_get_contents($tmp_file);
    unlink($tmp_file);

    if($data === false) {
        throw new Exception("Unable to retrieve ZIP file content.", EQ_ERROR_UNKNOWN);
    }

    // switch to root user
    $auth->su();

    // create the export archive
    Export::create([
        'center_office_id'  => $params['center_office_id'],
        'export_type'       => 'invoices',
        'data'              => $data
    ]);

    // mark processed invoices as exported
    Invoice::ids(array_column($invoices, 'id'))
        ->update(['is_exported' => true]);
}

$context->httpResponse()
        ->status(201)
        ->send();

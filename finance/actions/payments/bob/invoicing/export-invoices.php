<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use discope\setting\Setting;
use documents\Export;
use equal\text\TextTransformer;
use identity\CenterOffice;
use finance\accounting\AccountingJournal;
use sale\booking\Invoice;
use sale\booking\Funding;
use sale\booking\export\InvoiceNotScope;
use sale\catalog\Product;

[$params, $providers] = eQual::announce([
    'description'   => "Creates an export archive containing all emitted invoices that haven't been exported yet (for external invoicing software).",
    'params'        => [
        'center_office_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\CenterOffice',
            'description'       => 'Management Group to which the center belongs.',
            'required'          => true
        ],
        'journal_type' => [
            'type'              => 'string',
            'description'       => "The type of journal to export for the center office.",
            'selection'         => [
                'sales',
                'sales_peppol'
            ],
            'default'           => 'sales_peppol'
        ]
    ],
    'access'        => [
        'groups'        => ['finance.default.user'],
    ],
    'response'      => [
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
['context' => $context, 'auth' => $auth, 'dispatch' => $dispatch] = $providers;

/**
 * Methods
 */

$createFieldsSchema = function($fields_conf) {
    $index = 1;
    $position = 0;
    $fields_schema = [];
    foreach($fields_conf as $field => $field_conf) {
        // e.g., Field1=DBK,Char,04,00,00
        $fields_schema[] = sprintf('Field%d=%s,%s,%02d,%02d,%02d',
            $index,
            $field,
            $field_conf['type'],
            $field_conf['length'],
            $field_conf['decimals'],
            $position
        );

        $index++;
        $position += $field_conf['length'];
    }

    return implode("\r\n", $fields_schema);
};


/**
 * Action
 */

$office = CenterOffice::id($params['center_office_id'])
    ->read(['id'])
    ->first();

if(!$office) {
    throw new Exception("unknown_center_office", EQ_ERROR_UNKNOWN_OBJECT);
}

$journal = AccountingJournal::search([
    ['center_office_id', '=', $office['id']],
    ['type', '=', $params['journal_type']]
])
    ->read(['code', 'type'])
    ->first();

if(!$journal) {
    throw new Exception("unknown_accounting_journal", EQ_ERROR_UNKNOWN_OBJECT);
}

$domain = [
    [
        ['journal_id', '=', $journal['id']],
        ['is_exported', '=', false],
        ['center_office_id', '=', $params['center_office_id']],
        ['booking_id', '>', 0],
        ['status', '<>', 'proforma']
    ],
    [
        ['journal_id', '=', $journal['id']],
        ['is_exported', '=', false],
        ['center_office_id', '=', $params['center_office_id']],
        ['has_orders', '=', true],
        ['status', '<>', 'proforma']
    ]
];

$funding_fields = [
    'payment_reference',
    'due_date',
    'paid_amount'
];

// #memo - InvoiceNotScope was created as a quick fix to have one query instead of two (1. for booking invoices, 2. for pos invoices)
$invoices = InvoiceNotScope::search($domain, ['limit' => 100, 'sort' => ['number' => 'asc']])
    ->read([
        'name',
        'date',
        'due_date',
        'type',
        'status',
        'is_paid',
        // #memo - accounting price is the amount to be recorded in accountancy (does not include installment payments)
        'accounting_price',
        'is_deposit',
        'organisation_id',
        'subtotals_vat',
        'total',
        'total_vat',
        'price',
        'partner_id' => [
            '@domain' => ['state', 'in', ['instance', 'archive']],
            'name',
            'partner_identity_id' => [
                '@domain' => ['state', 'in', ['instance', 'archive']],
                'id'
            ]
        ],
        'has_orders',
        'center_office_id' => [
            'analytic_section_id' => [
                'code'
            ]
        ],
        'booking_id' => [
            'name',
            'date_from',
            'date_to',
            'center_id' => [
                'analytic_section_id' => [
                    'code'
                ]
            ]
        ],
        'funding_id' => $funding_fields,
        'invoice_lines_ids' => [
            'name',
            'unit_price',
            'qty',
            'total',
            'price',
            'product_id',
            'vat_rate',
            'discount',
            'downpayment_invoice_id' => [
                'status'
            ],
            'price_id' => [
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

if(empty($invoices)) {
    // exit with no error
    throw new Exception("no_match");
}

/*
    Check invoices consistency : discard invalid invoices and emit a warning.
*/

foreach($invoices as $index => $invoice) {
    if(empty($invoice['partner_id']['partner_identity_id'])) {
        $invoice_info = print_r($invoice, true);
        trigger_error("APP::Ignoring invalid invoice : missing partner info for invoice {$invoice['name']} [{$invoice['id']}] - $invoice_info", EQ_REPORT_WARNING);
        unset($invoices[$index]);
    }
    elseif(!$invoice['has_orders'] && !isset($invoice['booking_id'])) {
        trigger_error("APP::Ignoring invalid invoice : missing booking info for invoice {$invoice['name']} [{$invoice['id']}]", EQ_REPORT_WARNING);
        unset($invoices[$index]);
    }
    // #memo - for cancelled invoices and orders invoices, it is ok not to have funding
}

/*
    Get fundings
 */

foreach($invoices as &$inv) {
    if($inv['funding_id']) {
        continue;
    }

    $funding = Funding::search(['invoice_id', '=', $inv['id']])
        ->read($funding_fields)
        ->first(true);

    $inv['funding_id'] = $funding;
}


/*
    Generate headers: IHDDOC_FACT.txt
*/

$invoices_fields_conf = [
    'DBK'           => ['type' => 'Char',           'length' => 4,      'decimals' => 0],
    'FYEAR'         => ['type' => 'Char',           'length' => 5,      'decimals' => 0],
    'DOCNO'         => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'YEAR'          => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'MONTH'         => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'DBKTYPE'       => ['type' => 'Char',           'length' => 3,      'decimals' => 0],
    'DOCDATE'       => ['type' => 'Date',           'length' => 11,     'decimals' => 0],
    'DUEDATE'       => ['type' => 'Date',           'length' => 11,     'decimals' => 0],
    'CPID'          => ['type' => 'Char',           'length' => 12,     'decimals' => 0],
    'CPTYPE'        => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'INTREM'        => ['type' => 'Char',           'length' => 30,     'decimals' => 0],
    'EXTREM'        => ['type' => 'Char',           'length' => 30,     'decimals' => 0],
    'VCS'           => ['type' => 'Char',           'length' => 17,     'decimals' => 0],
    'TOTLINE'       => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'BASEVATAMN'    => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'PAYAMN'        => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'XUSRACOMPTE'   => ['type' => 'Float',          'length' => 21,     'decimals' => 2]
];

$invoices_schema = implode("\r\n", ['[IHDDOC_FACT]', 'FileType=Fixed', 'CharSet=ascii'])."\r\n".$createFieldsSchema($invoices_fields_conf)."\r\n";

$invoices_data = [];
$map_partners_ids = [];
foreach($invoices as $invoice) {
    // when invoice is a credit note, PAYAMN must be inverted (in most cases should be negative)
    if($invoice['type'] == 'credit_note') {
        $invoice['accounting_price'] = -$invoice['accounting_price'];
    }
    // for orders, use static memo as internal comments
    if($invoice['has_orders']) {
        $comments = 'VENTES COMPTOIR';
    }
    // for bookings, use as internal comments
    else {
        $comments = $invoice['booking_id']['name'].' '.date('d/m/Y', $invoice['booking_id']['date_from']).'-'.date('d/m/Y', $invoice['booking_id']['date_to']);
    }

    $payment_reference = isset($invoice['funding_id']['payment_reference']) ? (substr($invoice['funding_id']['payment_reference'], 0, 17)) : '';

    $total_paid = 0;
    $internal_note = $payment_reference;
    if($invoice['is_paid']) {
        $internal_note = 'PAYE';
        $total_paid = $invoice['price'];
    }
    elseif($invoice['booking_id']) {
        if($invoice['type'] === 'invoice') {
            $fundings = Funding::search(['invoice_id', '=', $invoice['id']])
                ->read(['paid_amount'])
                ->get();

            foreach($fundings as $funding) {
                $total_paid += $funding['paid_amount'];
            }
        }
        elseif($invoice['type'] == 'credit_note') {
            $total_paid = $invoice['funding_id']['paid_amount'];
        }

        if(round($total_paid, 2) >= round($invoice['price'], 2)) {
            // #todo - Fix problem is_paid of booking Invoice not true even if fundings paid (maybe because is_paid is calc from finance\accounting\Invoice and not sale\booking\Invoice ?)
            $internal_note = 'PAYE';
        }
    }

    $map_values = [
        'DBK'           => str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
        'FYEAR'         => str_pad(date('Y', $invoice['date']), 5,' ', STR_PAD_RIGHT),
        'DOCNO'         => str_pad(str_replace('-', '', $invoice['name']), 11,' ', STR_PAD_RIGHT),
        'YEAR'          => str_pad(date('Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
        'MONTH'         => str_pad(date('m', $invoice['date']), 11,' ', STR_PAD_RIGHT),
        'DBKTYPE'       => str_pad('SAL', 3,' ', STR_PAD_RIGHT),
        'DOCDATE'       => str_pad(date('d/m/Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
        'DUEDATE'       => str_pad(date('d/m/Y', $invoice['due_date']), 11,' ', STR_PAD_RIGHT),
        'CPID'          => str_pad('C'.$invoice['partner_id']['partner_identity_id']['id'], 12, ' ', STR_PAD_RIGHT),
        'CPTYPE'        => str_pad('C', 1,' ',STR_PAD_LEFT),
        'INTREM'        => str_pad($internal_note, 30, ' ', STR_PAD_RIGHT),
        'EXTREM'        => str_pad($comments, 30,' ', STR_PAD_RIGHT),
        'VCS'           => str_pad($payment_reference, 17, ' ', STR_PAD_RIGHT),
        'TOTLINE'       => str_pad(str_replace('.', ',', sprintf('%.02f', $invoice['total'])), 21,' ', STR_PAD_LEFT),
        'BASEVATAMN'    => str_pad(str_replace('.', ',', sprintf('%.02f', $invoice['total_vat'])), 21,' ', STR_PAD_LEFT),
        'PAYAMN'        => str_pad(str_replace('.', ',', sprintf('%.02f', $invoice['price'])), 21,' ', STR_PAD_LEFT),
        'XUSRACOMPTE'   => str_pad(str_replace('.', ',', sprintf('%.02f', $total_paid)), 21,' ', STR_PAD_LEFT)
    ];

    $values = [];
    foreach(array_keys($invoices_fields_conf) as $field) {
        $values[] = $map_values[$field];
    }

    $invoices_data[$invoice['id']] = implode('', $values);

    $map_partners_ids[$invoice['partner_id']['id']] = true;
}

/*
    Generate headers: IHISTO_FACT.txt
*/

$allowed_rates = [0.0, 0.06, 0.12, 0.21];

// #todo #settings - adapt to new conventions
$account_downpayment = Setting::get_value('sale', 'accounting', 'invoice.downpayment_account', '4460000');
$account_discount = Setting::get_value('finance', 'accounting', 'account.discount', '7080000');

$map_downpayment_products = [];
foreach($invoices as $invoice) {
    if(isset($map_downpayment_products[$invoice['organisation_id']])) {
        continue;
    }

    $downpayment_sku = Setting::get_value('sale', 'organization', 'sku.downpayment.'.$invoice['organisation_id']);
    if($downpayment_sku) {
        $downpayment_product = Product::search(['sku', '=', $downpayment_sku])
            ->read(['id'])
            ->first();

        if($downpayment_product) {
            $map_downpayment_products[$invoice['organisation_id']] = $downpayment_product['id'];
        }
    }
}

// #todo #settings #catalog - store this value in the settings
// discount product is the same for all organisations: KA-Remise-A [65]
$discount_product_id = 65;

$invoices_lines_fields_conf = [
    'DBK'           => ['type' => 'Char',           'length' => 4,      'decimals' => 0],
    'FYEAR'         => ['type' => 'Char',           'length' => 5,      'decimals' => 0],
    'DOCNO'         => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'DOCLINE'       => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'YEAR'          => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'MONTH'         => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'DOCDATE'       => ['type' => 'Date',           'length' => 11,     'decimals' => 0],
    'CPID'          => ['type' => 'Char',           'length' => 10,     'decimals' => 0],
    'DBKTYPE'       => ['type' => 'Char',           'length' => 4,      'decimals' => 0],
    'LINETYPE'      => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'ARTREF'        => ['type' => 'Char',           'length' => 21,     'decimals' => 0],
    'IMPUT'         => ['type' => 'Char',           'length' => 10,     'decimals' => 0],
    'QTYMVT'        => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'QTYORDER'      => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'QTYDELIV'      => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'COMMENT'       => ['type' => 'Char',           'length' => 120,    'decimals' => 0],
    'VSTORED'       => ['type' => 'Char',           'length' => 10,     'decimals' => 0],
    'TVATTYPE'      => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'TVANAT1'       => ['type' => 'Char',           'length' => 3,      'decimals' => 0],
    'WAREHOUSE'     => ['type' => 'Char',           'length' => 21,     'decimals' => 0],
    'PU'            => ['type' => 'Float',          'length' => 21,     'decimals' => 4],
    'PRCDISC'       => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'BASEAMN'       => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'PAYAMN'        => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'CPTYPE'        => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'NETPU'         => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'DISCAMN'       => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'PURPRICE'      => ['type' => 'Float',          'length' => 21,     'decimals' => 2]
];

$invoices_lines_schema = implode("\r\n", ['[IHISTO_FACT]', 'FileType=Fixed', 'CharSet=ascii'])."\r\n".$createFieldsSchema($invoices_lines_fields_conf)."\r\n";

$invoices_lines_data = [];

foreach($invoices as $invoice) {
    $invoice_lines_data = [];

    $index = 1;
    foreach($invoice['invoice_lines_ids'] as $line) {
        // for orders, use static memo as internal comments
        if($invoice['has_orders']) {
            $comments = ($invoice['type'] === 'invoice' ? 'F. ' : 'NC.').'VENTES COMPTOIR';
        }
        // for bookings, use invoice type, customer name and booking number
        else {
            $comments = ($invoice['type'] === 'invoice' ? 'F. ' : 'NC.').strtoupper( substr(TextTransformer::normalize($invoice['partner_id']['name']), 0, 30) ).'/'.$invoice['booking_id']['name'];
        }

        $raw_rate = $line['vat_rate'];
        $vat_rate = array_reduce($allowed_rates, function ($c, $r) use ($raw_rate) {
                return (abs($r - $raw_rate) < abs($c - $raw_rate)) ? $r : $c;
            },
            0.0
        );

        $account_code = '';
        if(isset($map_downpayment_products[$invoice['organisation_id']]) && $line['product_id'] === $map_downpayment_products[$invoice['organisation_id']]) {
            // deposit invoice or credit note
            if($invoice['is_deposit'] || $invoice['type'] == 'credit_note') {
                $account_code = $account_downpayment;
            }
            // balance invoice
            elseif($invoice['type'] == 'invoice') {
                // if the line refers to an invoiced downpayment and if the related downpayment invoice hasn't been cancelled
                // #memo - we perform this test because some invoices include lines that incorrectly state non-deposit downpayments (that shouldn't be on the invoice)
                if(isset($line['downpayment_invoice_id']) && $line['downpayment_invoice_id'] && isset($line['downpayment_invoice_id']['status']) && $line['downpayment_invoice_id']['status'] == 'invoice') {
                    // add a writing symmetrical to the deposit invoice
                    // #memo - price should be a negative value
                    $account_code = $account_downpayment;
                }
            }
        }
        elseif($line['product_id'] == $discount_product_id) {
            $account_code = $account_discount;
        }
        elseif(!empty($line['price_id']['accounting_rule_id']['accounting_rule_line_ids'])) {
            $rule_line = $line['price_id']['accounting_rule_id']['accounting_rule_line_ids'][0];
            $pos = strpos($rule_line['account_id']['code'], '_');
            $account_code = ($pos !== false) ? substr($rule_line['account_id']['code'], 0, $pos) : $rule_line['account_id']['code'];
        }
        elseif($line['price'] != 0.0) {
            // #memo - this should not occur! - products shouldn't be embedded to invoices if there is no accounting rule
            trigger_error("APP::No related price found for non-null amount for line {$line['name']} [{$line['product_id']}] with price [{$line['price_id']}] of invoice {$invoice['name']} [{$invoice['id']}]", EQ_REPORT_WARNING);
            // remove invoice from processed invoices, and skip all lines
            unset($invoices_data[$invoice['id']]);
            // #todo - dispatch an alert that relates to a dedicated controller
            $dispatch->dispatch('lodging.accounting.invoice.invalid', 'sale\booking\Invoice', $invoice['id'], 'important', null, [], [], null, $params['center_office_id']);
            continue 2;
        }

        $unit_price_discounted = $line['unit_price'];
        if($line['discount'] > 0) {
            $discount = $line['unit_price'] * $line['discount'];
            $unit_price_discounted = $line['unit_price'] - $discount;
        }

        $discount = '';
        if($line['discount'] > 0) {
            $discount = ($line['discount'] * 100).'';
        }

        $name = substr(TextTransformer::toAscii($line['name']), 0, 120);

        $map_values = [
            'DBK'       => str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
            'FYEAR'     => str_pad(date('Y', $invoice['date']), 5,' ', STR_PAD_RIGHT),
            'DOCNO'     => str_pad(str_replace('-', '', $invoice['name']), 11,' ', STR_PAD_RIGHT),
            'DOCLINE'   => str_pad($index, 11,' ', STR_PAD_RIGHT),
            'YEAR'      => str_pad(date('Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
            'MONTH'     => str_pad(date('m', $invoice['date']), 11,' ', STR_PAD_RIGHT),
            'DOCDATE'   => str_pad(date('d/m/Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
            'CPID'      => str_pad('C'.$invoice['partner_id']['partner_identity_id']['id'], 10, ' ', STR_PAD_RIGHT),
            'DBKTYPE'   => str_pad('SAL', 4,' ', STR_PAD_RIGHT),
            'LINETYPE'  => str_pad('O', 1,' ',STR_PAD_LEFT),
            'ARTREF'    => str_pad('', 21,' ',STR_PAD_LEFT),
            'IMPUT'     => str_pad($account_code, 10,' ', STR_PAD_RIGHT),
            'QTYMVT'    => str_pad('', 21,' ', STR_PAD_LEFT),
            'QTYORDER'  => str_pad('1', 21,' ', STR_PAD_LEFT),
            'QTYDELIV'  => str_pad(str_replace('.', ',', sprintf('%.02f', $line['qty'])), 21,' ', STR_PAD_LEFT),
            'COMMENT'   => str_pad($name, 120, ' ', STR_PAD_RIGHT),
            'VSTORED'   => str_pad('NSS  '.intval($vat_rate * 100), 10,' ', STR_PAD_RIGHT),
            'TVATTYPE'  => str_pad('N', 1,' ', STR_PAD_RIGHT),
            'TVANAT1'   => str_pad('V', 3,' ', STR_PAD_RIGHT),
            'WAREHOUSE' => str_pad('', 21,' ',STR_PAD_LEFT),
            'PU'        => str_pad(str_replace('.', ',', sprintf('%.04f', $line['unit_price'])), 21,' ', STR_PAD_LEFT),
            'PRCDISC'   => str_pad($discount, 11,' ', STR_PAD_RIGHT),
            'BASEAMN'   => str_pad(str_replace('.', ',', sprintf('%.02f', $line['total'])), 21,' ', STR_PAD_LEFT),
            'PAYAMN'    => str_pad(str_replace('.', ',', sprintf('%.02f', $line['price'])), 21,' ', STR_PAD_LEFT),
            'CPTYPE'    => str_pad('C', 1,' ',STR_PAD_LEFT),
            'NETPU'     => str_pad(str_replace('.', ',', sprintf('%.02f', $unit_price_discounted)), 21,' ', STR_PAD_LEFT),
            'DISCAMN'   => str_pad('', 21,' ', STR_PAD_LEFT),
            'PURPRICE'  => str_pad('', 21,' ', STR_PAD_LEFT)
        ];

        $values = [];
        foreach(array_keys($invoices_lines_fields_conf) as $field) {
            $values[] = $map_values[$field];
        }

        $invoice_lines_data[] = implode('', $values);

        $index++;
    }

    $invoices_lines_data = array_merge(
        $invoices_lines_data,
        $invoice_lines_data
    );
}

/*
    Generate: CLIENTS_FACT.txt
*/

$customers_files_data = eQual::run('get', 'finance_payments_bob_customers-files', [
    'domain'    => ['id', 'in', array_keys($map_partners_ids)],
    'file_name' => 'CLIENTS_FACT_PEPPOL'
]);

/*
    Create Export
*/

// #memo - prevent generating empty archives (can occur when all processed invoices were faulty)
if(count($invoices_data)) {
    // generate the zip archive
    $tmp_file = tempnam(sys_get_temp_dir(), "zip");
    $zip = new ZipArchive();
    if($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        // could not create the ZIP archive
        throw new Exception('Unable to create a ZIP file.', EQ_ERROR_UNKNOWN);
    }

    // embed schema files
    $zip->addFromString('CLIENTS_FACT_PEPPOL.sch', $customers_files_data['schema']);
    $zip->addFromString('IHDDOC_FACT.sch', $invoices_schema);
    $zip->addFromString('IHISTO_FACT.sch', $invoices_lines_schema);

    // embed data files
    $zip->addFromString('CLIENTS_FACT_PEPPOL.txt', $customers_files_data['data']);
    $zip->addFromString('IHDDOC_FACT.txt', implode("\r\n", array_values($invoices_data))."\r\n");
    $zip->addFromString('IHISTO_FACT.txt', implode("\r\n", $invoices_lines_data)."\r\n");

    $zip->close();

    // read raw data
    $data = file_get_contents($tmp_file);
    unlink($tmp_file);

    if($data === false) {
        throw new Exception('Unable to retrieve ZIP file content.', EQ_ERROR_UNKNOWN);
    }

    // switch to root user
    $auth->su();

    // create the export archive
    $export = Export::create([
        'center_office_id'      => $params['center_office_id'],
        'export_type'           => $journal['type'] === 'sales' ? 'invoices' : 'invoices_peppol',
        'data'                  => $data,
        'object_class'          => InvoiceNotScope::getType(), // #memo - must use InvoiceNotScope to allow reversing export
        'object_ids'            => json_encode(array_keys($invoices_data))
    ])
        ->read(['id'])
        ->first();

    /*
        Set invoices as exported
    */

    try {
        // mark processed invoices as exported
        InvoiceNotScope::ids(array_keys($invoices_data))->update(['is_exported' => true]);
    }
    catch(Exception $e) {
        // remove export if error triggered while flagging invoices as exported
        Export::id($export['id'])->delete();
        throw $e;
    }
}

$context
    ->httpResponse()
    ->status(201)
    ->send();

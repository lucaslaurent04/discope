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
use sale\booking\Funding;
use sale\booking\Invoice;
use sale\booking\export\InvoiceNotScope;
use sale\catalog\Product;

[$params, $providers] = eQual::announce([
    'description'   => "Creates an export archive containing all emitted invoices that haven't been exported yet (for external accounting software).",
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
            'default'           => 'sales'
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

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
['context' => $context, 'orm' => $orm, 'auth' => $auth, 'dispatch' => $dispatch] = $providers;

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

/*
    This controller generates an export file related to invoices of a given center Office.
    Invoices can only be exported once, but the result of the export generation is kept as history that can be re-downloaded if necessary.

    Kaleo uses a double import of the CODA files (in Discope AND in accounting soft [BOB])

    Postulats
    * l'origine des fichiers n'a pas d'importance
    * les noms de fichiers peuvent avoir de l'importance
    * les fichiers peuvent regrouper des lignes issues de différents centres
    * les imports COMPTA se font par centre de gestion : il faut un export par centre de gestion
*/

$office = CenterOffice::id($params['center_office_id'])
    ->read(['id'])
    ->first();

if(!$office) {
    throw new Exception("unknown_center_office", EQ_ERROR_UNKNOWN_OBJECT);
}

$journal = AccountingJournal::search([
    ['center_office_id', '=', $params['center_office_id']],
    ['type', '=', $params['journal_type']]
])
    ->read(['code', 'type'])
    ->first(true);

if(!$journal) {
    throw new Exception("unknown_accounting_journal", EQ_ERROR_UNKNOWN_OBJECT);
}

$domain = [
    [
        ['journal_id', '=', $journal['id']],
        ['is_exported', '=', false],
        ['center_office_id', '=', $params['center_office_id']],
        ['booking_id', '>', 0],
        ['status', '<>', 'proforma'],
    ],
    [
        ['journal_id', '=', $journal['id']],
        ['is_exported', '=', false],
        ['center_office_id', '=', $params['center_office_id']],
        ['has_orders', '=', true],
        ['status', '<>', 'proforma'],
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
            'total',
            'price',
            'product_id',
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
    throw new Exception('no match', 0);
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
    Generate headers: HOPDIV_FACT.txt
*/

$invoices_fields_conf = [
    'TDBK'      => ['type' => 'Char',           'length' => 4,      'decimals' => 0],
    'TFYEAR'    => ['type' => 'Char',           'length' => 5,      'decimals' => 0],
    'TYEAR'     => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'TMONTH'    => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'TDOCNO'    => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'TINTMODE'  => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'TCOMPAN'   => ['type' => 'Char',           'length' => 10,     'decimals' => 0],
    'TDOCDATE'  => ['type' => 'Date',           'length' => 11,     'decimals' => 0],
    'TTYPCIE'   => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'TDUEDATE'  => ['type' => 'Date',           'length' => 11,     'decimals' => 0],
    'TAMOUNT'   => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'TREMINT'   => ['type' => 'Char',           'length' => 40,     'decimals' => 0],
    'TINVVCS'   => ['type' => 'Char',           'length' => 10,     'decimals' => 0]
];

$invoices_schema = implode("\r\n", ['[HOPDIV_FACT]', 'FileType=Fixed', 'CharSet=ascii'])."\r\n".$createFieldsSchema($invoices_fields_conf)."\r\n";

$invoices_data = [];
$map_partners_ids = [];
foreach($invoices as $invoice) {
    // when invoice is a credit note, TAMOUNT must be inverted (in most cases should be negative)
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

    $map_values = [
        'TDBK'      => str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
        'TFYEAR'    => str_pad(date('Y', $invoice['date']), 5,' ', STR_PAD_RIGHT),
        'TYEAR'     => str_pad(date('Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
        'TMONTH'    => str_pad(date('m', $invoice['date']), 11,' ', STR_PAD_RIGHT),
        'TDOCNO'    => str_pad(str_replace('-', '', $invoice['name']), 11,' ', STR_PAD_RIGHT),
        'TINTMODE'  => str_pad('S', 1,' ',STR_PAD_LEFT),
        'TCOMPAN'   => str_pad('C'.$invoice['partner_id']['partner_identity_id']['id'], 10, ' ', STR_PAD_RIGHT),
        'TDOCDATE'  => str_pad(date('d/m/Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
        'TTYPCIE'   => str_pad('C', 1,' ',STR_PAD_LEFT),
        'TDUEDATE'  => str_pad(date('d/m/Y', $invoice['due_date']), 11,' ', STR_PAD_RIGHT),
        'TAMOUNT'   => str_pad(str_replace('.', ',', sprintf('%.02f', $invoice['accounting_price'])), 21,' ', STR_PAD_LEFT),
        'TREMINT'   => str_pad($comments, 40,' ', STR_PAD_RIGHT),
        'TINVVCS'   => str_pad(isset($invoice['funding_id']['payment_reference'])?(substr($invoice['funding_id']['payment_reference'], 0, 10)) : '', 10, ' ', STR_PAD_RIGHT)
    ];

    $values = [];
    foreach(array_keys($invoices_fields_conf) as $field) {
        $values[] = $map_values[$field];
    }

    $invoices_data[$invoice['id']] = implode('', $values);

    $map_partners_ids[$invoice['partner_id']['id']] = true;
}

/*
    Generate lines: LOPDIV_FACT.txt
*/

$invoices_lines_fields_conf = [
    'TDBK'          => ['type' => 'Char',           'length' => 4,      'decimals' => 0],
    'TFYEAR'        => ['type' => 'Char',           'length' => 5,      'decimals' => 0],
    'TYEAR'         => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'TMONTH'        => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'TDOCNO'        => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'TDOCLINE'      => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'TTYPELINE'     => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'TDOCDATE'      => ['type' => 'Date',           'length' => 11,     'decimals' => 0],
    'TACTTYPE'      => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'TACCOUNT'      => ['type' => 'Char',           'length' => 10,     'decimals' => 0],
    'TCURAMN'       => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'TAMOUNT'       => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'TDC'           => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'TREM'          => ['type' => 'Char',           'length' => 40,     'decimals' => 0],
    'COST_GITES'    => ['type' => 'Char',           'length' => 4,      'decimals' => 0],
    'TBASVAT'       => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'TVATTOTAMN'    => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'TVATAMN'       => ['type' => 'Float',          'length' => 21,     'decimals' => 2],
    'TVSTORED'      => ['type' => 'Char',           'length' => 10,     'decimals' => 0]
];

$invoices_lines_schema = implode("\r\n", ['[LOPDIV_FACT]', 'FileType=Fixed', 'CharSet=ascii'])."\r\n".$createFieldsSchema($invoices_lines_fields_conf)."\r\n";

// #todo #settings - adapt to new conventions
$account_sales = Setting::get_value('finance', 'accounting', 'account.sales', '7000000');
$account_downpayment = Setting::get_value('sale', 'accounting', 'invoice.downpayment_account', '4460000');
$account_discount = Setting::get_value('finance', 'accounting', 'account.discount', '7080000');

// #todo #settings #catalog - store this value in the settings
// discount product is the same for all organisations: KA-Remise-A [65]
$discount_product_id = 65;

$invoices_lines_data = [];

foreach($invoices as $invoice) {
    $invoice_lines_accounts = [];

    // retrieve downpayment product
    $downpayment_product_id = 0;
    $downpayment_sku = Setting::get_value('sale', 'organization', 'sku.downpayment.'.$invoice['organisation_id']);
    if($downpayment_sku) {
        $products_ids = Product::search(['sku', '=', $downpayment_sku])->ids();
        if($products_ids) {
            $downpayment_product_id = reset($products_ids);
        }
    }

    $subtotals_vat_lines = [];

    // pass-1 : group all lines by account_id
    foreach($invoice['invoice_lines_ids'] as $lid => $line) {
        $vat = round($line['price'] - round($line['total'], 2), 2);
        $amount = $line['price'] - $vat;
        // when invoice is a credit note, TAMOUNT and TVATOTAMN must be inverted (#memo - not necessarily negative)
        if($invoice['type'] == 'credit_note') {
            $amount = -$amount;
            $vat = -$vat;
        }
        // #memo - Sage BOB will reject invoices with no line and lines with nul amount, so the situation of an invoice with a single nul line is invalid / should not occur (discount without distinct product ?)
        if($amount == 0.0) {
            continue;
        }
        // #memo - we don't use $line['price_id']['vat_rate'] since VAT rate can be set manually
        // #memo - this might lead to incorrect values if distinct VAT rates have been applied on distinct products relating to a same account id (in such case, the accounting software will mark the import as invalid)
        // #memo - for small amounts (< 1 $) `price-total` may lead to a rounding issue, so we must make sure applied VTA rate is amongst a predefined list
        // #todo - use VAT rates from config
        $allowed_rates = [0.0, 0.06, 0.12, 0.21];

        $raw_rate = ($amount != 0.0) ? abs(round($vat / $amount, 2)) : 0.0;
        $vat_rate = array_reduce($allowed_rates, function ($c, $r) use ($raw_rate) {
                return (abs($r - $raw_rate) < abs($c - $raw_rate)) ? $r : $c;
            },
            0.0);

        $accounting_rule_lines_ids = [];
        if($line['product_id'] == $downpayment_product_id) {
            // deposit invoice or credit note
            if($invoice['is_deposit'] || $invoice['type'] == 'credit_note') {
                $accounting_rule_lines_ids = [
                        ['account_id' => ['code' => $account_downpayment], 'share' => 1.0]
                    ];
            }
            // balance invoice
            elseif($invoice['type'] == 'invoice') {
                // if the line refers to an invoiced downpayment and if the related downpayment invoice hasn't been cancelled
                // #memo - we perform this test because some invoices include lines that incorrectly state non-deposit downpayments (that shouldn't be on the invoice)
                if(isset($line['downpayment_invoice_id']) && $line['downpayment_invoice_id'] && isset($line['downpayment_invoice_id']['status']) && $line['downpayment_invoice_id']['status'] == 'invoice') {
                    // add a writing symmetrical to the deposit invoice
                    // #memo - price should be a negative value
                    $accounting_rule_lines_ids = [
                            ['account_id' => ['code' => $account_downpayment], 'share' => 1.0]
                        ];
                }
            }
        }
        elseif($line['product_id'] == $discount_product_id) {
            $accounting_rule_lines_ids = [
                    ['account_id' => ['code' => $account_discount], 'share' => 1.0]
                ];
        }
        elseif(isset($line['price_id']['accounting_rule_id']['accounting_rule_line_ids'])) {
            $accounting_rule_lines_ids = $line['price_id']['accounting_rule_id']['accounting_rule_line_ids'];
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

        $amount_remaining = $amount;
        $vat_remaining = $vat;

        $i = 0;
        $n = count($accounting_rule_lines_ids);

        foreach($accounting_rule_lines_ids as $rlid => $rline) {
            // if there is no entry yet, create one
            if(!isset($invoice_lines_accounts[$rline['account_id']['code']])) {
                $invoice_lines_accounts[$rline['account_id']['code']] = [
                        'vat_rate'  => $vat_rate,
                        'vat'       => 0.0,
                        'amount'    => 0.0
                    ];
            }

            if($i == $n-1) {
                $line_amount = round($amount_remaining, 2);
                $line_vat = round($vat_remaining, 2);
            }
            else {
                $line_amount = round($amount * $rline['share'], 2);
                $line_vat = round($line_amount * $vat_rate, 2);
            }

            // add vat and amount to the entry, according to share
            $invoice_lines_accounts[$rline['account_id']['code']]['vat'] += $line_vat;
            $invoice_lines_accounts[$rline['account_id']['code']]['amount'] += $line_amount;

            // update vat subtotals map that will be used to adapt the vats amount later (to match Peppol style calculation of vat)
            $vat_rate_index = number_format($vat_rate * 100, 2, '.', '');
            if(!isset($subtotals_vat_lines[$vat_rate_index])) {
                $subtotals_vat_lines[$vat_rate_index] = 0.0;
            }
            $subtotals_vat_lines[$vat_rate_index] = round($subtotals_vat_lines[$vat_rate_index] + $line_vat, 2);

            $amount_remaining -= $line_amount;
            $vat_remaining -= $line_vat;

            ++$i;
        }
    }

    // the check $invoice['price'] === $new_calculation_price can be removed when the new vat calculation price (peppol compatible) is the only used (no more old invoice to export)
    $new_calculation_price = round($invoice['total_vat'] + $invoice['total'], 2);
    if($invoice['price'] === $new_calculation_price) {
        // #memo - if result of vat "calculation per line" is different from result of vat "calculation per vat_rate" (BE: 6%, 12% and 21%), then adapt it to match Invoices data
        foreach($subtotals_vat_lines as $vat_rate_index => $subtotal_vat) {
            $invoice_subtotals_vat = json_decode($invoice['subtotals_vat'], true);
            $diff = $invoice_subtotals_vat[$vat_rate_index] - abs($subtotal_vat);
            if(round(abs($diff), 2) == 0.0) {
                continue;
            }
            if($invoice['type'] === 'credit_note') {
                $diff *= -1;
            }
            foreach($invoice_lines_accounts as &$account_values) {
                $vat_rate = ((float) $vat_rate_index) / 100;
                if($account_values['vat_rate'] === $vat_rate) {
                    // adapt here
                    $account_values['vat'] = round($account_values['vat'] + $diff, 2);
                    unset($account_values);
                    continue 2;
                }
            }
            unset($account_values);
        }
    }

    $invoice_lines_data = [];

    // pass-2 : generate lines based on account entries
    $index = 1;
    foreach($invoice_lines_accounts as $account_code => $account_values) {
        // #memo - Sage BOB will reject lines with an amount of 0.0
        if($account_values['amount'] == 0.0) {
            // skip line
            continue;
        }

        $analytic_section = '';

        // downpayments are not part of the analytic accounting
        if($account_code != $account_downpayment) {
            if($invoice['has_orders']) {
                $analytic_section = $invoice['center_office_id']['analytic_section_id']['code'];
            }
            else {
                $analytic_section = $invoice['booking_id']['center_id']['analytic_section_id']['code'];
            }
        }

        // #memo - remove any '_' and trailing chars (since that notation is not supported by Sage BOB)
	    $pos = strpos($account_code, '_');
	    $account_code = ($pos !== false)?substr($account_code, 0, $pos):$account_code;
        // for orders, use static memo as internal comments
        if($invoice['has_orders']) {
            $comments = (($invoice['type'] == 'invoice')?'F. ':'NC.').'VENTES COMPTOIR';
        }
        // for bookings, use invoice type, customer name and booking number
        else {
            $comments = (($invoice['type'] == 'invoice')?'F. ':'NC.').strtoupper( substr(TextTransformer::normalize($invoice['partner_id']['name']), 0, 30) ).'/'.$invoice['booking_id']['name'];
        }

        $map_values = [
            'TDBK'          => str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
            'TFYEAR'        => str_pad(date('Y', $invoice['date']), 5,' ', STR_PAD_RIGHT),
            'TYEAR'         => str_pad(date('Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
            'TMONTH'        => str_pad(date('m', $invoice['date']), 11,' ', STR_PAD_RIGHT),
            'TDOCNO'        => str_pad(str_replace('-', '', $invoice['name']), 11,' ', STR_PAD_RIGHT),
            'TDOCLINE'      => str_pad($index, 11,' ', STR_PAD_RIGHT),
            'TTYPELINE'     => str_pad('S', 1,' ',STR_PAD_LEFT),
            'TDOCDATE'      => str_pad(date('d/m/Y', $invoice['date']), 11,' ', STR_PAD_RIGHT),
            'TACTTYPE'      => str_pad('A', 1,' ', STR_PAD_RIGHT),
            'TACCOUNT'      => str_pad($account_code, 10,' ', STR_PAD_RIGHT),
            'TCURAMN'       => str_pad('0,00', 21,' ', STR_PAD_LEFT),
            'TAMOUNT'       => str_pad(str_replace('.', ',', sprintf('%.02f', $account_values['amount'])), 21,' ', STR_PAD_LEFT),
            'TDC'           => str_pad('C', 1,' ', STR_PAD_RIGHT),
            'TREM'          => str_pad( $comments, 40,' ', STR_PAD_RIGHT),
            'COST_GITES'    => str_pad($analytic_section, 4,' ', STR_PAD_RIGHT),
            'TBASVAT'       => str_pad(str_replace('.', ',', sprintf('%.02f', $account_values['amount'])), 21,' ', STR_PAD_LEFT),
            'TVATTOTAMN'    => str_pad(str_replace('.', ',', sprintf('%.02f', $account_values['vat'])), 21,' ', STR_PAD_LEFT),
            'TVATAMN'       => str_pad(str_replace('.', ',', sprintf('%.02f', $account_values['vat'])), 21,' ', STR_PAD_LEFT),
            'TVSTORED'      => str_pad('NSS  '.intval($account_values['vat_rate'] * 100), 10,' ', STR_PAD_RIGHT)
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
    'file_name' => 'CLIENTS_FACT'
]);


/*
    Create Export
*/

// #memo - prevent generating empty archives (can occur when all processed invoices were faulty)
if(count($invoices_data)) {
    // generate the zip archive
    $tmpfile = tempnam(sys_get_temp_dir(), "zip");
    $zip = new ZipArchive();
    if($zip->open($tmpfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        // could not create the ZIP archive
        throw new Exception('Unable to create a ZIP file.', EQ_ERROR_UNKNOWN);
    }

    // embed schema files
    $zip->addFromString('CLIENTS_FACT.sch', $customers_files_data['schema']);
    $zip->addFromString('HOPDIV_FACT.sch', $invoices_schema);
    $zip->addFromString('LOPDIV_FACT.sch', $invoices_lines_schema);

    // embed data files
    $zip->addFromString('CLIENTS_FACT.txt', $customers_files_data['data']);
    $zip->addFromString('HOPDIV_FACT.txt', implode("\r\n", array_values($invoices_data)) . "\r\n");
    $zip->addFromString('LOPDIV_FACT.txt', implode("\r\n", $invoices_lines_data) . "\r\n");

    $zip->close();

    // read raw data
    $data = file_get_contents($tmpfile);
    unlink($tmpfile);

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
        'object_ids'            => json_encode(array_column($invoices, 'id'))
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

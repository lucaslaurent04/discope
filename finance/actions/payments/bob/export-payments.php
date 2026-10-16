<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use documents\Export;
use equal\text\TextTransformer;
use identity\CenterOffice;
use finance\accounting\AccountingJournal;
use sale\booking\Payment;

[$params, $providers] = eQual::announce([
    'description'   => "Creates an export archive containing all emitted invoices that haven't been exported yet (for external accounting software).",
    'params'        => [
        'center_office_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\CenterOffice',
            'description'       => 'Management Group to which the center belongs.',
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
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

// make sure we have right on all involved objects: switch to root user
$auth->su();

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

// retrieve center_office
$office = CenterOffice::id($params['center_office_id'])->read(['id'])->first(true);

if(!$office) {
    throw new Exception("unknown_center_office", EQ_ERROR_UNKNOWN_OBJECT);
}

// retrieve the journal of miscellaneous operations
$journal = AccountingJournal::search([['center_office_id', '=', $params['center_office_id']], ['type', '=', 'miscellaneous']])->read(['id', 'code', 'index'])->first(true);

if(!$journal) {
    throw new Exception("unknown_center_office", EQ_ERROR_UNKNOWN_OBJECT);
}

/*
    Retrieve non-exported payments.
*/

$payments_ids = Payment::search([
        [
            ['is_exported', '=', false],
            ['center_office_id', '=', $params['center_office_id']],
            ['funding_id', '>', 0],
            ['payment_origin', '=', 'bank']
        ],
        [
            ['is_exported', '=', false],
            ['center_office_id', '=', $params['center_office_id']],
            ['funding_id', '>', 0],
            ['payment_origin', '=', 'cashdesk'],
            ['payment_method', 'in', ['bank_card', 'cash']],
            ['status', '=', 'paid']
        ],
        [
            ['is_exported', '=', false],
            ['center_office_id', '=', $params['center_office_id']],
            ['funding_id', '>', 0],
            ['payment_origin', '=', 'online'],
            ['has_psp', '=', true],
            ['psp_type', '=', 'stripe']
        ],
        [
            ['is_exported', '=', false],
            ['center_office_id', '=', $params['center_office_id']],
            ['order_payment_id', '>', 0],
            ['payment_origin', '=', 'cashdesk'],
            ['payment_method', 'in', ['bank_card', 'cash']]
        ]
    ], [
        'limit' => 100,
        'sort'  => ['created' => 'asc']
    ])
    ->ids();

$payments = Payment::ids($payments_ids)
    ->read([
        'id',
        'created',
        'receipt_date',
        'amount',
        'statement_line_id' => ['date'],
        'has_psp',
        'psp_type',
        'psp_fee_amount',
        'funding_id' => [
            'id',
            'type',
            'invoice_id' => [
                'partner_id' => [
                    '@domain' => ['state', 'in', ['instance', 'archive']],
                    'id',
                    'name',
                    'partner_identity_id' => [
                        '@domain' => ['state', 'in', ['instance', 'archive']],
                        'id'
                    ]
                ]
            ],
            'booking_id' => [
                'id',
                'name',
                'customer_id' => [
                    '@domain' => ['state', 'in', ['instance', 'archive']],
                    'id',
                    'name',
                    'partner_identity_id' => [
                        '@domain' => ['state', 'in', ['instance', 'archive']],
                        'id'
                    ]
                ]
            ]
        ],
        'order_payment_id' => [
            'order_id' => [
                'customer_id' => [
                    '@domain' => ['state', 'in', ['instance', 'archive']],
                    'id',
                    'name',
                    'partner_identity_id' => [
                        '@domain' => ['state', 'in', ['instance', 'archive']],
                        'id'
                    ]
                ]
            ]
        ]
    ])
    ->get(true);


$payments_count = count($payments);
if($payments_count == 0) {
    // exit with no error
    throw new Exception('no match', 0);
}


// generate file holding the schema for payments: HOPDIV_REGL.sch
ob_start();
echo "[HOPDIV_REGL]
FileType = Fixed
Charset = ascii
Field1=TDBK,Char,04,00,00
Field2=TFYEAR,Char,05,00,04
Field3=TYEAR,Long Integer,11,00,09
Field4=TMONTH,Long Integer,11,00,20
Field5=TDOCNO,Long Integer,11,00,31
Field6=TINTMODE,Char,01,00,42
";
$payments_header_schema = ob_get_clean();

// export file holding the schema for payments lines: LOPDIV_REGL.sch
ob_start();
echo "[LOPDIV_REGL]
FileType = Fixed
Charset = ascii
Field1=TDBK,Char,04,00,00
Field2=TFYEAR,Char,05,00,04
Field3=TYEAR,Long Integer,11,00,09
Field4=TMONTH,Long Integer,11,00,20
Field5=TDOCNO,Long Integer,11,00,31
Field6=TDOCLINE,Long Integer,11,00,42
Field7=TTYPELINE,Char,01,00,53
Field8=TDOCDATE,Date,11,00,54
Field9=TACTTYPE,Char,01,00,65
Field10=TACCOUNT,Char,10,00,66
Field11=TCURAMN,Float,21,02,76
Field12=TAMOUNT,Float,21,02,97
Field13=TDC,Char,01,00,118
Field14=TREM,Char,40,00,119
Field15=COST_GITES,Char,04,00,159
";
$payments_lines_schema = ob_get_clean();


/*
    Generate headers: CLIENTS_REGL.txt
*/


$map_partners_ids = [];
foreach($payments as $payment) {
    // ignore payments that are not related to an invoice
    // #memo - export payments for funding not yet invoiced - we use temporary accounts to handle this situation
    if(is_null($payment['funding_id']['invoice_id'])) {
        // continue;
    }

    // retrieve targeted partner
    if(isset($payment['funding_id'])) {
        if($payment['funding_id']['type'] == 'invoice' && isset($payment['funding_id']['invoice_id']['partner_id'])) {
            $partner = $payment['funding_id']['invoice_id']['partner_id'];
        }
        elseif(isset($payment['funding_id']['booking_id']['customer_id'])) {
            $partner = $payment['funding_id']['booking_id']['customer_id'];
        }
        else {
            // malformed payment : ignore
            continue;
        }
    }
    elseif(isset($payment['order_payment_id']['order_id']['customer_id'])) {
        $partner = $payment['order_payment_id']['order_id']['customer_id'];
    }
    else {
        // malformed payment : ignore
        continue;
    }

    $map_partners_ids[$partner['id']] = true;
}

$customers_files_data = eQual::run('get', 'finance_payments_bob_customers-files', [
    'domain'    => ['id', 'in', array_keys($map_partners_ids)],
    'file_name' => 'CLIENTS_REGL'
]);


/*
    Generate headers: HOPDIV_REGL.txt
*/

$result = [];
$offset = 0;
foreach($payments as $payment) {
    // ignore payments that are not related to an invoice
    // #memo - export payments for funding not yet invoiced - we use temporary accounts to handle this situation
    if(is_null($payment['funding_id']['invoice_id'])) {
        // continue;
    }

    // retrieve targeted partner
    if(isset($payment['funding_id'])) {
        if($payment['funding_id']['type'] == 'invoice' && isset($payment['funding_id']['invoice_id']['partner_id'])) {
            $partner = $payment['funding_id']['invoice_id']['partner_id'];
        }
        elseif(isset($payment['funding_id']['booking_id']['customer_id'])) {
            $partner = $payment['funding_id']['booking_id']['customer_id'];
        }
        else {
            // malformed payment : ignore
            continue;
        }
    }
    elseif(isset($payment['order_payment_id']['order_id']['customer_id'])) {
        $partner = $payment['order_payment_id']['order_id']['customer_id'];
    }
    else {
        // malformed payment : ignore
        continue;
    }

    $date = $payment['receipt_date'];
    // if payment refers to a statement line, use the date of the latter
    // #todo - this should be done in BankStatementLine::reconcile()
    if($payment['statement_line_id']) {
        $date = $payment['statement_line_id']['date'];
    }

    $values = [
        // Field1=TDBK,Char,04,00,00
        str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
        // Field2=TFYEAR,Char,05,00,04
        str_pad(date('Y', $date), 5,' ', STR_PAD_RIGHT),
        // Field3=TYEAR,Long Integer,11,00,09
        str_pad(date('Y', $date), 11,' ', STR_PAD_RIGHT),
        // Field4=TMONTH,Long Integer,11,00,20
        str_pad(date('m', $date), 11,' ', STR_PAD_RIGHT),
        // Field5=TDOCNO,Long Integer,11,00,31
        str_pad($offset + $journal['index'], 11,' ', STR_PAD_RIGHT),
        // Field6=TINTMODE,Char,01,00,42
        str_pad('B', 1,' ',STR_PAD_LEFT),
    ];

    $result[] = implode('', $values);
    ++$offset;
}

$payments_header_data = implode("\r\n", $result)."\r\n";


/*
    Generate lines: LOPDIV_REGL.txt
*/

$result = [];
// we use offset + journal index as virtual document ref. for payments
$offset = 0;
foreach($payments as $payment) {
    // ignore payments that are not related to an invoice
    // #memo - export payments for funding not yet invoiced - we use temporary accounts to handle this situation
    if(is_null($payment['funding_id']['invoice_id'])) {
        // continue;
    }

    $remark = '';

    // retrieve targeted partner
    if(isset($payment['funding_id'])) {
        $remark = ($payment['funding_id']['booking_id']['name']).' - IMPORT REGLT : '.($offset + $journal['index']);
        if($payment['funding_id']['type'] == 'invoice' && isset($payment['funding_id']['invoice_id']['partner_id'])) {
            $partner = $payment['funding_id']['invoice_id']['partner_id'];
        }
        elseif(isset($payment['funding_id']['booking_id']['customer_id'])) {
            $partner = $payment['funding_id']['booking_id']['customer_id'];
        }
        else {
            // malformed payment : ignore
            continue;
        }
    }
    elseif(isset($payment['order_payment_id']['order_id']['customer_id'])) {
        $remark = 'VENTE COMPTOIR : '.($offset + $journal['index']);
        $partner = $payment['order_payment_id']['order_id']['customer_id'];
    }
    else {
        // malformed payment : ignore
        continue;
    }

    $date = $payment['receipt_date'];
    // if payment refers to a statement line, use the date of the latter
    if($payment['statement_line_id']) {
        $date = $payment['statement_line_id']['date'];
    }

    // create lines for accounting entries (2 or 3)

    // first line : credit for the customer account
    $values = [
        // Field1=TDBK,Char,04,00,00
        str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
        // Field2=TFYEAR,Char,05,00,04
        str_pad(date('Y', $date), 5,' ', STR_PAD_RIGHT),
        // Field3=TYEAR,Long Integer,11,00,09
        str_pad(date('Y', $date), 11,' ', STR_PAD_RIGHT),
        // Field4=TMONTH,Long Integer,11,00,20
        str_pad(date('m', $date), 11,' ', STR_PAD_RIGHT),
        // Field5=TDOCNO,Long Integer,11,00,31
        str_pad($offset + $journal['index'], 11,' ', STR_PAD_RIGHT),
        // Field6=TDOCLINE,Long Integer,11,00,42
        str_pad(0, 11,' ', STR_PAD_RIGHT),
        // Field7=TTYPELINE,Char,01,00,53
        str_pad('B', 1,' ', STR_PAD_LEFT),
        // Field8=TDOCDATE,Date,11,00,54
        str_pad(date('d/m/Y', $date), 11,' ', STR_PAD_RIGHT),
        // Field9=TACTTYPE,Char,01,00,65
        str_pad('C', 1,' ', STR_PAD_RIGHT),
        // Field10=TACCOUNT,Char,10,00,66
        str_pad('C'.$partner['partner_identity_id']['id'], 10,' ', STR_PAD_RIGHT),
        // Field11=TCURAMN,Float,21,02,76
        str_pad('0,00', 21,' ', STR_PAD_LEFT),
        // Field12=TAMOUNT,Float,21,02,97
        str_pad(str_replace('.', ',', sprintf('%.02f', $payment['amount'])), 21,' ', STR_PAD_LEFT),
        // Field13=TDC,Char,01,00,118
        str_pad('C', 1,' ', STR_PAD_RIGHT),
        // Field14=TREM,Char,40,00,119
        str_pad($remark, 40,' ', STR_PAD_RIGHT),
        // Field15=COST_GITES,Char,04,00,159
        str_pad('', 4,' ', STR_PAD_RIGHT),
    ];
    $result[] = implode('', $values);

    // second line : debit for the temporary account ("compte d'attente")

    $map_temp_accounts = [
        1   => '4990200',        // GG
        2   => '4990210',        // Eupen
        3   => '4990220',        // Han
        4   => '4990290',        // LLN
        5   => '4990230',        // Ovifat
        6   => '4990240',        // Rochefort
        7   => '4990270',        // VSG
        8   => '4990250',        // Wanne
        9   => '4990260'         // HVG
    ];

    $temp_account = $map_temp_accounts[$office['id']];

    if(isset($payment['funding_id'])) {
        $remark = ($payment['funding_id']['booking_id']['name']).' - '.substr(strtoupper(TextTransformer::normalize($partner['name'])), 0, 15).' Virement IMPORT';
    }
    else {
        $remark = 'VENTE COMPTOIR - IMPORT';
    }

    // scenario 1 : regular payment (1 entry)
    if(!$payment['has_psp']) {
        $values = [
            // Field1=TDBK,Char,04,00,00
            str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
            // Field2=TFYEAR,Char,05,00,04
            str_pad(date('Y', $date), 5,' ', STR_PAD_RIGHT),
            // Field3=TYEAR,Long Integer,11,00,09
            str_pad(date('Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field4=TMONTH,Long Integer,11,00,20
            str_pad(date('m', $date), 11,' ', STR_PAD_RIGHT),
            // Field5=TDOCNO,Long Integer,11,00,31
            str_pad($offset + $journal['index'], 11,' ', STR_PAD_RIGHT),
            // Field6=TDOCLINE,Long Integer,11,00,42
            str_pad(1, 11,' ', STR_PAD_RIGHT),
            // Field7=TTYPELINE,Char,01,00,53
            str_pad('B', 1,' ', STR_PAD_LEFT),
            // Field8=TDOCDATE,Date,11,00,54
            str_pad(date('d/m/Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field9=TACTTYPE,Char,01,00,65
            str_pad('A', 1,' ', STR_PAD_RIGHT),
            // Field10=TACCOUNT,Char,10,00,66
            str_pad($temp_account, 10,' ', STR_PAD_RIGHT),
            // Field11=TCURAMN,Float,21,02,76
            str_pad('0,00', 21,' ', STR_PAD_LEFT),
            // Field12=TAMOUNT,Float,21,02,97
            str_pad(str_replace('.', ',', sprintf('%.02f', $payment['amount'])), 21,' ', STR_PAD_LEFT),
            // Field13=TDC,Char,01,00,118
            str_pad('D', 1,' ', STR_PAD_RIGHT),
            // Field14=TREM,Char,40,00,119
            str_pad($remark, 40,' ', STR_PAD_RIGHT),
            // Field15=COST_GITES,Char,04,00,159
            str_pad('', 4,' ', STR_PAD_RIGHT),
        ];
        $result[] = implode('', $values);
    }
    // scenario 2 : online payment involving PSP (2 entries)
    else {
        if($payment['psp_type'] != 'stripe') {
            // #todo - send an email to admin
            throw new Exception('non_supported_psp', EQ_ERROR_UNKNOWN);
        }
        if(is_null($payment['psp_fee_amount']) || $payment['psp_fee_amount'] <= 0) {
            // #todo - send an email to admin
            // throw new Exception('invalid_psp_fee', EQ_ERROR_UNKNOWN);
        }

        // entry 1 : amount minus fees to temp account
        $values = [
            // Field1=TDBK,Char,04,00,00
            str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
            // Field2=TFYEAR,Char,05,00,04
            str_pad(date('Y', $date), 5,' ', STR_PAD_RIGHT),
            // Field3=TYEAR,Long Integer,11,00,09
            str_pad(date('Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field4=TMONTH,Long Integer,11,00,20
            str_pad(date('m', $date), 11,' ', STR_PAD_RIGHT),
            // Field5=TDOCNO,Long Integer,11,00,31
            str_pad($offset + $journal['index'], 11,' ', STR_PAD_RIGHT),
            // Field6=TDOCLINE,Long Integer,11,00,42
            str_pad(1, 11,' ', STR_PAD_RIGHT),
            // Field7=TTYPELINE,Char,01,00,53
            str_pad('B', 1,' ', STR_PAD_LEFT),
            // Field8=TDOCDATE,Date,11,00,54
            str_pad(date('d/m/Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field9=TACTTYPE,Char,01,00,65
            str_pad('A', 1,' ', STR_PAD_RIGHT),
            // Field10=TACCOUNT,Char,10,00,66
            str_pad($temp_account, 10,' ', STR_PAD_RIGHT),
            // Field11=TCURAMN,Float,21,02,76
            str_pad('0,00', 21,' ', STR_PAD_LEFT),
            // Field12=TAMOUNT,Float,21,02,97
            str_pad(str_replace('.', ',', sprintf('%.02f', $payment['amount']-$payment['psp_fee_amount'])), 21,' ', STR_PAD_LEFT),
            // Field13=TDC,Char,01,00,118
            str_pad('D', 1,' ', STR_PAD_RIGHT),
            // Field14=TREM,Char,40,00,119
            str_pad( ($payment['funding_id']['booking_id']['name']).' - '.substr(strtoupper(TextTransformer::normalize($partner['name'])), 0, 15).' Virement IMPORT', 40,' ', STR_PAD_RIGHT),
            // Field15=COST_GITES,Char,04,00,159
            str_pad('', 4,' ', STR_PAD_RIGHT),
        ];
        $result[] = implode('', $values);

        // entry 2 : PSP fees to Stripe account
        $values = [
            // Field1=TDBK,Char,04,00,00
            str_pad($journal['code'], 4, ' ', STR_PAD_RIGHT),
            // Field2=TFYEAR,Char,05,00,04
            str_pad(date('Y', $date), 5,' ', STR_PAD_RIGHT),
            // Field3=TYEAR,Long Integer,11,00,09
            str_pad(date('Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field4=TMONTH,Long Integer,11,00,20
            str_pad(date('m', $date), 11,' ', STR_PAD_RIGHT),
            // Field5=TDOCNO,Long Integer,11,00,31
            str_pad($offset + $journal['index'], 11,' ', STR_PAD_RIGHT),
            // Field6=TDOCLINE,Long Integer,11,00,42
            str_pad(2, 11,' ', STR_PAD_RIGHT),
            // Field7=TTYPELINE,Char,01,00,53
            str_pad('B', 1,' ', STR_PAD_LEFT),
            // Field8=TDOCDATE,Date,11,00,54
            str_pad(date('d/m/Y', $date), 11,' ', STR_PAD_RIGHT),
            // Field9=TACTTYPE,Char,01,00,65
            str_pad('S', 1,' ', STR_PAD_RIGHT),
            // Field10=TACCOUNT,Char,10,00,66
            str_pad('STRIPE', 10,' ', STR_PAD_RIGHT),
            // Field11=TCURAMN,Float,21,02,76
            str_pad('0,00', 21,' ', STR_PAD_LEFT),
            // Field12=TAMOUNT,Float,21,02,97
            str_pad(str_replace('.', ',', sprintf('%.02f', $payment['psp_fee_amount'])), 21,' ', STR_PAD_LEFT),
            // Field13=TDC,Char,01,00,118
            str_pad('D', 1,' ', STR_PAD_RIGHT),
            // Field14=TREM,Char,40,00,119
            str_pad( ($payment['funding_id']['booking_id']['name']).' - '.substr(strtoupper(TextTransformer::normalize($partner['name'])), 0, 15).' Commission PSP', 40,' ', STR_PAD_RIGHT),
            // Field15=COST_GITES,Char,04,00,159
            str_pad('', 4,' ', STR_PAD_RIGHT),
        ];
        if($payment['psp_fee_amount'] > 0) {
            $result[] = implode('', $values);
        }
    }

    ++$offset;
}

$payments_lines_data = implode("\r\n", $result)."\r\n";


/*
    Create Export
*/

// generate the zip archive
$tmpfile = tempnam(sys_get_temp_dir(), "zip");
$zip = new ZipArchive();
if($zip->open($tmpfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    // could not create the ZIP archive
    throw new Exception('Unable to create a ZIP file.', EQ_ERROR_UNKNOWN);
}

// embed schema files
$zip->addFromString('CLIENTS_REGL.sch', $customers_files_data['schema']);
$zip->addFromString('HOPDIV_REGL.sch', $payments_header_schema);
$zip->addFromString('LOPDIV_REGL.sch', $payments_lines_schema);

// embed data files
$zip->addFromString('CLIENTS_REGL.txt', $customers_files_data['data']);
$zip->addFromString('HOPDIV_REGL.txt', $payments_header_data);
$zip->addFromString('LOPDIV_REGL.txt', $payments_lines_data);

$zip->close();

// read raw data
$data = file_get_contents($tmpfile);
unlink($tmpfile);

if($data === false) {
    throw new Exception('Unable to retrieve ZIP file content.', EQ_ERROR_UNKNOWN);
}

// create the export archive
$export = Export::create([
    'center_office_id'      => $params['center_office_id'],
    'export_type'           => 'payments',
    'data'                  => $data,
    'object_class'          => Payment::getType(),
    'object_ids'            => json_encode(array_column($payments, 'id'))
])
    ->read(['id'])
    ->first();

$accounting_index_updated = false;

try {
    // update journal index according to the number of payments
    AccountingJournal::id($journal['id'])->update(['index' => $journal['index']+$payments_count]);

    $accounting_index_updated = true;

    // mark processed payements as exported
    Payment::ids($payments_ids)->update(['is_exported' => true]);
}
catch(Exception $e) {
    // remove export if error triggered while updating journal index or flagging invoices as exported
    Export::id($export['id'])->delete();

    // set index of accounting journal back to previous value, if it was updated
    if($accounting_index_updated) {
        AccountingJournal::id($journal['id'])->update(['index' => $journal['index']]);
    }

    throw $e;
}

$context
    ->httpResponse()
    ->status(201)
    ->send();

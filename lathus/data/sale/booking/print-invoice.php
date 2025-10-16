<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use identity\Center;
use identity\Identity;
use sale\booking\Booking;
use sale\booking\Invoice;
use sale\booking\InvoiceLine;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;

use SepaQr\Data;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;

use equal\data\DataFormatter;
use core\setting\Setting;
use Twig\TwigFilter;

[$params, $providers] = eQual::announce([
    'description'   => "Render an invoice given its ID as a PDF document, for Lathus.",
    'params'        => [

        'id' => [
            'description'   => 'Identifier of the invoice to print.',
            'type'          => 'integer',
            'required'      => true
        ],

        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.default'
        ],

        'mode' =>  [
            'description'   => 'Mode in which document has to be rendered: simple (default) or detailed.',
            'type'          => 'string',
            'selection'     => ['grouped', 'detailed'],
            'default'       => 'grouped'
        ],

        'lang' =>  [
            'description'   => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'          => 'string',
            'default'       => constant('DEFAULT_LANG')
        ],

        'output' =>  [
            'description'   => 'Output format of the document.',
            'type'          => 'string',
            'selection'     => ['pdf', 'html'],
            'default'       => 'pdf'
        ]

    ],
    'constants'     => ['DEFAULT_LANG', 'L10N_LOCALE'],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/pdf',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

/**
 * Methods
 */

$currency = Setting::get_value('core', 'locale', 'currency', '€');
$formatMoney = function ($value) use($currency) {
    return number_format((float)($value), 2, ",", ".") . ' ' .$currency;
};

$date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
$formatDate = fn($value) => date($date_format, $value);

$map_days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$map_months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$formatDateLong = function($value) use($map_days, $map_months) {
    return $map_days[date('w', $value)].' '.date('j', $value).' '.$map_months[date('n', $value) - 1].' '.date('Y', $value);
};

$formatTime = fn($value) => sprintf('%02dh%02d', $value / 3600, $value / 60 % 60);

$formatPhone = function($value) {
    if(strlen($value) === 10) {
        return sprintf("%s %s %s %s %s",
            substr($value, 0, 2),
            substr($value, 2, 2),
            substr($value, 4, 2),
            substr($value, 6, 2),
            substr($value, 8)
        );
    }

    return DataFormatter::format($value, 'phone');
};

/**
 * Data controller
 */

/*
    1) retrieve the requested template
*/

$entity = 'lathus\sale\booking\Invoice';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = EQ_BASEDIR."/packages/$package/views/$class_path.{$params['view_id']}.html";
if(!file_exists($file)) {
    throw new Exception("unknown_view_id", EQ_ERROR_UNKNOWN_OBJECT);
}

/*
    2) check invoice exists
*/

$invoice = Invoice::id($params['id'])
    ->read(['id'])
    ->first();

if(is_null($invoice)) {
    throw new Exception("unknown_invoice", EQ_ERROR_UNKNOWN_OBJECT);
}

/*
    3) create valus array to inject data in template
*/

$data_to_inject = [
    'invoice' => [
        'number', 'status', 'type', 'has_orders', 'price', 'payment_reference', 'due_date'
    ],
    'booking' => [
        'date_expiry', 'date_from', 'date_to', 'time_from', 'time_to', 'price',
    ],
    'customer' => [
        'display_name', 'address_street', 'address_zip', 'address_dispatch', 'address_city'
    ],
    'center' => [
        'name', 'address_street', 'address_dispatch', 'address_zip', 'address_city', 'address_country', 'email', 'phone'
    ],
    'organisation' => [
        'name', 'legal_name', 'address_street', 'address_dispatch', 'address_zip', 'address_city', 'address_country', 'email', 'fax', 'phone',
        'bank_account_iban', 'bank_account_bic', 'registration_number', 'vat_number', 'website', 'signature'
    ]
];

/*
      3.1) get invoice data and format dates
*/

$invoice = Invoice::id($params['id'])
    ->read(array_merge(
        $data_to_inject['invoice'],
        [
            'customer_identity_id',
            'booking_id' => ['center_id' => ['organisation_id']]
        ]
    ))
    ->first(true);

$invoice['due_date'] = $formatDate($invoice['due_date']);

$reference_type = Setting::get_value('sale', 'organization', 'booking.reference.type', 'VCS');
$invoice['payment_reference'] = DataFormatter::format($invoice['payment_reference'], $reference_type);

/*
      3.2) get booking data
*/

$booking = Booking::id($invoice['booking_id']['id'])
    ->read($data_to_inject['booking'])
    ->first(true);

$booking['date_from_long'] = $formatDateLong($booking['date_from']);
$booking['date_from_long'] = $formatDateLong($booking['date_from']);
$booking['date_to_long'] = $formatDateLong($booking['date_to']);
$booking['date_from'] = $formatDate($booking['date_from']);
$booking['date_to'] = $formatDate($booking['date_to']);
$booking['date_expiry'] = $formatDate($booking['date_expiry']);
$booking['time_from'] = $formatTime($booking['time_from']);
$booking['time_to'] = $formatTime($booking['time_to']);

/*
      3.3) get customer data
*/

$customer = Identity::id($invoice['customer_identity_id'])
    ->read($data_to_inject['customer'])
    ->first(true);

/*
      3.4) get center data
*/

$center = Center::id($invoice['booking_id']['center_id']['id'])
    ->read($data_to_inject['center'])
    ->first(true);

$center['phone'] = $formatPhone($center['phone']);

/*
      3.5) get organisation data and handle img url
*/

$organisation = Identity::id($invoice['booking_id']['center_id']['organisation_id'])
    ->read(array_merge(
        $data_to_inject['organisation'],
        ['logo_document_id' => ['data', 'type']]
    ))
    ->first(true);

$organisation['bank_account_iban'] = DataFormatter::format($organisation['bank_account_iban'], 'iban');
$organisation['phone'] = $formatPhone($organisation['phone']);

/*
    3.6) handle fundings
*/

$inv = Invoice::id($params['id'])
    ->read([
        'status',
        'type',
        'is_deposit',
        'accounting_price',
        'fundings_ids' => [
            'modified',
            'description',
            'status',
            'type',
            'invoice_id',
            'due_date',
            'due_amount',
            'paid_amount',
            'payments_ids' => [
                'receipt_date',
                'amount',
                'payment_origin'
            ]
        ]
    ])
    ->first();

$fundings = [];
$fundings_payments = [];
$latest_funding_update = 0;
$total_paid = 0;
foreach($inv['fundings_ids'] as $funding) {
    // ignore fundings that have an invoice of their own (invoiced downpayments)
    if($funding['type'] === 'invoice' && $funding['invoice_id'] !== $inv['id']) {
        continue;
    }

    // assign standalone fundings (not attached to an invoice) to latest balance invoice only
    if(!$funding['invoice_id'] && ($inv['is_deposit'] || $inv['status'] === 'cancelled' || $inv['type'] !== 'invoice')) {
        continue;
    }

    // for credit notes, consider only direct fundings (relating to the invoice)
    if($inv['type'] === 'credit_note' && $funding['invoice_id'] !== $inv['id']) {
        continue;
    }

    $total_paid += $funding['paid_amount'];
    if($funding['modified'] > $latest_funding_update) {
        $latest_funding_update = $funding['modified'];
    }

    // stack payments
    if(!isset($funding['payments_ids']) || count($funding['payments_ids']) <= 0) {
        if($funding['paid_amount'] > 0 ) {
            $fundings_payments[] = [
                'receipt_date'      => $formatDate($funding['modified']),
                'amount'            => $funding['paid_amount'],
                'payment_origin'    => 'direct'
            ];
        }
    }
    else {
        foreach($funding['payments_ids'] as $payment) {
            $fundings_payments[] = [
                'receipt_date'      => $formatDate($payment['receipt_date']),
                'amount'            => $payment['amount'],
                'payment_origin'    => $payment['payment_origin']
            ];
        }
    }
    // ignore paid fundings
    if(round($funding['due_amount'], 2) == round($funding['paid_amount'], 2)) {
        continue;
    }
    $fundings[] = [
        'name'          => $funding['description'],
        'due_date'      => $formatDate($funding['due_date']),
        'due_amount'    => $funding['due_amount'],
        'paid_amount'   => $funding['paid_amount'],
        'remaining'     => round($funding['due_amount'], 2) - round($funding['paid_amount'], 2)
    ];
}


$date_fundings_update = date('d/m/Y', $latest_funding_update);
$total_paid = round($total_paid, 2);
// #memo: also used for payment_qr_uri
$total_remaining = round($inv['accounting_price'] - $total_paid , 2);

/*
    3.7) handle payment qr code
*/

$inv = Invoice::id($params['id'])
    ->read([
        'payment_reference',
        'booking_id' => [
            'center_id' => [
                'organisation_id' => [
                    'name',
                    'bank_account_iban',
                    'bank_account_bic'
                ]
            ]
        ]
    ])
    ->first();

$payment_qr_uri = '';
try {
    if(!isset($inv['payment_reference'])) {
        throw new Exception('no payment ref');
    }

    $inv['payment_reference'] = DataFormatter::format($inv['payment_reference'], $reference_type);

    $paymentData = Data::create()
        ->setServiceTag('BCD')
        ->setIdentification('SCT')
        ->setName($inv['booking_id']['center_id']['organisation_id']['name'])
        ->setIban(str_replace(' ', '', $inv['booking_id']['center_id']['organisation_id']['bank_account_iban']))
        ->setBic(str_replace(' ', '', $inv['booking_id']['center_id']['organisation_id']['bank_account_bic']))
        ->setRemittanceReference($inv['payment_reference'])
        ->setAmount($total_remaining);

    $result = Builder::create()
        ->data($paymentData)
        ->errorCorrectionLevel(new ErrorCorrectionLevelMedium()) // required by EPC standard
        ->build();

    $payment_qr_uri = $result->getDataUri();

}
catch(Exception $exception) {
    // unknown error
}

/*
    3.8) handle lines
*/

$invoice_lines = InvoiceLine::search(['invoice_id', '=', $invoice['id']])
    ->read([
        'product_id' => [
            'grouping_code_id' => [
                'name',
                'code'
            ],
            'product_model_id' => [
                'grouping_code_id' => [
                    'name',
                    'code'
                ]
            ]
        ]
    ])
    ->get();

$map_products_groupings = [];
foreach($invoice_lines as $line) {
    if(isset($map_products_groupings[$line['product_id']['id']])) {
        continue;
    }

    $grouping_code = null;
    if(isset($line['product_id']['grouping_code_id'])) {
        $grouping_code = $line['product_id']['grouping_code_id'];
    }
    elseif(isset($line['product_id']['product_model_id']['grouping_code_id'])) {
        $grouping_code = $line['product_id']['product_model_id']['grouping_code_id'];
    }

    if(is_null($grouping_code) || ($grouping_code['code'] === 'invisible' && $line['price'] === 0)) {
        continue;
    }

    $map_products_groupings[$line['product_id']['id']] = $grouping_code;
}

$invoice_lines = InvoiceLine::search(['invoice_id', '=', $invoice['id']])
    ->read([
        'name',
        'description',
        'qty',
        'free_qty',
        'unit_price',
        'vat_rate',
        'total',
        'price',
        'product_id' => ['label']
    ])
    ->get();

$map_groupings_lines = [];
foreach($invoice_lines as $line) {
    $grouping_name = $line['product_id']['label'];
    if(isset($map_products_groupings[$line['product_id']['id']])) {
        $grouping_name = $map_products_groupings[$line['product_id']['id']]['name'];
    }
    elseif(!empty($line['description'])) {
        $grouping_name = $line['description'];
    }

    if(!isset($map_groupings_lines[$grouping_name])) {
        $map_groupings_lines[$grouping_name] = [];
    }

    $map_groupings_lines[$grouping_name][] = $line;
}

$lines = [];
foreach($map_groupings_lines as $grouping_name => $grouping_lines) {
    switch($params['mode']) {
        case 'grouped':
            $total = 0;
            $price = 0;
            foreach($grouping_lines as $line) {
                $total += $line['total'];
                $price += $line['price'];
            }

            $lines[] = [
                'name'          => $grouping_name,
                'qty'           => 1,
                'free_qty'      => null,
                'unit_price'    => $total,
                'vat_rate'      => null,
                'total'         => $total,
                'price'         => $price,
                'is_group'      => true
            ];
            break;
        case 'detailed':
            $lines[] = [
                'name'          => $grouping_name,
                'qty'           => null,
                'free_qty'      => null,
                'unit_price'    => null,
                'vat_rate'      => null,
                'total'         => null,
                'price'         => null,
                'is_group'      => true
            ];

            foreach($grouping_lines as $line) {
                $lines[] = [
                    'name'          => $line['name'],
                    'qty'           => $line['qty'],
                    'free_qty'      => $line['free_qty'],
                    'unit_price'    => $line['unit_price'],
                    'vat_rate'      => $line['vat_rate'],
                    'total'         => $line['total'],
                    'price'         => $line['price'],
                    'is_group'      => false
                ];
            }
            break;
    }
}

/*
      3.10) set values
*/

$today = time();

$values = compact(
    'invoice',
    'booking',
    'customer',
    'center',
    'organisation',
    'fundings',
    'fundings_payments',
    'date_fundings_update',
    'total_paid',
    'total_remaining',
    'payment_qr_uri',
    'lines',
    'today'
);

/*
    3.9) handle template parts
*/

$booking = Booking::id($booking['id'])
    ->read(['center_id' => ['template_category_id']])
    ->first();

$template = Template::search([
    ['category_id', '=', $booking['center_id']['template_category_id']],
    ['code', '=', 'invoice'],
    ['type', '=', 'invoice']
])
    ->read(['id','parts_ids' => ['name', 'value']], $params['lang'])
    ->first(true);

$template_parts = [];
foreach($template['parts_ids'] as $part) {
    $value = $part['value'];
    foreach($data_to_inject as $object => $fields) {
        foreach($fields as $field) {
            $value = str_replace('{'.$object.'.'.$field.'}', $values[$object][$field], $value);
        }
    }

    $booking_extra_fields = ['date_from_long', 'date_to_long'];
    foreach($booking_extra_fields as $field) {
        $value = str_replace('{booking.'.$field.'}', $values['booking'][$field], $value);
    }

    $template_parts[$part['name']] = $value;
}

$values['parts'] = $template_parts;

/*
    5) inject all values into the template
*/

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR."/packages/$package/views/");

    $twig = new TwigEnvironment($loader);
    /**  @var ExtensionInterface **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    // do not rely on system locale (LC_*)

    $filter = new TwigFilter('format_money', $formatMoney);
    $twig->addFilter($filter);

    $date_filter = new TwigFilter('format_date', $formatDate);
    $twig->addFilter($date_filter);

    $date_filter = new TwigFilter('format_date_long', $formatDateLong);
    $twig->addFilter($date_filter);

    $date_filter = new TwigFilter('format_time', $formatTime);
    $twig->addFilter($date_filter);

    $template = $twig->load("{$class_path}.{$params['view_id']}.html");

    $html = $template->render($values);
}
catch(Exception $e) {
    trigger_error("ORM::error while parsing template - ".$e->getMessage(), QN_REPORT_DEBUG);
    throw new Exception("template_parsing_issue", QN_ERROR_INVALID_CONFIG);
}

if($params['output'] == 'html') {
    $context->httpResponse()
        ->header('Content-Type', 'text/html')
        ->body($html)
        ->send();
    exit(0);
}

/*
    6) convert HTML to PDF
*/

// instantiate and use the dompdf class
$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml((string) $html);
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");
$canvas->page_text(530, $canvas->get_height() - 35, "p. {PAGE_NUM} / {PAGE_COUNT}", $font, 9, array(0,0,0));
// $canvas->page_text(40, $canvas->get_height() - 35, "Export", $font, 9, array(0,0,0));


/*
    7) handle response
*/

$output = $dompdf->output();

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();

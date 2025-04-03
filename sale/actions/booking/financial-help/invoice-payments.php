<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use equal\data\DataFormatter;
use identity\Identity;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;
use Twig\TwigFilter;

use sale\booking\FinancialHelp;

[$params, $providers] = eQual::announce([
    'description'   => "Generates an invoice intended for a specific financial help.",
    'params'        => [
        'id' =>  [
            'type'              => 'integer',
            'description'       => "Identifier of the targeted financial help.",
            'min'               => 1,
            'required'          => true
        ],

        'organisation_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Identity',
            'description'       => "The organisation which the targeted identity is a partner of.",
            'default'           => function() {
                return Identity::id(1)
                    ->read(['id', 'name'])
                    ->adapt('json')
                    ->first(true);
            }
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "Start interval of the payments filter.",
            'default'           => function() {
                return strtotime('first day of january this year');
            }
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "End interval of the payments filter.",
            'required'          => true,
            'default'           => function() {
                return strtotime('last day of december this year');
            }
        ],

        'helper_name' => [
            'type'              => 'string',
            'description'       => "Financial helper name.",
            'required'          => true
        ],

        'helper_address1' => [
            'type'              => 'string',
            'description'       => "Financial helper address line 1.",
            'required'          => true
        ],

        'helper_address_dispatch' => [
            'type'              => 'string',
            'description'       => "Financial helper address dispatch."
        ],

        'helper_address2' => [
            'type'              => 'string',
            'description'       => "Financial helper address line 2."
        ]
    ],
    'access'        => [
        'groups'        => ['booking.default.user', 'finance.default.administrator', 'finance.default.user']
    ],
    'response'      => [
        'content-type'      => 'application/pdf',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$financial_help = FinancialHelp::id($params['id'])
    ->read([
        'payments_ids' => [
            'amount',
            'receipt_date',
            'funding_id' => ['booking_id' => ['organisation_id', 'date_from', 'date_to', 'customer_id' => ['name']]]
        ]
    ])
    ->first(true);

if(is_null($financial_help)) {
    throw new Exception("unknown_financial_help", EQ_ERROR_UNKNOWN_OBJECT);
}

$organisation = Identity::id($params['organisation_id'])
    ->read(['logo_document_id' => ['type', 'data']])
    ->first(true);

if(is_null($organisation)) {
    throw new Exception("unknown_organisation", EQ_ERROR_UNKNOWN_OBJECT);
}

$payments = [];
$price = 0;
foreach($financial_help['payments_ids'] as $payment) {
    if(
        $payment['receipt_date'] < $params['date_from'] || $payment['receipt_date'] > $params['date_to']
        || $payment['funding_id']['booking_id']['organisation_id'] !== $params['organisation_id']
    ) {
        continue;
    }

    $payments[] = $payment;
    $price += $payment['amount'];
}

$organisation = Identity::id($params['organisation_id'])
    ->read([
        'legal_name',
        'address_street',
        'address_zip',
        'address_city',
        'email',
        'phone',
        'fax',
        'signature',
        'website',
        'registration_number',
        'has_vat',
        'vat_number',
        'bank_account_iban',
        'bank_account_bic',
        'logo_document_id' => ['type', 'data']
    ])
    ->first();

$img_url = null;
if(!is_null($organisation['logo_document_id'])) {
    $img_url = "data:{$organisation['logo_document_id']['type']};base64, ".base64_encode($organisation['logo_document_id']['data']);
}

$values = [
    'header_img_url'            => $img_url ?? 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=',
    'signature'                 => $organisation['signature'] ?? '',
    'invoice_header_html'       => '',
    'invoice_notice_html'       => '',

    'customer_name'             => $params['helper_name'],
    'customer_address1'         => $params['helper_address1'],
    'customer_address_dispatch' => $params['helper_address_dispatch'],
    'customer_address2'         => $params['helper_address2'],

    'price'                     => round($price, 2),

    'company_name'              => $organisation['legal_name'],
    'company_address'           => sprintf("%s %s %s", $organisation['address_street'], $organisation['address_zip'], $organisation['address_city']),
    'company_email'             => $organisation['email'],
    'company_phone'             => DataFormatter::format($organisation['phone'], 'phone'),
    'company_fax'               => DataFormatter::format($organisation['fax'], 'phone'),
    'company_website'           => $organisation['website'],
    'company_reg_number'        => $organisation['registration_number'],
    'company_has_vat'           => $organisation['has_vat'],
    'company_vat_number'        => $organisation['vat_number'],

    // by default, we use organisation payment details (overridden in case Center has a management Office, see below)
    'company_iban'              => DataFormatter::format($organisation['bank_account_iban'], 'iban'),
    'company_bic'               => DataFormatter::format($organisation['bank_account_bic'], 'bic'),

    'payments'                  => $payments
];

$values['i18n'] = [
    'invoice'           => Setting::get_value('sale', 'locale', 'terms.invoice', null, array(), $params['lang']),
    'customer_name'     => Setting::get_value('lodging', 'locale', 'i18n.customer_name', null, array(), $params['lang']),
    'customer_address'  => Setting::get_value('lodging', 'locale', 'i18n.customer_address', null, array(), $params['lang']),
    'company_registry'  => Setting::get_value('lodging', 'locale', 'i18n.company_registry', null, array(), $params['lang']),
    'vat_number'        => Setting::get_value('lodging', 'locale', 'i18n.vat_number', null, array(), $params['lang']),
    'vat'               => Setting::get_value('lodging', 'locale', 'i18n.vat', null, array(), $params['lang']),
    'total_tax_incl'    => Setting::get_value('lodging', 'locale', 'i18n.total_tax_incl', null, [], $params['lang'])
];

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR.'/packages/sale/views/');

    $twig = new TwigEnvironment($loader);

    /** @var ExtensionInterface $extension */
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    $format_money_filter = new TwigFilter('format_money', function($value) {
        return number_format((float) $value, 2, ',', '.').' €';
    });
    $twig->addFilter($format_money_filter);

    $date_filter = new TwigFilter('format_date', function($value) {
        return date('d/m/y', $value);
    });
    $twig->addFilter($date_filter);

    $template = $twig->load('/booking/FinancialHelp.payments-invoice.html');

    $html = $template->render($values);
}
catch(Exception $e) {
    trigger_error("ORM::error while parsing template - ".$e->getMessage(), EQ_REPORT_DEBUG);
    throw new Exception("template_parsing_issue", EQ_ERROR_INVALID_CONFIG);
}

/***********************
 * Convert HTML to PDF *
 ***********************/

// instantiate and use the dompdf class
$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
// if external fonts are involved, tell dompdf to store them in /bin
$options->setFontDir(QN_BASEDIR.'/bin');
$dompdf->setPaper('A4', 'portrait');

// remove utf8mb4 chars (emojis)
$html = preg_replace('/(?:\xF0[\x90-\xBF][\x80-\xBF]{2} | [\xF1-\xF3][\x80-\xBF]{3} | \xF4[\x80-\x8F][\x80-\xBF]{2})/xs', '', $html);

$dompdf->loadHtml((string) $html, 'UTF-8');
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('helvetica', 'regular');
$canvas->page_text(530, $canvas->get_height() - 35, 'p. {PAGE_NUM} / {PAGE_COUNT}', $font, 9, array(0,0,0));

// get generated PDF raw binary
$output = $dompdf->output();

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();

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
use sale\camp\price\PriceAdapter;
use sale\camp\Sponsor;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;
use Twig\TwigFilter;

[$params, $providers] = eQual::announce([
    'description'   => "Generate the invoice pdf for the given price adapters of a specific sponsor.",
    'params'        => [

        'ids' => [
            'type'              => 'array',
            'description'       => "Price adapters ids of the sponsor the invoice.",
            'required'          => true
        ]

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

if(empty($params['ids'])) {
    throw new Exception("empty_ids", EQ_ERROR_INVALID_PARAM);
}

$price_adapters = PriceAdapter::ids($params['ids'])
    ->read([
        'sponsor_id',
        'value',
        'enrollment_id' => [
            'child_id'      => ['name'],
            'camp_id'       => ['short_name', 'accounting_code', 'date_from', 'date_to']
        ]
    ])
    ->get(true);

if(count($price_adapters) !== count($params['ids'])) {
    throw new Exception("unknown_objects", EQ_ERROR_UNKNOWN_OBJECT);
}

$sponsor_id = $price_adapters[0]['sponsor_id'];
$total = 0.0;
foreach($price_adapters as $price_adapter) {
    if($price_adapter['sponsor_id'] !== $sponsor_id) {
        throw new Exception("not_same_sponsor", EQ_ERROR_INVALID_PARAM);
    }

    $total += $price_adapter['value'];
}

$sponsor = Sponsor::id($sponsor_id)
    ->read(['name', 'complete_name', 'address_street', 'address_dispatch', 'address_zip', 'address_city', 'sponsor_type'])
    ->first(true);

if(is_null($sponsor)) {
    throw new Exception("unknow_object", EQ_ERROR_UNKNOWN_OBJECT);
}

$values = [
    'sponsor'           => $sponsor,
    'price_adapters'    => $price_adapters,
    'total'             => $total
];

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR.'/packages/sale/views/');

    $twig = new TwigEnvironment($loader);

    /** @var ExtensionInterface $extension */
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    $currency = Setting::get_value('core', 'locale', 'currency', '€');
    $format_money_filter = new TwigFilter('format_money', function($value) use($currency) {
        return number_format((float) $value, 2, ',', '.').' '.$currency;
    });
    $twig->addFilter($format_money_filter);

    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
    $date_filter = new TwigFilter('format_date', function($value) use($date_format) {
        return date($date_format, $value);
    });
    $twig->addFilter($date_filter);

    $template = $twig->load('/camp/sponsor/invoice.html');

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
$canvas->page_text(530, $canvas->get_height() - 35, 'p. {PAGE_NUM} / {PAGE_COUNT}', $font, 9);

// get generated PDF raw binary
$output = $dompdf->output();

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();

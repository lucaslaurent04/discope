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
use identity\Center;
use sale\camp\Sponsor;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\TwigFilter;

[$params, $providers] = eQual::announce([
    'description'   => "Print the invoice of a specific sponsor.",
    'params'        => [

        'id' => [
            'type'              => 'integer',
            'description'       => "Id of the sponsor to invoice.",
            'required'          => true
        ],

        'view_id' =>  [
            'description'       => 'The identifier of the view <type.name>.',
            'type'              => 'string',
            'default'           => 'print.invoice'
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit.",
            'default'           => fn() => strtotime('first day of january this year')
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "Date interval upper limit.",
            'default'           => fn() => strtotime('last day of december this year')
        ],

        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => "The center for which we want to invoice the sponsors helps.",
            'default'           => 1
        ]

    ],
    'constants'     => ['L10N_LOCALE', 'L10N_TIMEZONE'],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['camp.default.user'],
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

/**
 * Methods
 */

$currency = Setting::get_value('core', 'locale', 'currency', '€');
$formatMoney = function($value) use($currency) {
    return number_format((float) $value, 2, ',', '.').' '.$currency;
};

$map_days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$map_months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

$formatDateLong = function($value) use($map_days, $map_months) {
    return $map_days[date('w', $value)].' '.date('j', $value).' '.$map_months[date('n', $value) - 1].' '.date('Y', $value);
};

/**
 * Action
 */

/*
    Retrieve the requested template
*/

$entity = 'sale\camp\Sponsor';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = EQ_BASEDIR."/packages/{$package}/views/$class_path.{$params['view_id']}.html";

if(!file_exists($file)) {
    throw new Exception("unknown_view_id", EQ_ERROR_UNKNOWN_OBJECT);
}

/*
    Prepare values for template
*/

$sponsor = Sponsor::id($params['id'])
    ->read([
        'complete_name',
        'address_street',
        'address_dispatch',
        'address_zip',
        'address_city',
        'price_adapters_ids' => [
            'value',
            'enrollment_id' => [
                'center_id',
                'date_from'
            ]
        ]
    ])
    ->first(true);

if(is_null($sponsor)) {
    throw new Exception("unknown_sponsor", EQ_ERROR_UNKNOWN_OBJECT);
}

$center = Center::id($params['center_id'])
    ->read([
        'organisation_id' => [
            'bank_account_iban',
            'bank_account_bic',
            'logo_document_id' => [
                'type',
                'data'
            ]
        ]])
    ->first();

if(is_null($center)) {
    throw new Exception("unknown_center", EQ_ERROR_UNKNOWN_OBJECT);
}

$center['organisation_id']['bank_account_iban'] = DataFormatter::format($center['organisation_id']['bank_account_iban'], 'iban');
$center['organisation_id']['bank_account_bic'] = DataFormatter::format($center['organisation_id']['bank_account_bic'], 'bic');

$img_url = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';

$logo_document_data = $center['organisation_id']['logo_document_id']['data'] ?? null;
if($logo_document_data) {
    $content_type = $center['organisation_id']['logo_document_id']['type'] ?? 'image/png';
    $img_url = "data:{$content_type};base64, ".base64_encode($logo_document_data);
}

$total = 0;

$map_amount_price_adapters = [];
foreach($sponsor['price_adapters_ids'] as $price_adapter) {
    if(
        $price_adapter['enrollment_id']['center_id'] !== $center['id']
        || $price_adapter['enrollment_id']['date_from'] < $params['date_from']
        || $price_adapter['enrollment_id']['date_from'] > $params['date_to']
    ) {
        continue;
    }

    if(!isset($map_amount_price_adapters[$price_adapter['value']])) {
        $map_amount_price_adapters[$price_adapter['value']] = [
            'qty'   => 0,
            'total' => 0
        ];
    }
    $map_amount_price_adapters[$price_adapter['value']]['qty']++;
    $map_amount_price_adapters[$price_adapter['value']]['total'] += $price_adapter['value'];
    $total += $price_adapter['value'];
}

$today = time();

$values = compact('sponsor', 'center', 'img_url', 'map_amount_price_adapters', 'total', 'today');

/*
    Inject all values into the template
*/

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR."/packages/$package/views/");

    $twig = new TwigEnvironment($loader);
    /**  @var ExtensionInterface **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    $date_long_filter = new TwigFilter('format_date_long', $formatDateLong);
    $twig->addFilter($date_long_filter);

    $format_money_filter = new TwigFilter('format_money', $formatMoney);
    $twig->addFilter($format_money_filter);

    $template = $twig->load("$class_path.{$params['view_id']}.html");

    $html = $template->render($values);
}
catch(Exception $e) {
    trigger_error("ORM::error while parsing template - ".$e->getMessage(), EQ_REPORT_DEBUG);
    throw new Exception("template_parsing_issue", EQ_ERROR_INVALID_CONFIG);
}

/*
    Convert HTML to PDF
*/

$date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->setPaper('A4');
$dompdf->loadHtml($html);
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");

/*
    Response
*/

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="facture.pdf"')
        ->body($dompdf->output())
        ->send();

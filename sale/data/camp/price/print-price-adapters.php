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
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\TwigFilter;

[$params, $providers] = eQual::announce([
    'description'   => "Print list of price adapters.",
    'params'        => [

        'view_id' =>  [
            'description'       => 'The identifier of the view <type.name>.',
            'type'              => 'string',
            'default'           => 'print.list'
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

/**
 * Action
 */

/*
    Retrieve the requested template
*/

$entity = 'sale\camp\price\PriceAdapter';
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

$price_adapters = PriceAdapter::search([
    ['sponsor_id', '<>', null],
    ['origin_type', 'in', ['commune', 'community-of-communes']]
])
    ->read([
        'value',
        'sponsor_id' => [
            'complete_name'
        ],
        'enrollment_id' => [
            'price',
            'child_id' => [
                'firstname',
                'lastname'
            ],
            'camp_id' => [
                'sojourn_code',
                'accounting_code'
            ]
        ]
    ])
    ->get(true);

usort($price_adapters, fn($a, $b) => $a['sponsor_id']['complete_name'] <=> $b['sponsor_id']['complete_name']);

$values = compact('price_adapters');

/*
    Inject all values into the template
*/

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR."/packages/$package/views/");

    $twig = new TwigEnvironment($loader);
    /**  @var ExtensionInterface **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

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

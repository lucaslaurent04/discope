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
use identity\Center;
use sale\camp\Child;
use sale\camp\Guardian;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\TwigFilter;

[$params, $providers] = eQual::announce([
    'description'   => "Render children's pre-registration given their IDs as a PDF document.",
    'params'        => [

        'id' => [
            'type'          => 'integer',
            'description'   => "Identifier of the child for which we need to print the pre-registration invoice."
        ],

        'ids' => [
            'type'          => 'array',
            'description'   => "Identifiers of the children for which we need to print the pre-registration invoice.",
            'help'          => "All children must have the same main guardian.",
            'default'       => []
        ],

        'center_id' => [
            'type'          => 'integer',
            'description'   => "The center for which we want to confirm the children pre-registration.",
            'required'      => true
        ],

        'lang' =>  [
            'type'          => 'string',
            'description'   => "Language in which labels and multilang field have to be returned (2 letters ISO 639-1).",
            'default'       => constant('DEFAULT_LANG')
        ],

        'output' =>  [
            'type'          => 'string',
            'description'   => 'Output format of the document.',
            'selection'     => ['pdf', 'html'],
            'default'       => 'pdf'
        ]

    ],
    'constants'             => ['DEFAULT_LANG', 'L10N_LOCALE'],
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

if(isset($params['id'])  && !empty($params['ids'])) {
    throw new Exception("only_one_id_param_allowed", EQ_ERROR_INVALID_PARAM);
}

if(!isset($params['id']) && empty($params['ids'])) {
    throw new Exception("one_id_param_required", EQ_ERROR_INVALID_PARAM);
}

$ids = $params['ids'];
if(empty($ids)) {
    $ids[] = $params['id'];
}

$center = Center::id($params['center_id'])
    ->read([
        'name',
        'address_street',
        'address_dispatch',
        'address_zip',
        'address_city',
        'phone'
    ])
    ->first(true);

if(is_null($center)) {
    throw new Exception("unknown_center", EQ_ERROR_UNKNOWN_OBJECT);
}

$children = Child::ids($ids)
    ->read([
        'firstname',
        'lastname',
        'main_guardian_id',
        'enrollments_ids' => [
            'price',
            'camp_id' => [
                'short_name',
                'sojourn_number',
                'date_from',
                'date_to',
                'accounting_code',
                'product_id',
                'day_product_id',
                'center_id'
            ],
            'enrollment_lines_ids' => [
                'product_id' => ['label'],
                'price'
            ],
            'price_adapters_ids' => [
                'name',
                'price_adapter_type',
                'origin_type',
                'value'
            ]
        ]
    ])
    ->get(true);

if(count($ids) !== count($children)) {
    throw new Exception("unknown_children", EQ_ERROR_UNKNOWN_OBJECT);
}

foreach($children as $child) {
    $child['enrollments_ids'] = array_filter($child['enrollments_ids'] ?? [], function($enrollment) use($center) {
        return $enrollment['camp_id']['center_id'] === $center['id']
            && date('Y', $enrollment['camp_id']['date_from']) === date('Y');
    });
}

$children = array_filter($children, function($child) {
    return !empty($child['enrollments_ids']);
});

if(empty($children)) {
    throw new Exception("no_enrollments", EQ_ERROR_INVALID_PARAM);
}

$main_guardian = Guardian::id($children[0]['main_guardian_id'])
    ->read(['lastname', 'firstname', 'address_street', 'address_dispatch', 'address_zip', 'address_city'])
    ->first();

/***************
 * Create HTML *
 ***************/

$total_amount = 0;
foreach($children as $child) {
    foreach($child['enrollments_ids'] as $enrollment) {
        foreach($enrollment['enrollment_lines_ids'] as $line) {
            $total_amount += $line['price'];
        }
    }
}

$remaining_amount = 0;
foreach($children as $child) {
    foreach($child['enrollments_ids'] as $enrollment) {
        $remaining_amount += $enrollment['price'];

        foreach($enrollment['price_adapters_ids'] as $price_adapter) {
            // #memo - the 'other' and 'loyalty-discount' price-adapters are already applied on price
            if(in_array($price_adapter['origin_type'], ['other', 'loyalty-discount'])) {
                continue;
            }

            $remaining_amount -= $price_adapter['value'];
        }
    }
}

$map_enrollment_percent_price_adapter = [];
foreach($children as $child) {
    foreach($child['enrollments_ids'] as $enrollment) {
        foreach($enrollment['price_adapters_ids'] as $price_adapter) {
            if($price_adapter['price_adapter_type'] === 'percent') {
                if(strpos($price_adapter['name'], '%') === false) {
                    $price_adapter['name'] .= ' ('.$price_adapter['value'].'%)';
                }

                $camp_enrollment_line = null;
                foreach($enrollment['enrollment_lines_ids'] as $enrollment_line) {
                    if(in_array($enrollment_line['product_id']['id'], [$enrollment['camp_id']['product_id'], $enrollment['camp_id']['day_product_id']])) {
                        $camp_enrollment_line = $enrollment_line;
                    }
                }

                $price_adapter['value'] = $price_adapter['value'] / 100 * $camp_enrollment_line['price'];

                $map_enrollment_percent_price_adapter[$enrollment['id']] = $price_adapter;
                break;
            }
        }
    }
}

$values = [
    'center'                                => $center,
    'main_guardian'                         => $main_guardian,
    'children'                              => $children,
    'date'                                  => strtotime('now'),
    'total_amount'                          => $total_amount,
    'remaining_amount'                      => $remaining_amount,
    'map_enrollment_percent_price_adapter'  => $map_enrollment_percent_price_adapter
];

$entity = 'sale\camp\Enrollment';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR.'/packages/sale/views/');

    $twig = new TwigEnvironment($loader);
    /**  @var ExtensionInterface **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    $currency = Setting::get_value('core', 'locale', 'currency', '€');
    // do not rely on system locale (LC_*)
    $filter = new TwigFilter('format_money', function ($value) use($currency) {
        return number_format((float)($value), 2, ",", ".").' '.$currency;
    });
    $twig->addFilter($filter);

    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
    $date_filter = new TwigFilter('format_date', function($value) use($date_format) {
        if($value instanceof DateTime) {
            return $value->format($date_format);
        }
        return date($date_format, $value);
    });
    $twig->addFilter($date_filter);

    $template = $twig->load("$class_path.print.preregistration-invoice.html");

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

/***********************
 * Convert HTML to PDF *
 ***********************/

// instantiate and use the dompdf class
$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml((string) $html);
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");
// $canvas->page_text(530, $canvas->get_height() - 35, "p. {PAGE_NUM} / {PAGE_COUNT}", $font, 9, array(0,0,0));
// $canvas->page_text(40, $canvas->get_height() - 35, "Export", $font, 9, array(0,0,0));

// get generated PDF raw binary
$output = $dompdf->output();

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();

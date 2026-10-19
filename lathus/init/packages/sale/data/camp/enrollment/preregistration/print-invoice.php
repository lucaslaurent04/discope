<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use discope\setting\Setting;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use identity\Center;
use sale\camp\catalog\Product;
use sale\camp\Child;
use sale\camp\Guardian;
use sale\camp\Institution;
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
        'institution_id',
        'enrollments_ids' => [
            'status',
            'price',
            'is_ase',
            'camp_id' => [
                'short_name',
                'sojourn_number',
                'date_from',
                'date_to',
                'accounting_code',
                'product_id',
                'day_product_id',
                'center_id',
                'is_clsh'
            ],
            'enrollment_lines_ids' => [
                'price',
                'product_id' => ['label']
            ],
            'fundings_ids' => [
                'paid_amount',
                'payments_ids' => [
                    'amount',
                    'payment_origin',
                    'payment_method',
                    'description'
                ]
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

foreach($children as &$child) {
    $child['enrollments_ids'] = array_filter($child['enrollments_ids'] ?? [], function($enrollment) use($center) {
        return $enrollment['camp_id']['center_id'] === $center['id'] && $enrollment['camp_id']['date_from'] > time() && $enrollment['status'] === 'confirmed';
    });
}
unset($child);

$children = array_filter($children, function($child) {
    return !empty($child['enrollments_ids']);
});

if(empty($children)) {
    throw new Exception("no_enrollments", EQ_ERROR_INVALID_PARAM);
}

$recipient_address = [];
if($children[0]['enrollments_ids'][0]['is_ase'] && !is_null($children[0]['institution_id'])) {
    $institution = Institution::id($children[0]['institution_id'])
        ->read(['name', 'address_street', 'address_dispatch', 'address_zip', 'address_city'])
        ->first();

    $recipient_address = [
        'name'      => $institution['name'],
        'street'    => $institution['address_street'],
        'dispatch'  => $institution['address_dispatch'],
        'zip'       => $institution['address_zip'],
        'city'      => $institution['address_city']
    ];
}
else {
    $main_guardian = Guardian::id($children[0]['main_guardian_id'])
        ->read(['lastname', 'firstname', 'address_street', 'address_dispatch', 'address_zip', 'address_city'])
        ->first();

    $recipient_address = [
        'name'      => strtoupper($main_guardian['lastname']).' '.$main_guardian['firstname'],
        'street'    => $main_guardian['address_street'],
        'dispatch'  => $main_guardian['address_dispatch'],
        'zip'       => $main_guardian['address_zip'],
        'city'      => $main_guardian['address_city']
    ];
}

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
        foreach($enrollment['fundings_ids'] as $funding) {
            $remaining_amount -= $funding['paid_amount'];
        }
    }
}

$enrollments = [];
foreach($children as $child) {
    foreach($child['enrollments_ids'] as &$enrollment) {
        $enrollment['child_id'] = [
            'firstname' => $child['firstname'],
            'lastname'  => $child['lastname']
        ];
    }

    $enrollments = array_merge($enrollments, $child['enrollments_ids']);
}

foreach($enrollments as &$enrollment) {
    $camp_product_ids = null;
    if($enrollment['camp_id']['is_clsh']) {
        $camp_product_ids = Product::search(['camp_product_type', 'in', ['clsh-full-5-days', 'clsh-full-4-days', 'clsh-day']])->ids();
    }
    else {
        $camp_product_ids = Product::search(['camp_product_type', '=', 'full'])->ids();
    }

    $camp_product_line = null;
    foreach($enrollment['enrollment_lines_ids'] as &$line) {
        $line['is_camp_product'] = in_array($line['product_id']['id'], $camp_product_ids);
        if($line['is_camp_product']) {
            $camp_product_line = $line;
        }
    }

    foreach($enrollment['price_adapters_ids'] as &$price_adapter) {
        if(in_array($price_adapter['origin_type'], ['commune', 'community-of-communes', 'department-ase', 'department-caf', 'department-msa'])) {
            continue;
        }

        if($price_adapter['price_adapter_type'] === 'percent') {
            $price_adapter['value'] = round($camp_product_line['price'] * ($price_adapter['value'] / 100), 2);
        }

        $price_adapter['value'] = -1 * $price_adapter['value'];

        $total_amount += $price_adapter['value'];
    }
}

usort($enrollments, function($a, $b) {
    return $a['camp_id']['date_from'] <=> $b['camp_id']['date_from'];
});

$values = [
    'center'                                => $center,
    'recipient_address'                     => $recipient_address,
    'children'                              => $children,
    'enrollments'                           => $enrollments,
    'date'                                  => strtotime('now'),
    'total_amount'                          => $total_amount,
    'remaining_amount'                      => $remaining_amount
];

$entity = 'sale\camp\Enrollment';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR.'/packages/sale/views/');

    $twig = new TwigEnvironment($loader);
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

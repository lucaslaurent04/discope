<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use core\setting\Setting;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use sale\camp\Enrollment;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\TwigFilter;

[$params, $providers] = eQual::announce([
    'description'   => "Render enrollment confirmation given its ID as a PDF document.",
    'params'        => [

        'id' => [
            'type'          => 'integer',
            'description'   => "Identifier of the enrollment for which we need to print the confirmation.",
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
    'access' => [
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

$enrollment = Enrollment::id($params['id'])
    ->read([
        'price',
        'weekend_extra',
        'presence_day_1', 'presence_day_2', 'presence_day_3', 'presence_day_4', 'presence_day_5',
        'daycare_day_1', 'daycare_day_2', 'daycare_day_3', 'daycare_day_4', 'daycare_day_5',
        'camp_id' => [
            'short_name',
            'sojourn_number',
            'accounting_code',
            'price',
            'date_from',
            'date_to',
            'is_clsh',
            'center_id' => [
                'name',
                'template_category_id',
                'address_street',
                'address_dispatch',
                'address_zip',
                'address_city',
                'phone'
            ]
        ],
        'child_id' => [
            'name',
            'firstname',
            'lastname',
            'main_guardian_id' => [
                'lastname',
                'firstname',
                'address_street',
                'address_dispatch',
                'address_zip',
                'address_city'
            ]
        ]
    ])
    ->first(true);

if(is_null($enrollment)) {
    throw new Exception("unknown_enrollment", EQ_ERROR_UNKNOWN_OBJECT);
}

$camp = $enrollment['camp_id'];
$center = $enrollment['camp_id']['center_id'];

$child = $enrollment['child_id'];
$main_guardian = $enrollment['child_id']['main_guardian_id'];

/***************
 * Create HTML *
 ***************/

$template = Template::search([
    ['category_id', '=', $center['template_category_id']],
    ['type', '=', 'camp'],
    ['code', '=', 'confirmation'],
])
    ->read(['parts_ids' => ['name', 'value']])
    ->first();

if(is_null($template)) {
    throw new Exception("unknow_template", EQ_ERROR_UNKNOWN_OBJECT);
}
$map_parts = [];
foreach($template['parts_ids'] as $part) {
    $map_parts[$part['name']] = $part['value'];
}
$required_parts = [
    'message', 'message_signature', 'start_date', 'start_schedule', 'start_location', 'end_date', 'end_schedule',
    'end_saturday_morning_schedule', 'end_location', 'additional_info', 'weekend_info',
    'clsh_start_schedule', 'clsh_end_schedule', 'clsh_daycare_start_schedule', 'clsh_daycare_end_schedule'
];
foreach($required_parts as $part_name) {
    if(!isset($map_parts[$part_name])) {
        throw new Exception("unknow_template_part", EQ_ERROR_UNKNOWN_OBJECT);
    }
}

$date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
$currency = Setting::get_value('core', 'locale', 'currency', '€');

$formatter = new IntlDateFormatter(
    constant('L10N_LOCALE'),
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    constant('L10N_TIMEZONE'),
    IntlDateFormatter::GREGORIAN
);

$presence_dates = [];
$date = $camp['date_from'];
$index = 1;
while($date <= $camp['date_to']) {
    if(!$camp['is_clsh'] || $enrollment['presence_day_'.$index]) {
        $presence_dates[$index] = $date;
    }
    $date += 60 * 60 * 24;
    $index++;
}

$template_values = [
    'name'              => $child['name'],
    'firstname'         => $child['firstname'],
    'lastname'          => strtoupper($child['lastname']),
    'sojourn_number'    => $camp['sojourn_number'],
    'accounting_code'   => $camp['accounting_code'],
    'camp'              => $camp['short_name'],
    'date_from'         => date($date_format, $camp['date_from']),
    'date_to'           => date($date_format, $camp['date_to']),
    'date_from_long'    => $formatter->format($camp['date_from']),
    'date_to_long'      => $formatter->format($camp['date_to']),
    'presence_dates'    => implode(', ', array_map(function($date) use($date_format) { return date($date_format, $date); },$presence_dates)),
    'days_qty'          => count($presence_dates),
    'price'             => number_format((float)($enrollment['price']), 2, ",", ".").' '.$currency
];

foreach(array_keys($map_parts) as $part_name) {
    foreach($template_values as $key => $value) {
        $map_parts[$part_name] = str_replace('{'.$key.'}', $value, $map_parts[$part_name]);
    }
}

if($camp['is_clsh']) {
    if(isset($map_parts['message_clsh'])) {
        $map_parts['message'] = $map_parts['message_clsh'];
    }
    if(isset($map_parts['additional_info_clsh'])) {
        $map_parts['additional_info'] = $map_parts['additional_info_clsh'];
    }

    $tmp_start_date = $map_parts['start_date'];
    $map_parts['start_date'] = '';
    foreach($presence_dates as $day_index => $presence_date) {
        if(!$enrollment['presence_day_'.$day_index]) {
            continue;
        }

        $vals = [
            'date'      => date($date_format, $presence_date),
            'date_long' => $formatter->format($presence_date),
            'schedule'  => strip_tags(in_array($enrollment['daycare_day_'.$day_index], ['am', 'full']) ? $map_parts['clsh_daycare_start_schedule'] : $map_parts['clsh_start_schedule'])
        ];
        $day_start = $tmp_start_date;
        foreach($vals as $key => $val) {
            $day_start = str_replace('{'.$key.'}', $val, $day_start);
        }

        $map_parts['start_date'] .= $day_start;
    }

    $tmp_end_date = $map_parts['end_date'];
    $map_parts['end_date'] = '';
    foreach($presence_dates as $day_index => $presence_date) {
        if(!$enrollment['presence_day_'.$day_index]) {
            continue;
        }

        $vals = [
            'date'      => date($date_format, $presence_date),
            'date_long' => $formatter->format($presence_date),
            'schedule'  => strip_tags(in_array($enrollment['daycare_day_'.$day_index], ['pm', 'full']) ? $map_parts['clsh_daycare_end_schedule'] : $map_parts['clsh_end_schedule'])
        ];
        $day_end = $tmp_end_date;
        foreach($vals as $key => $val) {
            $day_end = str_replace('{'.$key.'}', $val, $day_end);
        }

        $map_parts['end_date'] .= $day_end;
    }
}
else {
    $vals = [
        'date'      => $template_values['date_from'],
        'date_long' => $template_values['date_from_long'],
        'schedule'  => strip_tags($map_parts['start_schedule'])
    ];
    foreach($vals as $key => $val) {
        $map_parts['start_date'] = str_replace('{'.$key.'}', $val, $map_parts['start_date']);
    }

    $vals = [
        'date'      => $template_values['date_to'],
        'date_long' => $template_values['date_to_long'],
        'schedule'  => strip_tags($map_parts['end_schedule'])
    ];

    if($enrollment['weekend_extra'] === 'saturday-morning') {
        $date_to = $camp['date_to'] + (60 * 60 * 24);
        $vals['date'] = date($date_format, $date_to);
        $vals['date_long'] = $formatter->format($date_to);
        $vals['schedule'] = strip_tags($map_parts['end_saturday_morning_schedule']);
    }

    foreach($vals as $key => $val) {
        $map_parts['end_date'] = str_replace('{'.$key.'}', $val, $map_parts['end_date']);
    }
}

$values = [
    'enrollment'    => $enrollment,
    'center'        => $center,
    'main_guardian' => $main_guardian,
    'date'          => strtotime('now'),
    'map_parts'     => $map_parts
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

    // do not rely on system locale (LC_*)
    $filter = new TwigFilter('format_money', function ($value) use($currency) {
        return number_format((float)($value), 2, ",", ".").' '.$currency;
    });
    $twig->addFilter($filter);

    $date_filter = new TwigFilter('format_date', function($value) use($date_format) {
        if($value instanceof DateTime) {
            return $value->format($date_format);
        }
        return date($date_format, $value);
    });
    $twig->addFilter($date_filter);

    $template = $twig->load("$class_path.print.confirmation.html");

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

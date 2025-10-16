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
use sale\booking\Booking;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\TwigFilter;

[$params, $providers] = eQual::announce([
    'description'   => "Render a booking quote as a PDF document, given its id.",
    'params'        => [
        'id' => [
            'description'   => 'Identifier of the booking to print.',
            'type'          => 'integer',
            'required'      => true
        ],
        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.activity-weekly'
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
    'constants'             => ['DEFAULT_LANG', 'L10N_LOCALE'],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/pdf',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

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

$date_key_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
$formatDateIndex = function($value) use($map_days) {
    $date = new DateTime($value);
    $day_index = $date->format('w');

    return $map_days[$day_index].' '.$date->format('d/m');
};

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

$startOfWeek = function($value) {
    $timestamp = strtotime($value);
    $monday = strtotime('monday this week', $timestamp);

    return date('Y-m-d', $monday);
};

$formatActivityName = function($value) {
    return preg_replace('/\s\([^)]+\)$/', '', $value);
};

/**
 * Data controller
 */

/*
    Retrieve the requested template
*/

$entity = 'sale\booking\Booking';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = QN_BASEDIR."/packages/{$package}/views/{$class_path}.{$params['view_id']}.html";

if(!file_exists($file)) {
    throw new Exception("unknown_view_id", QN_ERROR_UNKNOWN_OBJECT);
}

/*
    Get data
*/

$booking = Booking::id($params['id'])
    ->read([
        'date_from',
        'date_to',
        'activity_weeks_descriptions',
        'customer_identity_id' => [
            'name',
            'address_city'
        ],
        'booking_activities_ids' => [
            'name',
            'group_num',
            'activity_date',
            'schedule_from',
            'schedule_to',
            'time_slot_id'  => ['code']
        ],
        'booking_lines_groups_ids' => [
            'activity_group_num'
        ],
        'center_id' => [
            'organisation_id' => [
                'name',
                'logo_document_id' => ['data', 'type']
            ]
        ]
    ])
    ->first(true);

if(!is_null($booking['activity_weeks_descriptions'])) {
    $booking['activity_weeks_descriptions'] = json_decode($booking['activity_weeks_descriptions']);
}

$map_week_day_timeslot_group_activity = [];

$has_ev_activities = false;

$week_num = -1;
$date = $booking['date_from'];
while($date <= $booking['date_to']) {
    if($week_num < 0 || date('w', $date) == '1') {
        $map_week_day_timeslot_group_activity[++$week_num] = [];
    }

    $date_key = date('Y-m-d', $date);

    $map_week_day_timeslot_group_activity[$week_num][$date_key] = [
        'AM' => null,
        'PM' => null,
        'EV' => null
    ];

    $group_qty = 0;
    foreach($booking['booking_lines_groups_ids'] as $group) {
        if($group['activity_group_num'] > $group_qty) {
            $group_qty = $group['activity_group_num'];
        }
    }

    foreach(['AM', 'PM', 'EV'] as $time_slot_code) {
        $map_week_day_timeslot_group_activity[$week_num][$date_key][$time_slot_code] = array_fill(1, $group_qty, null);
    }

    foreach($booking['booking_activities_ids'] as $activity) {
        if($activity['activity_date'] === $date) {
            $map_week_day_timeslot_group_activity[$week_num][$date_key][$activity['time_slot_id']['code']][$activity['group_num']] = $activity;
        }
        if($activity['time_slot_id']['code'] === 'EV') {
            $has_ev_activities = true;
        }
    }

    $date += 60 * 60 * 24;
}

$time_slot_codes = ['AM', 'PM'];
if($has_ev_activities) {
    $time_slot_codes[] = 'EV';
}

$img_url = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';

$logo_document_data = $booking['center_id']['organisation_id']['logo_document_id']['data'] ?? null;
if($logo_document_data) {
    $content_type = $booking['center_id']['organisation_id']['logo_document_id']['type'] ?? 'image/png';
    $img_url = "data:{$content_type};base64, ".base64_encode($logo_document_data);
}

/*
    Set values
*/

$today = time();

$values = compact('booking', 'map_week_day_timeslot_group_activity', 'time_slot_codes', 'img_url', 'today');

/*
    Generate html
*/

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR . "/packages/{$package}/views/");

    $twig = new TwigEnvironment($loader);

    /**  @var $extension ExtensionInterface **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    $filter = new TwigFilter('format_money', $formatMoney);
    $twig->addFilter($filter);

    $date_filter = new TwigFilter('format_date', $formatDate);
    $twig->addFilter($date_filter);

    $date_index_filter = new TwigFilter('format_date_index', $formatDateIndex);
    $twig->addFilter($date_index_filter);

    $date_long_filter = new TwigFilter('format_date_long', $formatDateLong);
    $twig->addFilter($date_long_filter);

    $time_filter = new TwigFilter('format_time', $formatTime);
    $twig->addFilter($time_filter);

    $start_of_week_filter = new TwigFilter('start_of_week', $startOfWeek);
    $twig->addFilter($start_of_week_filter);

    $format_activity_name = new TwigFilter('format_activity_name', $formatActivityName);
    $twig->addFilter($format_activity_name);

    $template = $twig->load("{$class_path}.{$params['view_id']}.html");
    $html = $template->render($values);
}
catch(Exception $e) {
    trigger_error("ORM::error while parsing template - ".$e->getMessage(), EQ_REPORT_DEBUG);
    throw new Exception("template_parsing_issue", EQ_ERROR_INVALID_CONFIG);
}

/*
    Handle response
*/

if($params['output'] == 'html') {
    $context->httpResponse()
            ->header('Content-Type', 'text/html')
            ->body($html)
            ->send();

    exit(0);
}

$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->setPaper('A4', 'landscape');
$dompdf->loadHtml($html);
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('helvetica', 'regular');
$canvas->page_text(780, $canvas->get_height() - 35, 'p. {PAGE_NUM} / {PAGE_COUNT}', $font, 9);

$output = $dompdf->output();

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();

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
use sale\booking\TimeSlot;
use sale\camp\Camp;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

[$params, $providers] = eQual::announce([
    'description'   => "Print the planning of activities and meals for given week (time interval is always reduced to 7 days based on date_from).",
    'params'        => [
        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.planning'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit."
        ],
        'camp_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Camp',
            'description'       => "Filter by camp."
        ]
    ],
    'constants'     => ['L10N_LOCALE', 'L10N_TIMEZONE'],
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

/*
    Check given params
*/

if(!isset($params['date_from']) && !isset($params['camp_id'])) {
    throw new Exception("invalid_params", EQ_ERROR_INVALID_PARAM);
}

if(isset($params['date_from']) && isset($params['camp_id'])) {
    unset($params['date_from']);
}

/*
    Retrieve the requested template
*/

$entity = 'sale\camp\Camp';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = EQ_BASEDIR."/packages/$package/views/$class_path.{$params['view_id']}.html";

if(!file_exists($file)) {
    throw new Exception("unknown_view_id", EQ_ERROR_UNKNOWN_OBJECT);
}

/*
    Prepare values for template
*/

$domain = [
    ['status', '=', 'published']
];

$date_from = null;
$date_to = null;

if(isset($params['camp_id'])) {
    $domain[] = ['id', '=', $params['camp_id']];
}
else {
    $date_from = strtotime('Last Sunday');
    $date_to = strtotime('Saturday this week');
    if(isset($params['date_from'])) {
        if(date('l', $params['date_from']) !== 'Sunday') {
            $date_from = (new DateTime(date('Y-m-d', $params['date_from'])))->modify('last Sunday')->getTimestamp();
            $date_to = (new DateTime(date('Y-m-d', $params['date_from'])))->modify('Saturday this week')->getTimestamp();
        }
        else {
            $date_from = $params['date_from'];
            $date_to = (new DateTime(date('Y-m-d', $params['date_from'])))->modify('Saturday next week')->getTimestamp();
        }
    }

    if(isset($params['date_from'])) {
        $domain[] = ['date_from', '>=', $date_from];
        $domain[] = ['date_from', '<', $date_to];
    }
}

$camps = Camp::search($domain, ['sort' => ['sojourn_number' => 'asc']])
    ->read([
        'date_from',
        'date_to',
        'short_name',
        'sojourn_number',
        'enrollments_qty',
        'location',
        'camp_groups_ids' => [
            'activity_group_num',
            'employee_id' => [
                'partner_identity_id' => ['firstname', 'lastname'],
            ],
            'booking_activities_ids' => [
                'name',
                'activity_date',
                'product_id',
                'time_slot_id' => ['code']
            ]
        ],
        'booking_meals_ids' => [
            'date',
            'meal_place_id' => ['name'],
            'time_slot_id'  => ['code'],
            'meal_type_id'  => ['name']
        ]
    ])
    ->get(true);

if(empty($camps)) {
    throw new Exception("no_camps", EQ_ERROR_INVALID_PARAM);
}

// If camp_id param used in domain, dates aren't set
if(is_null($date_from)) {
    $date_from = $camps[0]['date_from'];
    if(date('l', $camps[0]['date_from']) !== 'Sunday') {
        $date_from = (new DateTime(date('Y-m-d', $camps[0]['date_from'])))->modify('last Sunday')->getTimestamp();
        $date_to = (new DateTime(date('Y-m-d', $camps[0]['date_from'])))->modify('Saturday this week')->getTimestamp();
    }
    else {
        $date_to = (new DateTime(date('Y-m-d', $date_from)))->modify('Saturday next week')->getTimestamp();
    }
}

$formatter = new IntlDateFormatter(
    constant('L10N_LOCALE'),
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    constant('L10N_TIMEZONE'),
    IntlDateFormatter::GREGORIAN,
    'EEEE'
);

$date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

$time_slots = TimeSlot::search(['code', 'in', ['B', 'AM', 'L', 'PM', 'D', 'EV']], ['sort' => ['order' => 'asc']])
    ->read(['name', 'code', 'order'])
    ->get(true);

$days_names = [];
$date = $date_from;
while($date <= $date_to) {
    $days_names[] = $formatter->format($date);
    $date += 86400;
}

foreach($camps as $camp) {
    $camp_planning = [];

    $employees = [];
    foreach($camp['camp_groups_ids'] as $group) {
        if(isset($group['employee_id'])) {
            $employees[] = sprintf('%s %s.',
                $group['employee_id']['partner_identity_id']['firstname'],
                substr($group['employee_id']['partner_identity_id']['lastname'], 0, 1)
            );
        }
    }

    foreach($time_slots as $time_slot) {
        $time_slot_planning = [];

        $date = $date_from;
        while($date <= $date_to) {
            $date_key = date('Y-m-d', $date);
            $time_slot_planning[$date_key] = [];

            if(in_array($time_slot['code'], ['AM', 'PM', 'EV'])) {
                foreach($camp['camp_groups_ids'] as $group) {
                    foreach($group['booking_activities_ids'] as $activity) {
                        if(
                            $activity['time_slot_id']['code'] !== $time_slot['code']
                            || $activity['activity_date'] !== $date
                            || is_null($activity['product_id'])
                        ) {
                            continue;
                        }

                        $short_name = preg_replace('/\s*\(\d+\)$/', '', $activity['name']);

                        if(count($camp['camp_groups_ids']) > 0) {
                            $time_slot_planning[$date_key][] = sprintf('%d. %s',
                                $group['activity_group_num'],
                                $short_name
                            );
                        }
                        else {
                            $time_slot_planning[$date_key][] = $short_name;
                        }
                    }
                }
            }
            else {
                foreach($camp['booking_meals_ids'] as $meal) {
                    if($meal['time_slot_id']['code'] !== $time_slot['code'] || $meal['date'] !== $date) {
                        continue;
                    }

                    if($meal['meal_type_id']['name'] === 'Normal') {
                        $time_slot_planning[$date_key][] = sprintf('%s - %d',
                            $meal['meal_place_id']['name'],
                            $camp['enrollments_qty']
                        );
                    }
                    else {
                        $time_slot_planning[$date_key][] = sprintf('%s - %d (%s)',
                            $meal['meal_place_id']['name'],
                            $camp['enrollments_qty'],
                            $meal['meal_type_id']['name']
                        );
                    }
                }
            }

            $date += 86400;
        }

        $camp_planning[$time_slot['code']] = $time_slot_planning;
    }

    $map_location = [
        'cricket'   => 'Criquets',
        'ladybug'   => 'Coccinelles',
        'dragonfly' => 'Libellules',
        'butterfly' => 'Papillon'
    ];

    $camps_planning[] = [
        'date_from' => date($date_format, $camp['date_from']),
        'date_to'   => date($date_format, $camp['date_to']),
        'name'      => $camp['short_name'],
        'code'      => str_pad($camp['sojourn_number'], 3, '0', STR_PAD_LEFT),
        'employees' => count($employees) > 0 ? implode(', ', $employees) : '-',
        'location'  => $map_location[$camp['location']],
        'planning'  => $camp_planning
    ];
}

/*
    Inject all values into the template
*/

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR."/packages/$package/views/");

    $twig = new TwigEnvironment($loader);
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    $template = $twig->load("$class_path.{$params['view_id']}.html");

    $html = $template->render(compact('camps_planning', 'days_names'));
}
catch(Exception $e) {
    trigger_error("ORM::error while parsing template - ".$e->getMessage(), QN_REPORT_DEBUG);
    throw new Exception("template_parsing_issue", QN_ERROR_INVALID_CONFIG);
}

/*
    Convert HTML to PDF
*/

$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->setPaper('A4', 'landscape');
$dompdf->loadHtml($html);
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");

/*
    Response
*/

$context->httpResponse()
    ->header('Content-Disposition', 'inline; filename="planning-with-meals.pdf"')
    ->body($dompdf->output())
    ->send();



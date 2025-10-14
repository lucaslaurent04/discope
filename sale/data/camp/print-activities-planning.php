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
use sale\camp\Camp;
use sale\camp\CampGroup;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

[$params, $providers] = eQual::announce([
    'description'   => "Print the planning of activities for given date interval.",
    'params'        => [
        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.activities-planning'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit."
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Date interval Upper limit.'
        ],
        'camp_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Camp',
            'description'       => "Filter by camp."
        ],
        'camp_group_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\CampGroup',
            'description'       => "Filter by camp group."
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

/*
    Retrieve the requested template
*/

$entity = 'sale\camp\Camp';
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

$domain = [
    ['status', '=', 'published']
];

if(isset($params['camp_id']) || isset($params['camp_group_id'])) {
    if(isset($params['camp_id'])) {
        $domain[] = ['id', '=', $params['camp_id']];
    }
    elseif(isset($params['camp_group_id'])) {
        $campGroup = CampGroup::id($params['camp_group_id'])
            ->read(['camp_id'])
            ->first();

        $domain[] = ['id', '=', $campGroup['camp_id']];
    }
}
else {
    if(isset($params['date_from'])) {
        $day_of_week = date('w', $params['date_from']);

        // find previous Sunday
        $sunday = $params['date_from'] - ($day_of_week * 86400);

        // next Friday (+5 days)
        $friday = $sunday + (5 * 86400);

        $domain[] = ['date_from', '>=', $sunday];
        $domain[] = ['date_from', '<=', $friday];
    }
}

$camps = Camp::search($domain)
    ->read([
        'date_from',
        'date_to',
        'short_name',
        'sojourn_number',
        'enrollments_qty',
        'camp_groups_ids' => [
            'activity_group_num',
            'employee_id' => [
                'partner_identity_id' => ['firstname'],
            ],
            'booking_activities_ids' => [
                'name',
                'activity_date',
                'time_slot_id' => ['code']
            ]
        ],
        'booking_meals_ids' => [
            'date',
            'meal_place_id' => ['name'],
            'meal_type_id' => ['name'],
            'time_slot_id'  => ['code']
        ]
    ])
    ->get(true);

$formatter = new IntlDateFormatter(
    constant('L10N_LOCALE'),
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    constant('L10N_TIMEZONE'),
    IntlDateFormatter::GREGORIAN,
    'EEEE'
);

$date_from = null;
$date_to = null;

$camps_planning = [];
foreach($camps as $camp) {
    if (is_null($date_from) || $camp['date_from'] < $date_from) {
        $date_from = $camp['date_from'];
    }
    if (is_null($date_to) || $camp['date_to'] > $date_to) {
        $date_to = $camp['date_to'];
    }
}

$days_names = [];
$date = $date_from;
while($date <= $date_to) {
    if(in_array(date('l', $date), ['Saturday', 'Sunday'])) {
        $date += 86400;
        continue;
    }

    $days_names[] = $formatter->format($date);
    $date += 86400;
}

foreach($camps as $camp) {
    $groups = [];
    foreach($camp['camp_groups_ids'] as $group) {
        if(isset($params['camp_group_id']) && $group['id'] !== $params['camp_group_id']) {
            continue;
        }

        $groups[] = [
            'num'       => $group['activity_group_num'],
            'employee'  => $group['employee_id']['partner_identity_id']['firstname']
        ];
    }

    $days = [];

    $date = $date_from;
    while($date <= $date_to) {
        if(in_array(date('l', $date), ['Saturday', 'Sunday'])) {
            $date += 86400;
            continue;
        }

        $day = [
            'AM'    => [],
            'L'     => null,
            'PM'    => [],
            'D'     => null
        ];

        foreach($camp['camp_groups_ids'] as $group) {
            foreach($group['booking_activities_ids'] as $activity) {
                if($activity['activity_date'] !== $date || !in_array($activity['time_slot_id']['code'], ['AM', 'PM'])) {
                    continue;
                }

                $short_name = $activity['name'];
                if(strpos($activity['name'], 'Activité ') === 0) {
                    $short_name = substr($activity['name'], strlen('Activité '));
                }
                $short_name = preg_replace('/\s*\(\d+\)$/', '', $short_name);

                $day[$activity['time_slot_id']['code']][$group['activity_group_num']] = array_merge(
                    $activity,
                    ['short_name' => ucfirst($short_name)]
                );
            }
        }

        foreach($camp['booking_meals_ids'] as $meal) {
            if($meal['date'] !== $date || !in_array($meal['time_slot_id']['code'], ['L', 'D'])) {
                continue;
            }

            $day[$meal['time_slot_id']['code']] = $meal;
        }

        $days[] = $day;

        $date += 86400;
    }

    $camps_planning[] = [
        'sojourn_number'    => str_pad($camp['sojourn_number'], 3, '0', STR_PAD_LEFT),
        'children_qty'      => $camp['enrollments_qty'],
        'animators_qty'     => count($camp['camp_groups_ids']),
        'camp_name'         => $camp['short_name'],
        'groups'            => $groups,
        'groups_qty'        => count($camp['camp_groups_ids']),
        'days'              => $days
    ];
}

/*
    Inject all values into the template
*/

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR."/packages/$package/views/");

    $twig = new TwigEnvironment($loader);
    /**  @var ExtensionInterface **/
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

$date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->setPaper('A4', 'landscape');
$dompdf->loadHtml($html);
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");

// title
$date_from = date($date_format, $date_from);
$date_to = date($date_format, $date_to);

$canvas->page_text($canvas->get_width() / 2 - 100, 20, "SEMAINE DU $date_from AU $date_to", $font, 10);

// footer
$canvas->page_text(40, $canvas->get_height() - 35, "Centre de Plein Air de Lathus", $font, 8);
$canvas->page_text($canvas->get_width() - 70, $canvas->get_height() - 35, date($date_format), $font, 8);

/*
    Response
*/

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="planning.pdf"')
        ->body($dompdf->output())
        ->send();

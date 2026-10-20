<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use discope\setting\Setting;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use equal\orm\Domain;
use equal\orm\DomainCondition;
use sale\camp\Camp;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

[$params, $providers] = eQual::announce([
    'description'   => "Print list of enrollments.",
    'params'        => [

        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.enrollments-list'
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit (defaults to first day of the current week).",
            'default'           => fn() => strtotime('last Sunday')
        ],

        'sojourn_number' => [
            'type'              => 'string',
            'description'       => "Sojourn number."
        ],

        'only_weekend' => [
            'type'              => 'boolean',
            'description'       => "Show only the children present during the weekend.",
            'default'           => false
        ],

        'only_saturday' => [
            'type'              => 'boolean',
            'description'       => "Show only the children present during the Saturday morning.",
            'default'           => false
        ],

        'confirmed' => [
            'type'              => 'boolean',
            'description'       => "Display enrollments with confirmed status.",
            'default'           => true
        ],

        'validated' => [
            'type'              => 'boolean',
            'description'       => "Display enrollments with validated status.",
            'default'           => true
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

/*
    Retrieve the requested template
*/

$entity = 'sale\camp\Enrollment';
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

$domain = new Domain($params['domain']);

$domain->addCondition(new DomainCondition('status', '=', 'published'));

if(!empty($params['sojourn_number'])) {
    $domain->addCondition(new DomainCondition('sojourn_number', 'like', "%{$params['sojourn_number']}%"));
}
elseif(isset($params['date_from'])) {
    $day_of_week = date('w', $params['date_from']);

    // find previous Sunday
    $sunday = $params['date_from'] - ($day_of_week * 86400);

    // next Friday (+5 days)
    $friday = $sunday + (5 * 86400);

    $domain->addCondition(new DomainCondition('date_from', '>=', $sunday));
    $domain->addCondition(new DomainCondition('date_from', '<=', $friday));
}

$enrollment_weekend_extras = ['none', 'saturday-morning', 'full'];
if($params['only_saturday'] || $params['only_weekend']) {
    $enrollment_weekend_extras = [];
    if($params['only_saturday']) {
        $enrollment_weekend_extras[] = 'saturday-morning';
    }
    if($params['only_weekend']) {
        $enrollment_weekend_extras[] = 'full';
    }
}

$enrollment_statuses = [];
if($params['confirmed']) {
    $enrollment_statuses[] = 'confirmed';
}
if($params['validated']) {
    $enrollment_statuses[] = 'validated';
}

$camps = Camp::search($domain->toArray())
    ->read([
        'short_name',
        'sojourn_number',
        'location',
        'camp_groups_ids' => [
            'employee_id' => [
                'partner_identity_id' => ['firstname', 'lastname']
            ],
            'second_employee_id' => [
                'partner_identity_id' => ['firstname', 'lastname']
            ]
        ],
        'enrollments_ids' => [
            '@domain' => [
                ['status', 'in', $enrollment_statuses],
                ['weekend_extra', 'in', $enrollment_weekend_extras]
            ],
            'child_lastname',
            'child_firstname',
            'child_gender',
            'child_birthdate',
            'child_age',
            'child_remarks',
            'all_documents_received',
            'doc_aquatic_skills_received',
            'description'
        ]
    ])
    ->get();

$date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

$map_location = [
    'cricket'   => 'Criquets',
    'ladybug'   => 'Coccinelles',
    'dragonfly' => 'Libellules',
    'butterfly' => 'Papillon'
];

$enrollments = [];
foreach($camps as $camp) {
    $animators = '';
    foreach($camp['camp_groups_ids'] as $group) {
        if(isset($group['employee_id']['partner_identity_id'])) {
            if(!empty($animators)) {
                $animators .= ' + ';
            }
            $animators .= $group['employee_id']['partner_identity_id']['firstname'].' '.substr($group['employee_id']['partner_identity_id']['lastname'], 0, 1).'.';
        }
        if(isset($group['second_employee_id']['partner_identity_id'])) {
            if(!empty($animators)) {
                $animators .= ' + ';
            }
            $animators .= $group['second_employee_id']['partner_identity_id']['firstname'].' '.substr($group['second_employee_id']['partner_identity_id']['lastname'], 0, 1).'.';
        }
    }

    foreach($camp['enrollments_ids'] as $enrollment) {
        $enrollments[] = [
            'child_lastname'                => $enrollment['child_lastname'],
            'child_firstname'               => $enrollment['child_firstname'],
            'child_gender'                  => $enrollment['child_gender'],
            'child_birthdate'               => date($date_format, $enrollment['child_birthdate']),
            'child_age'                     => $enrollment['child_age'],
            'child_remarks'                 => $enrollment['child_remarks'],
            'all_documents_received'        => $enrollment['all_documents_received'],
            'doc_aquatic_skills_received'   => $enrollment['doc_aquatic_skills_received'],
            'camp_name'                     => $camp['sojourn_number'].' '.$camp['short_name'],
            'camp_animators'                => $animators,
            'camp_location'                 => $map_location[$camp['location']],
            'description'                   => $enrollment['description']
        ];
    }
}

usort($enrollments, function($a, $b) {
    if($a['child_lastname'] === $b['child_lastname']) {
        return $a['child_firstname'] <=> $b['child_firstname'];
    }
    return $a['child_lastname'] <=> $b['child_lastname'];
});

$today = date($date_format);

$day_of_week = date('w', $params['date_from']);
// find previous Sunday
$sunday = $params['date_from'] - ($day_of_week * 86400);
// set date_from as Monday
$date_from = date($date_format, $sunday + 86400);

// next Sunday (+5 days)
$next_sunday = $sunday + (7 * 86400);
// set date_to as following sunday
$date_to = date($date_format, $next_sunday);

$title = 'Inscriptions par ordre alphabétique';
$subtitle = '(dossier complet ou non)';

/*
    Inject all values into the template
*/

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR."/packages/$package/views/");

    $twig = new TwigEnvironment($loader);
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    $template = $twig->load("$class_path.{$params['view_id']}.html");

    $html = $template->render(compact('enrollments', 'today', 'date_from', 'date_to', 'title', 'subtitle'));
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

$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html);
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");

/*
    Response
*/

$context
    ->httpResponse()
    ->header('Content-Disposition', 'inline; filename="enrollments-list.pdf"')
    ->body($dompdf->output())
    ->send();

<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use identity\Center;
use identity\Identity;
use sale\camp\Camp;
use sale\camp\Child;
use sale\camp\Enrollment;
use sale\camp\Guardian;
use sale\camp\Institution;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;

use equal\data\DataFormatter;
use core\setting\Setting;
use Twig\TwigFilter;

[$params, $providers] = eQual::announce([
    'description'   => "Render an end of camp certificate given the child ID as a PDF document, for Lathus.",
    'params'        => [

        'child_id' => [
            'type'          => 'integer',
            'description'   => "Identifier of child concerned by the camp certificate.",
            'required'      => true
        ],

        'year' => [
            'type'          => 'integer',
            'description'   => "Year the certificate is needed for.",
            'default'       => fn() => intval(date('Y'))
        ],

        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => 'The center to which the child enrollments relates to.',
            'default'           => function() {
                return ($centers = Center::search())->count() === 1 ? current($centers->ids()) : null;
            }
        ],

        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.camp-certificate'
        ],

        'output' =>  [
            'description'   => 'Output format of the document.',
            'type'          => 'string',
            'selection'     => ['pdf', 'html'],
            'default'       => 'pdf'
        ]

    ],
    'constants'     => [],
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
$formatMoney = function ($value) use($currency) {
    return number_format((float)($value), 2, ",", ".") . ' ' .$currency;
};

$date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
$formatDate = fn($value) => date($date_format, $value);

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

/**
 * Data controller
 */

/*
    1) retrieve the requested template
*/

$entity = 'lathus\sale\camp\Child';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = EQ_BASEDIR."/packages/$package/views/$class_path.{$params['view_id']}.html";
if(!file_exists($file)) {
    throw new Exception("unknown_view_id", EQ_ERROR_UNKNOWN_OBJECT);
}

/*
    2) check child and center exists and has attended at least camp
*/

$child = Child::id($params['child_id'])
    ->read(['id'])
    ->first();

if(is_null($child)) {
    throw new Exception("unknown_child", EQ_ERROR_UNKNOWN_OBJECT);
}

$center = Center::id($params['center_id'])
    ->read(['organisation_id'])
    ->first();

if(is_null($center)) {
    throw new Exception("unknown_center", EQ_ERROR_UNKNOWN_OBJECT);
}

$center_id = $center['id'];
$organisation_id = $center['organisation_id'];

$start_year = mktime(0, 0, 0, 1, 1, $params['year']);
$end_year = mktime(0, 0, 0, 12, 31, $params['year']);

$camps_ids = Camp::search([
    ['date_from', '>=', $start_year],
    ['date_from', '<', $end_year],
    ['center_id', '=', $center_id]
])
    ->ids();

if(empty($camps_ids)) {
    throw new Exception("no_camps_for_date_interval", EQ_ERROR_INVALID_PARAM);
}

$enrollments = Enrollment::search([
    ['child_id', '=', $child['id']],
    ['camp_id', 'in', $camps_ids]
])
    ->read([
        'price', 'weekend_extra',
        'presence_day_1', 'presence_day_2', 'presence_day_3', 'presence_day_4', 'presence_day_5',
        'camp_id' => ['short_name', 'date_from', 'date_to', 'is_clsh', 'clsh_type']
    ])
    ->get(true);

if(empty($enrollments)) {
    throw new Exception("no_camps_attended", EQ_ERROR_INVALID_PARAM);
}

/*
    3) create valus array to inject data in template
*/

$data_to_inject = [
    'child' => [
        'firstname', 'lastname'
    ],
    'main_guardian' => [
        'firstname', 'lastname', 'address_street', 'address_dispatch', 'address_zip', 'address_city'
    ],
    'institution' => [
        'name', 'address_street', 'address_dispatch', 'address_zip', 'address_city'
    ],
    'center' => [
        'name', 'address_street', 'address_dispatch', 'address_zip', 'address_city', 'address_country', 'email', 'phone'
    ],
    'organisation' => [
        'name', 'legal_name', 'address_street', 'address_dispatch', 'address_zip', 'address_city', 'address_country', 'email', 'fax', 'phone',
        'bank_account_iban', 'bank_account_bic', 'registration_number', 'vat_number', 'website', 'signature'
    ]
];

/*
      3.1) get child data and format dates
*/

$child = Child::id($params['child_id'])
    ->read(array_merge(
        $data_to_inject['child'],
        ['main_guardian_id', 'institution_id']
    ))
    ->first(true);

/*
      3.2) get main guardian and institution for customer data
*/

$main_guardian = Guardian::id($child['main_guardian_id'])
    ->read($data_to_inject['main_guardian'])
    ->first(true);

$institution = null;
if(!is_null($child['institution_id'])) {
    $institution = Institution::id($child['main_guardian_id'])
        ->read($data_to_inject['institution'])
        ->first(true);
}

/*
      3.3) get center data
*/

$center = Center::id($center_id)
    ->read($data_to_inject['center'])
    ->first(true);

$center['phone'] = $formatPhone($center['phone']);

/*
      3.4) get organisation data and handle img url
*/

$organisation = Identity::id($organisation_id)
    ->read(array_merge(
        $data_to_inject['organisation'],
        ['logo_document_id' => ['data', 'type']]
    ))
    ->first(true);

$organisation['bank_account_iban'] = DataFormatter::format($organisation['bank_account_iban'], 'iban');
$organisation['phone'] = $formatPhone($organisation['phone']);

/*
      3.5) set values to inject
*/

$today = $formatDate(time());

$values = compact(
    'child',
    'main_guardian',
    'institution',
    'center',
    'organisation',
    'today'
);

/*
    4) handle template parts
*/

$center = Center::id($center_id)
    ->read(['template_category_id'])
    ->first();

$template = Template::search([
    ['category_id', '=', $center['template_category_id']],
    ['type', '=', 'camp'],
    ['code', '=', 'certificate']
])
    ->read(['id','parts_ids' => ['name', 'value']], $params['lang'])
    ->first(true);

$camps_price = 0;
$camps_days = 0;
$camps_list = '<ul>';
foreach($enrollments as $enrollment) {
    $camps_price += $enrollment['price'];

    $days_qty = 6;
    if($enrollment['camp_id']['is_clsh']) {
        $days_qty = 0;
        for($i = 1; $i <= 5; $i++) {
            if($enrollment["presence_day_$i"]) {
                $days_qty++;
            }
        }
    }
    else {
        switch($enrollment['weekend_extra']) {
            case 'full':
                $days_qty += 2;
                break;
            case 'saturday-morning':
                $days_qty += 1;
                break;
        }
    }
    $camps_days += $days_qty;

    $date_from = $formatDate($enrollment['camp_id']['date_from']);
    $date_to = $formatDate($enrollment['camp_id']['date_to']);
    $camps_list .= "<li>{$enrollment['camp_id']['short_name']}, organisé par notre association du $date_from au $date_to</li>";
}
$camps_list .= '</ul>';

$camps_price = $formatMoney($camps_price);
$values['camps_price'] = $camps_price;
$values['camps_days'] = $camps_days;
$values['camps_list'] = $camps_list;

$template_parts = [];
foreach($template['parts_ids'] as $part) {
    $value = $part['value'];
    foreach($data_to_inject as $object => $fields) {
        foreach($fields as $field) {
            $value = str_replace('{'.$object.'.'.$field.'}', $values[$object][$field], $value);
        }
    }

    $extra_fields = ['today', 'camps_price', 'camps_days', 'camps_list'];
    foreach($extra_fields as $field) {
        $value = str_replace('{'.$field.'}', $values[$field], $value);
    }

    $template_parts[$part['name']] = $value;
}

$values['parts'] = $template_parts;

/*
    5) inject all values into the template
*/

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR."/packages/$package/views/");

    $twig = new TwigEnvironment($loader);
    /**  @var ExtensionInterface **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    // do not rely on system locale (LC_*)

    $filter = new TwigFilter('format_money', $formatMoney);
    $twig->addFilter($filter);

    $date_filter = new TwigFilter('format_date', $formatDate);
    $twig->addFilter($date_filter);

    $template = $twig->load("{$class_path}.{$params['view_id']}.html");

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

/*
    6) convert HTML to PDF
*/

// instantiate and use the dompdf class
$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml((string) $html);
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");

/*
    7) handle response
*/

$output = $dompdf->output();

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();

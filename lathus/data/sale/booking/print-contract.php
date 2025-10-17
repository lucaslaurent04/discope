<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use core\setting\Setting;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use equal\data\DataFormatter;
use identity\Identity;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\BookingMeal;
use sale\booking\Contact;
use sale\booking\Contract;
use sale\booking\ContractLine;
use sale\booking\SojournProductModelRentalUnitAssignement;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\TwigFilter;

[$params, $providers] = eQual::announce([
    'description'   => "Render a contract given its ID as a PDF document, for Lathus.",
    'params'        => [
        'id' => [
            'type'          => 'integer',
            'description'   => 'Identifier of the contract to print.',
            'required'      => true
        ],
        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.default'
        ],
        'output' =>  [
            'description'   => 'Output format of the document.',
            'type'          => 'string',
            'selection'     => ['pdf', 'html'],
            'default'       => 'pdf'
        ]
    ],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
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

$map_days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$map_months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
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

/**
 * Data controller
 */

/*
    1) retrieve the requested template
*/

$entity = 'lathus\sale\booking\Contract';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = QN_BASEDIR."/packages/{$package}/views/{$class_path}.{$params['view_id']}.html";

if(!file_exists($file)) {
    throw new Exception("unknown_view_id", QN_ERROR_UNKNOWN_OBJECT);
}

/*
    2) check contract exists
*/

$contract = Contract::id($params['id'])
    ->read(['id'])
    ->first();

if(is_null($contract)) {
    throw new Exception("unknown_contract", QN_ERROR_UNKNOWN_OBJECT);
}

/*
    3) create valus array to inject data in template
*/

$data_to_inject = [
    'booking' => [
        'date_from', 'date_to', 'time_from', 'time_to', 'price'
    ],
    'organisation' => [
        'name', 'phone', 'address_street', 'address_dispatch', 'address_zip', 'address_city'
    ],
    'customer' => [
        'display_name', 'address_street', 'address_zip', 'address_dispatch', 'address_city'
    ]
];

/*
      3.1) get contract data
*/

$contract = Contract::id($contract['id'])
    ->read([
        'booking_id' => [
            'contacts_ids',
            'rental_unit_assignments_ids',
            'center_id'     => ['organisation_id'],
            'customer_id'   => ['partner_identity_id']
        ]
    ])
    ->first(true);

/*
      3.2) get booking data
*/

$booking = Booking::id($contract['booking_id']['id'])
    ->read(array_merge(
        $data_to_inject['booking'],
        [
            'booking_lines_groups_ids'  => [
                'nb_pers',
                'group_type',
                'age_range_assignments_ids' => [
                    'age_range_id',
                    'qty',
                    'free_qty'
                ]
            ]
        ]
    ))
    ->first(true);

$booking['date_from_long'] = $formatDateLong($booking['date_from']);
$booking['date_from_long'] = $formatDateLong($booking['date_from']);
$booking['date_to_long'] = $formatDateLong($booking['date_to']);
$booking['date_from'] = $formatDate($booking['date_from']);
$booking['date_to'] = $formatDate($booking['date_to']);
$booking['time_from'] = $formatTime($booking['time_from']);
$booking['time_to'] = $formatTime($booking['time_to']);

// nb_pers are used to inject in GroupingCode name
$nb_pers = 0;
$map_age_range_nb_pers = [];
foreach($booking['booking_lines_groups_ids'] as $group) {
    if($group['group_type'] !== 'sojourn') {
        continue;
    }

    $nb_pers = $group['nb_pers'];
    foreach($group['age_range_assignments_ids'] as $assignment) {
        if(!isset($map_age_range_nb_pers[$assignment['age_range_id']['id']])) {
            $map_age_range_nb_pers[$assignment['age_range_id']['id']] = 0;
        }

        $map_age_range_nb_pers[$assignment['age_range_id']['id']] += $assignment['qty'] - $assignment['free_qty'];
    }
}

/*
      3.3) get customer data
*/

$customer = Identity::id($contract['booking_id']['customer_id']['partner_identity_id'])
    ->read($data_to_inject['customer'])
    ->first();

/*
      3.4) handle img url and signature
*/

$organisation = Identity::id($contract['booking_id']['center_id']['organisation_id'])
    ->read(array_merge(
        $data_to_inject['organisation'],
        ['logo_document_id' => ['data', 'type'], 'signature']
    ))
    ->first(true);

$organisation['phone'] = $formatPhone($organisation['phone']);

$img_url = '';

$logo_document_data = $organisation['logo_document_id']['data'] ?? null;
if($logo_document_data) {
    $content_type = $organisation['logo_document_id']['type'] ?? 'image/png';
    $img_url = "data:{$content_type};base64, ".base64_encode($logo_document_data);
}

$signature_html = $organisation['signature'];

/*
    3.5) handle contact
*/

$contacts = Contact::search(['id', 'in', $contract['booking_id']['contacts_ids']])
    ->read(['type', 'name'])
    ->get(true);

$sojourn_contact_name = '';
foreach($contacts as $contact) {
    if($contact['type'] === 'sojourn') {
        $sojourn_contact_name = $contact['name'];
        break;
    }
}

/*
      3.6) handle children and adults qty
*/

$sojourn_groups = BookingLineGroup::search([
    ['booking_id', '=', $contract['booking_id']['id']],
    ['group_type', '=', 'sojourn']
])
    ->read(['age_range_assignments_ids' => ['age_to', 'qty']])
    ->get();

$nb_pers = 0;
$nb_adults = 0;
$nb_children = 0;
foreach($sojourn_groups as $group) {
    foreach($group['age_range_assignments_ids'] as $age_range_assignment) {
        $nb_pers +=$age_range_assignment['qty'];
        if($age_range_assignment['age_to'] <= 18) {
            $nb_children += $age_range_assignment['qty'];
        }
        else {
            $nb_adults += $age_range_assignment['qty'];
        }
    }
}

/*
      3.7) handle hosting
*/

$hosting_conf = [
    'CH' => false,
    'CC' => false,
    'MB' => false,
    'CP' => false
];

if(!empty($contract['booking_id']['rental_unit_assignments_ids'])) {
    $ru_assignments = SojournProductModelRentalUnitAssignement::search([
        ['id', 'in', $contract['booking_id']['rental_unit_assignments_ids']],
    ])
        ->read(['is_accomodation', 'rental_unit_id' => ['rental_unit_category_id' => ['code']]])
        ->get();

    $hosting_conf['hosted'] = !empty($ru_assignments);

    foreach($ru_assignments as $ru_assignment) {
        if(!$ru_assignment['is_accomodation']) {
            continue;
        }

        $hosting_conf['hosted'] = true;
        if(isset($hosting_conf[$ru_assignment['rental_unit_id']['rental_unit_category_id']['code']])) {
            $hosting_conf[$ru_assignment['rental_unit_id']['rental_unit_category_id']['code']] = true;
        }
    }
}

/*
      3.8) handle meals
*/

$meals = BookingMeal::search(
    [['booking_id', '=', $contract['booking_id']['id']], ['is_self_provided', '=', false]],
    ['sort' => ['date' => 'asc', 'time_slot_order' => 'asc']]
)
    ->read(['date', 'time_slot_id' => ['code']])
    ->get(true);

$map_meals_names = [
    'B'     => 'petit-déjeuner',
    'L'     => 'déjeuner',
    'PM'    => 'goûter',
    'D'     => 'diner'
];

$first = null;
if(!is_null($meals[0])) {
    $first = [
        'date'      => $formatDate($meals[0]['date']),
        'moment'    => $map_meals_names[$meals[0]['time_slot_id']['code']],
    ];
}

$last = null;
if(!is_null($meals[count($meals) - 1])) {
    $last = [
        'date'      => $formatDate($meals[count($meals) - 1]['date']),
        'moment'    => $map_meals_names[$meals[count($meals) - 1]['time_slot_id']['code']],
    ];
}

$meals_conf = [
    'has_meals'     => !empty($meals),
    'first'         => $first,
    'last'          => $last,
    'has_breakfast' => false,
    'has_lunch'     => false,
    'has_dinner'     => false,
    'has_snack'     => false
];

foreach($meals as $meal) {
    switch($meal['time_slot_id']['code']) {
        case 'B':
            $meals_conf['has_breakfast'] = true;
            break;
        case 'L':
            $meals_conf['has_lunch'] = true;
            break;
        case 'PM':
            $meals_conf['has_snack'] = true;
            break;
        case 'D':
            $meals_conf['has_dinner'] = true;
            break;
    }
}

/*
      3.9) handle has activities or not
*/

$activities_ids = BookingLine::search([
    ['booking_id', '=', $booking['id']],
    ['is_activity', '=', true]
])
    ->ids();

$has_activities = !empty($activities_ids);

if(!$has_activities) {
    $contract = Contract::id($contract['id'])
        ->read([
            'booking_id' => ['booking_lines_ids' => ['product_id' => ['sku']]]
        ])
        ->first();

    foreach($contract['booking_id']['booking_lines_ids'] as $line) {
        if(isset($line['product_id']['sku']) && strpos($line['product_id']['sku'], '-MAP') !== false) {
            $has_activities = true;
            break;
        }
    }
}

/*
      3.10) handle booking
*/

$cont = Contract::id($params['id'])
    ->read([
        'booking_id' => [
            'invoices_ids' => [
                'is_deposit',
                'is_paid',
                'price'
            ],
            'customer_id' => [
                'partner_identity_id' => [
                    'flag_trusted'
                ]
            ]
        ]
    ])
    ->first(true);

$booking_conf = [
    'security_deposit_check'    => 0,
    'deposits'                  => []
];

if(!$cont['booking_id']['customer_id']['partner_identity_id']['flag_trusted']) {
    $booking_conf['security_deposit_check'] = $formatMoney(305);
}

foreach($cont['booking_id']['fundings_ids'] as $invoice) {
    if($invoice['is_deposit']) {
        $booking_conf['deposits'][] = [
            'price' => $formatMoney($invoice['price'])
        ];
    }
}

/*
      3.11) handle cancellation
*/

$cancellation_conf = [
    'type'      => 'pay_amount',
    'amount'    => $formatMoney(1500)
];

foreach($cont['booking_id']['invoices_ids'] as $invoice) {
    if($invoice['is_deposit'] && $invoice['is_paid']) {
        $cancellation_conf['type'] = 'keep_deposit';
        break;
    }
}

/*
    3.12) handle lines
*/

$contract_lines = ContractLine::search(['contract_id', '=', $contract['id']])
    ->read([
        'product_id' => [
            'grouping_code_id' => [
                'name',
                'code',
                'has_age_range',
                'age_range_id'
            ],
            'product_model_id' => [
                'grouping_code_id' => [
                    'name',
                    'code',
                    'has_age_range',
                    'age_range_id'
                ]
            ]
        ]
    ])
    ->get();

$map_products_groupings = [];
foreach($contract_lines as $line) {
    if(isset($map_products_groupings[$line['product_id']['id']])) {
        continue;
    }

    $grouping_code = null;
    if(isset($line['product_id']['grouping_code_id'])) {
        $grouping_code = $line['product_id']['grouping_code_id'];
    }
    elseif(isset($line['product_id']['product_model_id']['grouping_code_id'])) {
        $grouping_code = $line['product_id']['product_model_id']['grouping_code_id'];
    }

    if(is_null($grouping_code) || ($grouping_code['code'] === 'invisible' && $line['price'] === 0)) {
        continue;
    }

    $map_products_groupings[$line['product_id']['id']] = $grouping_code;
}

$contract_lines = ContractLine::search(['contract_id', '=', $contract['id']])
    ->read([
        'name',
        'description',
        'qty',
        'free_qty',
        'unit_price',
        'vat_rate',
        'total',
        'price',
        'product_id' => ['label']
    ])
    ->get(true);

$map_groupings_lines = [];
foreach($contract_lines as $line) {
    $grouping_name = $line['product_id']['label'];
    if(isset($map_products_groupings[$line['product_id']['id']])) {
        $grouping = $map_products_groupings[$line['product_id']['id']];

        $grouping_name = $map_products_groupings[$line['product_id']['id']]['name'];

        if(strpos($grouping_name, '{nb_pers}') !== false) {
            if($grouping['has_age_range'] && isset($map_age_range_nb_pers[$grouping['age_range_id']])) {
                $grouping_name = str_replace('{nb_pers}', $map_age_range_nb_pers[$grouping['age_range_id']], $grouping_name);
            }
            else {
                $grouping_name = str_replace('{nb_pers}', $nb_pers, $grouping_name);
            }
        }
    }
    elseif(!empty($line['description'])) {
        $grouping_name = $line['description'];
    }

    if(!isset($map_groupings_lines[$grouping_name])) {
        $map_groupings_lines[$grouping_name] = [];
    }

    $map_groupings_lines[$grouping_name][] = $line;
}

$lines = [];
foreach($map_groupings_lines as $grouping_name => $grouping_lines) {
    $total = 0;
    $price = 0;
    foreach($grouping_lines as $line) {
        $total += $line['total'];
        $price += $line['price'];
    }

    $lines[] = [
        'name'          => $grouping_name,
        'qty'           => 1,
        'free_qty'      => null,
        'unit_price'    => $total,
        'vat_rate'      => null,
        'total'         => $total,
        'price'         => $price,
        'is_group'      => true
    ];
}

/*
      3.13) set values
*/

$today = $formatDate(time());
$today_long = $formatDateLong(time());

$values = compact(
    'booking', 'organisation', 'customer', 'img_url', 'signature_html', 'sojourn_contact_name',
    'nb_pers', 'nb_adults', 'nb_children', 'hosting_conf', 'cancellation_conf', 'meals_conf', 'map_meals_names', 'has_activities',
    'booking_conf', 'today', 'today_long', 'lines'
);

/*
      3.14) set values
*/

$contract = Contract::id($params['id'])
    ->read(['booking_id' => ['center_id' => ['template_category_id']]])
    ->first();

$template = Template::search([
    ['category_id', '=', $contract['booking_id']['center_id']['template_category_id']],
    ['code', '=', 'contract'],
    ['type', '=', 'contract']
])
    ->read(['id','parts_ids' => ['name', 'value']], $params['lang'])
    ->first(true);

$template_parts = [];
foreach($template['parts_ids'] as $part) {
    $value = $part['value'];
    foreach($data_to_inject as $object => $fields) {
        foreach($fields as $field) {
            $value = str_replace('{'.$object.'.'.$field.'}', $values[$object][$field], $value);
        }
    }

    $extra_fields = ['today', 'today_long', 'sojourn_contact_name', 'nb_pers', 'nb_adults', 'nb_children'];
    foreach($extra_fields as $field) {
        $value = str_replace('{'.$field.'}', $values[$field], $value);
    }

    $booking_extra_fields = ['date_from_long', 'date_to_long'];
    foreach($booking_extra_fields as $field) {
        $value = str_replace('{booking.'.$field.'}', $values['booking'][$field], $value);
    }

    $template_parts[$part['name']] = $value;
}

$values['parts'] = $template_parts;

/*
    4) inject all values into the template
*/

try {
    $loader = new TwigFilesystemLoader(QN_BASEDIR."/packages/{$package}/views/");

    $twig = new TwigEnvironment($loader);
    /**  @var ExtensionInterface **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    // do not rely on system locale (LC_*)

    $filter = new TwigFilter('format_money', $formatMoney);
    $twig->addFilter($filter);

    $date_filter = new TwigFilter('format_date', $formatDate);
    $twig->addFilter($date_filter);

    $date_filter = new TwigFilter('format_date_long', $formatDateLong);
    $twig->addFilter($date_filter);

    $date_filter = new TwigFilter('format_time', $formatTime);
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
    5) convert HTML to PDF
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
$canvas->page_text(530, $canvas->get_height() - 35, "p. {PAGE_NUM} / {PAGE_COUNT}", $font, 9, array(0,0,0));
// $canvas->page_text(40, $canvas->get_height() - 35, "Export", $font, 9, array(0,0,0));


/*
    6) handle response
*/

$output = $dompdf->output();

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();

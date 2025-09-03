<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use identity\Identity;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\BookingMeal;
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

$contract = Contract::id($contract['id'])
    ->read([
        'booking_id' => [
            'date_from',
            'date_to',
            'time_from',
            'time_to',
            'rental_unit_assignments_ids',
            'price',
            'center_id'     => ['organisation_id'],
            'customer_id'   => ['partner_identity_id']
        ]
    ])
    ->first(true);

$booking = $contract['booking_id'];

/*
      3.1) get customer data
*/

$customer = Identity::id($booking['customer_id']['partner_identity_id'])
    ->read(['display_name', 'address_street', 'address_zip', 'address_dispatch', 'address_city'])
    ->first();

/*
      3.2) handle img url and signature
*/

$organisation = Identity::id($booking['center_id']['organisation_id'])
    ->read(['logo_document_id' => ['data', 'type'], 'signature'])
    ->first();

$img_url = '';

$logo_document_data = $organisation['logo_document_id']['data'] ?? null;
if($logo_document_data) {
    $content_type = $organisation['logo_document_id']['type'] ?? 'image/png';
    $img_url = "data:{$content_type};base64, ".base64_encode($logo_document_data);
}

$signature_html = $organisation['signature'];

/*
      3.3) handle children and adults qty
*/

$sojourn_groups = BookingLineGroup::search([
    ['booking_id', '=', $booking['id']],
    ['group_type', '=', 'sojourn']
])
    ->read(['age_range_assignments_ids' => ['age_to', 'qty']])
    ->get();

$children_qty = 0;
$adult_qty = 0;
foreach($sojourn_groups as $group) {
    foreach($group['age_range_assignments_ids'] as $age_range_assignment) {
        if($age_range_assignment['age_to'] <= 18) {
            $children_qty += $age_range_assignment['qty'];
        }
        else {
            $adult_qty += $age_range_assignment['qty'];
        }
    }
}

/*
      3.4) handle hosting
*/

$hosting_conf = [
    'hosted'    => false,
    'marabout'  => false,
    'camping'   => false,
    'other'     => false
];

if(!empty($booking['rental_unit_assignments_ids'])) {
    $ru_assignments = SojournProductModelRentalUnitAssignement::search([
        ['id', 'in', $booking['rental_unit_assignments_ids']],
    ])
        ->read(['is_accomodation', 'rental_unit_id' => ['rental_unit_category_id' => ['code']]])
        ->get();

    $hosting_conf['hosted'] = !empty($ru_assignments);

    foreach($ru_assignments as $ru_assignment) {
        if($ru_assignment['is_accomodation']) {
            $hosting_conf['hosted'] = true;
        }
        switch($ru_assignment['rental_unit_id']['rental_unit_category_id']['code']) {
            case 'MB':
                $hosting_conf['marabout'] = true;
                break;
            case 'CP':
                $hosting_conf['camping'] = true;
                break;
            default:
                $hosting_conf['other'] = true;
                break;
        }
    }
}

/*
      3.5) handle meals
*/

$meals = BookingMeal::search(
    [['booking_id', '=', $booking['id']], ['is_self_provided', '=', false]],
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
        'date'      => $meals[0]['date'],
        'moment'    => $map_meals_names[$meals[0]['time_slot_id']['code']],
    ];
}

$last = null;
if(!is_null($meals[count($meals) - 1])) {
    $last = [
        'date'      => $meals[count($meals) - 1]['date'],
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
      3.6) handle has activities or not
*/

$activities_ids = BookingLine::search([
    ['booking_id', '=', $booking['id']],
    ['is_activity', '=', true]
])
    ->ids();

/*
      3.7) TODO: handle booking
*/

$booking_conf = [
    'downpayment'   => false, // downpayment of 1500 euros needed
    'deposit'       => false, // if damages done to Lathus equipments
    'order_form'    => false  // order form if school or local
];

/*
      3.8) TODO: handle cancellation
             - "l'acompte versé restera acquis au CPA Lathus, à titre de dédit" or "le groupe devra au CPA Lathus la somme de 1500 € à titre de dédit"
*/

$cancellation_conf = [
    'deposit'       => false,
    'amount_1500'   => false
];

$has_activities = !empty($activities_ids);

/*
    3.9) handle lines
*/

$contract_lines = ContractLine::search(['contract_id', '=', $contract['id']])
    ->read([
        'product_id' => [
            'grouping_code_id' => [
                'name',
                'code'
            ],
            'product_model_id' => [
                'grouping_code_id' => [
                    'name',
                    'code'
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

    if(is_null($grouping_code) || $grouping_code['code'] === 'invisible') {
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
        $grouping_name = $map_products_groupings[$line['product_id']['id']]['name'];
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
      3.10) set values
*/

$today = time();

$values = compact(
    'booking', 'customer', 'img_url', 'signature_html', 'children_qty', 'hosting_conf',
    'adult_qty', 'meals_conf', 'map_meals_names', 'has_activities', 'booking_conf',
    'today', 'lines'
);

/*
    4) inject all values into the template
*/

try {
    $loader = new TwigFilesystemLoader(QN_BASEDIR."/packages/{$package}/views/");

    $twig = new TwigEnvironment($loader);
    /**  @var ExtensionInterface **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);
    $currency = Setting::get_value('core', 'locale', 'currency', '€');
    // do not rely on system locale (LC_*)
    $filter = new TwigFilter('format_money', function ($value) use($currency) {
        return number_format((float)($value), 2, ",", ".") . ' ' .$currency;
    });
    $twig->addFilter($filter);

    $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
    $date_filter = new TwigFilter('format_date', function($value) use($date_format) {
        return date($date_format, $value);
    });
    $twig->addFilter($date_filter);

    $map_days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $map_months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $date_filter = new TwigFilter('format_date_long', function($value) use($map_days, $map_months) {
        return $map_days[date('w', $value)].' '.date('j', $value).' '.$map_months[date('n', $value) - 1].' '.date('Y', $value);
    });
    $twig->addFilter($date_filter);

    $date_filter = new TwigFilter('format_time', function($value) {
        return sprintf('%02d:%02d', $value / 3600, $value / 60 % 60);
    });
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

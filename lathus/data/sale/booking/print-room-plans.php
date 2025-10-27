<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
;

use core\setting\Setting;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use realestate\RentalUnit;
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
            'default'       => 'print.room-plans'
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

// Returns main building parent id
$getParentBuildingRentalUnitId = function($parent_id, $map_parents) use(&$getParentBuildingRentalUnitId) {
    if(!isset($map_parents[$parent_id])) {
        return $parent_id;
    }
    return $getParentBuildingRentalUnitId($map_parents[$parent_id], $map_parents);
};

// Returns ids of sub rental units without any children
$getSubRentalUnitsIds = function($parent_rental_unit_id) use(&$getSubRentalUnitsIds) {
    $sub_rental_units = RentalUnit::search(['parent_id', '=', $parent_rental_unit_id])
        ->read(['has_children'])
        ->get();

    $sub_rental_units_ids = [];
    foreach($sub_rental_units as $sub_rental_unit) {
        if(!$sub_rental_unit['has_children']) {
            $sub_rental_units_ids[] = $sub_rental_unit['id'];
        }
        else {
            $sub_rental_units_ids = array_merge(
                $sub_rental_units_ids,
                $getSubRentalUnitsIds($sub_rental_unit['id'])
            );
        }
    }

    return $sub_rental_units_ids;
};

$date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
$formatDate = fn($value) => date($date_format, $value);

/**
 * Data controller
 */

/*
    Retrieve the requested template
*/

$entity = 'lathus\sale\booking\Booking';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = EQ_BASEDIR."/packages/$package/views/$class_path.{$params['view_id']}.html";

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
        'center_id' => [
            'organisation_id' => [
                'logo_document_id' => [
                    'data',
                    'type'
                ]
            ]
        ],
        'customer_identity_id' => [
            'name',
            'address_city'
        ],
        'rental_unit_assignments_ids' => [
            'rental_unit_id' => [
                'name',         // template
                'capacity',     // template
                'description',  // template
                'parent_id',    // needed to map by main buildings
                'has_children'  // needed to list only sub rental units (without any children)
            ]
        ]
    ])
    ->first(true);

$buildings_rental_units = RentalUnit::search([
    ['has_parent', '=', false],
    ['plan_document_id', 'is not', null]
])
    ->read(['name', 'plan_document_id' => ['data', 'type']])
    ->get();

$map_buildings_rental_units = [];
foreach($buildings_rental_units as $id => $building_rental_unit) {
    $content_type = $building_rental_unit['plan_document_id']['type'] ?? 'image/png';
    $img_url = "data:$content_type;base64, ".base64_encode($building_rental_unit['plan_document_id']['data']);

    $map_buildings_rental_units[$id] = [
        'name'          => $building_rental_unit['name'],
        'plan_img_url'  => $img_url,
        'rental_units'  => []
    ];
}

$sub_rental_units = RentalUnit::search(['parent_id', 'is not', null])
    ->read(['parent_id'])
    ->get();

$map_parents = [];
foreach($sub_rental_units as $id => $sub_rental_unit) {
    if(is_null($sub_rental_unit['parent_id'])) {
        continue;
    }

    $map_parents[$id] = $sub_rental_unit['parent_id'];
}

foreach($booking['rental_unit_assignments_ids'] as $rental_unit_assignment) {
    $parent_building_rental_unit_id = null;
    if(is_null($rental_unit_assignment['rental_unit_id']['parent_id'])) {
        $parent_building_rental_unit_id = $rental_unit_assignment['rental_unit_id']['id'];
    }
    else {
        $parent_building_rental_unit_id = $getParentBuildingRentalUnitId($rental_unit_assignment['rental_unit_id']['parent_id'], $map_parents);
    }

    if(isset($map_buildings_rental_units[$parent_building_rental_unit_id])) {
        if($rental_unit_assignment['rental_unit_id']['has_children']) {
            $rental_units = RentalUnit::ids($getSubRentalUnitsIds($rental_unit_assignment['rental_unit_id']['id']))
                ->read(['name', 'capacity', 'description'])
                ->get(true);

            $map_buildings_rental_units[$parent_building_rental_unit_id]['rental_units'] = array_merge(
                $map_buildings_rental_units[$parent_building_rental_unit_id]['rental_units'],
                $rental_units
            );
        }
        else {
            $map_buildings_rental_units[$parent_building_rental_unit_id]['rental_units'][] = $rental_unit_assignment['rental_unit_id'];
        }
    }
}

foreach($map_buildings_rental_units as $key => $building_rental_units) {
    // Remove empty building
    if(count($building_rental_units['rental_units']) === 0) {
        unset($map_buildings_rental_units[$key]);
    }
    else {
        usort($map_buildings_rental_units[$key]['rental_units'], function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
    }
}

$img_url = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';

$logo_document_data = $booking['center_id']['organisation_id']['logo_document_id']['data'] ?? null;
if($logo_document_data) {
    $content_type = $booking['center_id']['organisation_id']['logo_document_id']['type'] ?? 'image/png';
    $img_url = "data:$content_type;base64, ".base64_encode($logo_document_data);
}

/*
    Set values
*/

$today = time();

$values = compact('booking', 'map_buildings_rental_units', 'img_url', 'today');

/*
    Generate html
*/

try {
    $loader = new TwigFilesystemLoader(EQ_BASEDIR . "/packages/{$package}/views/");

    $twig = new TwigEnvironment($loader);

    /**  @var $extension ExtensionInterface **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);

    $date_filter = new TwigFilter('format_date', $formatDate);
    $twig->addFilter($date_filter);

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

$dompdf->setPaper('A4');
$dompdf->loadHtml($html);
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('helvetica', 'regular');
$canvas->page_text(530, $canvas->get_height() - 35, 'p. {PAGE_NUM} / {PAGE_COUNT}', $font, 9);

$output = $dompdf->output();

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();

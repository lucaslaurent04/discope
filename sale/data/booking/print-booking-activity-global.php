<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use discope\setting\Setting;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use sale\booking\Booking;
use sale\booking\BookingActivity;
use sale\booking\BookingMeal;
use sale\booking\TimeSlot;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

[$params, $providers] = eQual::announce([
    'description'   => "Render a booking activities planning as a PDF document, given its id.",
    'params'        => [
        'id' => [
            'type'              => 'integer',
            'description'       => 'Identifier of the booking to print.',
            'required'          => true
        ],
        'view_id' => [
            'type'              => 'string',
            'description'       => 'The identifier of the view <type.name>.',
            'default'           => 'print.activity'
        ],
        'mode' => [
            'type'              => 'string',
            'description'       => 'Mode in which document has to be rendered: simple or detailed.',
            'selection'         => ['simple', 'grouped', 'detailed'],
            'default'           => 'grouped'
        ],
        'lang' => [
            'type'              => 'string',
            'description'       => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'default'           => constant('DEFAULT_LANG')
        ],
        'output' => [
            'type'              => 'string',
            'description'       => 'Output format of the document.',
            'selection'         => ['pdf', 'html'],
            'default'           => 'pdf'
        ],
        'booking_line_group_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\BookingLineGroup',
            'description'       => 'Identifier of the booking line group (sojourn) to print.'
        ]
    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/pdf',
        'accept-origin' => '*'
    ],
    'constants'     => ['DEFAULT_LANG'],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$output = '';

$getLabels = function($lang, $default_labels = []) {
    $view_i18n_file_path = sprintf('%s/packages/sale/i18n/%s/_parts/labels.json', EQ_BASEDIR, $lang);

    $readLabels = function($path) {
        if(!$path || !file_exists($path)) {
            return [];
        }
        $labels = json_decode(file_get_contents($path), true);
        return is_array($labels) ? $labels : [];
    };

    return array_merge(
        $default_labels,
        $readLabels($view_i18n_file_path)
    );
};

$getCustomPackageOutput = function() use($context, $params) {
    $has_custom_package = Setting::get_value('discope', 'features', 'has_custom_package', false);
    if(!$has_custom_package) {
        return null;
    }

    $output = null;
    $custom_package = Setting::get_value('discope', 'features', 'custom_package');
    if(is_null($custom_package)) {
        trigger_error('APP::Missing customization package setting (despite `discope.features.has_custom_package`)', EQ_REPORT_WARNING);
    }
    elseif($custom_package !== 'sale') {
        $operation = $context->get('operation');

        $custom_ctrl_file = sprintf(
            '%s/packages/%s/data/%s/%s',
            EQ_BASEDIR,
            $custom_package,
            $operation['package'],
            $operation['script']
        );

        if(file_exists($custom_ctrl_file)) {
            $custom_ctrl = sprintf(
                '%s_%s',
                $custom_package,
                $operation['operation']
            );

            $output = eQual::run('get', $custom_ctrl, $params, true, true);
        }
    }

    return $output;
};

$getTemplateFilePath = function($entity, $view_id) {
    $template_file = '';

    $parts = explode('\\', $entity);
    $package = array_shift($parts);
    $class_path = implode('/', $parts);

    $has_custom_package = Setting::get_value('discope', 'features', 'has_custom_package', false);
    if($has_custom_package) {
        $custom_package = Setting::get_value('discope', 'features', 'custom_package');
        if(is_null($custom_package)) {
            trigger_error('APP::Missing customization package setting (despite `discope.features.has_custom_package`)', EQ_REPORT_WARNING);
        }
        elseif(file_exists(EQ_BASEDIR."/packages/{$custom_package}/views/{$package}/{$class_path}.{$view_id}.html")) {
            $template_file = EQ_BASEDIR . "/packages/{$custom_package}/views/{$package}/{$class_path}.{$view_id}.html";
        }
    }

    if(empty($template_file)) {
        $template_file = EQ_BASEDIR."/packages/{$package}/views/{$class_path}.{$view_id}.html";

        if(!file_exists($template_file)) {
            throw new Exception("unknown_view_id", QN_ERROR_UNKNOWN_OBJECT);
        }
    }

    return $template_file;
};

// handle custom package override, if any
$output = $getCustomPackageOutput();

if(is_null($output)) {
    $template_file_path = $getTemplateFilePath(Booking::getType(), $params['view_id']);

    // read booking
    $fields = [
        'name',
        'customer_identity_id' => [
            'id',
            'display_name',
            'address_street', 'address_dispatch', 'address_city', 'address_zip', 'address_country',
            'phone',
            'mobile',
            'email'
        ],
        'customer_id' => [
            'partner_identity_id' => [
                'id',
                'display_name',
                'legal_name',
                'type_id',
                'type',
                'address_street', 'address_dispatch', 'address_city', 'address_zip', 'address_country',
                'type',
                'phone',
                'mobile',
                'email',
                'has_vat',
                'vat_number'
            ]
        ],
        'center_id' => [
            'name',
            'manager_id' => ['name'],
            'address_street',
            'address_city',
            'address_zip',
            'phone',
            'email',
            'bank_account_iban',
            'template_category_id',
            'use_office_details',
            'center_office_id' => [
                'code',
                'address_street',
                'address_city',
                'address_zip',
                'phone',
                'email',
                'signature',
                'bank_account_iban',
                'bank_account_bic'
            ],
            'organisation_id' => [
                'id',
                'legal_name',
                'address_street',
                'address_zip',
                'address_city',
                'email',
                'phone',
                'fax',
                'website',
                'registration_number',
                'has_vat',
                'vat_number',
                'bank_account_iban',
                'bank_account_bic',
                'signature',
                'logo_document_id' => ['id', 'type', 'data']
            ]
        ]

    ];


    $booking = Booking::id($params['id'])->read($fields, $params['lang'])->first(true);

    if(!$booking) {
        throw new Exception("unknown_contract", QN_ERROR_UNKNOWN_OBJECT);
    }

    $logo_document_data = $booking['center_id']['organisation_id']['logo_document_id']['data'] ?? null;
    if($logo_document_data) {
        $content_type = $booking['center_id']['organisation_id']['logo_document_id']['type'] ?? 'image/png';
        $img_url = "data:{$content_type};base64, ".base64_encode($logo_document_data);
    }

    $printable_code = sprintf("%04d.%03d", intval($booking['name']) / 1000, intval($booking['name']) % 1000);
    // #kaleo - remove leading zero if present
    if(substr($printable_code, 0, 1) === '0') {
        $printable_code = substr($printable_code, 1);
    }

    $customer_name = $booking['customer_id']['partner_identity_id']['display_name'];
    if($booking['customer_id']['partner_identity_id']['type_id'] !== 1) {
        $customer_name = $booking['customer_id']['partner_identity_id']['legal_name'];
    }
    $customer_name = substr($customer_name, 0, 66);

    $values = [
        'activities_map'             => '',
        'attn_address1'              => '',
        'attn_address2'              => '',
        'attn_name'                  => '',
        'code'                       => $printable_code,
        'company_name'               => $booking['center_id']['organisation_id']['legal_name'],
        'company_reg_number'         => $booking['center_id']['organisation_id']['registration_number'],
        'customer_address1'          => $booking['customer_id']['partner_identity_id']['address_street'],
        'customer_address2'          => $booking['customer_id']['partner_identity_id']['address_zip'].' '.$booking['customer_id']['partner_identity_id']['address_city'].(($booking['customer_id']['partner_identity_id']['address_country'] != 'BE')?(' - '.$booking['customer_id']['partner_identity_id']['address_country']):''),
        'customer_name'              => $customer_name,
        'header_img_url'             => $img_url ?? 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=',
        'postal_address'             => sprintf("%s - %s %s", $booking['center_id']['organisation_id']['address_street'], $booking['center_id']['organisation_id']['address_zip'], $booking['center_id']['organisation_id']['address_city']),
        'time_slots_activities'      => [],
    ];

    $values['i18n'] = $getLabels($params['lang']);



    if($booking['customer_id']['partner_identity_id']['id'] != $booking['customer_identity_id']['id']) {
        $values['attn_name'] = substr($booking['customer_identity_id']['display_name'], 0, 33);
        $values['attn_address1'] = $booking['customer_identity_id']['address_street'];
        $values['attn_address2'] = $booking['customer_identity_id']['address_zip'].' '.$booking['customer_identity_id']['address_city'].(($booking['customer_identity_id']['address_country'] != 'BE')?(' - '.$booking['customer_identity_id']['address_country']):'');
    }


    if($booking['center_id']['use_office_details']) {
        $office = $booking['center_id']['center_office_id'];
        $values['postal_address'] = $office['address_street'].' - '.$office['address_zip'].' '.$office['address_city'];
    }


    $days_languages = [
        ['fr' => 'Dimanche',  'en' => 'Sunday',    'nl' => 'Zondag'],
        ['fr' => 'Lundi',     'en' => 'Monday',    'nl' => 'Maandag'],
        ['fr' => 'Mardi',     'en' => 'Tuesday',   'nl' => 'Dinsdag'],
        ['fr' => 'Mercredi',  'en' => 'Wednesday', 'nl' => 'Woensdag'],
        ['fr' => 'Jeudi',     'en' => 'Thursday',  'nl' => 'Donderdag'],
        ['fr' => 'Vendredi',  'en' => 'Friday',    'nl' => 'Vrijdag'],
        ['fr' => 'Samedi',    'en' => 'Saturday',  'nl' => 'Zaterdag']
    ];

    $days_names = array_map(function($day) use ($params) {
        return $day[$params['lang']];
    }, $days_languages);


    $activities_map = [];

    $time_slots_activities = TimeSlot::search(["is_meal", "=", false])->read(['id', 'name','code', 'order'], $params['lang'])->get();
    usort($time_slots_activities, function ($a, $b) {
        return $a['order'] <=> $b['order'];
    });


    $values['time_slots_activities'] = $time_slots_activities;


    $booking_activities = BookingActivity::search(['booking_id', '=', $booking['id'] ])
        ->read([
            'id',
            'name',
            'description',
            'activity_date',
            'schedule_from', 'schedule_to',
            'product_model_id' => ['name'],
            'activity_booking_line_id' => ['id', 'description', 'product_id' => ['id','name', 'description'] ],
            'booking_line_group_id' => ['id', 'name', 'nb_pers', 'nb_children'],
            'time_slot_id' => ['id', 'code','name'],
        ])
        ->get();


    usort($booking_activities, function ($a, $b) {
        return $a['booking_line_group_id']['id'] <=> $b['booking_line_group_id']['id']
            ?: $a['activity_date'] <=> $b['activity_date'];
    });



    $map_meals = [];

    $meals = BookingMeal::search(['booking_id', '=', $booking['id']])
        ->read(['date', 'time_slot_id' => ['code'], 'is_self_provided', 'meal_type_id' => ['code', 'name'], 'meal_place_id' => ['place_type']]);

    foreach($meals as $meal_id => $meal) {
        $time_slot_code = ['B' => 'AM', 'L' => 'PM', 'D' => 'EV'][$meal['time_slot_id']['code']] ?? $meal['time_slot_id']['code'];
        $date = date('d/m/Y', $meal['date']) . ' ' . $days_names[date('w', $meal['date'])];

        $meal_name = '';
        $meal_place = '';
        $meal_provided = $meal['is_self_provided'] ? 'par vos soins' : '';

        if(in_array($meal['time_slot_id']['code'], ['B', 'L', 'D'])) {
            if($meal['meal_type_id']['code'] == 'regular') {
                $meal_name = ['AM' => 'Petit déjeuner', 'PM' => 'Déjeuner', 'EV' => 'Dîner'][$time_slot_code];
            }
            else {
                $meal_name = $meal['meal_type_id']['name'];
            }
        }
        else {
            $meal_name = 'Goûter';
        }
        if($meal['meal_place_id']['place_type'] != 'offsite') {
            if(!$meal['is_self_provided']) {
                $meal_place = 'au centre';
            }
        }
        else {
            $meal_place = 'en déplacement';
        }

        $map_meals[$date][$time_slot_code][] = trim($meal_name . ' ' . $meal_place . ' ' . $meal_provided);

    }

    foreach($booking_activities as $activity) {
        $group_id = $activity['booking_line_group_id']['id'];

        if(isset($params['booking_line_group_id']) && $params['booking_line_group_id'] != $group_id) {
            continue;
        }

        if(!isset($activities_map[$group_id])) {
            $activities_map[$group_id] = [
                'info' => [
                    'name' => $activity['booking_line_group_id']['name'],
                    'nb_pers' => $activity['booking_line_group_id']['nb_pers'],
                    'nb_children' => $activity['booking_line_group_id']['nb_children']
                ],
                'dates' => []
            ];
        }

        $date = date('d/m/Y', $activity['activity_date']) . ' ' . $days_names[date('w', $activity['activity_date'])];
        if(!isset($activities_map[$group_id]['dates'][$date])) {
            $activities_map[$group_id]['dates'][$date] = [
                'time_slots' => []
            ];

            foreach($time_slots_activities as $time_slot) {
                $activities_map[$group_id]['dates'][$date]['time_slots'][$time_slot['code']] = [];
            }
        }

        $time_slot_code = $activity['time_slot_id']['code'];
        if(isset($activities_map[$group_id]['dates'][$date]['time_slots'][$time_slot_code])) {
            $activities_map[$group_id]['dates'][$date]['time_slots'][$time_slot_code] = [
                'meal'                  => implode(', ', $map_meals[$date][$time_slot_code] ?? []),
                'activity'              => $activity['product_model_id']['name'],
                'schedule_from'         => $activity['schedule_from'],
                'schedule_to'           => $activity['schedule_to'],
                'description'           => $activity['description'],
                /*
                'activity_description'  => $activity['description'],
                'product_description'   => $activity['activity_booking_line_id']['product_id']['description'],
                'service_description'   => $activity['activity_booking_line_id']['description']
                */
            ];
        }
    }

    $values['activities_map'] = $activities_map;

    $template_comments = Template::search([
        ['code', '=', 'activity.doc'],
        ['category_id', '=', $booking['center_id']['template_category_id']],
        ['type', '=', 'planning']
    ])
        ->read(['parts_ids' => ['name', 'value']])
        ->first();

    $values['comments'] = [];
    if(!is_null($template_comments)) {
        foreach($template_comments['parts_ids'] as $part) {
            if(!empty($part['value'])) {
                $values['comments'][$part['name']] = $part['value'];
            }
        }
    }

    try {
        $loader = new TwigFilesystemLoader(dirname($template_file_path));

        $twig = new TwigEnvironment($loader);
        $extension  = new IntlExtension();
        $twig->addExtension($extension);
        $filter = new \Twig\TwigFilter('format_money', function ($value) {
            return number_format((float)($value), 2, ",", ".") . ' €';
        });
        $twig->addFilter($filter);
        $template = $twig->load(basename($template_file_path));
        $html = $template->render($values);
    }
    catch(Exception $e) {
        trigger_error("ORM::error while parsing template - ".$e->getMessage(), EQ_REPORT_DEBUG);
        throw new Exception("template_parsing_issue", EQ_ERROR_INVALID_CONFIG);
    }

    if($params['output'] === 'html') {
        $output = $html;
    }
    else {
        /*
            Convert HTML to PDF
        */

        // instantiate and use the dompdf class
        $options = new DompdfOptions();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);

        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");
        $canvas->page_text(530, $canvas->get_height() - 35, "p. {PAGE_NUM} / {PAGE_COUNT}", $font, 9, [0,0,0]);

        $output = $dompdf->output();
    }
}

if($params['output'] === 'html') {
    $context
        ->httpResponse()
        ->header('Content-Type', 'text/html')
        ->body($output)
        ->send();
}
else {
    $context
        ->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
}

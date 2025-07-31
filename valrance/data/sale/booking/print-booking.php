<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use communication\TemplatePart;
use core\setting\Setting;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use equal\data\DataFormatter;
use sale\booking\Booking;
use sale\booking\BookingActivity;
use sale\booking\Consumption;
use sale\booking\TimeSlot;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use sale\booking\BookingMeal;

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
            'default'       => 'print.default'
        ],
        'mode' =>  [
            'description'   => 'Mode in which document has to be rendered: simple or detailed.',
            'type'          => 'string',
            'selection'     => ['simple', 'grouped', 'detailed'],
            'default'       => 'grouped'
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
    'providers'     => ['context', 'orm']
]);

list($context, $orm) = [$providers['context'], $providers['orm']];

/**
 * Methods
 */

// #todo - this should be in the Customer class
$lodgingBookingPrintBookingFormatMember = function($booking) {
    $customer_assignment = Setting::get_value('sale', 'organization', 'customer.number_assignment', 'id');
    $code = $booking['customer_id']['partner_identity_id'][$customer_assignment];
    if($customer_assignment === 'id') {
        $code = ltrim(sprintf("%3d.%03d.%03d", intval($code) / 1000000, (intval($code) / 1000) % 1000, intval($code)% 1000), '0');
    }
    return $code . ' - ' . $booking['customer_id']['partner_identity_id']['display_name'];
};

$lodgingBookingPrintAgeRangesText = function($booking, $connection_names) {
    $age_rang_maps = [];

    foreach($booking['booking_lines_groups_ids'] as $booking_line_group) {
        if(!$booking_line_group['is_sojourn'] || $booking_line_group['group_type'] !== 'sojourn') {
            continue;
        }
        foreach($booking_line_group['age_range_assignments_ids'] as $age_range_assignment) {
            $age_range_assignment_code = $age_range_assignment['age_range_id']['id'];
            if(!isset($age_rang_maps[$age_range_assignment_code])) {
                $age_rang_maps[$age_range_assignment_code] = [
                    'age_range' => $age_range_assignment['age_range_id']['name'],
                    'qty'       => 0
                ];
            }
            $age_rang_maps[$age_range_assignment_code]['qty'] += $age_range_assignment['qty'];
        }
    }

    $parts = array_map(function($item) { return $item['qty'] . ' ' . strtolower($item['age_range']); }, $age_rang_maps);
    $last = array_pop($parts);
    return count($parts) ? implode(', ', $parts) . ' ' . $connection_names[0] . ' ' . $last : $last;
};

/*
    Retrieve the requested template
*/

$entity = 'valrance\sale\booking\Booking';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = QN_BASEDIR."/packages/{$package}/views/{$class_path}.{$params['view_id']}.html";

if(!file_exists($file)) {
    throw new Exception("unknown_view_id", QN_ERROR_UNKNOWN_OBJECT);
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

// read booking
$fields = [
    'name',
    'modified',
    'status',
    'date_from',
    'date_to',
    'nb_pers',
    'total',
    'price',
    'is_price_tbc',
    'type_id' => [
            'id',
            'booking_schedule_layout'
    ],
    'customer_identity_id' => [
            'id',
            'display_name',
            'address_street', 'address_dispatch', 'address_city', 'address_zip', 'address_country',
            'phone',
            'mobile',
            'email'
    ],
    'customer_id' => [
        'rate_class_id' => ['id', 'name', 'code'],
        'partner_identity_id' => [
            'id',
            'display_name',
            'accounting_account',
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
    ],
    'contacts_ids' => [
        'type',
        'partner_identity_id' => [
            'display_name',
            'phone',
            'mobile',
            'email',
            'title'
        ]
    ],
    'booking_lines_groups_ids' => [
        'id',
        'name',
        'has_pack',
        'group_type',
        'is_locked',
        'is_sojourn',
        'pack_id'  => ['label'],
        'qty',
        'unit_price',
        'vat_rate',
        'total',
        'price',
        'fare_benefit',
        'rate_class_id' => ['id', 'name', 'code', 'description'],
        'date_from',
        'date_to',
        'nb_pers',
        'age_range_assignments_ids'=> ['id', 'age_range_id' =>['id', 'name'] ,'booking_line_group_id','qty'],
        'booking_lines_ids' => [
            'name',
            'is_activity',
            'is_transport',
            'is_supply',
            'product_id' => [
                'id',
                'label' ,
                'age_range_id',
                'grouping_code_id' => ['id', 'code', 'name'],
                'product_model_id' => ['id', 'name', 'grouping_code_id' => ['id', 'code', 'name']]
            ],
            'booking_activity_id' => [
                'id', 'name', 'total', 'price',
                'supplies_booking_lines_ids' => ['id', 'qty', 'total', 'price'],
                'transports_booking_lines_ids' => ['id', 'qty', 'total', 'price']
            ],
            'description',
            'is_accomodation',
            'qty',
            'unit_price',
            'free_qty',
            'discount',
            'total',
            'price',
            'vat_rate',
            'qty_accounting_method',
            'qty_vars',
            'has_own_qty',
            'own_duration',
            'price_adapters_ids' => ['type', 'value', 'is_manual_discount']
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

$center_office_code = (isset( $booking['center_id']['center_office_id']['code']) && $booking['center_id']['center_office_id']['code'] == 1) ? 'GG' : 'GA';

// #memo - client has requested no to show activites on booking/contract but always use disctint doc (print-booking-activity)
// $has_activity = Setting::get_value('sale', 'features', 'booking.activity', true);
$has_activity = false;

$consumption_table_show  = Setting::get_value('sale', 'features', 'templates.quote.consumption_table', 1);
$values = [
    'attn_address1'              => '',
    'attn_address2'              => '',
    'attn_name'                  => '',
    'benefit_lines'              => [],
    'center'                     => $booking['center_id']['name'],
    'center_address'             => $booking['center_id']['address_street'].' - '.$booking['center_id']['address_zip'].' '.$booking['center_id']['address_city'],
    'center_contact1'            => (isset($booking['center_id']['manager_id']['name']))?$booking['center_id']['manager_id']['name']:'',
    'center_contact2'            => DataFormatter::format($booking['center_id']['phone'], 'phone').' - '.$booking['center_id']['email'],
    'center_email'               => $booking['center_id']['email'],
    'center_office'              => $center_office_code,
    'center_phone'               => DataFormatter::format($booking['center_id']['phone'], 'phone'),
    'center_signature'           => $booking['center_id']['center_office_id']['signature'],
    'code'                       => sprintf("%03d.%03d", intval($booking['name']) / 1000, intval($booking['name']) % 1000),
    'company_address'            => sprintf("%s %s %s", $booking['center_id']['organisation_id']['address_street'], $booking['center_id']['organisation_id']['address_zip'], $booking['center_id']['organisation_id']['address_city']),
    'company_bic'                => DataFormatter::format($booking['center_id']['organisation_id']['bank_account_bic'], 'bic'),
    'company_email'              => $booking['center_id']['organisation_id']['email'],
    'company_fax'                => DataFormatter::format($booking['center_id']['organisation_id']['fax'], 'phone'),
    'company_has_vat'            => $booking['center_id']['organisation_id']['has_vat'],
    'company_iban'               => DataFormatter::format($booking['center_id']['organisation_id']['bank_account_iban'], 'iban'),
    'company_name'               => $booking['center_id']['organisation_id']['legal_name'],
    'company_phone'              => DataFormatter::format($booking['center_id']['organisation_id']['phone'], 'phone'),
    'company_reg_number'         => $booking['center_id']['organisation_id']['registration_number'],
    'company_vat_number'         => $booking['center_id']['organisation_id']['vat_number'],
    'company_website'            => $booking['center_id']['organisation_id']['website'],
    'consumptions_map_detailed'  => [],
    'consumptions_map_simple'    => [],
    'consumptions_tye'           => isset($booking['type_id']['booking_schedule_layout'])?$booking['type_id']['booking_schedule_layout']:'simple',
    'contact_email'              => $booking['customer_id']['partner_identity_id']['email'],
    'contact_name'               => '',
    'contact_phone'              => (strlen($booking['customer_id']['partner_identity_id']['phone']))?$booking['customer_id']['partner_identity_id']['phone']:$booking['customer_id']['partner_identity_id']['mobile'],
    'customer_address1'          => $booking['customer_id']['partner_identity_id']['address_street'],
    'customer_address2'          => $booking['customer_id']['partner_identity_id']['address_zip'].' '.$booking['customer_id']['partner_identity_id']['address_city'].(($booking['customer_id']['partner_identity_id']['address_country'] != 'BE')?(' - '.$booking['customer_id']['partner_identity_id']['address_country']):''),
    'customer_country'           => $booking['customer_id']['partner_identity_id']['address_country'],
    'customer_has_vat'           => (int) $booking['customer_id']['partner_identity_id']['has_vat'],
    'customer_name'              => substr($booking['customer_id']['partner_identity_id']['display_name'], 0, 66),
    'customer_vat'               => $booking['customer_id']['partner_identity_id']['vat_number'],
    'date'                       => date('d/m/Y', $booking['modified']),
    'has_footer'                 => 0,
    'header_img_url'             => $img_url ?? 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=',
    'is_price_tbc'               => $booking['is_price_tbc'],
    'is_agreement_html'          => '',
    'lines'                      => [],
    'member'                     => $lodgingBookingPrintBookingFormatMember($booking),
    'period'                     => 'Du '.date('d/m/Y', $booking['date_from']).' au '.date('d/m/Y', $booking['date_to']),
    'postal_address'             => sprintf("%s - %s %s", $booking['center_id']['organisation_id']['address_street'], $booking['center_id']['organisation_id']['address_zip'], $booking['center_id']['organisation_id']['address_city']),
    'price'                      => $booking['price'],
    'agreement_html'             => '',
    'signature_html'             => '',
    'footer_html'                => '',
    'header_html'                => '',
    'service_html'               => '',
    'stamp'                      => $booking['center_id']['organisation_id']['signature'] ?? '',
    'status'                     => $booking['status'],
    'tax_lines'                  => [],
    'total'                      => $booking['total'],
    'has_activity'               => $has_activity,
    'activities_map'             => '',
    'show_consumption'           => $consumption_table_show
];


/*
    retrieve terms translations
*/
$values['i18n'] = [
    'invoice'               => Setting::get_value('lodging', 'locale', 'i18n.invoice', null, [], $params['lang']),
    'quote'                 => Setting::get_value('lodging', 'locale', 'i18n.quote', null, [], $params['lang']),
    'option'                => Setting::get_value('lodging', 'locale', 'i18n.option', null, [], $params['lang']),
    'contract'              => Setting::get_value('lodging', 'locale', 'i18n.contract', null, [], $params['lang']),
    'booking_invoice'       => Setting::get_value('lodging', 'locale', 'i18n.booking_invoice', null, [], $params['lang']),
    'booking_quote'         => Setting::get_value('lodging', 'locale', 'i18n.booking_quote', null, [], $params['lang']),
    'booking_contract'      => Setting::get_value('lodging', 'locale', 'i18n.booking_contract', null, [], $params['lang']),
    'credit_note'           => Setting::get_value('lodging', 'locale', 'i18n.credit_note', null, [], $params['lang']),
    'company_registry'      => Setting::get_value('lodging', 'locale', 'i18n.company_registry', null, [], $params['lang']),
    'vat_number'            => Setting::get_value('lodging', 'locale', 'i18n.vat_number', null, [], $params['lang']),
    'vat'                   => Setting::get_value('lodging', 'locale', 'i18n.vat', null, [], $params['lang']),
    'your_stay_at'          => Setting::get_value('lodging', 'locale', 'i18n.your_stay_at', null, [], $params['lang']),
    'contact'               => Setting::get_value('lodging', 'locale', 'i18n.contact', null, [], $params['lang']),
    'period'                => Setting::get_value('lodging', 'locale', 'i18n.period', null, [], $params['lang']),
    'member'                => Setting::get_value('lodging', 'locale', 'i18n.member', null, [], $params['lang']),
    'phone'                 => Setting::get_value('lodging', 'locale', 'i18n.phone', null, [], $params['lang']),
    'email'                 => Setting::get_value('lodging', 'locale', 'i18n.email', null, [], $params['lang']),
    'booking_ref'           => Setting::get_value('lodging', 'locale', 'i18n.booking_ref', null, [], $params['lang']),
    'your_reference'        => Setting::get_value('lodging', 'locale', 'i18n.your_reference', null, [], $params['lang']),
    'number_short'          => Setting::get_value('lodging', 'locale', 'i18n.number_short', null, [], $params['lang']),
    'date'                  => Setting::get_value('lodging', 'locale', 'i18n.date', null, [], $params['lang']),
    'status'                => Setting::get_value('lodging', 'locale', 'i18n.status', null, [], $params['lang']),
    'paid'                  => Setting::get_value('lodging', 'locale', 'i18n.paid', null, [], $params['lang']),
    'to_pay'                => Setting::get_value('lodging', 'locale', 'i18n.to_pay', null, [], $params['lang']),
    'to_refund'             => Setting::get_value('lodging', 'locale', 'i18n.to_refund', null, [], $params['lang']),
    'product_label'         => Setting::get_value('lodging', 'locale', 'i18n.product_label', null, [], $params['lang']),
    'quantity_short'        => Setting::get_value('lodging', 'locale', 'i18n.quantity_short', null, [], $params['lang']),
    'freebies_short'        => Setting::get_value('lodging', 'locale', 'i18n.freebies_short', null, [], $params['lang']),
    'unit_price'            => Setting::get_value('lodging', 'locale', 'i18n.unit_price', null, [], $params['lang']),
    'discount_short'        => Setting::get_value('lodging', 'locale', 'i18n.discount_short', null, [], $params['lang']),
    'taxes'                 => Setting::get_value('lodging', 'locale', 'i18n.taxes', null, [], $params['lang']),
    'price'                 => Setting::get_value('lodging', 'locale', 'i18n.price', null, [], $params['lang']),
    'total'                 => Setting::get_value('lodging', 'locale', 'i18n.total', null, [], $params['lang']),
    'price_tax_excl'        => Setting::get_value('lodging', 'locale', 'i18n.price_tax_excl', null, [], $params['lang']),
    'total_tax_excl'        => Setting::get_value('lodging', 'locale', 'i18n.total_tax_excl', null, [], $params['lang']),
    'total_tax_incl'        => Setting::get_value('lodging', 'locale', 'i18n.total_tax_incl', null, [], $params['lang']),
    'stay_total_tax_incl'   => Setting::get_value('lodging', 'locale', 'i18n.stay_total_tax_incl', null, [], $params['lang']),
    'balance_of'            => Setting::get_value('lodging', 'locale', 'i18n.balance_of', null, [], $params['lang']),
    'to_be_paid_before'     => Setting::get_value('lodging', 'locale', 'i18n.to_be_paid_before', null, [], $params['lang']),
    'communication'         => Setting::get_value('lodging', 'locale', 'i18n.communication', null, [], $params['lang']),
    'amount_to_be refunded' => Setting::get_value('lodging', 'locale', 'i18n.amount_to_be refunded', null, [], $params['lang']),
    'advantage_included'    => Setting::get_value('lodging', 'locale', 'i18n.advantage_included', null, [], $params['lang']),
    'fare_category'         => Setting::get_value('lodging', 'locale', 'i18n.fare_category', null, [], $params['lang']),
    'advantage'             => Setting::get_value('lodging', 'locale', 'i18n.advantage', null, [], $params['lang']),
    'consumptions_details'  => Setting::get_value('lodging', 'locale', 'i18n.consumptions_details', null, [], $params['lang']),
    'day'                   => Setting::get_value('lodging', 'locale', 'i18n.day', null, [], $params['lang']),
    'meals_morning'         => Setting::get_value('lodging', 'locale', 'i18n.meals_morning', null, [], $params['lang']),
    'meals_midday'          => Setting::get_value('lodging', 'locale', 'i18n.meals_midday', null, [], $params['lang']),
    'meal_evening'          => Setting::get_value('lodging', 'locale', 'i18n.meal_evening', null, [], $params['lang']),
    'nights'                => Setting::get_value('lodging', 'locale', 'i18n.nights', null, [], $params['lang']),
    'payments_schedule'     => Setting::get_value('lodging', 'locale', 'i18n.payments_schedule', null, [], $params['lang']),
    'payment'               => Setting::get_value('lodging', 'locale', 'i18n.payment', null, [], $params['lang']),
    'already_paid'          => Setting::get_value('lodging', 'locale', 'i18n.already_paid', null, [], $params['lang']),
    'amount'                => Setting::get_value('lodging', 'locale', 'i18n.amount', null, [], $params['lang']),
    'yes'                   => Setting::get_value('lodging', 'locale', 'i18n.yes', null, [], $params['lang']),
    'no'                    => Setting::get_value('lodging', 'locale', 'i18n.no', null, [], $params['lang']),
    'the_amount_of'         => Setting::get_value('lodging', 'locale', 'i18n.the_amount_of', null, [], $params['lang']),
    'must_be_paid_before'   => Setting::get_value('lodging', 'locale', 'i18n.must_be_paid_before', null, [], $params['lang']),
    'date_and_signature'    => Setting::get_value('lodging', 'locale', 'i18n.date_and_signature', null, [], $params['lang']),
    'time_slot'             => Setting::get_value('lodging', 'locale', 'i18n.time_slot', null, [], $params['lang']),
    'snack'                 => Setting::get_value('lodging', 'locale', 'i18n.snack', null, [], $params['lang']),
    'meals'                 => Setting::get_value('lodging', 'locale', 'i18n.meals', null, [], $params['lang']),
    'title_agreement'       => Setting::get_value('lodging', 'locale', 'i18n.title_agreement', null, [], $params['lang']),
    'activities_details'    => Setting::get_value('lodging', 'locale', 'i18n.activities_details', null, [], $params['lang']),
    'activity'              => Setting::get_value('lodging', 'locale', 'i18n.activity', null, [], $params['lang'])
];


/**
 * Add info for ATTN, if required.
 * If the invoice is emitted to a partner distinct from the booking customer, the latter is ATTN and the former is considered as the customer.
 */

if($booking['customer_id']['partner_identity_id']['id'] != $booking['customer_identity_id']['id']) {
    $values['attn_name'] = substr($booking['customer_identity_id']['display_name'], 0, 33);
    $values['attn_address1'] = $booking['customer_identity_id']['address_street'];
    $values['attn_address2'] = $booking['customer_identity_id']['address_zip'].' '.$booking['customer_identity_id']['address_city'].(($booking['customer_identity_id']['address_country'] != 'BE')?(' - '.$booking['customer_identity_id']['address_country']):'');
}

/*
    override contact and payment details with center's office, if set
*/
if($booking['center_id']['use_office_details']) {
    $office = $booking['center_id']['center_office_id'];
    $values['company_iban'] = DataFormatter::format($office['bank_account_iban'], 'iban');
    $values['company_bic'] = DataFormatter::format($office['bank_account_bic'], 'bic');
    $values['center_phone'] = DataFormatter::format($office['phone'], 'phone');
    $values['center_email'] = $office['email'];
    $values['center_signature'] = $office['signature'];
    $values['postal_address'] = $office['address_street'].' - '.$office['address_zip'].' '.$office['address_city'];
}


$connection_languages = [
    ['fr' => 'et', 'en' => 'and', 'nl' => 'en'],
];

$connection_names = array_map(function($item) use ($params) {
    return $item[$params['lang']];
}, $connection_languages);



/*
    retrieve templates
*/
if($booking['center_id']['template_category_id']) {

    $template = Template::search([
                            ['category_id', '=', $booking['center_id']['template_category_id']],
                            ['code', '=', $booking['status']],
                            ['type', '=', $booking['status']]
                        ])
                        ->read( ['id','parts_ids' => ['name', 'value']], $params['lang'])
                        ->first(true);

    foreach($template['parts_ids'] as $part_id => $part) {
        if($part['name'] == 'header') {
            $value = $part['value'];
            $value = str_replace('{center}', $booking['center_id']['name'], $value);

            $date_from = $days_names[date('w', $booking['date_from'])] . ' '. date('d/m/Y', $booking['date_from']);
            $date_to = $days_names[date('w', $booking['date_to'])] . ' '. date('d/m/Y', $booking['date_to']);

            // 1) convert config to textual info for arrival day

            // retrieve meals info for arrival day
            $has_breakfast = false;
            $has_lunch = false;
            $has_diner = false;
            $has_snack = false;
            $is_lunch_picnic = false;

            $meals = BookingMeal::search([['booking_id', '=', $booking['id']], ['date', '=', $booking['date_from']]])
                ->read([
                    'time_slot_id' => ['code'],
                    'meal_type_id' => ['code'],
                    'is_self_provided'
                ]);

            foreach($meals as $meal_id => $meal) {
                if($meal['time_slot_id']['code'] === 'B' && !$meal['is_self_provided']) {
                    $has_breakfast = true;
                }
                elseif($meal['time_slot_id']['code'] === 'L') {
                    if(!$meal['is_self_provided']) {
                        $has_lunch = true;
                    }
                    if($meal['meal_type_id']['code'] === 'picnic') {
                        $is_lunch_picnic = true;
                    }
                }
                elseif($meal['time_slot_id']['code'] === 'D' && !$meal['is_self_provided']) {
                    $has_diner = true;
                }
                elseif($meal['time_slot_id']['code'] === 'PM' && !$meal['is_self_provided']) {
                    $has_snack = true;
                }
            }

            $date_from_text = '';

            if($has_breakfast) {
                $date_from_text .= 'pour le petit-déjeuner';
            }
            elseif($has_lunch || $is_lunch_picnic) {
                $date_from_text .= 'pour le déjeuner';
            }
            elseif($has_snack) {
                $date_from .= 'pour le goûter';
            }
            elseif($has_diner) {
                $date_from .= 'pour le dîner';
            }
            else {
                $date_from .= 'pour la nuitée';
            }

            if($has_picnic) {
                if(strlen($date_from_text)) {
                    $date_from_text .= ', ';
                }
                if($has_lunch) {
                    $date_from_text .= 'avec pique-nique fourni par le Relais Valrance';
                }
                else {
                    $date_from_text .= 'avec pique-nique amené par vos soins';
                }
            }

            if($has_snack) {
                if(strlen($date_from_text)) {
                    $date_from_text .= ', et ';
                }
                $date_from_text .= 'avec goûter fourni par le Relais Valrance';
            }
            elseif($has_lunch || $is_lunch_picnic) {
                if(strlen($date_from_text)) {
                    $date_from_text .= ', et ';
                }
                $date_from_text .= 'avec goûter amené par vos soins';
            }

            if(strlen($date_from_text)) {
                $date_from .= ' (' . $date_from_text . ')';
            }

            $value = str_replace('{date_from}', $date_from, $value);


            // 2) convert config to textual info for departure day

            // retrieve meals info for departure day
            $has_breakfast = false;
            $has_lunch = false;
            $has_snack = false;
            $has_diner = false;
            $is_breakfast_offsite = false;
            $is_lunch_offsite = false;
            $is_snack_offsite = false;
            $is_diner_offsite = false;

            $meals = BookingMeal::search([['booking_id', '=', $booking['id']], ['date', '=', $booking['date_to']]])
                ->read([
                    'time_slot_id' => ['code'],
                    'meal_place_id' => ['place_type'],
                    'is_self_provided'
                ]);

            foreach($meals as $meal_id => $meal) {
                $offsite = in_array($meal['meal_place_id']['place_type'], ['offsite', 'auto']);
                if($meal['time_slot_id']['code'] === 'B' && !$meal['is_self_provided']) {
                    $has_breakfast = true;
                    $is_breakfast_offsite = $offsite;
                }
                elseif($meal['time_slot_id']['code'] === 'L' && !$meal['is_self_provided']) {
                    $has_lunch = true;
                    $is_lunch_offsite = $offsite;
                }
                elseif($meal['time_slot_id']['code'] === 'PM' && !$meal['is_self_provided']) {
                    $has_snack = true;
                    $is_snack_offsite = $offsite;
                }
                elseif($meal['time_slot_id']['code'] === 'D' && !$meal['is_self_provided']) {
                    $has_diner = true;
                    $is_diner_offsite = $offsite;
                }
            }

            $date_to_text = '';

            if($has_diner && !$is_diner_offsite) {
                $date_to_text .= 'après le dîner';
            }
            elseif($has_snack && !$is_snack_offsite) {
                $date_to_text .= 'après le goûter';
            }
            elseif($has_lunch && !$is_lunch_offsite) {
                $date_to_text .= 'après le déjeuner';
            }
            elseif($has_breakfast && !$is_breakfast_offsite) {
                $date_to_text .= 'après le petit-déjeuner';
            }

            if($has_breakfast && $is_breakfast_offsite) {
                if(strlen($date_to_text)) {
                    $date_to_text .= ', ';
                }
                if($is_lunch_offsite) {
                    if($is_snack_offsite) {
                        if($is_diner_offsite) {
                            $date_to_text .= 'avec collation petit-déjeuner, pique-nique, goûter, et pique-nique du soir à emporter';
                        }
                        else {
                            $date_to_text .= 'avec collation petit-déjeuner, pique-nique et goûter à emporter';
                        }
                    }
                    else {
                        $date_to_text .= 'avec collation petit-déjeuner et pique-nique à emporter';
                    }
                }
                else {
                    $date_to_text .= 'avec collation petit-déjeuner à emporter';
                }
            }
            elseif($has_lunch && $is_lunch_offsite) {
                if(strlen($date_to_text)) {
                    $date_to_text .= ', ';
                }
                if($is_snack_offsite) {
                    if($is_diner_offsite) {
                        $date_to_text .= 'avec pique-nique, goûter, et pique-nique du soir à emporter';
                    }
                    else {
                        $date_to_text .= 'avec pique-nique et goûter à emporter';
                    }
                }
                else {
                    $date_to_text .= 'avec pique-nique à emporter';
                }
            }
            elseif($has_snack && $is_snack_offsite) {
                if(strlen($date_to_text)) {
                    $date_to_text .= ', ';
                }
                if($is_diner_offsite) {
                    $date_to_text .= 'avec goûter et pique-nique du soir à emporter';
                }
                else {
                    $date_to_text .= 'avec goûter à emporter';
                }
            }
            elseif($has_diner && $is_diner_offsite) {
                if(strlen($date_to_text)) {
                    $date_to_text .= ', ';
                }
                $date_to_text .= 'avec pique-nique du soir à emporter';
            }

            if(strlen($date_to_text)) {
                $date_to .= ' (' . $date_to_text . ')';
            }

            $value = str_replace('{date_to}', $date_to, $value);

            $text_pers = $lodgingBookingPrintAgeRangesText($booking, $connection_names);
            $value = str_replace('{nb_pers}', $text_pers, $value);

            $values['header_html'] = $value;
        }
        elseif($part['name'] == 'service') {
            $value = $part['value'];
            if($booking['customer_id']['rate_class_id']) {
                $map_rate_class = [
                    230 => 'sejour',
                    220 => 'groupe',
                    210 => 'classe',
                    250 => 'clsh',
                    240 => 'cv'
                ];
                $part_name = 'service_'. $map_rate_class[$booking['customer_id']['rate_class_id']['code']];
                $template_part = TemplatePart::search([['name', '=', $part_name], ['template_id', '=', $template['id']] ])
                        ->read(['value'], $params['lang'])
                        ->first(true);
                if($template_part){
                    $value .= $template_part['value'];
                }

            }
            $values['service_html'] = $value;
        }
        elseif($part['name'] == 'agreement_notice') {
            $values['is_agreement_html'] = 1;
            $values['agreement_html'] = $part['value'];
        }
        elseif($part['name'] == 'footer') {
            $values['has_footer'] = 1;
            $values['footer_html'] = $part['value'];
            $hasFooter = true;
        }
        elseif($part['name'] == 'signature') {
            $values['signature_html'] = $part['value'] . $values['center_signature'];
        }
    }

}

if (!$hasFooter) {
    $values['header_html'] .= $values['center_signature'];
}

$template_part = TemplatePart::search(['name', '=', 'advantage_notice'])
                        ->read(['value'], $params['lang'])
                        ->first(true);

if($template_part) {
    $values['advantage_notice_html'] = $template_part['value'];
}

$template_part = TemplatePart::search(['name', '=', 'tbc_notice'])
                        ->read(['value'], $params['lang'])
                        ->first(true);

if($template_part) {
    $values['tbc_notice_html'] = $template_part['value'];
}

/*
    feed lines
*/
$lines = [];

// all lines are stored in groups
foreach($booking['booking_lines_groups_ids'] as $booking_line_group) {

    // generate group details
    $group_details = '';

    if($booking_line_group['date_from'] == $booking_line_group['date_to']) {
        $group_details .= date('d/m/y', $booking_line_group['date_from']);
    }
    else {
        $group_details .= date('d/m/y', $booking_line_group['date_from']).' - '.date('d/m/y', $booking_line_group['date_to']);
    }

    $group_details .= ' - '.$booking_line_group['nb_pers'].'p.';

    if($booking_line_group['has_pack'] && $booking_line_group['is_locked']) {
        // group is a product pack (bundle) with own price
        $group_is_pack = true;

        $line = [
            'name'          => $booking_line_group['name'],
            'details'       => $group_details,
            'description'   => $booking_line_group['pack_id']['label'],
            'price'         => $booking_line_group['price'],
            'total'         => $booking_line_group['total'],
            'unit_price'    => $booking_line_group['unit_price'],
            'vat_rate'      => $booking_line_group['vat_rate'],
            'qty'           => $booking_line_group['qty'],
            'free_qty'      => $booking_line_group['free_qty'],
            'discount'      => $booking_line_group['discount'],
            'is_group'      => true,
            'is_pack'       => true
        ];
        $lines[] = $line;

        if($params['mode'] == 'detailed') {
            foreach($booking_line_group['booking_lines_ids'] as $booking_line) {
                $line = [
                    'name'          => $booking_line['name'],
                    'qty'           => $booking_line['qty'],
                    'price'         => null,
                    'total'         => null,
                    'unit_price'    => null,
                    'vat_rate'      => null,
                    'discount'      => null,
                    'free_qty'      => null,
                    'is_group'      => false,
                    'is_pack'       => false
                ];
                $lines[] = $line;
            }
        }
    }
    else {

        // group is a pack with no own price
        $group_is_pack = false;

        $vat_rate = floatval($booking_line_group['total']) ? (floatval($booking_line_group['price']) / floatval($booking_line_group['total']) - 1.0) : 0;

        if($params['mode'] == 'grouped') {
            $line = [
                'name'          => $booking_line_group['name'],
                'details'       => $group_details,
                'price'         => $booking_line_group['price'],
                'total'         => $booking_line_group['total'],
                'unit_price'    => $booking_line_group['total'],
                'vat_rate'      => $vat_rate,
                'qty'           => 1,
                'free_qty'      => 0,
                'discount'      => 0,
                'is_group'      => true,
                'is_pack'       => false
            ];
        }
        else {
            $line = [
                'name'          => $booking_line_group['name'],
                'details'       => $group_details,
                'price'         => null,
                'total'         => null,
                'unit_price'    => null,
                'vat_rate'      => null,
                'qty'           => null,
                'free_qty'      => null,
                'discount'      => null,
                'is_group'      => true,
                'is_pack'       => false
            ];
        }
        $lines[] = $line;


        $group_lines = [];

        foreach($booking_line_group['booking_lines_ids'] as $booking_line) {

            if($params['mode'] == 'grouped') {
                $line = [
                    'name'          => (strlen($booking_line['description']) > 0) ? $booking_line['description']:$booking_line['product_id']['label'],
                    'price'         => null,
                    'total'         => null,
                    'unit_price'    => null,
                    'vat_rate'      => null,
                    'qty'           => $booking_line['qty'],
                    'discount'      => null,
                    'free_qty'      => $booking_line['free_qty'],
                    'is_group'      => false,
                    'is_pack'       => false
                ];
            }
            else {
                $line = [
                    'name'          => (strlen($booking_line['description']) > 0) ? $booking_line['description'] : $booking_line['product_id']['label'],
                    'price'         => $booking_line['price'],
                    'total'         => $booking_line['total'],
                    'unit_price'    => $booking_line['unit_price'],
                    'vat_rate'      => $booking_line['vat_rate'],
                    'qty'           => $booking_line['qty'],
                    'discount'      => $booking_line['discount'],
                    'free_qty'      => $booking_line['free_qty'],
                    'is_group'      => false,
                    'is_pack'       => false
                ];
            }

            $group_lines[] = $line;
        }
        if($params['mode'] == 'detailed' || $params['mode'] == 'grouped') {
            foreach($group_lines as $line) {
                $lines[] = $line;
            }
        }
        // mode is 'simple' : group lines by VAT rate
        else {
            $group_tax_lines = [];
            foreach($group_lines as $line) {
                $vat_rate = strval($line['vat_rate']);
                if(!isset($group_tax_lines[$vat_rate])) {
                    $group_tax_lines[$vat_rate] = 0;
                }
                $group_tax_lines[$vat_rate] += $line['total'];
            }

            if(count(array_keys($group_tax_lines)) <= 1) {
                $pos = count($lines)-1;
                foreach($group_tax_lines as $vat_rate => $total) {
                    $lines[$pos]['qty'] = 1;
                    $lines[$pos]['vat_rate'] = $vat_rate;
                    $lines[$pos]['total'] = $total;
                    $lines[$pos]['price'] = $total * (1 + $vat_rate);
                }
            }
            else {
                foreach($group_tax_lines as $vat_rate => $total) {
                    $line = [
                        'name'      => 'Services avec TVA '.($vat_rate*100).'%',
                        'qty'       => 1,
                        'vat_rate'  => $vat_rate,
                        'total'     => $total,
                        'price'     => $total * (1 + $vat_rate)
                    ];
                    $lines[] = $line;
                }
            }
        }
    }
}

$lines_map = [];

if($params['mode'] === 'grouped') {
    $lines = [];
    foreach ($booking['booking_lines_groups_ids'] as $booking_line_group) {
        foreach ($booking_line_group['booking_lines_ids'] as $booking_line) {
            /*
            // #memo - even if part of an activity - transports must be grouped distinctively
            if ($booking_line['is_transport'] && !empty($booking_line['booking_activity_id'])){
                continue;
            }
            */

            if ($booking_line['is_supply'] && !empty($booking_line['booking_activity_id'])){
                continue;
            }

            $booking_line_group_id = $booking_line_group['id'];
            $product = $booking_line['product_id'];

            $grouping_code = $booking_line['product_id']['label'];

            if(isset($product['grouping_code_id']['name'])) {
                if($product['grouping_code_id']['code'] === 'invisible') {
                    continue;
                }
                $grouping_code = $product['grouping_code_id']['name'];
            }
            elseif(isset($product['product_model_id']['grouping_code_id']['name'])) {
                if($product['product_model_id']['grouping_code_id']['code'] === 'invisible') {
                    continue;
                }
                $grouping_code = $product['product_model_id']['grouping_code_id']['name'];
            }
            elseif(strlen($booking_line['description']) > 0) {
                $grouping_code = $booking_line['description'];
            }

            if(!isset($lines_map[$booking_line_group_id])) {
                $lines_map[$booking_line_group_id] = [];
            }
            if(!isset($lines_map[$booking_line_group_id][$grouping_code])) {
                $lines_map[$booking_line_group_id][$grouping_code] = [];
            }

            if(!isset($lines_map[$booking_line_group_id][$grouping_code][$product['id']])) {
                $lines_map[$booking_line_group_id][$grouping_code][$product['id']] = [
                    'name'          => $booking_line['name'],
                    'price'         => null,
                    'total'         => null,
                    'unit_price'    => null,
                    'vat_rate'      => null,
                    'qty'           => $booking_line['qty'],
                    'discount'      => null,
                    'has_pack'      => $booking_line_group['has_pack'],
                    'is_activity'   => $booking_line['is_activity'],
                    'free_qty'      => $booking_line['free_qty'],
                    'grouping'      => $grouping_code
                ];

                if($booking_line['booking_activity_id'] &&
                    !empty($booking_line['booking_activity_id']['supplies_booking_lines_ids'])
                ) {
                    $lines_map[$booking_line_group_id][$grouping_code][$product['id']]['price'] += $booking_line['booking_activity_id']['price'];
                    $lines_map[$booking_line_group_id][$grouping_code][$product['id']]['total'] += $booking_line['booking_activity_id']['total'];
                }
                else {
                    $lines_map[$booking_line_group_id][$grouping_code][$product['id']]['unit_price'] = $booking_line['unit_price'];
                    $lines_map[$booking_line_group_id][$grouping_code][$product['id']]['price'] = $booking_line['price'];
                    $lines_map[$booking_line_group_id][$grouping_code][$product['id']]['total'] = $booking_line['total'];
                }
            }
            else {
                $lines_map[$booking_line_group_id][$grouping_code][$product['id']]['price'] += $booking_line['price'];
                $lines_map[$booking_line_group_id][$grouping_code][$product['id']]['total'] += $booking_line['total'];
                $lines_map[$booking_line_group_id][$grouping_code][$product['id']]['qty'] += $booking_line['qty'];
            }
        }
    }
    foreach($lines_map as $booking_line_group_id => $groupings) {
        foreach($groupings as $grouping_code_id => $products) {
            foreach($products as $product_id => $product) {
                if(!isset($lines[$grouping_code_id])) {
                        $lines[$grouping_code_id] = [
                            'name'          => $product['grouping'],
                            'unit_price'    => 0,
                            'vat_rate'      => 0,
                            'free_qty'      => 0,
                            'qty'           => 1,
                            'price'         => 0,
                            'total'         => 0,
                            'is_group'      => false,
                            'is_pack'       => false
                        ];
                }

                $lines[$grouping_code_id]['total'] += $product['total'];
                $lines[$grouping_code_id]['price'] += $product['price'];
                $lines[$grouping_code_id]['unit_price'] += $product['total'];
            }
            /*
            // #memo - we must display all grouping lines, even if the price is 0.0 (to show the customer what is included, even if free)
            if($lines[$grouping_code_id]['price'] == 0.0) {
                unset($lines[$grouping_code_id]);
            }
            */
        }
    }

}

$values['lines'] = $lines;


/*
    compute fare benefit detail
*/
$values['benefit_lines'] = [];

foreach($booking['booking_lines_groups_ids'] as $group) {
    if($group['fare_benefit'] == 0) {
        continue;
    }
    $index = $group['rate_class_id']['description'];
    if(!isset($values['benefit_lines'][$index])) {
        $values['benefit_lines'][$index] = [
            'name'  => $index,
            'value' => $group['fare_benefit']
        ];
    }
    else {
        $values['benefit_lines'][$index]['value'] += $group['fare_benefit'];
    }
}



/*
    retrieve final VAT and group by rate
*/
foreach($lines as $line) {
    $vat_rate = $line['vat_rate'];
    $tax_label = 'TVA '.strval( intval($vat_rate * 100) ).'%';
    $vat = $line['price'] - $line['total'];
    if(!isset($values['tax_lines'][$tax_label])) {
        $values['tax_lines'][$tax_label] = 0;
    }
    $values['tax_lines'][$tax_label] += $vat;
}


// retrieve contact details
foreach($booking['contacts_ids'] as $contact) {
    if(strlen($values['contact_name']) == 0 || $contact['type'] == 'booking') {
        // overwrite data of customer with contact info
        $values['contact_name'] = str_replace(["Dr", "Ms", "Mrs", "Mr", "Pr"], ["Dr", "Melle", "Mme", "Mr", "Pr"], $contact['partner_identity_id']['title'] ?? '') . ' ' . ($contact['partner_identity_id']['display_name'] ?? '');
        $values['contact_phone'] = (strlen($contact['partner_identity_id']['phone'] ?? '')) ? $contact['partner_identity_id']['phone'] : ($contact['partner_identity_id']['mobile'] ?? '');
        $values['contact_email'] = $contact['partner_identity_id']['email'] ?? '';
    }
}

/*
    Generate simple consumptions map
*/

$consumptions_map_simple = [];

$consumptions_simple = Consumption::search([ ['booking_id', '=', $booking['id']], ['type', '=', 'book'] ])
    ->read([
        'id',
        'date',
        'qty',
        'is_meal',
        'rental_unit_id',
        'is_accomodation',
        'time_slot_id' => ['id', 'code'],
        'schedule_to'
    ])
    ->get();

// sort consumptions on dates
usort($consumptions_simple, function ($a, $b) {
    return strcmp($a['date'], $b['date']);
});

foreach($consumptions_simple as $cid => $consumption) {
    // #memo - we only count overnight accommodations (a sojourn always has nb_nights+1 days)
    // ignore accommodation consumptions that do not end at midnight (24:00:00)
    if($consumption['is_accomodation'] && $consumption['schedule_to'] != 86400) {
        continue;
    }

    $date = date('d/m/Y', $consumption['date']).' ('.$days_names[date('w', $consumption['date'])].')';

    if(!isset($consumptions_map_simple[$date])) {
        $consumptions_map_simple[$date] = [];
    }
    if(!isset($consumptions_map_simple['total'])) {
        $consumptions_map_simple['total'] = [];
    }

    if($consumption['is_meal']) {
        $consumption_time_slot_code = $consumption['time_slot_id']['code'];

        if(!isset($consumptions_map_simple[$date][$consumption_time_slot_code])) {
            $consumptions_map_simple[$date][$consumption_time_slot_code] = 0;
        }
        if(!isset($consumptions_map_simple['total'][$consumption_time_slot_code])) {
            $consumptions_map_simple['total'][$consumption_time_slot_code] = 0;
        }
        $consumptions_map_simple[$date][$consumption_time_slot_code] += $consumption['qty'];
        $consumptions_map_simple['total'][$consumption_time_slot_code] += $consumption['qty'];
    }
    elseif($consumption['is_accomodation']) {
        if(!isset($consumptions_map_simple[$date]['night'])) {
            $consumptions_map_simple[$date]['night'] = 0;
        }
        if(!isset($consumptions_map_simple['total']['night'])) {
            $consumptions_map_simple['total']['night'] = 0;
        }
        $consumptions_map_simple[$date]['night'] += $consumption['qty'];
        $consumptions_map_simple['total']['night'] += $consumption['qty'];
    }
}

$values['consumptions_map_simple'] = $consumptions_map_simple;


/*
    Generate detailed consumptions map
*/

$consumptions_map_detailed = [
        'total' => [
            'total_snack'  => 0,
            'total_meals'  => 0,
            'total_nights' => 0
        ]
    ];

$consumptions_detailed = Consumption::search(['booking_id', '=', $booking['id'] ])
    ->read([
        'id',
        'name',
        'date',
        'qty',
        'is_meal',
        'is_snack',
        'rental_unit_id',
        'is_accomodation',
        'type',
        'time_slot_id' => ['id', 'code','name'],
        'schedule_to'
    ])
    ->get();

$time_slots_ids = TimeSlot::search(["code", "<>", "EV"])->read(['id', 'code', 'order'], $params['lang'])->get();

usort($time_slots_ids, function ($a, $b) {
    return $a['order'] <=> $b['order'];
});


// sort consumptions on dates
usort($consumptions_detailed, function ($a, $b) {
    return strcmp($a['date'], $b['date']);
});

foreach($consumptions_detailed as $cid => $consumption) {
    // #memo - we only count overnight accommodations (a sojourn always has nb_nights+1 days)
    // ignore accommodation consumptions that do not end at midnight (24:00:00)
    if($consumption['is_accomodation'] && $consumption['schedule_to'] != 86400) {
        continue;
    }

    $date = date('d/m/Y', $consumption['date']).' ('.$days_names[date('w', $consumption['date'])].')';
    if (!isset($consumptions_map_detailed[$date])) {
        $consumptions_map_detailed[$date] = [
            'total_nights'  => 0,
            'time_slots'    => [],
        ];
        foreach ($time_slots_ids as $time_slot_id) {
            $time_slot = TimeSlot::id($time_slot_id)->read(['name'], $params['lang'])->first(true);
            $time_slot_index = $time_slot['name'];
            $consumptions_map_detailed[$date]['time_slots'][$time_slot_index] = [
                'total_snack'   => 0,
                'total_meals'   => 0,
            ];
        }
    }

    $consumption_time_slot = TimeSlot::search(["code", "=", "AM"])->read(['id'])->first(true);
    $consumption_time_slot_id = $consumption_time_slot['id'];
    if (isset($consumption['time_slot_id'])) {
        if ($consumption['is_accomodation']) {
            $consumption_time_slot = TimeSlot::search(["code", "=", "EV"])->read(['id'])->first(true);
            $consumption_time_slot_id = $consumption_time_slot['id'];
        }
        else {
            $consumption_time_slot_id = $consumption['time_slot_id']['id'];
        }
    }
    $time_slot = TimeSlot::id($consumption_time_slot_id)->read(['name'], $params['lang'])->first(true);
    $time_slot_index = $time_slot['name'];

    if($consumption['is_meal']) {
        $consumptions_map_detailed[$date]['time_slots'][$time_slot_index]['total_meals'] += $consumption['qty'];
        $consumptions_map_detailed['total']['total_meals'] += $consumption['qty'];
    }
    elseif($consumption['is_snack']) {
        $consumptions_map_detailed[$date]['time_slots'][$time_slot_index]['total_snack'] += $consumption['qty'];
        $consumptions_map_detailed['total']['total_snack'] += $consumption['qty'];
    }
    elseif($consumption['is_accomodation'] && $consumption['type'] == 'book') {
        $consumptions_map_detailed[$date]['total_nights'] += $consumption['qty'];
        $consumptions_map_detailed['total']['total_nights'] += $consumption['qty'];
    }
}

$values['consumptions_map_detailed'] = $consumptions_map_detailed;


if($has_activity){
    $activities_map = [];

    $booking_activities = BookingActivity::search(['booking_id', '=', $booking['id'] ])
        ->read([
            'id',
            'name',
            'activity_date',
            'activity_booking_line_id'  => ['id','name', 'product_id' => ['id', 'label']],
            'booking_line_group_id' => ['id', 'name'],
            'time_slot_id' => ['id', 'code','name'],
        ])
        ->get();

    $time_slots_activities_ids = TimeSlot::search(["is_meal", "=", false])->read(['id', 'name','code', 'order'], $params['lang'])->get();

    usort($time_slots_activities_ids, function ($a, $b) {
        return $a['order'] <=> $b['order'];
    });

    usort($booking_activities, function ($a, $b) {
        return $a['booking_line_group_id']['id'] <=> $b['booking_line_group_id']['id']
            ?: $a['activity_date'] <=> $b['activity_date'];
    });

    foreach ($booking_activities as $activity) {
        $group = $activity['booking_line_group_id']['name'];

        if (!isset($activities_map[$group])) {
            $activities_map[$group] = [];
        }

        $date = date('d/m/Y', $activity['activity_date']) . ' (' . $days_names[date('w', $activity['activity_date'])] . ')';
        if (!isset($activities_map[$group][$date])) {
            $activities_map[$group][$date] = [
                'time_slots' => [],
            ];

            foreach ($time_slots_activities_ids as $time_slot) {
                $activities_map[$group][$date]['time_slots'][$time_slot['name']] = [];
            }
        }

        $time_slot_name = $activity['time_slot_id']['name'];
        if (isset($activities_map[$group][$date]['time_slots'][$time_slot_name])) {
            $activities_map[$group][$date]['time_slots'][$time_slot_name][] = $activity['activity_booking_line_id']['product_id']['label'];
        }
    }

    foreach ($activities_map as &$dates) {
        foreach ($dates as &$time_slots) {
            array_walk($time_slots['time_slots'], fn(&$activities) =>
                $activities = $activities ? (count($activities) === 1 ? $activities[0] : implode(', ', $activities)) : null
            );
        }
    }
    unset($dates, $time_slots);

    $values['activities_map'] = $activities_map;
}
/*
    Inject all values into the template
*/

try {
    $loader = new TwigFilesystemLoader(QN_BASEDIR."/packages/{$package}/views/");

    $twig = new TwigEnvironment($loader);
    /**  @var ExtensionInterface **/
    $extension  = new IntlExtension();
    $twig->addExtension($extension);
    // do not rely on system locale (LC_*)
    $filter = new \Twig\TwigFilter('format_money', function ($value) {
        return number_format((float)($value),2,",",".").' €';
    });
    $twig->addFilter($filter);

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
    Convert HTML to PDF
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


// get generated PDF raw binary
$output = $dompdf->output();

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();

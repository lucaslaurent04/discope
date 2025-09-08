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
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use equal\data\DataFormatter;
use sale\booking\Consumption;
use sale\booking\Contract;
use sale\booking\ContractLine;
use sale\booking\TimeSlot;
use SepaQr\Data;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use sale\booking\BookingActivity;
use sale\catalog\Product;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\BookingLineGroupAgeRangeAssignment;
use sale\booking\BookingMeal;
use sale\booking\SojournProductModelRentalUnitAssignement;

list($params, $providers) = announce([
    'description'   => "Render a contract given its ID as a PDF document, for Valrance.",
    'params'        => [
        'id' => [
            'description'   => 'Identifier of the contract to print.',
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

/*
    Retrieve the requested template
*/

$entity = 'valrance\sale\booking\Contract';
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

$connection_languages = [
    ['fr' => 'et', 'en' => 'and', 'nl' => 'en'],
];

$connection_names = array_map(function($item) use ($params) {
    return $item[$params['lang']];
}, $connection_languages);

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

// read contract
$fields = [
    'created',
    'booking_id' => [
        'id', 'name', 'modified', 'date_from', 'date_to','nb_pers', 'time_from', 'time_to', 'price',
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
            'bank_account_bic',
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
                'address_street', 'address_zip', 'address_city',
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
        'fundings_ids' => [
            'description',
            'due_date',
            'is_paid',
            'due_amount',
            'payment_reference',
            'payment_deadline_id' => ['name']
        ],
        'booking_lines_groups_ids' => [
            'name',
            'is_sojourn',
            'group_type',
            'age_range_assignments_ids'    => [
                'qty',
                'age_range_id' => [
                    'name',
                    'qty'
                ]
            ],
            'booking_lines_ids' => [
                'name',
                'description',
                'qty',
                'free_qty',
                'unit_price',
                'price',
                'total',
                'is_activity',
                'is_supply',
                'is_transport',
                'product_id' => [
                    'label',
                    'grouping',
                    'age_range_id',
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
                ],
                'booking_activity_id' => [
                    'activity_booking_line_id' => [
                        'name',
                        'description',
                        'qty',
                        'free_qty',
                        'unit_price',
                        'price',
                        'total',
                        'is_activity',
                        'is_supply',
                        'is_transport',
                        'product_id' => [
                            'label',
                            'grouping',
                            'age_range_id',
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
                    ]
                ],
            ]
        ]
    ],
    'contract_line_groups_ids' => [
        'name',
        'is_pack',
        'fare_benefit',
        'total',
        'price',
        'rate_class_id' => ['id', 'name', 'description'],
        'contract_line_id' => [
            'name',
            'qty',
            'unit_price',
            'discount',
            'free_qty',
            'vat_rate',
            'total',
            'price'
        ],
        'contract_lines_ids' => [
            'name',
            'description',
            'qty',
            'unit_price',
            'discount',
            'free_qty',
            'vat_rate',
            'total',
            'price',
            'product_id' => [
                'id',
                'label' ,
                'age_range_id',
                'grouping_code_id' => ['id', 'name', 'code', 'has_age_range'],
                'product_model_id' => ['id', 'name', 'grouping_code_id' => ['id', 'code', 'name', 'has_age_range']]
            ],
        ]
    ],
    'price',
    'total'
];


$contract = Contract::id($params['id'])->read($fields, $params['lang'])->first(true);

if(!$contract) {
    throw new Exception("unknown_contract", QN_ERROR_UNKNOWN_OBJECT);
}


/*
    extract required data and compose the $value map for the twig template
*/

$booking = $contract['booking_id'];


if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$logo_document_data = $booking['center_id']['organisation_id']['logo_document_id']['data'] ?? null;
if($logo_document_data) {
    $content_type = $booking['center_id']['organisation_id']['logo_document_id']['type'] ?? 'image/png';
    $img_url = "data:{$content_type};base64, ".base64_encode($logo_document_data);
}

$member_name = $lodgingBookingPrintBookingFormatMember($booking);

$center_office_code = (isset( $booking['center_id']['center_office_id']['code']) && $booking['center_id']['center_office_id']['code'] == 1) ? 'GG' : 'GA';

$postal_address = sprintf("%s - %s %s", $booking['center_id']['organisation_id']['address_street'], $booking['center_id']['organisation_id']['address_zip'], $booking['center_id']['organisation_id']['address_city']);
$customer_name = substr($booking['customer_id']['partner_identity_id']['display_name'], 0,  65);
$customer_address = $booking['customer_id']['partner_identity_id']['address_street'] .' '. $booking['customer_id']['partner_identity_id']['address_zip'].' '.$booking['customer_id']['partner_identity_id']['address_city'];

// #memo - client has request no to show activites on booking/contract but always use disctint doc (print-booking-activity)
// $has_activity = Setting::get_value('sale', 'features', 'booking.activity', true);
$has_activity = false;

$consumption_table_show  = Setting::get_value('sale', 'features', 'templates.quote.consumption_table', 1);



// retrieve transport

// transport to and from the group to the accommodation
$setting_transport = Setting::get_value('sale', 'organization', 'sku.transport', 'not_found');

$product_transport = Product::search(['sku', '=', $setting_transport])
    ->read(['id' , 'product_model_id'])
    ->first(true);

$transport = BookingLine::search([
        ['booking_id', '=', $booking['id']],
        ['product_model_id', '=', $product_transport['product_model_id']]
    ])
    ->read(['product_model_id' => ['id', 'name']])
    ->first(true);


$has_roundtrip_transport = (bool) $transport;


// travel throughout the stay for external activities
// #todo - put this in settings
$product_transport = Product::search(['name', 'ilike', 'Transport externe%'])
    ->read(['id' , 'product_model_id'])
    ->first(true);

$transport = BookingLine::search([
        ['booking_id', '=', $booking['id']],
        ['product_model_id', '=', $product_transport['product_model_id']]
    ])
    ->read(['product_model_id' => ['id', 'name']])
    ->first(true);


$has_activities_transport = (bool) $transport;


$values = [
    'attn_address1'                 => '',
    'attn_address2'                 => '',
    'attn_name'                     => '',
    'benefit_lines'                 => [],
    'benefit_freebies'              => [],
    'center'                        => $booking['center_id']['name'],
    'center_address'                => $booking['center_id']['address_street'].' - '.$booking['center_id']['address_zip'].' '.$booking['center_id']['address_city'],
    'center_contact1'               => (isset($booking['center_id']['manager_id']['name']))?$booking['center_id']['manager_id']['name']:'',
    'center_contact2'               => DataFormatter::format($booking['center_id']['phone'], 'phone').' - '.$booking['center_id']['email'],
    'center_email'                  => $booking['center_id']['email'],
    'center_office'                 => $center_office_code,
    'center_phone'                  => DataFormatter::format($booking['center_id']['phone'], 'phone'),
    'center_signature'              => $booking['center_id']['organisation_id']['signature'],
    'code'                          => sprintf("%03d.%03d", intval($booking['name']) / 1000, intval($booking['name']) % 1000),
    'company_address'               => sprintf("%s %s %s", $booking['center_id']['organisation_id']['address_street'], $booking['center_id']['organisation_id']['address_zip'], $booking['center_id']['organisation_id']['address_city']),
    'company_bic'                   => DataFormatter::format($booking['center_id']['organisation_id']['bank_account_bic'], 'bic'),
    'company_email'                 => $booking['center_id']['organisation_id']['email'],
    'company_fax'                   => DataFormatter::format($booking['center_id']['organisation_id']['fax'], 'phone'),
    'company_has_vat'               => $booking['center_id']['organisation_id']['has_vat'],
    'company_iban'                  => DataFormatter::format($booking['center_id']['organisation_id']['bank_account_iban'], 'iban'),
    'company_name'                  => $booking['center_id']['organisation_id']['legal_name'],
    'company_phone'                 => DataFormatter::format($booking['center_id']['organisation_id']['phone'], 'phone'),
    'company_reg_number'            => $booking['center_id']['organisation_id']['registration_number'],
    'company_vat_number'            => $booking['center_id']['organisation_id']['vat_number'],
    'company_website'               => $booking['center_id']['organisation_id']['website'],
    'contact_email'                 => $booking['customer_id']['partner_identity_id']['email'],
    'contact_name'                  => '',
    'contact_phone'                 => (strlen($booking['customer_id']['partner_identity_id']['phone']))?$booking['customer_id']['partner_identity_id']['phone']:$booking['customer_id']['partner_identity_id']['mobile'],
    'consumptions_map_detailed'     => [],
    'consumptions_map_simple'       => [],
    'consumptions_type'             => isset($booking['type_id']['booking_schedule_layout'])?$booking['type_id']['booking_schedule_layout']:'simple',
    'contract_authorization_html'   => '',
    'contract_header_html'          => '',
    'contract_service_html'         => '',
    'contract_engage_html'          => '',
    'contract_notice_html'          => '',
    'contract_payment_html'         => '',
    'contract_withdrawal_html'      => '',
    'contract_cancellation_html'    => '',
    'contract_misc_provisions_html' => '',
    'customer_address1'             => $booking['customer_id']['partner_identity_id']['address_street'],
    'customer_address2'             => $booking['customer_id']['partner_identity_id']['address_zip'].' '.$booking['customer_id']['partner_identity_id']['address_city'].(($booking['customer_id']['partner_identity_id']['address_country'] != 'BE')?(' - '.$booking['customer_id']['partner_identity_id']['address_country']):''),
    'customer_address_dispatch'     => $booking['customer_id']['partner_identity_id']['address_dispatch'],
    'customer_country'              => $booking['customer_id']['partner_identity_id']['address_country'],
    'customer_has_vat'              => (int) $booking['customer_id']['partner_identity_id']['has_vat'],
    'customer_name'                 => $customer_name,
    'customer_vat'                  => $booking['customer_id']['partner_identity_id']['vat_number'],
    'date'                          => date('d/m/Y', $contract['created']),
    'fundings'                      => [],
    'has_contract_approved'         => 0,
    'has_roundtrip_transport'       => $has_roundtrip_transport,
    'has_activities_transport'      => $has_activities_transport,
    'header_img_url'                => $img_url ?? 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=',
    'installment_amount'            => 0,
    'installment_date'              => '',
    'installment_qr_url'            => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=',
    'installment_reference'         => '',
    'lines'                         => [],
    'member'                        => substr($member_name, 0, 33) . ((strlen($member_name) > 33) ? '...' : ''),
    'period'                        => date('d/m/Y', $booking['date_from']).' '.date('H:i', $booking['time_from']).' - '.date('d/m/Y', $booking['date_to']).' '.date('H:i', $booking['time_to']),
    'postal_address'                => $postal_address,
    'price'                         => $contract['price'],
    'tax_lines'                     => [],
    'total'                         => $contract['total'],
    'has_activity'                  => $has_activity,
    'activities_map'                => '',
    'show_consumption'              => $consumption_table_show
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
    retrieve contact for booking
*/
foreach($booking['contacts_ids'] as $contact) {
    if(strlen($values['contact_name']) == 0 || $contact['type'] == 'booking') {
        // overwrite data of customer with contact info

        $contact_name = str_replace(["Dr", "Ms", "Mrs", "Mr", "Pr"], ["Dr", "Melle", "Mme", "Mr", "Pr"], $contact['partner_identity_id']['title']) . ' ' . $contact['partner_identity_id']['display_name'];
        $values['contact_name'] =  $contact_name;
        $values['contact_phone'] = (strlen($contact['partner_identity_id']['phone']))?$contact['partner_identity_id']['phone']:$contact['partner_identity_id']['mobile'];
        $values['contact_email'] = $contact['partner_identity_id']['email'];
    }
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

$hasFooter = false;


/*
    retrieve specific options
*/
$booking_options = [];

// retrieve bed linens & make beds
$booking_options['has_bed_linens'] = false;
$booking_options['has_make_beds'] = false;

$bookingLineGroups = BookingLineGroup::search([
        ['booking_id', '=', $booking['id']],
        ['bed_linens', '=', true]
    ])
    ->read(['bed_linens', 'make_beds']);

foreach($bookingLineGroups as $booking_line_group_id => $bookingLineGroup) {
    if($bookingLineGroup['bed_linens']) {
        $booking_options['has_bed_linens'] = true;
    }
    if($bookingLineGroup['make_beds']) {
        $booking_options['has_make_beds'] = true;
    }
}

// retrieve number of rooms
$booking_options['nb_rooms'] = 0;

$spmAssignments = SojournProductModelRentalUnitAssignement::search([
        ['booking_id', '=', $booking['id']],
        ['is_accomodation', '=', true]
    ])
    ->read(['rental_unit_id' => ['has_children', 'children_ids']]);

foreach($spmAssignments as $spmAssignment) {
    if($spmAssignment['rental_unit_id']['has_children']) {
        $booking_options['nb_rooms'] += count($spmAssignment['rental_unit_id']['children_ids']);
    }
    else {
        ++$booking_options['nb_rooms'];
    }
}

// compute insurance amount
$booking_options['insurance_amount'] = round(3.5 * $contract['price'] / 100, 2);


/*
    retrieve templates
*/
if($booking['center_id']['template_category_id']) {

    $template = Template::search([
            ['category_id', '=', $booking['center_id']['template_category_id']],
            ['code', '=', 'contract'],
            ['type', '=', 'contract']
        ])
        ->read(['id','parts_ids' => ['name', 'value']], $params['lang'])
        ->first(true);

    foreach($template['parts_ids'] as $part_id => $part) {
        $value = $part['value'];

        $value = str_replace('{center}', $booking['center_id']['name'], $value);
        $value = str_replace('{address}', $postal_address, $value);
        $value = str_replace('{customer}', $customer_name, $value);
        $value = str_replace('{customer_address}', $customer_address, $value);
        $value = str_replace('{contact}', $contact_name, $value);
        $value = str_replace('{price}', $booking['price'], $value);

        if($part['name'] == 'header') {
            $value = str_replace('{date_from}', date('d/m/Y', $booking['date_from']), $value);
            $value = str_replace('{date_to}', date('d/m/Y', $booking['date_to']), $value);

            $values['contract_header_html'] = $value;
        }
        // services provided by the center Valrance
        elseif($part['name'] == 'service') {

            if(!$booking_options['has_bed_linens']) {
                $service_beds = Setting::get_value('lodging', 'locale', 'i18n.not_bed_linens', null, [], $params['lang']);
            }
            else {
                if($booking_options['has_make_beds']) {
                    $service_beds = Setting::get_value('lodging', 'locale', 'i18n.make_beds', null, [], $params['lang']);
                }
                else {
                    $service_beds = Setting::get_value('lodging', 'locale', 'i18n.bed_linens', null, [], $params['lang']);
                }
            }

            $service_transport_roundtrip = ($has_roundtrip_transport) ? "assurer la prise en charge du transport aller et le retour du groupe" : '';
            $service_transport_activities = ($has_activities_transport) ? "assurer les éventuels déplacements tout au long du séjour" : '';

            $value = str_replace('{beds_service}', $service_beds, $value);
            $value = str_replace('{transport_service}', $service_transport_roundtrip, $value);
            $value = str_replace('{transport_activity}', $service_transport_activities, $value);
            $value = str_replace('{nb_rooms}', $booking_options['nb_rooms'], $value);

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
            elseif($has_lunch) {
                $date_from_text .= 'pour le déjeuner';
            }
            elseif($has_snack) {
                $date_from_text .= 'pour le goûter';
            }
            elseif($has_diner) {
                $date_from_text .= 'pour le dîner';
            }
            else {
                $date_from_text .= 'pour la nuitée';
            }

            if($is_lunch_picnic) {
                if(strlen($date_from_text)) {
                    $date_from_text .= ', ';
                }
                if($has_lunch) {
                    if($has_snack) {
                        $date_from_text .= 'avec pique-nique et goûter fournis par le Relais Valrance';
                    }
                    else {
                        $date_from_text .= 'avec pique-nique fourni par le Relais Valrance';
                    }
                }
                else {
                    if($has_snack) {
                        $date_from_text .= 'avec pique-nique amenés par vos soins et goûter fourni par le Relais Valrance';
                    }
                    else {
                        $date_from_text .= 'avec pique-nique et goûter amenés par vos soins';
                    }
                }
            }

            if(strlen($date_from_text)) {
                $date_from .= ' (' . $date_from_text . ')';
            }


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

            $value = str_replace('{date_from}', $date_from, $value);
            $value = str_replace('{date_to}', $date_to, $value);

            $text_pers = $lodgingBookingPrintAgeRangesText($booking, $connection_names);
            $value = str_replace('{nb_pers}', $text_pers, $value);

            if($booking['customer_id']['rate_class_id']) {
                $part_name = 'service_'. $booking['customer_id']['rate_class_id']['name'];
                $template_part = TemplatePart::search([['name', '=', $part_name], ['template_id', '=', $template['id']] ])
                        ->read(['value'], $params['lang'])
                        ->first(true);

                if($template_part){
                    $value .= $template_part['value'];
                }
            }

            // remove empty list items, if any
            $value = preg_replace('/<li>\s*<\/li>/i', '', $value);

            $values['contract_service_html'] = $value;
        }
        // engagements the customer pledges to comply with
        elseif($part['name'] == 'engage') {
            if($booking['customer_id']['rate_class_id']) {
                $part_name = 'engage_'. $booking['customer_id']['rate_class_id']['name'];
                $template_part = TemplatePart::search([['name', '=', $part_name], ['template_id', '=', $template['id']] ])
                        ->read(['value'], $params['lang'])
                        ->first(true);

                if($template_part) {
                    $value .= $template_part['value'];
                }
            }

            $service_transport_roundtrip = ($has_roundtrip_transport) ? '' : "assurer la prise en charge du transport aller et le retour du groupe";
            $service_transport_activities = ($has_activities_transport) ? '' : "assurer les éventuels déplacements tout au long du séjour";

            $value = str_replace('{transport_service}', $service_transport_roundtrip, $value);
            $value = str_replace('{transport_activity}', $service_transport_activities, $value);

            // remove empty list items, if any
            $value = preg_replace('/<li>\s*<\/li>/i', '', $value);

            $values['contract_engage_html'] = $value;
        }
        elseif($part['name'] == 'notice') {
            if(in_array($booking['customer_id']['rate_class_id']['code'] ?? '', ['220', '230', '240'])) {
                $values['contract_notice_html'] = $value;
            }
        }
        elseif($part['name'] == 'payment') {
            $value = str_replace('{table_fundings}', '', $value);
            $values['contract_payment_html'] = $value;
        }
        elseif($part['name'] == 'withdrawal') {
            $values['contract_withdrawal_html'] = $value;
        }
        elseif($part['name'] == 'cancellation') {
            $value = str_replace('{insurance_amount}',  number_format($booking_options['insurance_amount'], 2, ',', ''), $value);
            $values['contract_cancellation_html'] = $value;
        }
        elseif($part['name'] == 'contract_approved') {
            $values['has_contract_approved'] = 1;
            $has_contract_approved = true;

            $values['contract_approved_html'] = $part['value'] . $values['center_signature'];
        }
        elseif($part['name'] == 'contract_authorization') {
            $values['contract_authorization_html'] = $value;
        }
        elseif($part['name'] == 'contract_misc_provisions') {
            $values['contract_misc_provisions_html'] = $value;
        }
    }
}

if(!$has_contract_approved) {
    $values['contract_header_html'] .= $values['center_signature'];
}

// fetch template parts that are common to all offices

$template_part = TemplatePart::search(['name', '=', 'advantage_notice'])
    ->read(['value'], $params['lang'])
    ->first(true);

if($template_part) {
    $values['advantage_notice_html'] = $template_part['value'];
}

$template_part = TemplatePart::search(['name', '=', 'contract_agreement'])
    ->read(['value'], $params['lang'])
    ->first(true);

if($template_part) {
    $values['contract_agreement_html'] = $template_part['value'];
}

/*
    feed lines
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

    if(is_null($grouping_code)) {
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
    $product_id = $line['product_id']['id'];

    if(($map_products_groupings[$product_id]['code'] ?? '') === 'invisible' && $line['price'] <= 0.01) {
        continue;
    }

    $grouping_name = $line['product_id']['label'];
    if(isset($map_products_groupings[$product_id])) {
        $grouping_name = $map_products_groupings[$product_id]['name'];
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
    switch($params['mode']) {
        case 'grouped':
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
            break;
        case 'detailed':
            $lines[] = [
                'name'          => $grouping_name,
                'qty'           => null,
                'free_qty'      => null,
                'unit_price'    => null,
                'vat_rate'      => null,
                'total'         => null,
                'price'         => null,
                'is_group'      => true
            ];

            foreach($grouping_lines as $line) {
                $lines[] = [
                    'name'          => $line['name'],
                    'qty'           => $line['qty'],
                    'free_qty'      => $line['free_qty'],
                    'unit_price'    => $line['unit_price'],
                    'vat_rate'      => $line['vat_rate'],
                    'total'         => $line['total'],
                    'price'         => $line['price'],
                    'is_group'      => false
                ];
            }
            break;
    }
}

$values['lines'] = $lines;

/*
    compute fare benefit detail
*/
$values['benefit_lines'] = [];

foreach($contract['contract_line_groups_ids'] as $group) {
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

$values['benefit_freebies'] = [];

foreach($booking['booking_lines_groups_ids'] as $group) {
    if($group['group_type'] !== 'sojourn') {
        continue;
    }

    $assignments = BookingLineGroupAgeRangeAssignment::search(['booking_line_group_id', '=', $group['id']])
    ->read(['free_qty', 'age_range_id' => ['name']]);

    foreach($assignments as $assignment) {
        if($assignment['free_qty'] > 0) {
            $values['benefit_freebies'][] = [
                'name'  => 'Gratuités ' . $assignment['age_range_id']['name'] . ' - ' . $group['name'],
                'value' => $assignment['free_qty']
            ];
        }
    }
}


/*
    retrieve final VAT and group by rate
*/
foreach($lines as $line) {
    $vat_rate = $line['vat_rate'];
    $tax_label = $values['i18n']['vat'].' '.strval( intval($vat_rate * 100) ).'%';
    $vat = $line['price'] - $line['total'];
    if(!isset($values['tax_lines'][$tax_label])) {
        $values['tax_lines'][$tax_label] = 0;
    }
    $values['tax_lines'][$tax_label] += $vat;
}



/*
    inject expected fundings and find the first installment
*/
$installment_date = PHP_INT_MAX;
$installment_amount = 0;
$installment_ref = '';

$reference_type = Setting::get_value('sale', 'organization', 'booking.reference.type', 'VCS');

foreach($booking['fundings_ids'] as $funding) {

    if($funding['due_date'] < $installment_date && !$funding['is_paid']) {
        $installment_date = $funding['due_date'];
        $installment_ref = $funding['payment_reference'];
        $installment_amount = $funding['due_amount'];
    }
    $line = [
        'name'          => (strlen($funding['payment_deadline_id']['name'] ?? '')) ? $funding['payment_deadline_id']['name'] : $funding['description'],
        'due_date'      => date('d/m/Y', $funding['due_date']),
        'due_amount'    => $funding['due_amount'],
        'is_paid'       => $funding['is_paid'],
        'reference'     => DataFormatter::format($funding['payment_reference'], $reference_type)
    ];
    $values['fundings'][] = $line;
}


if($installment_date == PHP_INT_MAX) {
    // no funding found : the final invoice will be release and generate a funding
    // qr code is not generated
}
else if($installment_amount > 0) {
    $values['installment_date'] = date('d/m/Y', $installment_date);
    $values['installment_amount'] = (float) $installment_amount;
    $values['installment_reference'] = DataFormatter::format($installment_ref, $reference_type);

    // generate a QR code
    try {
        $paymentData = Data::create()
            ->setServiceTag('BCD')
            ->setIdentification('SCT')
            ->setName($values['company_name'])
            ->setIban(str_replace(' ', '', $booking['center_id']['bank_account_iban']))
            ->setBic(str_replace(' ', '', $booking['center_id']['bank_account_bic']))
            ->setRemittanceReference($values['installment_reference'])
            ->setAmount($values['installment_amount']);

        $result = Builder::create()
            ->data($paymentData)
            ->errorCorrectionLevel(new ErrorCorrectionLevelMedium()) // required by EPC standard
            ->build();

        $dataUri = $result->getDataUri();
        $values['installment_qr_url'] = $dataUri;

    }
    catch(Exception $exception) {
        // unknown error
    }
}


/*
    Generate consumptions map simple
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
    Generate consumptions map detailed
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
    if(!isset($consumptions_map_detailed[$date])) {
        $consumptions_map_detailed[$date] = [
            'total_nights'  => 0,
            'time_slots'    => [],
        ];
        foreach($time_slots_ids as $time_slot_id) {
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
    if(isset($consumption['time_slot_id'])) {
        if($consumption['is_accomodation']) {
            $consumption_time_slot = TimeSlot::search(["code", "=", "EV"])->read(['id'])->first(true);
            $consumption_time_slot_id = $consumption_time_slot['id'];
        } else {
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

    foreach($booking_activities as $activity) {
        $group = $activity['booking_line_group_id']['name'];

        if(!isset($activities_map[$group])) {
            $activities_map[$group] = [];
        }

        $date = date('d/m/Y', $activity['activity_date']) . ' (' . $days_names[date('w', $activity['activity_date'])] . ')';
        if(!isset($activities_map[$group][$date])) {
            $activities_map[$group][$date] = [
                'time_slots' => [],
            ];

            foreach($time_slots_activities_ids as $time_slot) {
                $activities_map[$group][$date]['time_slots'][$time_slot['name']] = [];
            }
        }

        $time_slot_name = $activity['time_slot_id']['name'];
        if(isset($activities_map[$group][$date]['time_slots'][$time_slot_name])) {
            $activities_map[$group][$date]['time_slots'][$time_slot_name][] = $activity['activity_booking_line_id']['product_id']['label'];
        }
    }

    foreach($activities_map as &$dates) {
        foreach($dates as &$time_slots) {
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
    $currency = Setting::get_value('core', 'locale', 'currency', '€');
    // do not rely on system locale (LC_*)
    $filter = new \Twig\TwigFilter('format_money', function ($value) use($currency) {
        return number_format((float)($value), 2, ",", ".") . ' ' .$currency;
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




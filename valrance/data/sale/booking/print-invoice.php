<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use sale\booking\InvoiceLine;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;

use SepaQr\Data;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;

use sale\booking\Invoice;
use communication\TemplatePart;
use equal\data\DataFormatter;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => "Render an invoice given its ID as a PDF document, for Valrance.",
    'params'        => [

        'id' => [
            'description'   => 'Identifier of the invoice to print.',
            'type'          => 'integer',
            'required'      => true
        ],

        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.default'
        ],

        'mode' =>  [
            'description'   => 'Mode in which document has to be rendered: simple (default) or detailed.',
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
    'constants'     => ['DEFAULT_LANG', 'L10N_LOCALE'],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/pdf',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

/*
    1) retrieve the requested template
*/

$entity = 'valrance\sale\booking\Invoice';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = EQ_BASEDIR."/packages/$package/views/$class_path.{$params['view_id']}.html";
if(!file_exists($file)) {
    throw new Exception("unknown_view_id", QN_ERROR_UNKNOWN_OBJECT);
}

$fields = [
    'name',
    'created',
    'date',
    'notice_html',
    'partner_id' => [
        'partner_identity_id' => [
            'id',
            'display_name',
            'legal_name',
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
        'signature',
        'bank_account_iban',
        'bank_account_bic',
        'logo_document_id' => ['id', 'type', 'data']
    ],
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
    'booking_id' => [
        'name',
        'modified',
        'date_from',
        'date_to',
        'price',
        'customer_identity_id' => [
            'id',
            'display_name',
            'legal_name',
            'address_street', 'address_dispatch', 'address_city', 'address_zip', 'address_country',
            'phone',
            'mobile',
            'email'
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
            'use_office_details'
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
            'nb_pers',
            'group_type',
            'is_sojourn',
            'age_range_assignments_ids'    => [
                'qty',
                'free_qty',
                'age_range_id'
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
    'invoice_lines_ids' => [
        'id',
        'name',
        'description',
        'product_id',
        'downpayment_invoice_id' => ['status'],
        'qty',
        'unit_price',
        'discount',
        'free_qty',
        'vat_rate',
        'total',
        'price'
    ],
    'invoice_line_groups_ids' => [
        'name',
        'invoice_lines_ids' => [
            'id',
            'name',
            'description',
            'product_id',
            'downpayment_invoice_id' => ['status'],
            'qty',
            'unit_price',
            'discount',
            'free_qty',
            'vat_rate',
            'total',
            'price'
        ]
    ],
    'fundings_ids' => [
        'id',
        'modified',
        'due_amount',
        'paid_amount',
        'due_date',
        'description',
        'type',
        'invoice_id',
        'payments_ids' => [
            'receipt_date',
            'amount',
            'payment_origin'
        ]
    ],
    'funding_id' => ['id', 'payment_reference', 'due_date'],
    'is_deposit',
    'status',
    'customer_ref',
    'type',
    'is_paid',
    'due_date',
    'total',
    'price',
    'accounting_total',
    'accounting_price',
    'payment_reference',
    'has_orders'
];

$invoice = Invoice::id($params['id'])
    ->read($fields, $params['lang'])
    ->first(true);

if(is_null($invoice)) {
    throw new Exception("unknown_invoice", QN_ERROR_UNKNOWN_OBJECT);
}

/*
    2) extract required data and compose the $value map for the twig template
*/

$booking = $invoice['booking_id'];
if(is_null($booking)) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

// nb_pers are used to inject in GroupingCode name
$nb_pers = 0;
$map_age_range_nb_pers = [];
foreach($booking['booking_lines_groups_ids'] as $group) {
    if($group['group_type'] !== 'sojourn') {
        continue;
    }

    $nb_pers = $group['nb_pers'];
    foreach($group['age_range_assignments_ids'] as $assignment) {
        if(!isset($map_age_range_nb_pers[$assignment['age_range_id']])) {
            $map_age_range_nb_pers[$assignment['age_range_id']] = 0;
        }

        $map_age_range_nb_pers[$assignment['age_range_id']] += $assignment['qty'] - $assignment['free_qty'];
    }
}

$logo_document_data = $invoice['organisation_id']['logo_document_id']['data'] ?? null;
if($logo_document_data) {
    $content_type = $booking['center_id']['organisation_id']['logo_document_id']['type'] ?? 'image/png';
    $img_url = "data:{$content_type};base64, ".base64_encode($logo_document_data);
}

$values = [
    'header_img_url'            => $img_url ?? 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=',
    'signature'                 => $invoice['organisation_id']['signature'] ?? '',
    'invoice_header_html'       => '',
    'invoice_notice_html'       => $invoice['notice_html'],

    // by default, there is no ATTN - if required, it is set below
    'attn_name'                 => '',
    'attn_address1'             => '',
    'attn_address2'             => '',

    'contact_name'              => '',
    'contact_phone'             => (strlen($invoice['partner_id']['partner_identity_id']['phone']))?$invoice['partner_id']['partner_identity_id']['phone']:$invoice['partner_id']['partner_identity_id']['mobile'],
    'contact_email'             => $invoice['partner_id']['partner_identity_id']['email'],
    'has_orders'                => (int) $invoice['has_orders'],
    'customer_id'               => $invoice['partner_id']['partner_identity_id']['id'],
    'customer_name'             => substr($invoice['partner_id']['partner_identity_id']['legal_name'], 0, 66),
    'customer_address1'         => '',
    'customer_address_dispatch' => '',
    'customer_address2'         => '',
    'customer_country'          => (isset($invoice['partner_id']['partner_identity_id']['address_country']))?$invoice['partner_id']['partner_identity_id']['address_country']:'',
    'customer_has_vat'          => (int) $invoice['partner_id']['partner_identity_id']['has_vat'],
    'customer_vat'              => $invoice['partner_id']['partner_identity_id']['vat_number'],
    'customer_ref'              => substr($invoice['customer_ref'], 0, 115),

    'date'                      => date('d/m/Y', $invoice['date']),
    'code'                      => $invoice['name'],
    'is_paid'                   => $invoice['is_paid'],
    'status'                    => $invoice['status'],
    'type'                      => $invoice['type'],
    'booking_code'              => sprintf("%03d.%03d", intval($booking['name']) / 1000, intval($booking['name']) % 1000),
    'center'                    => $booking['center_id']['name'],
    'center_address'            => $booking['center_id']['address_street'].' - '.$booking['center_id']['address_zip'].' '.$booking['center_id']['address_city'],
    'postal_address'            => sprintf("%s - %s %s", $invoice['organisation_id']['address_street'], $invoice['organisation_id']['address_zip'], $invoice['organisation_id']['address_city']),
    'center_contact1'           => (isset($booking['center_id']['manager_id']['name']))?$booking['center_id']['manager_id']['name']:'',
    'center_contact2'           => DataFormatter::format($booking['center_id']['phone'], 'phone').' - '.$booking['center_id']['email'],

    // by default, we use center contact details (overridden in case Center has a management Office, see below)
    'center_phone'              => DataFormatter::format($booking['center_id']['phone'], 'phone'),
    'center_email'              => $booking['center_id']['email'],
    'center_signature'          => $invoice['organisation_id']['signature'],

    'period'                    => date('d/m/Y', $booking['date_from']).' - '.date('d/m/Y', $booking['date_to']),
    'price'                     => round($invoice['accounting_price'], 2),
    'total'                     => round($invoice['accounting_total'], 2),

    'company_name'              => $invoice['organisation_id']['legal_name'],
    'company_address'           => sprintf("%s %s %s", $invoice['organisation_id']['address_street'], $invoice['organisation_id']['address_zip'], $invoice['organisation_id']['address_city']),
    'company_email'             => $invoice['organisation_id']['email'],
    'company_phone'             => DataFormatter::format($invoice['organisation_id']['phone'], 'phone'),
    'company_fax'               => DataFormatter::format($invoice['organisation_id']['fax'], 'phone'),
    'company_website'           => $invoice['organisation_id']['website'],
    'company_reg_number'        => $invoice['organisation_id']['registration_number'],
    'company_has_vat'           => $invoice['organisation_id']['has_vat'],
    'company_vat_number'        => $invoice['organisation_id']['vat_number'],


    // by default, we use organisation payment details (overridden in case Center has a management Office, see below)
    'company_iban'              => DataFormatter::format($invoice['organisation_id']['bank_account_iban'], 'iban'),
    'company_bic'               => DataFormatter::format($invoice['organisation_id']['bank_account_bic'], 'bic'),

    'payment_deadline'          => '',
    'payment_reference'         => '',
    'payment_qr_uri'            => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=',

    'lines'                     => [],
    'tax_lines'                 => [],

    'fundings_ids'              => [],
    'funding_id'                => $invoice['funding_id']
];

// handle address output : #memo - users sometimes enter an invalid single char to avoid leaving the field empty
if(isset($invoice['partner_id']['partner_identity_id']['address_street']) && strlen($invoice['partner_id']['partner_identity_id']['address_street']) > 1) {
    $values['customer_address1'] = $invoice['partner_id']['partner_identity_id']['address_street'];
}

if(isset($invoice['partner_id']['partner_identity_id']['address_dispatch']) && strlen($invoice['partner_id']['partner_identity_id']['address_dispatch']) > 1) {
    $values['customer_address_dispatch'] = $invoice['partner_id']['partner_identity_id']['address_dispatch'];
}

$address_2 = '';

if(isset($invoice['partner_id']['partner_identity_id']['address_zip']) && strlen($invoice['partner_id']['partner_identity_id']['address_zip']) > 1) {
    $address_2 = $invoice['partner_id']['partner_identity_id']['address_zip'];
}
if(isset($invoice['partner_id']['partner_identity_id']['address_city']) && strlen($invoice['partner_id']['partner_identity_id']['address_city']) > 1) {
    if(strlen($address_2) > 0) {
        $address_2 .= ' ';
    }
    $address_2 .= $invoice['partner_id']['partner_identity_id']['address_city'];
}
if(isset($invoice['partner_id']['partner_identity_id']['address_country']) && strlen($invoice['partner_id']['partner_identity_id']['address_country']) > 0 && $invoice['partner_id']['partner_identity_id']['address_country'] != 'BE') {
    if(strlen($address_2) > 0) {
        $address_2 .= ' - ';
    }
    $address_2 .= $invoice['partner_id']['partner_identity_id']['address_country'];
}

$values['customer_address2'] = $address_2;


// invert sign for credit notes
if($invoice['type'] == 'credit_note') {
    $values['price'] = -$values['price'];
    $values['total'] = -$values['total'];
}

/*
    3) retrieve terms translations
*/
$values['i18n'] = [
    'invoice'               => Setting::get_value('lodging', 'locale', 'i18n.invoice', null, [], $params['lang']),
    'quote'                 => Setting::get_value('lodging', 'locale', 'i18n.quote', null, [], $params['lang']),
    'option'                => Setting::get_value('lodging', 'locale', 'i18n.option', null, [], $params['lang']),
    'contract'              => Setting::get_value('lodging', 'locale', 'i18n.contract', null, [], $params['lang']),
    'customer_name'         => Setting::get_value('lodging', 'locale', 'i18n.customer_name', null, [], $params['lang']),
    'customer_address'      => Setting::get_value('lodging', 'locale', 'i18n.customer_address', null, [], $params['lang']),
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
    'customer_num'          => Setting::get_value('lodging', 'locale', 'i18n.customer_num', null, [], $params['lang']),
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
    'amount_to_be_refunded' => Setting::get_value('lodging', 'locale', 'i18n.amount_to_be_refunded', null, [], $params['lang']),
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
    'origin'                => Setting::get_value('lodging', 'locale', 'i18n.origin', null, [], $params['lang']),
    'received'              => Setting::get_value('lodging', 'locale', 'i18n.received', null, [], $params['lang']),
    'left_to_pay'           => Setting::get_value('lodging', 'locale', 'i18n.left_to_pay', null, [], $params['lang']),
    'payments_history'      => Setting::get_value('lodging', 'locale', 'i18n.payments_history', null, [], $params['lang']),
];

/**
 * Add info for ATTN, if required.
 * If the invoice is emitted to a partner distinct from the booking customer, the latter is ATTN and the former is considered as the customer.
 */

if($invoice['partner_id']['partner_identity_id']['id'] != $booking['customer_identity_id']['id']) {
    $values['attn_name'] = substr($booking['customer_identity_id']['legal_name'], 0, 33);
    $values['attn_address1'] = $booking['customer_identity_id']['address_street'];
    $values['attn_address2'] = $booking['customer_identity_id']['address_zip'].' '.$booking['customer_identity_id']['address_city'].(($booking['customer_identity_id']['address_country'] != 'BE')?(' - '.$booking['customer_identity_id']['address_country']):'');
}

/*
    4) override contact and payment details with center's office, if set
*/
if($booking['center_id']['use_office_details']) {
    $office = $invoice['center_office_id'];
    $values['company_iban'] = DataFormatter::format($office['bank_account_iban'], 'iban');
    $values['company_bic'] = DataFormatter::format($office['bank_account_bic'], 'bic');
    $values['center_phone'] = DataFormatter::format($office['phone'], 'phone');
    $values['center_email'] = $office['email'];
    $values['center_signature'] = $office['signature'];
    $values['postal_address'] = $office['address_street'].' - '.$office['address_zip'].' '.$office['address_city'];
}

/*
    5) retrieve templates
*/
$template_part = TemplatePart::search(['name', '=', 'proforma_notice'])
    ->read(['value'], $params['lang'])
    ->first(true);

if($template_part) {
    $values['invoice_proforma_notice_html'] = $template_part['value'];
}

/*
    6) feed lines
*/

$invoice_lines = InvoiceLine::search(['invoice_id', '=', $invoice['id']])
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
foreach($invoice_lines as $line) {
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

// #memo - we don't have links to booking lines here (nor booking activities)
$invoice_lines = InvoiceLine::search(['invoice_id', '=', $invoice['id']])
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
foreach($invoice_lines as $line) {
    $grouping_name = $line['product_id']['label'];
    if(isset($map_products_groupings[$line['product_id']['id']])) {
        $grouping = $map_products_groupings[$line['product_id']['id']];

        $grouping_name = $grouping['name'];

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
    7) inject expected fundings and find the first installment
       $values['fundings'] holds the list of non (fully) paid fundings
*/
$fundings_payments = [];
$latest_funding_update = 0;
$total_paid = 0;
foreach($invoice['fundings_ids'] as $funding) {
    // ignore fundings that have an invoice of their own (invoiced downpayments)
    if($funding['type'] == 'invoice' && $funding['invoice_id'] != $invoice['id']) {
        continue;
    }

    // assign standalone fundings (not attached to an invoice) to latest balance invoice only
    if(!$funding['invoice_id'] && ($invoice['is_deposit'] || $invoice['status'] == 'cancelled' || $invoice['type'] != 'invoice')) {
        continue;
    }

    // for credit notes, consider only direct fundings (relating to the invoice)
    if($invoice['type'] == 'credit_note' && $funding['invoice_id'] != $invoice['id']) {
        continue;
    }

    $total_paid += $funding['paid_amount'];
    if( $funding['modified'] > $latest_funding_update) {
        $latest_funding_update = $funding['modified'];
    }

    // stack payments
    if(!isset($funding['payments_ids']) || count($funding['payments_ids']) <= 0) {
        if($funding['paid_amount'] > 0 ) {
            $fundings_payments[] = [
                'receipt_date'      => date('d/m/Y', $funding['modified']),
                'amount'            => $funding['paid_amount'],
                'payment_origin'    => 'direct'
            ];
        }
    }
    else {
        foreach($funding['payments_ids'] as $payment) {
            $fundings_payments[] = [
                'receipt_date'      => date('d/m/Y', $payment['receipt_date']),
                'amount'            => $payment['amount'],
                'payment_origin'    => str_replace(['cashdesk', 'bank'], ['caisse', 'banque'], $payment['payment_origin'])
            ];
        }
    }
    // ignore paid fundings
    if(round($funding['due_amount'], 2) == round($funding['paid_amount'], 2)) {
        continue;
    }
    $line = [
        'name'          => $funding['description'],
        'due_date'      => date('d/m/Y', $funding['due_date']),
        'due_amount'    => $funding['due_amount'],
        'paid_amount'   => $funding['paid_amount'],
        'remaining'     => round($funding['due_amount'], 2) - round($funding['paid_amount'], 2)
    ];
    $values['fundings'][] = $line;
}

// $values['date_fundings_update'] holds the date of latest edition of the fundings
$values['date_fundings_update'] = date('d/m/Y', $latest_funding_update);
// $values['total_paid'] holds the total amount received from the customer for the booking
$values['total_paid'] = round($total_paid, 2);
// $values['total_remaining'] holds the total amount left due by the customer for the booking
$values['total_remaining'] = round($invoice['accounting_price'] - $total_paid , 2);
// $values['fundings_payments'] holds the list of (virtual) payments : all operation where money was received from the customer
$values['fundings_payments'] = $fundings_payments;


// add payment terms
if(isset($invoice['funding_id']['due_date'])) {
    $values['payment_deadline'] = date('d/m/Y', $invoice['funding_id']['due_date']);
}
else {
    $values['payment_deadline'] = date('d/m/Y', $invoice['due_date']);
}


// use funding reference, if any
if(isset($invoice['funding_id']['payment_reference'])) {
    $invoice['payment_reference'] = $invoice['funding_id']['payment_reference'];
}

/*
    8) add payment ref and qr code
 */
$reference_type = Setting::get_value('sale', 'organization', 'booking.reference.type', 'VCS');

try {
    if(!isset($invoice['payment_reference'])) {
        throw new Exception('no payment ref');
    }
    $values['payment_reference'] = DataFormatter::format($invoice['payment_reference'], $reference_type);
    $paymentData = Data::create()
        ->setServiceTag('BCD')
        ->setIdentification('SCT')
        ->setName($values['company_name'])
        ->setIban(str_replace(' ', '', $values['company_iban']))
        ->setBic(str_replace(' ', '', $values['company_bic']))
        ->setRemittanceReference($values['payment_reference'])
        ->setAmount($values['total_remaining']);

    $result = Builder::create()
        ->data($paymentData)
        ->errorCorrectionLevel(new ErrorCorrectionLevelMedium()) // required by EPC standard
        ->build();

    $dataUri = $result->getDataUri();
    $values['payment_qr_uri'] = $dataUri;

}
catch(Exception $exception) {
    // unknown error
}

/*
    9) inject all values into the template
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
    10) convert HTML to PDF
*/

// instantiate and use the dompdf class
$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
// if external fonts are involved, tell dompdf to store them in /bin
$options->setFontDir(QN_BASEDIR.'/bin');
$dompdf->setPaper('A4', 'portrait');

// remove utf8mb4 chars (emojis)
$html = preg_replace('/(?:\xF0[\x90-\xBF][\x80-\xBF]{2} | [\xF1-\xF3][\x80-\xBF]{3} | \xF4[\x80-\x8F][\x80-\xBF]{2})/xs', '', $html);


$dompdf->loadHtml((string) $html, 'UTF-8');
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

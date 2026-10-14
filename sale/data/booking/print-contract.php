<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use communication\TemplatePart;
use discope\setting\Setting;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use equal\data\DataFormatter;
use sale\booking\Consumption;
use sale\booking\Contract;
use sale\booking\TimeSlot;
use SepaQr\Data;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use sale\booking\BookingActivity;

[$params, $providers] = eQual::announce([
    'description'   => "Render a contract given its ID as a PDF document.",
    'params'        => [
        'id' => [
            'type'          => 'integer',
            'description'   => 'Identifier of the contract to print.',
            'required'      => true
        ],
        'view_id' => [
            'type'          => 'string',
            'description'   => 'The identifier of the view <type.name>.',
            'default'       => 'print.default'
        ],
        'mode' => [
            'type'          => 'string',
            'selection'     => [
                'simple',
                'grouped',
                'detailed'
            ],
            'description'   => 'Mode in which document has to be rendered: simple or detailed.',
            'default'       => 'grouped'
        ],
        'lang' => [
            'type'          => 'string',
            'description'   => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'default'       => constant('DEFAULT_LANG')
        ],
        'output' => [
            'type'          => 'string',
            'description'   => 'Output format of the document.',
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
    'constants'     => ['DEFAULT_LANG'],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

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

$formatMember = function($booking) {
    $id = $booking['customer_id']['partner_identity_id']['id'];
    $code = ltrim(sprintf("%3d.%03d.%03d", intval($id) / 1000000, (intval($id) / 1000) % 1000, intval($id)% 1000), '0');
    return $code.' - '.$booking['customer_id']['partner_identity_id']['display_name'];
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
    $template_file_path = $getTemplateFilePath(Contract::getType(), $params['view_id']);

    // read contract
    $fields = [
        'created',
        'total',
        'subtotals_vat',
        'price',
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
                'partner_identity_id' => [
                    'id',
                    'display_name',
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
                'price'
            ]
        ]
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

    $member_name = $formatMember($booking);

    $center_office_code = (isset( $booking['center_id']['center_office_id']['code']) && $booking['center_id']['center_office_id']['code'] == 1) ? 'GG' : 'GA';

    $postal_address = sprintf("%s - %s %s", $booking['center_id']['organisation_id']['address_street'], $booking['center_id']['organisation_id']['address_zip'], $booking['center_id']['organisation_id']['address_city']);
    $customer_name = substr($booking['customer_id']['partner_identity_id']['display_name'], 0,  66);
    $customer_address = $booking['customer_id']['partner_identity_id']['address_street'] .' '. $booking['customer_id']['partner_identity_id']['address_zip'].' '.$booking['customer_id']['partner_identity_id']['address_city'];
    $has_activity = Setting::get_value('sale', 'features', 'booking.activity', false);

    $printable_code = sprintf("%04d.%03d", intval($booking['name']) / 1000, intval($booking['name']) % 1000);
    // #kaleo - remove leading zero if present
    if(substr($printable_code, 0, 1) === '0') {
        $printable_code = substr($printable_code, 1);
    }

    $values = [
        'attn_address1'               => '',
        'attn_address2'               => '',
        'attn_name'                   => '',
        'benefit_lines'               => [],
        'center'                      => $booking['center_id']['name'],
        'center_address'              => $booking['center_id']['address_street'].' - '.$booking['center_id']['address_zip'].' '.$booking['center_id']['address_city'],
        'center_contact1'             => (isset($booking['center_id']['manager_id']['name']))?$booking['center_id']['manager_id']['name']:'',
        'center_contact2'             => DataFormatter::format($booking['center_id']['phone'], 'phone').' - '.$booking['center_id']['email'],
        'center_email'                => $booking['center_id']['email'],
        'center_office'               => $center_office_code,
        'center_phone'                => DataFormatter::format($booking['center_id']['phone'], 'phone'),
        'center_signature'            => $booking['center_id']['organisation_id']['signature'],
        'code'                        => $printable_code,
        'company_address'             => sprintf("%s %s %s", $booking['center_id']['organisation_id']['address_street'], $booking['center_id']['organisation_id']['address_zip'], $booking['center_id']['organisation_id']['address_city']),
        'company_bic'                 => DataFormatter::format($booking['center_id']['organisation_id']['bank_account_bic'], 'bic'),
        'company_email'               => $booking['center_id']['organisation_id']['email'],
        'company_fax'                 => DataFormatter::format($booking['center_id']['organisation_id']['fax'], 'phone'),
        'company_has_vat'             => $booking['center_id']['organisation_id']['has_vat'],
        'company_iban'                => DataFormatter::format($booking['center_id']['organisation_id']['bank_account_iban'], 'iban'),
        'company_name'                => $booking['center_id']['organisation_id']['legal_name'],
        'company_phone'               => DataFormatter::format($booking['center_id']['organisation_id']['phone'], 'phone'),
        'company_reg_number'          => $booking['center_id']['organisation_id']['registration_number'],
        'company_vat_number'          => $booking['center_id']['organisation_id']['vat_number'],
        'company_website'             => $booking['center_id']['organisation_id']['website'],
        'contact_email'               => $booking['customer_id']['partner_identity_id']['email'],
        'contact_name'                => '',
        'contact_phone'               => (strlen($booking['customer_id']['partner_identity_id']['phone']))?$booking['customer_id']['partner_identity_id']['phone']:$booking['customer_id']['partner_identity_id']['mobile'],
        'consumptions_map_detailed'   => [],
        'consumptions_map_simple'     => [],
        'consumptions_tye'            => isset($booking['type_id']['booking_schedule_layout']) ? $booking['type_id']['booking_schedule_layout'] : 'simple',
        'contract_authorization_html' => '',
        'contract_header_html'        => '',
        'contract_notice_html'        => '',
        'customer_address1'           => $booking['customer_id']['partner_identity_id']['address_street'],
        'customer_address2'           => $booking['customer_id']['partner_identity_id']['address_zip'].' '.$booking['customer_id']['partner_identity_id']['address_city'].(($booking['customer_id']['partner_identity_id']['address_country'] != 'BE')?(' - '.$booking['customer_id']['partner_identity_id']['address_country']):''),
        'customer_address_dispatch'   => $booking['customer_id']['partner_identity_id']['address_dispatch'],
        'customer_country'            => $booking['customer_id']['partner_identity_id']['address_country'],
        'customer_has_vat'            => (int) $booking['customer_id']['partner_identity_id']['has_vat'],
        'customer_name'               => $customer_name,
        'customer_vat'                => $booking['customer_id']['partner_identity_id']['vat_number'],
        'date'                        => date('d/m/Y', $contract['created']),
        'fundings'                    => [],
        'has_contract_approved'       => 0,
        'header_img_url'              => $img_url ?? 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=',
        'installment_amount'          => 0,
        'installment_date'            => '',
        'installment_qr_url'          => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=',
        'installment_reference'       => '',
        'lines'                       => [],
        'member'                      => substr($member_name, 0, 33) . ((strlen($member_name) > 33) ? '...' : ''),
        'period'                      => date('d/m/Y', $booking['date_from']).' '.date('H:i', $booking['time_from']).' - '.date('d/m/Y', $booking['date_to']).' '.date('H:i', $booking['time_to']),
        'postal_address'              => $postal_address,
        'price'                       => $contract['price'],
        'tax_lines'                   => [],
        'total'                       => $contract['total'],
        'has_activity'               => $has_activity,
        'activities_map'             => ''
    ];

    /*
        retrieve terms translations
    */
    $values['i18n'] = $getLabels($params['lang']);

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
    $contact_name = '';
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
        retrieve templates
    */
    if($booking['center_id']['template_category_id']) {

        $template = Template::search([
                ['category_id', '=', $booking['center_id']['template_category_id']],
                ['code', '=', 'contract'],
                ['type', '=', 'contract']
            ])
            ->read(['parts_ids' => ['name', 'value']], $params['lang'])
            ->first(true);

        foreach($template['parts_ids'] as $part_id => $part) {
            if($part['name'] == 'header') {
                $value = $part['value'];
                $value = str_replace('{center}', $booking['center_id']['name'], $value);
                $value = str_replace('{address}', $postal_address, $value);
                $value = str_replace('{customer}', $customer_name, $value);
                $value = str_replace('{customer_address}', $customer_address, $value);
                $value = str_replace('{contact}', $contact_name, $value);
                $value = str_replace('{nb_pers}', $booking['nb_pers'] ,$value);
                $value = str_replace('{date_from}', date('d/m/Y', $booking['date_from']), $value);
                $value = str_replace('{date_to}', date('d/m/Y', $booking['date_to']), $value);
                $values['contract_header_html'] = $value;
            }
            elseif($part['name'] == 'notice') {
                $value = $part['value'];
                $value = str_replace('{center}', $booking['center_id']['name'], $value);
                $value = str_replace('{price}', $booking['price'] ,$value);
                $values['contract_notice_html'] = $value;
            }
            elseif($part['name'] == 'contract_approved') {
                $value = $part['value'];
                $values['has_contract_approved'] = 1;
                $values['contract_approved_html'] = $part['value'] . $values['center_signature'];
            }
            elseif($part['name'] == 'contract_authorization') {
                $value = $part['value'];
                $value = str_replace('{center}', $booking['center_id']['name'], $value);
                $value = str_replace('{customer}', $customer_name, $value);
                $value = str_replace('{contact}', $contact_name, $value);
                $values['contract_authorization_html'] = $value;
            }
        }

    }

    if(!$values['has_contract_approved']) {
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

    $lines = [];

    // all lines are in groups
    foreach($contract['contract_line_groups_ids'] as $contract_line_group) {
        // generate group label
        $group_label = (strlen($contract_line_group['name']))?$contract_line_group['name']:'';

        if($contract_line_group['is_pack']) {
            // group is a product pack (bundle) with own price: add a single line with details
            $group_is_pack = true;

            $line = [
                'name'          => $group_label,
                'price'         => $contract_line_group['contract_line_id']['price'],
                'total'         => $contract_line_group['contract_line_id']['total'],
                'unit_price'    => $contract_line_group['contract_line_id']['unit_price'],
                'vat_rate'      => $contract_line_group['contract_line_id']['vat_rate'],
                'qty'           => $contract_line_group['contract_line_id']['qty'],
                'free_qty'      => $contract_line_group['contract_line_id']['free_qty'],
                'discount'      => $contract_line_group['contract_line_id']['discount'],
                'is_group'      => false,
                'is_pack'       => true
            ];
            $lines[] = $line;

            if($params['mode'] == 'detailed') {
                foreach($contract_line_group['contract_lines_ids'] as $contract_line) {
                    $line = [
                        'name'          => $contract_line['name'],
                        'qty'           => $contract_line['qty'],
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

            if($params['mode'] == 'grouped') {
                $line = [
                    'name'          => $group_label,
                    'price'         => $contract_line_group['price'],
                    'total'         => $contract_line_group['total'],
                    'unit_price'    => $contract_line_group['total'],
                    'vat_rate'      => (floatval($contract_line_group['price'])/floatval($contract_line_group['total']) - 1.0),
                    'qty'           => 1,
                    'free_qty'      => 0,
                    'discount'      => 0,
                    'is_group'      => true,
                    'is_pack'       => false
                ];
            }
            else {
                $line = [
                    'name'          => $group_label,
                    'price'         => null,
                    'total'         => null,
                    'unit_price'    => null,
                    'vat_rate'      => null,
                    'qty'           => null,
                    'free_qty'      => null,
                    'discount'      => null,
                    'is_group'      => true
                ];
            }
            $lines[] = $line;

            $group_lines = [];

            foreach($contract_line_group['contract_lines_ids'] as $contract_line) {

                if($params['mode'] == 'grouped') {
                    $line = [
                        'name'          => (strlen($contract_line['description']) > 0)?$contract_line['description']:$contract_line['name'],
                        'price'         => null,
                        'total'         => null,
                        'unit_price'    => null,
                        'vat_rate'      => null,
                        'qty'           => $contract_line['qty'],
                        'discount'      => null,
                        'free_qty'      => null,
                        'is_group'      => false,
                        'is_pack'       => false
                    ];
                }
                else {
                    $line = [
                        'name'          => (strlen($contract_line['description']) > 0)?$contract_line['description']:$contract_line['name'],
                        'price'         => $contract_line['price'],
                        'total'         => $contract_line['total'],
                        'unit_price'    => $contract_line['unit_price'],
                        'vat_rate'      => $contract_line['vat_rate'],
                        'qty'           => $contract_line['qty'],
                        'discount'      => $contract_line['discount'],
                        'free_qty'      => $contract_line['free_qty'],
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


    /*
        retrieve final VAT and group by rate
    */
    foreach($contract['subtotals_vat'] as $vat_rate_index => $subtotal_vat) {
        $vat_rate = ((float) $vat_rate_index) / 100;
        $tax_label = 'TVA '.intval($vat_rate * 100).'%';
        $values['tax_lines'][$tax_label] = $subtotal_vat;
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
            'name'          => (strlen($funding['payment_deadline_id']['name']))?$funding['payment_deadline_id']['name']:$funding['description'],
            'due_date'      => date('d/m/Y', $funding['due_date']),
            'due_amount'    => $funding['due_amount'],
            'is_paid'       => $funding['is_paid'],
            'reference'     =>  DataFormatter::format($funding['payment_reference'], $reference_type)
        ];
        $values['fundings'][] = $line;
    }


    if($installment_date == PHP_INT_MAX) {
        // no funding found : the final invoice will be release and generate a funding
        // qr code is not generated
    }
    else if ($installment_amount > 0) {
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
                'activity_booking_line_id' => ['product_id' => ['id','name']],
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
                $activities_map[$group][$date]['time_slots'][$time_slot_name][] = $activity['name'];
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
        $loader = new TwigFilesystemLoader(dirname($template_file_path));

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

        $template = $twig->load(basename($template_file_path));

        $html = $template->render($values);
    }
    catch(Exception $e) {
        trigger_error("ORM::error while parsing template - ".$e->getMessage(), QN_REPORT_DEBUG);
        throw new Exception("template_parsing_issue", QN_ERROR_INVALID_CONFIG);
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
        $canvas->page_text(530, $canvas->get_height() - 35, "p. {PAGE_NUM} / {PAGE_COUNT}", $font, 9, [0, 0, 0]);

        // get generated PDF raw binary
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

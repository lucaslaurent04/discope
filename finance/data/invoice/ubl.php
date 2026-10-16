<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use finance\accounting\Invoice;

[$params, $providers] = eQual::announce([
    'description'   => "Generate the UBL file of a given invoice.",
    'params'        => [
        'id' =>  [
            'type'          => 'integer',
            'description'   => "Identifier of the invoice for which the UBL file has to be generated.",
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'    => 'protected',
        'groups'        => ['finance.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
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

$formatQty = function($value) {
    return number_format($value, 1, ".", "");
};

$formatMoney = function($value, $decimals = 2) {
    return number_format($value, $decimals, ".", "");
};

$formatVatNumber = function($value, $country_code) {
    $vat_number = str_replace([" ", "."], "", $value);
    if(strpos($vat_number, $country_code) !== 0) {
        $vat_number = $country_code.$vat_number;
    }
    return $vat_number;
};

$formatRegistrationNumber = function($value) {
    return str_replace([" ", "."], "", $value);;
};

$formatVatRate = function($value) {
    return number_format($value * 100, 2, ".", "");
};

$formatToUblXml = function($data): string {
    if(!isset($data['Invoice'])) {
        throw new Exception("Missing 'Invoice' root.");
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;

    // Create root Invoice element with namespaces
    $invoice = $doc->createElement('Invoice');
    $invoice->setAttribute("xmlns", "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2");
    $invoice->setAttribute("xmlns:cac", "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2");
    $invoice->setAttribute("xmlns:cbc", "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2");
    $doc->appendChild($invoice);

    // Recursive builder
    $addElements = function($parent, $data) use(&$addElements, $doc) {
        foreach($data as $key => $value) {
            // Handle array of items → multiple repeated tags
            if(is_array($value) && isset($value['items']) && is_array($value['items'])) {
                foreach ($value['items'] as $item) {
                    $element = $doc->createElement($key);
                    $addElements($element, $item);
                    $parent->appendChild($element);
                }
                continue;
            }

            // Handle attribute/content structure
            if(is_array($value) && isset($value['content'])) {
                $element = $doc->createElement($key, htmlspecialchars((string)$value['content']));
                if (isset($value['attributes']) && is_array($value['attributes'])) {
                    foreach ($value['attributes'] as $attrName => $attrValue) {
                        $element->setAttribute($attrName, $attrValue);
                    }
                }
                $parent->appendChild($element);
                continue;
            }

            // Handle nested associative arrays
            if(is_array($value)) {
                $element = $doc->createElement($key);
                $addElements($element, $value);
                $parent->appendChild($element);
                continue;
            }

            // Handle simple scalar value
            $element = $doc->createElement($key, htmlspecialchars((string)$value));
            $parent->appendChild($element);
        }
    };

    // Start building from Invoice root
    $addElements($invoice, $data['Invoice']);

    return $doc->saveXML();
};

/**
 * Action
 */

$invoice = Invoice::id($params['id'])
    ->read([
        'type',
        'date',
        'due_date',
        'number',
        'total_discount',
        'total',
        'subtotals',
        'subtotals_vat',
        'total_vat',
        'price',
        'center_office_id' => [
            'organisation_id' => [
                'legal_name',
                'has_vat',
                'vat_number',
                'registration_number',
                'address_street',
                'address_zip',
                'address_city',
                'address_country',
                'email'
            ]
        ],
        'partner_id' => [
            'partner_identity_id' => [
                'type_id',
                'legal_name',
                'firstname',
                'lastname',
                'has_vat',
                'vat_number',
                'registration_number',
                'address_street',
                'address_zip',
                'address_city',
                'address_country',
                'email'
            ]
        ],
        'invoice_lines_ids' => [
            'name',
            'description',
            'qty',
            'unit_price',
            'vat_rate',
            'total',
            'price'
        ]
    ])
    ->first();

if(is_null($invoice)) {
    throw new Exception("unknown_invoice", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!$invoice['center_office_id']['organisation_id']['has_vat'] || !$invoice['partner_id']['partner_identity_id']['has_vat']) {
    throw new Exception("both_parties_must_have_vat_number", EQ_ERROR_INVALID_PARAM);
}

// see https://docs.peppol.eu/poacc/billing/3.0/codelist/eas/ for more information
$map_eas_scheme_codes = [
    'AD' => '9922',
    'AL' => '9923',
    'BA' => '9924',
    'BE' => '9925',
    'BG' => '9926',
    'CH' => '9927',
    'CY' => '9928',
    'CZ' => '9929',
    'DE' => '9930',
    'EE' => '9931',
    'GB' => '9932',
    'GR' => '9933',
    'HR' => '9934',
    'IE' => '9935',
    'LI' => '9936',
    'LT' => '9937',
    'LU' => '9938',
    'LV' => '9939',
    'MC' => '9940',
    'ME' => '9941',
    'MK' => '9942',
    'MT' => '9943',
    'NL' => '9944',
    'PL' => '9945',
    'PT' => '9946',
    'RO' => '9947',
    'RS' => '9948',
    'SI' => '9949',
    'SK' => '9950',
    'SM' => '9951',
    'TR' => '9952',
    'VA' => '9953',
    'SE' => '9955',
    'FR' => '9957',
    'US' => '9959',
];

$ubl = [];

$supplier = [
    'cac:Party' => [
        'cbc:EndpointID'        => [
            'attributes'            => ['schemeID' => $map_eas_scheme_codes[$invoice['center_office_id']['organisation_id']['address_country']]],
            'content'               => $formatVatNumber($invoice['center_office_id']['organisation_id']['vat_number'], $invoice['center_office_id']['organisation_id']['address_country']),
        ],
        'cac:PostalAddress'     => [
            'cbc:StreetName'            => $invoice['center_office_id']['organisation_id']['address_street'],
            'cbc:CityName'              => $invoice['center_office_id']['organisation_id']['address_city'],
            'cbc:PostalZone'            => $invoice['center_office_id']['organisation_id']['address_zip'],
            'cac:Country'               => [
                'cbc:IdentificationCode'    => $invoice['center_office_id']['organisation_id']['address_country']
            ]
        ],
        'cac:PartyTaxScheme'    => [],
        'cac:PartyLegalEntity'  => [
            'cbc:RegistrationName'      => $invoice['center_office_id']['organisation_id']['legal_name'],
            'cbc:CompanyID'             => $formatRegistrationNumber($invoice['center_office_id']['organisation_id']['registration_number'])
        ],
        'cac:Contact' => [
            'cbc:ElectronicMail' => $invoice['center_office_id']['organisation_id']['email']
        ]
    ]
];

if(!empty($invoice['center_office_id']['organisation_id']['address_dispatch'])) {
    $supplier['cac:Party']['cac:PostalAddress']['cbc:AdditionalStreetName'] = $invoice['center_office_id']['organisation_id']['address_dispatch'];
}

if($invoice['center_office_id']['organisation_id']['has_vat']) {
    $supplier['cac:Party']['cac:PartyTaxScheme'] = [
        'cbc:CompanyID' => $formatVatNumber($invoice['center_office_id']['organisation_id']['vat_number'], $invoice['center_office_id']['organisation_id']['address_country']),
        'cac:TaxScheme' => [
            'cbc:ID'        => 'VAT'
        ]
    ];
}
else {
    unset($supplier['cac:Party']['cac:PartyTaxScheme']);
}

$customer_name = $invoice['partner_id']['partner_identity_id']['legal_name'];
if($invoice['partner_id']['partner_identity_id']['type_id'] === 1) {
    $customer_name = sprintf('%s %s',
        $invoice['partner_id']['partner_identity_id']['firstname'],
        $invoice['partner_id']['partner_identity_id']['lastname']
    );
}

$customer = [
    'cac:Party' => [
        'cbc:EndpointID'        => [
            'attributes'            => ['schemeID' => $map_eas_scheme_codes[$invoice['partner_id']['partner_identity_id']['address_country']]],
            'content'               => $formatVatNumber($invoice['partner_id']['partner_identity_id']['vat_number'], $invoice['center_office_id']['organisation_id']['address_country']),
        ],
        'cac:PostalAddress'     => [
            'cbc:StreetName'            => $invoice['partner_id']['partner_identity_id']['address_street'],
            'cbc:CityName'              => $invoice['partner_id']['partner_identity_id']['address_city'],
            'cbc:PostalZone'            => $invoice['partner_id']['partner_identity_id']['address_zip'],
            'cac:Country'               => [
                'cbc:IdentificationCode'    => $invoice['partner_id']['partner_identity_id']['address_country']
            ]
        ],
        'cac:PartyTaxScheme'    => [],
        'cac:PartyLegalEntity'  => [
            'cbc:RegistrationName' => $customer_name
        ],
        'cac:Contact' => [
            'cbc:ElectronicMail' => $invoice['partner_id']['partner_identity_id']['email']
        ]
    ]
];

if(!empty($invoice['partner_id']['partner_identity_id']['address_dispatch'])) {
    $customer['cac:Party']['cac:PostalAddress']['cbc:AdditionalStreetName'] = $invoice['partner_id']['partner_identity_id']['address_dispatch'];
}

if($invoice['partner_id']['partner_identity_id']['has_vat']) {
    $customer['cac:Party']['cac:PartyTaxScheme'] = [
        'cbc:CompanyID' => $formatVatNumber($invoice['partner_id']['partner_identity_id']['vat_number'], $invoice['partner_id']['partner_identity_id']['address_country']),
        'cac:TaxScheme' => [
            'cbc:ID'        => 'VAT'
        ]
    ];
}
else {
    unset($customer['cac:Party']['cac:PartyTaxScheme']);
}

if(!empty($invoice['partner_id']['partner_identity_id']['registration_number'])) {
    $customer['cac:Party']['cac:PartyLegalEntity']['cbc:CompanyID'] = $formatRegistrationNumber($invoice['partner_id']['partner_identity_id']['registration_number']);
}

switch($invoice['type']) {
    case 'invoice':
        $ubl = [
            'Invoice' => [
                'cbc:CustomizationID'           => 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0',
                'cbc:ProfileID'                 => 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0',
                'cbc:ID'                        => $invoice['number'],
                'cbc:IssueDate'                 => date('Y-m-d', $invoice['date']),
                'cbc:DueDate'                   => date('Y-m-d', $invoice['due_date']),
                'cbc:InvoiceTypeCode'           => 380,
                'cbc:DocumentCurrencyCode'      => 'EUR',
                'cac:OrderReference'            => ['cbc:ID' => 'NA'],
                'cac:AccountingSupplierParty'   => $supplier,
                'cac:AccountingCustomerParty'   => $customer,
                'cac:TaxTotal'                  => [],
                'cac:LegalMonetaryTotal'        => [],
                'cac:InvoiceLine'               => ['items' => []],
            ]
        ];

        break;
    case 'credit_note':
        $ubl = [
            'CreditNote' => [
                'cbc' => [
                    'CustomizationID'       => 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0',
                    'ProfileID'             => 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0',
                    'ID'                    => $invoice['number'],
                    'IssueDate'             => date('Y-m-d', $invoice['date']),
                    'InvoiceTypeCode'       => 381,
                    'DocumentCurrencyCode'  => 'EUR'
                ]
            ]
        ];
        break;
}

$index = 0;
foreach($invoice['invoice_lines_ids'] as $line) {
    $vat_rate = $formatVatRate($line['vat_rate']);

    $has_vat = ((float) $vat_rate) !== 0.0;

    $item = [
        'cbc:ID' => ++$index,
        'cbc:InvoicedQuantity' => [
            'attributes'    => ['unitCode' => 'EA'], // Note unit code HEA = heads, EA = unit
            'content'       => $line['qty']
        ],
        'cbc:LineExtensionAmount' => [
            'attributes'    => ['currencyID' => 'EUR'],
            'content'       => $formatMoney($line['total'])
        ],
        'cac:Item' => [
            'cbc:Name'                  => $line['name'],
            'cac:ClassifiedTaxCategory' => [
                'cbc:ID'                    => $has_vat ? 'S' : 'E',
                'cbc:Percent'               => $vat_rate,
                'cac:TaxScheme'             => ['cbc:ID' => 'VAT']
            ]
        ],
        'cac:Price' => [
            'cbc:PriceAmount' => [
                'attributes'    => ['currencyID' => 'EUR'],
                'content'       => $formatMoney($line['unit_price'], 4)
            ]
        ]
    ];

    $ubl['Invoice']['cac:InvoiceLine']['items'][] = $item;
}

$ubl['Invoice']['cac:TaxTotal'] = [
    'cbc:TaxAmount'     => ['attributes' => ['currencyID' => 'EUR'], 'content' => $formatMoney($invoice['total_vat'])],
    'cac:TaxSubtotal'   => ['items' => []]
];

$subtotals_vat = json_decode($invoice['subtotals_vat'], true);

foreach($subtotals_vat as $vat_rate_index => $subtotal_vat) {
    $vat_rate = ((float) $vat_rate_index) / 100;

    $has_vat = $vat_rate !== 0.0;

    $item = [
        'cbc:TaxableAmount' => ['attributes' => ['currencyID' => 'EUR'], 'content' => $formatMoney($invoice['subtotals'][$vat_rate_index])],
        'cbc:TaxAmount'     => ['attributes' => ['currencyID' => 'EUR'], 'content' => $formatMoney($subtotal_vat)],
        'cac:TaxCategory'   => [
            'cbc:ID'                        => $has_vat ? 'S' : 'E',
            'cbc:Percent'                   => $formatVatRate($vat_rate),
            // #memo - 'VATEX-EU-132-1L' for organisation without VAT like ASBL
            'cbc:TaxExemptionReasonCode'    => 'VATEX-EU-132',
            'cac:TaxScheme'                 => ['cbc:ID' => 'VAT']
        ]
    ];

    if($has_vat) {
        unset($item['cac:TaxCategory']['cbc:TaxExemptionReasonCode']);
    }

    $ubl['Invoice']['cac:TaxTotal']['cac:TaxSubtotal']['items'][] = $item;
}

$ubl['Invoice']['cac:LegalMonetaryTotal'] = [
    'cbc:LineExtensionAmount' => [
        'attributes'    => ['currencyID' => 'EUR'],
        'content'       => $formatMoney($invoice['total'] + $invoice['total_discount'])
    ],
    'cbc:TaxExclusiveAmount' => [
        'attributes'    => ['currencyID' => 'EUR'],
        'content'       => $formatMoney($invoice['total'])
    ],
    'cbc:TaxInclusiveAmount' => [
        'attributes'    => ['currencyID' => 'EUR'],
        'content'       => $formatMoney($invoice['price'])
    ],
    'cbc:AllowanceTotalAmount' => [
        'attributes'    => ['currencyID' => 'EUR'],
        'content'       => $formatMoney($invoice['total_discount'])
    ],
    'cbc:ChargeTotalAmount' => [
        'attributes'    => ['currencyID' => 'EUR'],
        'content'       => 0
    ],
    'cbc:PayableAmount' => [
        'attributes'    => ['currencyID' => 'EUR'],
        'content'       => $formatMoney($invoice['price'])
    ]
];

$ubl_xml = $formatToUblXml($ubl);

$context
    ->httpResponse()
    ->status(200)
    ->body($ubl_xml)
    ->send();

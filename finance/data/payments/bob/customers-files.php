<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use equal\text\TextTransformer;
use identity\Partner;

[$params, $providers] = eQual::announce([
    'description'   => "Returns files 'CLIENTS_FACT.sch' and 'CLIENTS_FACT.txt' to import customers data in BOB software.",
    'params'        => [
        'domain' => [
            'type'          => 'array',
            'description'   => "Domain to filter the partners.",
            'default'       => []
        ],
        'file_name' => [
            'type'          => 'string',
            'description'   => "Name of the file.",
            'default'       => 'CLIENTS_FACT'
        ],
        'file_type' => [
            'type'          => 'string',
            'description'   => "Import file type.",
            'default'       => 'Fixed'
        ],
        'char_set' => [
            'type'          => 'string',
            'description'   => "Import file character set.",
            'default'       => 'ascii'
        ]
    ],
    'access'        => [
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

$createFieldsSchema = function($fields_conf) {
    $index = 1;
    $position = 0;
    $fields_schema = [];
    foreach($fields_conf as $field => $field_conf) {
        // e.g., Field1=DBK,Char,04,00,00
        $fields_schema[] = sprintf('Field%d=%s,%s,%02d,%02d,%02d',
            $index,
            $field,
            $field_conf['type'],
            $field_conf['length'],
            $field_conf['decimals'],
            $position
        );

        $index++;
        $position += $field_conf['length'];
    }

    return implode("\r\n", $fields_schema);
};

/**
 * Data controller
 */

if(empty($params['domain'])) {
    throw new Exception("empty_domain", EQ_ERROR_INVALID_PARAM);
}

$domain = new Domain($params['domain']);
$domain->addCondition(new DomainCondition('state', 'in', ['instance', 'archive']));

$partners = Partner::search($domain->toArray())
    ->read([
        'name',
        'partner_identity_id' => [
            '@domain' => ['state', 'in', ['instance', 'archive']],
            'address_street',
            'address_dispatch',
            'address_city',
            'address_zip',
            'address_country',
            'vat_number',
            'phone',
            'fax',
            'lang_id' => [
                'code'
            ]
        ]
    ])
    ->get();

$fields_conf = [
    'CID'               => ['type' => 'Char',           'length' => 10,     'decimals' => 0],
    'CCUSTYPE'          => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'CSUPTYPE'          => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'CNAME1'            => ['type' => 'Char',           'length' => 40,     'decimals' => 0],
    'CNAME2'            => ['type' => 'Char',           'length' => 40,     'decimals' => 0],
    'CADDRESS1'         => ['type' => 'Char',           'length' => 40,     'decimals' => 0],
    'CADDRESS2'         => ['type' => 'Char',           'length' => 40,     'decimals' => 0],
    'CZIPCODE'          => ['type' => 'Char',           'length' => 10,     'decimals' => 0],
    'CLOCALITY'         => ['type' => 'Char',           'length' => 40,     'decimals' => 0],
    'CLANGUAGE'         => ['type' => 'Char',           'length' => 2,      'decimals' => 0],
    'CISPERS'           => ['type' => 'Bool',           'length' => 1,      'decimals' => 0],
    'CCUSCAT'           => ['type' => 'Char',           'length' => 3,      'decimals' => 0],
    'CCURRENCY'         => ['type' => 'Char',           'length' => 3,      'decimals' => 0],
    'CVATCAT'           => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'CVATREF'           => ['type' => 'Char',           'length' => 2,      'decimals' => 0],
    'CVATNO'            => ['type' => 'Char',           'length' => 12,     'decimals' => 0],
    'CTELNO'            => ['type' => 'Char',           'length' => 14,     'decimals' => 0],
    'CFAXNO'            => ['type' => 'Char',           'length' => 14,     'decimals' => 0],
    'CCUSVNAT1'         => ['type' => 'Char',           'length' => 3,      'decimals' => 0],
    'CCUSVNAT2'         => ['type' => 'Char',           'length' => 3,      'decimals' => 0],
    'CCUSVATCMP'        => ['type' => 'Float',          'length' => 20,     'decimals' => 2],
    'CCUSCTRACC'        => ['type' => 'Char',           'length' => 10,     'decimals' => 0],
    'CCUSIMPUTA'        => ['type' => 'Char',           'length' => 10,     'decimals' => 0],
    'CCTRYCODE'         => ['type' => 'Char',           'length' => 2,      'decimals' => 0],
    'CBANKCODE'         => ['type' => 'Char',           'length' => 6,      'decimals' => 0],
    'CBANKNO'           => ['type' => 'Char',           'length' => 19,     'decimals' => 0],
    'CISWARNING'        => ['type' => 'Bool',           'length' => 1,      'decimals' => 0],
    'CISREADONL'        => ['type' => 'Bool',           'length' => 1,      'decimals' => 0],
    'CISBLOCK'          => ['type' => 'Bool',           'length' => 1,      'decimals' => 0],
    'CISSECRET'         => ['type' => 'Bool',           'length' => 1,      'decimals' => 0],
    'CCUSPAYDELAY'      => ['type' => 'Char',           'length' => 6,      'decimals' => 0],
    'CREMCAT'           => ['type' => 'Char',           'length' => 5,      'decimals' => 0],
    'CREMSTATUS'        => ['type' => 'Char',           'length' => 1,      'decimals' => 0],
    'CREATEDATE'        => ['type' => 'TimeStamp',      'length' => 30,     'decimals' => 0],
    'MODIFYDATE'        => ['type' => 'TimeStamp',      'length' => 30,     'decimals' => 0],
    'AUTHOR'            => ['type' => 'Char',           'length' => 10,     'decimals' => 0],
    'CNATREGISTRYID'    => ['type' => 'Char',           'length' => 15,     'decimals' => 0],
    'CCUSPDISCDEL'      => ['type' => 'Long Integer',   'length' => 11,     'decimals' => 0],
    'CCUSTEMPLID'       => ['type' => 'Char',           'length' => 10,     'decimals' => 0],
    'CMEMO'             => ['type' => 'Char',           'length' => 200,    'decimals' => 0]
];

$data = [];
foreach($partners as $partner) {
    $customer_name = substr(strtoupper(TextTransformer::normalize($partner['name'])), 0, 40);
    $customer_phone = substr(str_replace([' ', '.', '-', '_'], '', $partner['partner_identity_id']['phone']), 0, 14);
    $customer_fax = substr(str_replace([' ', '.', '-', '_'], '', $partner['partner_identity_id']['fax']), 0, 14);
    $customer_address = substr(strtoupper(TextTransformer::normalize(str_replace(["\n", "\t", "\r"], '', $partner['partner_identity_id']['address_street']))), 0, 40);
    $customer_address_dispatch = substr(strtoupper(TextTransformer::normalize(str_replace(["\n", "\t", "\r"], '', $partner['partner_identity_id']['address_dispatch']))), 0, 40);
    $customer_city = substr(strtoupper(TextTransformer::normalize($partner['partner_identity_id']['address_city'])), 0, 40);
    $customer_country = substr(strtoupper($partner['partner_identity_id']['address_country']), 0, 2);

    $customer_zip = str_replace([' ', '.', '-', '_'], '', $partner['partner_identity_id']['address_zip']);
    if(!empty($customer_zip) && !empty($customer_country) && substr($customer_zip, 0, 2) !== $customer_country) {
        // #memo - add country prefix from zipcode
        $customer_zip = substr($customer_country.$customer_zip, 0, 10);
    }
    else {
        $customer_zip = substr($customer_zip, 0, 10);
    }

    $customer_vat = str_replace([' ', '.', '-', '_'], '', $partner['partner_identity_id']['vat_number']);
    if(!empty($customer_vat) && !empty($customer_country) && substr($customer_vat, 0, 2) === $customer_country) {
        // #memo - remove country prefix from vat number
        $customer_vat = substr($customer_vat, 2, 12);
    }
    else {
        $customer_vat = substr($customer_vat, 0, 12);
    }

    $customer_lang = 'F';
    if(isset($partner['partner_identity_id']['lang_id']['code']) && is_string($partner['partner_identity_id']['lang_id']['code']) && strlen($partner['partner_identity_id']['lang_id']['code']) >= 1) {
        // #memo - BOB uses a single letter
        $customer_lang = strtoupper(substr($partner['partner_identity_id']['lang_id']['code'], 0, 1));
    }

    $map_values = [
        'CID'               => str_pad('C'.$partner['partner_identity_id']['id'], 10, ' ', STR_PAD_RIGHT),
        'CCUSTYPE'          => str_pad('C', 1,' ',STR_PAD_LEFT),
        'CSUPTYPE'          => str_pad('U', 1,' ',STR_PAD_LEFT),
        'CNAME1'            => str_pad($customer_name, 40, ' ', STR_PAD_RIGHT),
        'CNAME2'            => str_pad('', 40, ' ', STR_PAD_RIGHT),
        'CADDRESS1'         => str_pad($customer_address, 40, ' ', STR_PAD_RIGHT),
        'CADDRESS2'         => str_pad($customer_address_dispatch, 40, ' ', STR_PAD_RIGHT),
        'CZIPCODE'          => str_pad($customer_zip, 10, ' ', STR_PAD_RIGHT),
        'CLOCALITY'         => str_pad($customer_city, 40, ' ', STR_PAD_RIGHT),
        'CLANGUAGE'         => str_pad($customer_lang, 2, ' ', STR_PAD_RIGHT),
        'CISPERS'           => str_pad('0', 1, ' ', STR_PAD_RIGHT),
        'CCUSCAT'           => str_pad('', 3, ' ', STR_PAD_RIGHT),
        'CCURRENCY'         => str_pad('EUR', 3, ' ', STR_PAD_RIGHT),
        'CVATCAT'           => str_pad('', 1, ' ', STR_PAD_RIGHT),
        'CVATREF'           => str_pad($customer_country, 2, ' ', STR_PAD_RIGHT),
        'CVATNO'            => str_pad($customer_vat, 12, ' ', STR_PAD_RIGHT),
        'CTELNO'            => str_pad($customer_phone, 14, ' ', STR_PAD_RIGHT),
        'CFAXNO'            => str_pad($customer_fax, 14, ' ', STR_PAD_RIGHT),
        'CCUSVNAT1'         => str_pad('', 3, ' ', STR_PAD_RIGHT),
        'CCUSVNAT2'         => str_pad('', 3, ' ', STR_PAD_RIGHT),
        'CCUSVATCMP'        => str_pad('', 20, ' ', STR_PAD_RIGHT),
        'CCUSCTRACC'        => str_pad('', 10, ' ', STR_PAD_RIGHT),
        'CCUSIMPUTA'        => str_pad('', 10, ' ', STR_PAD_RIGHT),
        'CCTRYCODE'         => str_pad($customer_country, 2, ' ', STR_PAD_RIGHT),
        'CBANKCODE'         => str_pad('', 6, ' ', STR_PAD_RIGHT),
        'CBANKNO'           => str_pad('', 19, ' ', STR_PAD_RIGHT),
        'CISWARNING'        => str_pad('0', 1, ' ', STR_PAD_RIGHT),
        'CISREADONL'        => str_pad('0', 1, ' ', STR_PAD_RIGHT),
        'CISBLOCK'          => str_pad('0', 1, ' ', STR_PAD_RIGHT),
        'CISSECRET'         => str_pad('0', 1, ' ', STR_PAD_RIGHT),
        'CCUSPAYDELAY'      => str_pad('', 6, ' ', STR_PAD_RIGHT),
        'CREMCAT'           => str_pad('', 5, ' ', STR_PAD_RIGHT),
        'CREMSTATUS'        => str_pad('', 1, ' ', STR_PAD_RIGHT),
        'CREATEDATE'        => str_pad('', 30, ' ', STR_PAD_RIGHT),
        'MODIFYDATE'        => str_pad('', 30, ' ', STR_PAD_RIGHT),
        'AUTHOR'            => str_pad('', 10, ' ', STR_PAD_RIGHT),
        'CNATREGISTRYID'    => str_pad('', 15, ' ', STR_PAD_RIGHT),
        'CCUSPDISCDEL'      => str_pad('', 11, ' ', STR_PAD_RIGHT),
        'CCUSTEMPLID'       => str_pad('', 10, ' ', STR_PAD_RIGHT),
        'CMEMO'             => str_pad('', 200, ' ', STR_PAD_RIGHT),
    ];

    $values = [];
    foreach(array_keys($fields_conf) as $field) {
        $values[] = $map_values[$field];
    }

    $data[] = implode('', $values);
}

$result = [
    'schema'    => implode("\r\n", ["[{$params['file_name']}]", "FileType={$params['file_type']}", "CharSet={$params['char_set']}"])."\r\n".$createFieldsSchema($fields_conf)."\r\n",
    'data'      => implode("\r\n", $data)."\r\n"
];

$context
    ->httpResponse()
    ->body($result)
    ->send();

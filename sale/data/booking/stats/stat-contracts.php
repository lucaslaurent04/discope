<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

list($params, $providers) = eQual::announce([
    'description'   => 'Lists all+historic contracts and their related details for a given period.',
    'params'        => [
        /* mixed-usage parameters: required both for fetching data (input) and property of virtual entity (output) */
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => "Output: Center of the sojourn / Input: The center for which the stats are required."
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Output: Day of arrival / Input: Date interval lower limit (defaults to first day of previous month).",
            'default'           => mktime(0, 0, 0, date("m")-1, 1)
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Output: Day of departure / Input: Date interval upper limit (defaults to last day of previous month).',
            'default'           => mktime(0, 0, 0, date("m"), 0)
        ],
        'organisation_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Identity',
            'description'       => "The organisation the establishment belongs to.",
            'domain'            => ['id', '<', 6]
        ],

        /* parameters used as properties of virtual entity */

        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],
        'center_type' => [
            'type'              => 'string',
            'selection'         => [
                'GA',
                'GG'
            ],
            'description'       => 'Type of the center.'
        ],
        'booking' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],
        'created' => [
            'type'              => 'date',
            'description'       => 'Creation date of the booking.'
        ],
        'created_aamm' => [
            'type'              => 'string',
            'description'       => 'Index date of the creation date of the booking.'
        ],
        'aamm' => [
            'type'              => 'string',
            'description'       => 'Index date of the first day of the sojourn.'
        ],
        'year' => [
            'type'              => 'string',
            'description'       => 'Index date of the first day of the sojourn.'
        ],
        'nb_pers' => [
            'type'              => 'integer',
            'description'       => 'Number of hosted persons.'
        ],
        'nb_nights' => [
            'type'              => 'integer',
            'description'       => 'Duration of the sojourn (number of nights).'
        ],
        'nb_pers_nights' => [
            'type'              => 'integer',
            'description'       => 'Number of nights/accommodations.'
        ],
        'nb_room_nights' => [
            'type'              => 'integer',
            'description'       => 'Number of nights/accommodations.'
        ],
        'nb_rental_units' => [
            'type'              => 'integer',
            'description'       => 'Number of rental units (accommodations) involved in the sojourn.'
        ],
        'rate_class' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_name' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_lang' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_zip' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_country' => [
            'type'              => 'string',
            'usage'             => 'country/iso-3166:2',
            'selection'         => [
                'all'   => 'Tous',
                'AF'    => 'Afghanistan',
                'AX'    => 'Aland Islands',
                'AL'    => 'Albanie',
                'DZ'    => 'Algérie',
                'AS'    => 'American Samoa',
                'AD'    => 'Andorra',
                'AO'    => 'Angola',
                'AI'    => 'Anguilla',
                'AQ'    => 'Antarctica',
                'AG'    => 'Antigua And Barbuda',
                'AR'    => 'Argentina',
                'AM'    => 'Arménie',
                'AW'    => 'Aruba',
                'AU'    => 'Australia',
                'AT'    => 'Autriche',
                'AZ'    => 'Azerbaïdjan',
                'BS'    => 'Bahamas',
                'BH'    => 'Bahrain',
                'BD'    => 'Bangladesh',
                'BB'    => 'Barbados',
                'BY'    => 'Biélorussie',
                'BE'    => 'Belgique',
                'BZ'    => 'Belize',
                'BJ'    => 'Benin',
                'BM'    => 'Bermuda',
                'BT'    => 'Bhutan',
                'BO'    => 'Bolivia',
                'BA'    => 'Bosnie-Herzégovine',
                'BW'    => 'Botswana',
                'BV'    => 'Bouvet Island',
                'BR'    => 'Brazil',
                'IO'    => 'British Indian Ocean Territory',
                'BN'    => 'Brunei Darussalam',
                'BG'    => 'Bulgarie',
                'BF'    => 'Burkina Faso',
                'BI'    => 'Burundi',
                'KH'    => 'Cambodia',
                'CM'    => 'Cameroon',
                'CA'    => 'Canada',
                'CV'    => 'Cape Verde',
                'KY'    => 'Cayman Islands',
                'CF'    => 'Central African Republic',
                'TD'    => 'Chad',
                'CL'    => 'Chile',
                'CN'    => 'China',
                'CX'    => 'Christmas Island',
                'CC'    => 'Cocos (Keeling) Islands',
                'CO'    => 'Colombia',
                'KM'    => 'Comoros',
                'CG'    => 'Congo',
                'CD'    => 'Congo, Democratic Republic',
                'CK'    => 'Cook Islands',
                'CR'    => 'Costa Rica',
                'CI'    => 'Cote D\'Ivoire',
                'HR'    => 'Croatie',
                'CU'    => 'Cuba',
                'CY'    => 'Chypre',
                'CZ'    => 'Tchéquie',
                'DK'    => 'Danemark',
                'DJ'    => 'Djibouti',
                'DM'    => 'Dominica',
                'DO'    => 'Dominican Republic',
                'EC'    => 'Ecuador',
                'EG'    => 'Egypte',
                'SV'    => 'El Salvador',
                'GQ'    => 'Equatorial Guinea',
                'ER'    => 'Eritrea',
                'EE'    => 'Estonie',
                'ET'    => 'Ethiopia',
                'FK'    => 'Falkland Islands (Malvinas)',
                'FO'    => 'Faroe Islands',
                'FJ'    => 'Fiji',
                'FI'    => 'Finlande',
                'FR'    => 'France',
                'GF'    => 'French Guiana',
                'PF'    => 'French Polynesia',
                'TF'    => 'French Southern Territories',
                'GA'    => 'Gabon',
                'GM'    => 'Gambia',
                'GE'    => 'Géorgie',
                'DE'    => 'Allemagne',
                'GH'    => 'Ghana',
                'GI'    => 'Gibraltar',
                'GR'    => 'Greece',
                'GL'    => 'Greenland',
                'GD'    => 'Grenada',
                'GP'    => 'Guadeloupe',
                'GU'    => 'Guam',
                'GT'    => 'Guatemala',
                'GG'    => 'Guernsey',
                'GN'    => 'Guinea',
                'GW'    => 'Guinea-Bissau',
                'GY'    => 'Guyana',
                'HT'    => 'Haiti',
                'HM'    => 'Heard Island & Mcdonald Islands',
                'VA'    => 'Holy See (Vatican City State)',
                'HN'    => 'Honduras',
                'HK'    => 'Hong Kong',
                'HU'    => 'Hongrie',
                'IS'    => 'Islande',
                'IN'    => 'India',
                'ID'    => 'Indonesia',
                'IR'    => 'Iran, Islamic Republic Of',
                'IQ'    => 'Iraq',
                'IE'    => 'Irlande',
                'IM'    => 'Isle Of Man',
                'IL'    => 'Israel',
                'IT'    => 'Italie',
                'JM'    => 'Jamaica',
                'JP'    => 'Japan',
                'JE'    => 'Jersey',
                'JO'    => 'Jordanie',
                'KZ'    => 'Kazakhstan',
                'KE'    => 'Kenya',
                'KI'    => 'Kiribati',
                'KR'    => 'Korea',
                'KW'    => 'Kuwait',
                'KG'    => 'Kyrgyzstan',
                'LA'    => 'Lao People\'s Democratic Republic',
                'LV'    => 'Lettonie',
                'LB'    => 'Liban',
                'LS'    => 'Lesotho',
                'LR'    => 'Liberia',
                'LY'    => 'Libye',
                'LI'    => 'Liechtenstein',
                'LT'    => 'Lituanie',
                'LU'    => 'Luxembourg',
                'MO'    => 'Macao',
                'MK'    => 'Macedonia',
                'MG'    => 'Madagascar',
                'MW'    => 'Malawi',
                'MY'    => 'Malaysia',
                'MV'    => 'Maldives',
                'ML'    => 'Mali',
                'MT'    => 'Malta',
                'MH'    => 'Marshall Islands',
                'MQ'    => 'Martinique',
                'MR'    => 'Mauritania',
                'MU'    => 'Mauritius',
                'YT'    => 'Mayotte',
                'MX'    => 'Mexico',
                'FM'    => 'Micronesia, Federated States Of',
                'MD'    => 'Moldavie',
                'MC'    => 'Monaco',
                'MN'    => 'Mongolia',
                'ME'    => 'Monténégro',
                'MS'    => 'Montserrat',
                'MA'    => 'Maroc',
                'MZ'    => 'Mozambique',
                'MM'    => 'Myanmar',
                'NA'    => 'Namibia',
                'NR'    => 'Nauru',
                'NP'    => 'Nepal',
                'NL'    => 'Pays-Bas',
                'AN'    => 'Netherlands Antilles',
                'NC'    => 'New Caledonia',
                'NZ'    => 'New Zealand',
                'NI'    => 'Nicaragua',
                'NE'    => 'Niger',
                'NG'    => 'Nigeria',
                'NU'    => 'Niue',
                'NF'    => 'Norfolk Island',
                'MP'    => 'Northern Mariana Islands',
                'NO'    => 'Norvège',
                'OM'    => 'Oman',
                'PK'    => 'Pakistan',
                'PW'    => 'Palau',
                'PS'    => 'Palestinian Territory, Occupied',
                'PA'    => 'Panama',
                'PG'    => 'Papua New Guinea',
                'PY'    => 'Paraguay',
                'PE'    => 'Peru',
                'PH'    => 'Philippines',
                'PN'    => 'Pitcairn',
                'PL'    => 'Pologne',
                'PT'    => 'Portugal',
                'PR'    => 'Puerto Rico',
                'QA'    => 'Qatar',
                'RE'    => 'Reunion',
                'RO'    => 'Roumanie',
                'RU'    => 'Russie',
                'RW'    => 'Rwanda',
                'BL'    => 'Saint Barthelemy',
                'SH'    => 'Saint Helena',
                'KN'    => 'Saint Kitts And Nevis',
                'LC'    => 'Saint Lucia',
                'MF'    => 'Saint Martin',
                'PM'    => 'Saint Pierre And Miquelon',
                'VC'    => 'Saint Vincent And Grenadines',
                'WS'    => 'Samoa',
                'SM'    => 'San Marino',
                'ST'    => 'Sao Tome And Principe',
                'SA'    => 'Saudi Arabia',
                'SN'    => 'Senegal',
                'RS'    => 'Serbie',
                'SC'    => 'Seychelles',
                'SL'    => 'Sierra Leone',
                'SG'    => 'Singapore',
                'SK'    => 'Slovakia',
                'SI'    => 'Slovénie',
                'SB'    => 'Solomon Islands',
                'SO'    => 'Somalia',
                'ZA'    => 'South Africa',
                'GS'    => 'South Georgia And Sandwich Isl.',
                'ES'    => 'Espagne',
                'LK'    => 'Sri Lanka',
                'SD'    => 'Sudan',
                'SR'    => 'Suriname',
                'SJ'    => 'Svalbard And Jan Mayen',
                'SZ'    => 'Swaziland',
                'SE'    => 'Suède',
                'CH'    => 'Suisse',
                'SY'    => 'Syrie',
                'TW'    => 'Taiwan',
                'TJ'    => 'Tajikistan',
                'TZ'    => 'Tanzania',
                'TH'    => 'Thailand',
                'TL'    => 'Timor-Leste',
                'TG'    => 'Togo',
                'TK'    => 'Tokelau',
                'TO'    => 'Tonga',
                'TT'    => 'Trinidad And Tobago',
                'TN'    => 'Tunisie',
                'TR'    => 'Turquie',
                'TM'    => 'Turkmenistan',
                'TC'    => 'Turks And Caicos Islands',
                'TV'    => 'Tuvalu',
                'UG'    => 'Uganda',
                'UA'    => 'Ukraine',
                'AE'    => 'United Arab Emirates',
                'GB'    => 'United Kingdom',
                'US'    => 'United States',
                'UM'    => 'United States Outlying Islands',
                'UY'    => 'Uruguay',
                'UZ'    => 'Uzbekistan',
                'VU'    => 'Vanuatu',
                'VE'    => 'Venezuela',
                'VN'    => 'Viet Nam',
                'VG'    => 'Virgin Islands, British',
                'VI'    => 'Virgin Islands, U.S.',
                'WF'    => 'Wallis And Futuna',
                'EH'    => 'Western Sahara',
                'YE'    => 'Yemen',
                'ZM'    => 'Zambia',
                'ZW'    => 'Zimbabwe'
            ],
            'description'       => 'Country.',
            'default'           => 'all'
        ],
        'price_vate' => [
            'type'              => 'float',
            'description'       => 'Price of the sojourn VAT excluded.'
        ],
        'price_vati' => [
            'type'              => 'float',
            'description'       => 'Price of the sojourn VAT included.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$result = array_merge(
    eQual::run('get', 'sale_booking_stats_stat-contracts-history', $params),
    eQual::run('get', 'sale_booking_stats_stat-contracts-discope', $params)
);

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();

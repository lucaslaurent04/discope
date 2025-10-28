<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2025
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use identity\Identity;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\catalog\Product;
use sale\customer\Customer;

[$params, $providers] = eQual::announce([
    'description'   => 'Lists all contracts and their related details for a given period.',
    'params'        => [
        /* mixed-usage parameters: required both for fetching data (input) and property of virtual entity (output) */
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => "Output: Center of the sojourn / Input: The center for which the stats are required.",
            'default'           => fn() => Center::search()->ids()[0] ?? null
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Output: Day of arrival / Input: Date interval lower limit (defaults to first day of previous month).",
            'default'           => mktime(0, 0, 0, date("m")-1, 1)
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Output: Day of departure / Input: Date interval upper limit (defaults to last day of previous month).",
            'default'           => mktime(0, 0, 0, date("m"), 0)
        ],
        'organisation_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Identity',
            'description'       => "The organisation the establishment belongs to.",
            'domain'            => ['id', '<', 6]
        ],
        'status' => [
            'type'              => 'string',
            'selection'         => [
                'all'               => 'Tous',
                'quote'             => 'Devis',
                'option'            => 'Option',
                'confirmed'         => 'Confirmée',
                'validated'         => 'Validée',
                'checkedin'         => 'En cours',
                'checkedout'        => 'Terminée',
                'proforma'          => 'Pro forma',
                'invoiced'          => 'Facturée',
                'debit_balance'     => 'Solde débiteur',
                'credit_balance'    => 'Solde créditeur',
                'balanced'          => 'Clôturée',
                'cancelled'         => 'Annulée'
            ],
            'description'       => 'Status of the booking.',
            'default'           => 'all'
        ],
        'type_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\BookingType',
            'description'       => "The kind of booking it is about."
        ],
        'rate_class_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\customer\RateClass',
            'description'       => "The rate class of the customer.",
        ],
        'customer_type_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\IdentityType',
            'description'       => "Identity type of the customer."
        ],

        /* parameters used as properties of virtual entity */

        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],
        'booking' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],
        'booking_status' => [
            'type'              => 'string',
            'description'       => 'Status of the booking (quote, option, confirmed, invoiced).',
            'selection'         => [
                'quote',
                'option',
                'confirmed',
                'invoiced'
            ]
        ],
        'type' => [
            'type'              => 'string',
            'description'       => 'Type of the booking.'
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
        'nb_freebies' => [
            'type'              => 'integer',
            'description'       => 'Number of hosted persons for free.'
        ],
        'nb_nights' => [
            'type'              => 'integer',
            'description'       => 'Duration of the sojourn (number of nights).'
        ],
        'nb_pers_nights' => [
            'type'              => 'integer',
            'description'       => 'Number of nights/accommodations.'
        ],
        'nb_rental_units' => [
            'type'              => 'integer',
            'description'       => 'Number of rental units (accommodations) involved in the sojourn.'
        ],
        'nb_activities' => [
            'type'              => 'integer',
            'description'       => 'Number of booking activities involved in the sojourn.'
        ],
        'rate_class' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_type' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_name' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_zip' => [
            'type'              => 'string',
            'description'       => 'Internal code of the related booking.'
        ],
        'customer_area' => [
            'type'              => 'string',
            'selection'         => [
                'all'       => 'Tous',
                'FR-01'     => 'Ain (01)',
                'FR-02'     => 'Aisne (02)',
                'FR-03'     => 'Allier (03)',
                'FR-04'     => 'Alpes-de-Haute-Provence (04)',
                'FR-05'     => 'Hautes-Alpes (05)',
                'FR-06'     => 'Alpes-Maritimes (06)',
                'FR-07'     => 'Ardèche (07)',
                'FR-08'     => 'Ardennes (08)',
                'FR-09'     => 'Ariège (09)',
                'FR-10'     => 'Aube (10)',
                'FR-11'     => 'Aube (11)',
                'FR-12'     => 'Aveyron (12)',
                'FR-13'     => 'Bouches-du-Rhône (13)',
                'FR-14'     => 'Calvados (14)',
                'FR-15'     => 'Cantal (15)',
                'FR-16'     => 'Charente (16)',
                'FR-17'     => 'Charente-Maritime (17)',
                'FR-18'     => 'Cher (18)',
                'FR-19'     => 'Corrèze (19)',
                'FR-21'     => 'Côte-d\'Or (21)',
                'FR-22'     => 'Côtes-d\'Armor (22)',
                'FR-23'     => 'Creuse (23)',
                'FR-24'     => 'Dordogne (24)',
                'FR-25'     => 'Doubs (25)',
                'FR-26'     => 'Drôme (26)',
                'FR-27'     => 'Eure (27)',
                'FR-28'     => 'Eure-et-Loir (28)',
                'FR-29'     => 'Finistère (29)',
                'FR-2A'     => 'Corse-du-Sud (2A)',
                'FR-2B'     => 'Haute-Corse (2B)',
                'FR-30'     => 'Gard (30)',
                'FR-31'     => 'Haute-Garonne (31)',
                'FR-32'     => 'Gers (32)',
                'FR-33'     => 'Gironde (33)',
                'FR-34'     => 'Hérault (34)',
                'FR-35'     => 'Ille-et-Vilaine (35)',
                'FR-36'     => 'Indre (36)',
                'FR-37'     => 'Indre-et-Loire (37)',
                'FR-38'     => 'Isère (38)',
                'FR-39'     => 'Bourgogne-Franche-Comté (39)',
                'FR-40'     => 'Landes (40)',
                'FR-41'     => 'Loir-et-Cher (41)',
                'FR-42'     => 'Loire (42)',
                'FR-43'     => 'Haute-Loire (43)',
                'FR-44'     => 'Loire-Atlantique (44)',
                'FR-45'     => 'Loiret (45)',
                'FR-46'     => 'Lot (46)',
                'FR-47'     => 'Lot-et-Garonne (47)',
                'FR-48'     => 'Lozère (48)',
                'FR-49'     => 'Maine-et-Loire (49)',
                'FR-50'     => 'Manche (50)',
                'FR-51'     => 'Marne (51)',
                'FR-52'     => 'Haute-Marne (52)',
                'FR-53'     => 'Mayenne (53)',
                'FR-54'     => 'Meurthe-et-Moselle (54)',
                'FR-55'     => 'Meuse (55)',
                'FR-56'     => 'Morbihan (56)',
                'FR-57'     => 'Moselle (57)',
                'FR-58'     => 'Nièvre (58)',
                'FR-59'     => 'Nord (59)',
                'FR-60'     => 'Oise (60)',
                'FR-61'     => 'Orne (61)',
                'FR-62'     => 'Pas-de-Calais (62)',
                'FR-63'     => 'Puy-de-Dôme (63)',
                'FR-64'     => 'Pyrénées-Atlantiques (64)',
                'FR-65'     => 'Hautes-Pyrénées (65)',
                'FR-66'     => 'Pyrénées-Orientales (66)',
                'FR-67'     => 'Bas-Rhin (67)',
                'FR-68'     => 'Haut-Rhin (68)',
                'FR-69'     => 'Rhône (69)',
                'FR-70'     => 'Haute-Saône (70)',
                'FR-71'     => 'Saône-et-Loire (71)',
                'FR-72'     => 'Sarthe (72)',
                'FR-73'     => 'Savoie (73)',
                'FR-74'     => 'Haute-Savoie (74)',
                'FR-75'     => 'Paris (75)',
                'FR-76'     => 'Seine-Maritime (76)',
                'FR-77'     => 'Seine-et-Marne (77)',
                'FR-78'     => 'Yvelines (78)',
                'FR-79'     => 'Deux-Sèvres (79)',
                'FR-80'     => 'Somme (80)',
                'FR-81'     => 'Tarn (81)',
                'FR-82'     => 'Tarn-et-Garonne (82)',
                'FR-83'     => 'Var (83)',
                'FR-84'     => 'Vaucluse (84)',
                'FR-85'     => 'Vendée (85)',
                'FR-86'     => 'Vienne (86)',
                'FR-87'     => 'Haute-Vienne (87)',
                'FR-88'     => 'Vosges (88)',
                'FR-89'     => 'Yonne (89)',
                'FR-90'     => 'Territoire de Belfort (90)',
                'FR-91'     => 'Essonne (91)',
                'FR-92'     => 'Hauts-de-Seine (92)',
                'FR-93'     => 'Seine-Saint-Denis (93)',
                'FR-94'     => 'Val-de-Marne (94)',
                'FR-95'     => 'Val-d\'Oise (95)',
                'FR-971'    => 'Guadeloupe (971)',
                'FR-972'    => 'Martinique (972)',
                'FR-973'    => 'Guyane (973)',
                'FR-974'    => 'La Réunion (974)',
                'FR-976'    => 'Mayotte (976)',
            ],
            'description'       => 'Customer country area (for France \'Département\').',
            'default'           => 'all'
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
        'turnover_membership' => [
            'type'              => 'float',
            'description'       => 'Turnover amount for membership fees.'
        ],
        'turnover_nights_meals' => [
            'type'              => 'float',
            'description'       => 'Turnover amount for nights and meals fees.'
        ],
        'turnover_animations' => [
            'type'              => 'float',
            'description'       => 'Turnover amount for internal animations fees.'
        ],
        'turnover_providers' => [
            'type'              => 'float',
            'description'       => 'Turnover amount for external animations and transports fees.'
        ],
        'turnover_laundry' => [
            'type'              => 'float',
            'description'       => 'Turnover amount for laundry fees.'
        ],
        'turnover_total' => [
            'type'              => 'float',
            'description'       => 'Turnover total amount.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*',
        'cacheable'     => true,
        'expires'       => (60*60*1)
    ],
    'providers'     => ['context', 'orm', 'adapt']
]);

/**
 * @var \equal\php\Context                      $context
 * @var \equal\orm\ObjectManager                $orm
 * @var \equal\data\adapt\DataAdapterProvider   $adapter_provider
 */
['context' => $context, 'orm' => $orm, 'adapt' => $adapter_provider] = $providers;

/** @var \equal\data\adapt\DataAdapterJson */
$adapter = $adapter_provider->get('json');

// #memo - we consider all bookings for which at least one sojourn finishes during the given period
// #memo - only date_to matters: we collect all bookings that finished during the selection period (this is also the way stats are done in the accounting software)

$domain = [];
if(($params['center_id'] || $params['organisation_id'])){
    $domain = [
        ['date_to', '>=', $params['date_from']],
        ['date_to', '<=', $params['date_to']],
        ['state', 'in', ['instance', 'archive']],
        ['is_cancelled', '=', false]
    ];

    if($params['center_id'] && $params['center_id'] > 0) {
        $domain[] = [ 'center_id', '=', $params['center_id'] ];
    }

    if($params['organisation_id'] && $params['organisation_id'] > 0) {
        $domain[] = [ 'organisation_id', '=', $params['organisation_id'] ];
    }

    if(isset($params['status']) && $params['status'] !== 'all') {
        $domain[] = [ 'status', '=', $params['status'] ];
    }

    if(isset($params['type_id']) && $params['type_id'] > 0) {
        $domain[] = [ 'type_id', '=', $params['type_id'] ];
    }

    if($params['customer_type_id'] && $params['customer_type_id'] > 0) {
        $type_customers_ids = Customer::search([
            ['customer_type_id', '=', $params['customer_type_id']],
            ['relationship', '=', 'customer']
        ])
            ->ids();

        $domain[] = ['customer_id', 'in', $type_customers_ids];
    }

    if($params['rate_class_id'] && $params['rate_class_id'] > 0) {
        $rate_class_customers_ids = Customer::search([
            ['rate_class_id', '=', $params['rate_class_id']],
            ['relationship', '=', 'customer']
        ])
            ->ids();

        if(!empty($rate_class_customers_ids)) {
            $domain[] = ['customer_id', 'in', $rate_class_customers_ids];
        }
        else {
            $domain[] = ['customer_id', 'in', -1];
        }
    }

    if(isset($params['customer_area']) && substr($params['customer_area'], 0, 3) === 'FR-') {
        $area_identities_ids = Identity::search([
            ['address_country', '=', 'FR'],
            ['address_zip', 'like', substr($params['customer_area'], 3).'%']
        ])->ids();

        $area_customers_ids = Customer::search([
            ['partner_identity_id', 'in', $area_identities_ids],
            ['relationship', '=', 'customer']
        ])
            ->ids();



        if(!empty($area_customers_ids)) {
            $domain[] = ['customer_id', 'in',  $area_customers_ids];
        }
        else {
            $domain[] = ['customer_id', 'in', [-1]];
        }
    }
    elseif(isset($params['customer_country']) && $params['customer_country'] !== 'all') {
        $country_identities_ids = Identity::search(['address_country', '=', $params['customer_country']])->ids();

        $country_customers_ids = Customer::search([
            ['partner_identity_id', 'in', $country_identities_ids],
            ['relationship', '=', 'customer']
        ])
            ->ids();

        if(!empty($country_customers_ids)) {
            $domain[] = ['customer_id', 'in',  $country_customers_ids];
        }
        else {
            $domain[] = ['customer_id', 'in', [-1]];
        }
    }
}

$bookings = [];

if(!empty($domain)){
    $bookings = Booking::search($domain, ['sort'  => ['date_from' => 'asc']])
        ->read([
            'id',
            'created',
            'name',
            'date_from',
            'date_to',
            'total',
            'price',
            'status',
            'booking_activities_ids',
            'type_id'                   => ['name'],
            'center_id'                 => ['id', 'name', 'sojourn_type_id' => ['name']],
            'customer_id'               => ['rate_class_id' => ['name'], 'customer_type_id' => ['name']],
            'customer_identity_id'      => [
                'id',
                'name',
                'lang_id' => ['id', 'name'],
                'address_zip',
                'address_country'
            ],
            'booking_lines_groups_ids' => [
                'group_type',
                'nb_pers',
                'nb_nights',
                'rental_unit_assignments_ids'   => ['id', 'is_accomodation', 'qty'],
                'age_range_assignments_ids'     => ['free_qty']
            ]
        ])
        ->get(true);
}

$map_turnovers_accounts = [
    // OK - frais d'adhésion - Hébergement pension complète : petit déjeuner, lunch, goûter, repas, nuitée + fournitures des draps + lits faits + supplément ménage + supplément chambre simple + repas passage
    'turnover_membership'   => ['75620000'],
    // OK - centre de séjours PC
    'turnover_nights_meals' => ['70831000'],
    // OK - animateur Valrance
    'turnover_animations'   => ['70833000'],
    // OK - Lessives
    'turnover_laundry'      => ['70882200'],
    'turnover_providers'    => [
        // OK - Intervenants MFV - Prestataire externe avec fournitures associées (hors transport et cirque)
        '75814000',
        // OK - Cirque - Intervenant cirque et fournitures associées
        '75815000',
        // OK - Navette - Transports
        '70882400',
        // OK - Taxe de séjour
        '44700000',
        // OK - Photocopies
        '70882000',
        // OK - Assurances annulation
        '46840000',
        // OK - Frais médicaux
        '4671000',
        // OK - Timbres, affranchissements
        '70882300',
        // OK - États des lieux + Frais de virement
        '75813000'
    ]
];
$map_accounts_turnover = [];
foreach($map_turnovers_accounts as $turnover => $accounts) {
    foreach($accounts as $account) {
        $map_accounts_turnover[$account] = $turnover;
    }
}

$result = [];

foreach($bookings as $booking) {
    // nb_nights depends on booking
    $booking_nb_nights = round( ($booking['date_to'] - $booking['date_from']) / (3600*24) );
    // nb_rental_unit, nb_pers and nb_freebies depend on sojourns
    $booking_nb_rental_units = 0;
    $booking_nb_pers = 0;
    $booking_nb_freebies = 0;

    $count_nb_pers_nights = 0;
    $count_nb_room_nights = 0;

    $turnover_amounts = [
        'turnover_membership'   => 0,
        'turnover_nights_meals' => 0,
        'turnover_animations'   => 0,
        'turnover_providers'    => 0,
        'turnover_laundry'      => 0,
        'turnover_total'        => 0
    ];

    foreach($booking['booking_lines_groups_ids'] as $group) {
        // retrieve all lines relating to an accommodation
        $lines = BookingLine::search([
                ['booking_line_group_id', '=', $group['id']]
            ])
            ->read([
                'name',
                'qty',
                'price',
                'qty_accounting_method',
                'product_id',
                'price_id' => ['accounting_rule_id' => ['accounting_rule_line_ids' => ['id', 'account', 'share']]],
                'booking_activity_id' => [
                    'activity_booking_line_id' => [
                        'price_id' => ['accounting_rule_id' => ['accounting_rule_line_ids' => ['id', 'account', 'share']]],
                    ]
                ]
            ])
            ->get(true);

        /*
            Sojourn related stats
        */
        if($group['group_type'] === 'sojourn') {
            $sojourn_nb_freebies = 0;
            foreach($group['age_range_assignments_ids'] as $assignment) {
                $sojourn_nb_freebies += $assignment['free_qty'];
            }

            $sojourn_nb_pers = (count($lines)) ? $group['nb_pers'] : 0;
            $sojourn_nb_nights = (count($lines)) ? $group['nb_nights'] : 0;

            $sojourn_nb_accommodations = count(array_filter($group['rental_unit_assignments_ids'], fn($a) => $a['is_accomodation']));

            $booking_nb_rental_units += $sojourn_nb_accommodations;
            $booking_nb_pers += $sojourn_nb_pers;
            $booking_nb_freebies += $sojourn_nb_freebies;

            $count_nb_pers_nights += $sojourn_nb_pers * $sojourn_nb_nights;
            $count_nb_room_nights += $sojourn_nb_nights * $sojourn_nb_accommodations;
        }

        /*
            Global stats
        */
        foreach($lines as $line) {
            $accounting_rule_lines = $line['price_id']['accounting_rule_id']['accounting_rule_line_ids'];
            if(!is_null($line['booking_activity_id']['activity_booking_line_id']) && $line['booking_activity_id']['activity_booking_line_id']['id'] !== $line['id']) {
                // handle product that are part of activity as activity
                $accounting_rule_lines = $line['booking_activity_id']['activity_booking_line_id']['price_id']['accounting_rule_id']['accounting_rule_line_ids'];
            }

            foreach($accounting_rule_lines as $accounting_rule_line) {
                $account_amount = $line['price'] * $accounting_rule_line['share'];

                $turnover_amounts[$map_accounts_turnover[$accounting_rule_line['account']]] += $account_amount;
                $turnover_amounts['turnover_total'] += $account_amount;
            }
        }
    }

    $area = null;
    if($booking['customer_identity_id']['address_country'] === 'FR') {
        $area = substr($booking['customer_identity_id']['address_zip'], 0, 2);
        if($area === '97' && strlen($booking['customer_identity_id']['address_zip'] >= 3)) {
            $area = substr($booking['customer_identity_id']['address_zip'], 0, 3);
        }
        $area = 'FR-'.$area;
    }

    // status: quote, option, confirmed and invoiced (simplification)
    $status = $booking['status'];
    if(in_array($status, ['validated', 'checkedin', 'checkedout', 'proforma'])) {
        $status = 'confirmed';
    }
    elseif(in_array($status, ['invoiced', 'debit_balance', 'credit_balance', 'balanced'])) {
        $status = 'invoiced';
    }

    // #memo - one entry by booking
    $result[] = [
        'center'                => $booking['center_id']['name'],
        'center_type'           => $booking['center_id']['sojourn_type_id']['name'],
        'booking'               => $booking['name'],
        'type'                  => $booking['type_id']['name'],
        'booking_status'        => $status,
        'created'               => $adapter->adaptOut($booking['created'], 'date'),
        'created_aamm'          => date('Y-m', $booking['created']),
        'date_from'             => $adapter->adaptOut($booking['date_from'], 'date'),
        'date_to'               => $adapter->adaptOut($booking['date_to'], 'date'),
        'aamm'                  => date('Y/m', $booking['date_from']),
        'year'                  => date('Y', $booking['date_from']),
        'nb_pers'               => $booking_nb_pers,
        'nb_freebies'           => $booking_nb_freebies,
        'nb_nights'             => $booking_nb_nights,
        'nb_rental_units'       => $booking_nb_rental_units,
        'nb_pers_nights'        => $count_nb_pers_nights,
        'nb_room_nights'        => $count_nb_room_nights,
        'nb_activities'         => count($booking['booking_activities_ids']),
        'rate_class'            => $booking['customer_id']['rate_class_id']['name'],
        'customer_type'         => $booking['customer_id']['customer_type_id']['name'],
        'customer_name'         => $booking['customer_identity_id']['name'],
        'customer_lang'         => $booking['customer_identity_id']['lang_id']['name'],
        'customer_zip'          => $booking['customer_identity_id']['address_zip'],
        'customer_area'         => $area,
        'customer_country'      => $booking['customer_identity_id']['address_country'],
        'price_vate'            => $booking['total'],
        'price_vati'            => $booking['price'],
        'turnover_membership'   => $turnover_amounts['turnover_membership'],
        'turnover_nights_meals' => $turnover_amounts['turnover_nights_meals'],
        'turnover_animations'   => $turnover_amounts['turnover_animations'],
        'turnover_providers'    => $turnover_amounts['turnover_providers'],
        'turnover_laundry'      => $turnover_amounts['turnover_laundry'],
        'turnover_total'        => $turnover_amounts['turnover_total']
    ];
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();

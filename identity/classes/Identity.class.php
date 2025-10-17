<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;

use documents\Document;
use equal\data\DataGenerator;
use equal\orm\Model;
use sale\booking\Booking;
use sale\booking\Invoice;
use core\setting\Setting;

/**
 * This class is meant to be used as an interface for other entities (organisation and partner).
 * An identity is either a legal or natural person (Legal persons are Organisations).
 * An organisation usually has several partners of various kind (contact, employee, provider, customer, ...).
 */
class Identity extends Model {

    public static function getName() {
        return "Identity";
    }

    public static function getDescription() {
        return "An Identity is either a legal or natural person: organisations are legal persons and users, contacts and employees are natural persons.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'             => 'alias',
                'alias'            => 'display_name'
            ],

            'display_name' => [
                'type'              => 'computed',
                'function'          => 'calcDisplayName',
                'result_type'       => 'string',
                'store'             => true,
                'description'       => 'The display name of the identity.',
                'help'              => "
                    The display name is a computed field that returns a concatenated string containing either the firstname+lastname, or the legal name of the Identity, based on the kind of Identity.\n
                    For instance, 'display_name', for a company with \"My Company\" as legal name will return \"My Company\". \n
                    Whereas, for an individual having \"John\" as firstname and \"Smith\" as lastname, it returns \"John Smith\".
                "
            ],

            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\IdentityType',
                'onupdate'          => 'onupdateTypeId',
                'default'           => Setting::get_value('identity', 'organization', 'identity_type_default', 1),
                'description'       => 'Type of identity.',
                'dependents'        => ['type']
            ],

            'type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    'I'  => 'Individual (natural person)',
                    'SE' => 'Self-employed',
                    'C'  => 'Company',
                    'NP' => 'Non-profit organisation',
                    'PA' => 'Public administration'
                ],
                'function'          => 'calcType',
                'readonly'          => true,
                'store'             => true,
                'description'       => 'Code of the type of identity.',
                'help'              => 'This value has to be changed through type_id'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'A short reminder to help user identify the targeted person and its specifics.'
            ],

            'bank_account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn:iban',
                'description'       => "Number of the bank account of the Identity, if any.",
                'visible'           => [ ['has_parent', '=', false] ]
            ],

            'bank_account_bic' => [
                'type'              => 'string',
                'description'       => "Identifier of the Bank related to the Identity's bank account, when set.",
                'visible'           => [ ['has_parent', '=', false] ]
            ],

            'signature' => [
                'type'              => 'string',
                'usage'             => 'text/html:2000000',
                'description'       => 'Identity signature to append to communications.',
                'multilang'         => true
            ],

            /*
                Fields specific to organisations
            */
            'legal_name' => [
                'type'              => 'string',
                'description'       => 'Full name of the Identity.',
                'visible'           => [ ['type', '<>', 'I'] ],
                'onupdate'          => 'onupdateName'
            ],
            'short_name' => [
                'type'              => 'string',
                'description'       => 'Usual name to be used as a memo for identifying the organisation (acronym or short name).',
                'visible'           => [ ['type', '<>', 'I'] ],
                'onupdate'          => 'onupdateName',
                'generation'        => 'generateShortName'
            ],
            'has_vat' => [
                'type'              => 'boolean',
                'description'       => 'Does the organisation have a VAT number?',
                'visible'           => [ ['type', '<>', 'I'], ['has_parent', '=', false] ],
                'default'           => false
            ],
            'vat_number' => [
                'type'              => 'string',
                'description'       => 'Value Added Tax identification number, if any.',
                'visible'           => [ ['has_vat', '=', true], ['type', '<>', 'I'], ['has_parent', '=', false] ]
            ],
            'registration_number' => [
                'type'              => 'string',
                'description'       => 'Organisation registration number (company number).',
                'visible'           => [ ['type', '<>', 'I'] ]
            ],

            /*
                Fields specific to citizen: children organisations and parent company, if any
            */
            'citizen_identification' => [
                'type'              => 'string',
                'description'       => 'Citizen registration number, if any.',
                'visible'           => [ ['type', '=', 'I'] ]
            ],

            'nationality' => [
                'type'              => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'The country the person is citizen of.',
                'default'           => Setting::get_value('identity', 'organization', 'country_default', 'BE')
            ],

            /*
                Relational fields specific to organisations: children organisations and parent company, if any
            */
            'children_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Identity',
                'foreign_field'     => 'parent_id',
                'domain'            => ['type', '<>', 'I'],
                'description'       => 'Children departments of the organisation, if any.',
                'visible'           => [ ['type', '<>', 'I'] ]
            ],

            'has_parent' => [
                'type'              => 'boolean',
                'description'       => 'Does the identity have a parent organisation?',
                'visible'           => [ ['type', '<>', 'I'] ],
                'default'           => false
            ],

            'parent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'domain'            => ['type', '<>', 'I'],
                'description'       => 'Parent company of which the organisation is a branch (department), if any.',
                'visible'           => [ ['has_parent', '=', true] ]
            ],

            'employees_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Partner',
                'foreign_field'     => 'owner_identity_id',
                'domain'            => ['relationship', '=', 'employee'],
                'description'       => 'List of employees of the organisation, if any.' ,
                'visible'           => [ ['type', '<>', 'I'] ]
            ],

            'customers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Partner',
                'foreign_field'     => 'owner_identity_id',
                'domain'            => ['relationship', '=', 'customer'],
                'description'       => 'List of customers of the organisation, if any.',
                'visible'           => [ ['type', '<>', 'I'] ]
            ],

            'providers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Partner',
                'foreign_field'     => 'owner_identity_id',
                'domain'            => ['relationship', '=', 'provider'],
                'description'       => 'List of providers of the organisation, if any.',
                'visible'           => [ ['type', '<>', 'I'] ]
            ],

            // Any Identity can have several contacts
            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Contact',
                'foreign_field'     => 'owner_identity_id',
                'domain'            => [ ['partner_identity_id', '<>', 'object.id'] ],
                'description'       => 'List of contacts related to the organisation (not necessarily employees), if any.'
            ],

            'accounting_account' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Unique code identifying the associated accounting account.",
                'function'          => 'calcAccountingAccount',
                'store'             => true
            ],

            /*
                Description of the Identity address.
                For organisations this is the official (legal) address (typically headquarters, but not necessarily)
            */
            'address_street' => [
                'type'              => 'string',
                'description'       => 'Street and number.'
            ],

            'address_dispatch' => [
                'type'              => 'string',
                'description'       => 'Optional info for mail dispatch (apartment, box, floor, ...).'
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => 'City.',
                'onupdate'          => 'onupdateAddress'
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => 'Postal code.',
                'onupdate'          => 'onupdateAddress'
            ],

            'address_state' => [
                'type'              => 'string',
                'description'       => 'State or region.',
                'onupdate'          => 'onupdateAddress'
            ],

            'address_country' => [
                'type'              => 'string',
                'usage'             => 'country/iso-3166:2',
                'description'       => 'Country.',
                'default'           => Setting::get_value('identity', 'organization', 'country_default', 'BE'),
                'selection'         => [
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
                'onupdate'          => 'onupdateAddress'
            ],

            /*
                Additional official contact details.
                For individuals these are personal contact details, whereas for companies these are official (registered) details.
            */
            'email' => [
                'type'              => 'string',
                'usage'             => 'email',
                'onupdate'          => 'onupdateEmail',
                'description'       => "Identity main email address."
            ],

            'phone' => [
                'type'              => 'string',
                // #memo - too many users input variations for generic validation
                // 'usage'             => 'phone',
                'onupdate'          => 'onupdatePhone',
                'description'       => "Identity main phone number (mobile or landline)."
            ],

            'mobile' => [
                'type'              => 'string',
                // #memo - too many users input variations for generic validation
                // 'usage'             => 'phone',
                'onupdate'          => 'onupdateMobile',
                'description'       => "Identity mobile phone number."
            ],

            'fax' => [
                'type'              => 'string',
                // #memo - too many users input variations for generic validation
                // 'usage'             => 'phone',
                'description'       => "Identity main fax number."
            ],

            // Companies can also have an official website.
            'website' => [
                'type'              => 'string',
                'usage'             => 'uri/url',
                'description'       => 'Organisation main official website URL, if any.',
                'visible'           => ['type', '<>', 'I']
            ],

            // an identity can have several addresses
            'addresses_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Address',
                'foreign_field'     => 'identity_id',
                'description'       => 'List of addresses related to the identity.',
            ],

            /*
                For organisations, there might be a reference person: a person who is entitled to legally represent the organisation (typically the director, the manager, the CEO, ...).
                These contact details are commonly requested by service providers for validating the identity of an organisation.
            */
            'reference_partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'domain'            => ['relationship', '=', 'contact'],
                'description'       => 'Contact (natural person) that can legally represent the organisation.',
                'onupdate'          => 'onupdateReferencePartnerId',
                'visible'           => [ ['type', '<>', 'I'], ['type', '<>', 'SE'] ]
            ],

            /*
                For individuals, the identity might be related to a user.
            */
            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => 'User associated to this identity.',
                'visible'           => ['type', '=', 'I']
            ],

            /*
                Contact details.
                For individuals, these are the contact details of the person herself.
            */
            'firstname' => [
                'type'              => 'string',
                'description'       => "Full name of the contact (must be a person, not a role).",
                'visible'           => ['type', '=', 'I'],
                'onupdate'          => 'onupdateName'
            ],

            'lastname' => [
                'type'              => 'string',
                'description'       => 'Reference contact surname.',
                'visible'           => ['type', '=', 'I'],
                'onupdate'          => 'onupdateName'
            ],

            'gender' => [
                'type'              => 'string',
                'selection'         => ['M' => 'Male', 'F' => 'Female', 'X' => 'Non-binary'],
                'description'       => 'Reference contact gender.',
                'visible'           => ['type', '=', 'I'],
                'default'           => 'M'
            ],

            'title' => [
                'type'              => 'string',
                'selection'         => ['Dr' => 'Doctor', 'Ms' => 'Miss', 'Mrs' => 'Misses', 'Mr' => 'Mister', 'Pr' => 'Professor'],
                'description'       => 'Reference contact title.',
                'visible'           => ['type', '=', 'I']
            ],

            'date_of_birth' => [
                'type'              => 'date',
                'description'       => 'Date of birth.',
                'visible'           => ['type', '=', 'I']
            ],

            'lang_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'core\Lang',
                'description'       => "Preferred language of the identity.",
                'default'           => 2,
                'onupdate'          => 'onupdateLangId'
            ],

            'email_secondary' => [
                'type'              => 'string',
                'usage'             => 'email',
                'description'       => "Identity secondary email address."
            ],

            // field for retrieving all partners related to the identity
            'partners_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\customer\Customer',
                'foreign_field'     => 'partner_identity_id',
                'description'       => 'Partnerships that relate to the identity.',
                'domain'            => ['owner_identity_id', '<>', 'object.id']
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'foreign_field'     => 'partner_identity_id',
                'description'       => 'Customer associated to this identity, if any.'
            ],

            'flag_latepayer' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Mark a customer as bad payer.'
            ],

            'flag_damage' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Mark a customer with a damage history.'
            ],

            'flag_nuisance' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Mark a customer with a disturbances history.'
            ],

            'flag_trusted' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Mark a customer as trusted.'
            ],

            // handle duplicate
            'has_duplicate_clue' => [
                'type'              => 'boolean',
                'default'           => false,
                'description'       => 'Uncheck to force creation.',
                'help'              => 'Alert user that identity under creation may be a duplicate.'
            ],

            'duplicate_clue_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Identity that may be a duplicate.',
                'help'              => 'Showed to user when creating new identity.'
            ],

            'duplicate_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Identity that is a duplicate.',
            ],

            'is_duplicate' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Is a duplicate of another identity.',
                'store'             => true,
                'function'          => 'calcIsDuplicate',
                'onupdate'          => 'onupdateIsDuplicate'
            ],

            'duplicate_identities_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'identity\Identity',
                'foreign_field'     => 'duplicate_identity_id',
                'description'       => 'List of possible duplicates.',
                'domain'            => ['is_duplicate', '<>', false]
            ],

            'bookings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Booking',
                'foreign_field'     => 'customer_identity_id',
                'description'       => 'List of bookings relating to the identity.'
            ],

            'invoices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Invoice',
                'foreign_field'     => 'customer_identity_id',
                'description'       => 'List of invoices relating to the identity (as customer).'
            ],

            'is_ota' => [
                'type'              => 'boolean',
                'description'       => 'Is the identity from OTA origin.',
                'default'           => false
            ],

            'is_readonly' => [
                'type'              => 'boolean',
                'description'       => 'Is the identity readonly, used for static identities that should not be updated trivially.',
                'default'           => false,
                'readonly'          => true
            ],

            'logo_document_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\Document',
                'description'       => 'The document containing the logo associated with the identity.',
                'visible'           => ['type' ,'<>' , 'I']
            ],


        ];
    }

    public static function calcType($self) {
        $result = [];
        $self->read(['type_id' => ['code']]);
        foreach($self as $id => $identity) {
            if(isset($identity['type_id']['code'])) {
                $result[$id] = $identity['type_id']['code'];
            }
        }
        return $result;
    }

    public static function calcAccountingAccount($self) {
        $result = [];
        $self->read(['id','name']);
        foreach($self as $id => $identity) {
            $prefix_account = Setting::get_value('identity', 'accounting', 'customer_account.prefix', '411');
            $format = Setting::get_value('identity', 'accounting', 'customer_account.sequence_format', '%3d{prefix}%05d{sequence}');

            $sequence_account = Setting::get_value('identity', 'accounting', 'customer_account.sequence', 1);
            // #todo - #kaleo - this is an exception to follow Kaleo specific logic that could be configured in a standard way
            if($sequence_account <= 1) {
                $result[$id] = (string) $id;
            }
            else {
                $accounting_account = null;
                do {
                    Setting::set_value('identity', 'accounting', 'customer_account.sequence', $sequence_account + 1);

                    $accounting_account = Setting::parse_format($format, [
                            'prefix'    => $prefix_account,
                            'sequence'  => $sequence_account
                        ]);

                    $existingIdentity = Identity::search(['accounting_account', '=', $accounting_account])->first();
                    ++$sequence_account;
                }
                while($existingIdentity);

                if($accounting_account) {
                    $result[$id] = $accounting_account;
                }
            }

        }
        return $result;
    }

    /**
     * For organisations the display name is the legal name
     * For individuals, the display name is the concatenation of first and last names
     */
    public static function calcDisplayName($om, $oids, $lang) {
        $result = [];

        $person_format = Setting::get_value('identity', 'organization', 'identity.person.name_format', '%s{firstname} %s{lastname}');
        $entity_format = Setting::get_value('identity', 'organization', 'identity.entity.name_format', '%s{short_name} %s{legal_name}');

        $res = $om->read(self::getType(), $oids, ['type_id', 'firstname', 'lastname', 'legal_name', 'short_name', 'address_city']);
        foreach($res as $oid => $odata) {
            $name = '';
            if( isset($odata['type_id'])  ) {
                $address_city = !empty($odata['address_city']) ? $odata['address_city'] : '';

                if( $odata['type_id'] == 1  ) {
                    $firstname = !empty($odata['firstname']) ? ucfirst($odata['firstname']) : '';
                    $lastname = !empty($odata['lastname']) ? mb_strtoupper($odata['lastname']) : '';

                    $name = Setting::parse_format($person_format, [
                        'firstname'     => $firstname,
                        'lastname'      => $lastname,
                        'address_city'  => $address_city
                    ]);

                    $name = trim($name);
                }
                if( $odata['type_id'] != 1 || empty($name) ) {
                    $short_name = !empty($odata['short_name']) ? $odata['short_name'] : '';
                    $legal_name = !empty($odata['legal_name']) ? $odata['legal_name'] : '';

                    $name = Setting::parse_format($entity_format, [
                        'short_name'    => $short_name,
                        'legal_name'    => $legal_name,
                        'address_city'  => $address_city
                    ]);

                    $name = trim($name);
                }
            }
            $result[$oid] = $name;
        }
        return $result;
    }

    public static function onupdatePhone($om, $oids, $values, $lang) {
        $identities = $om->read(self::getType(), $oids, ['partners_ids']);
        foreach($identities as $oid => $odata) {
            $om->update('identity\Partner', $odata['partners_ids'], [ 'phone' => null ], $lang);
        }
    }

    public static function onupdateMobile($om, $oids, $values, $lang) {
        $identities = $om->read(self::getType(), $oids, ['partners_ids']);
        foreach($identities as $oid => $odata) {
            $om->update('identity\Partner', $odata['partners_ids'], [ 'mobile' => null ], $lang);
        }
    }

    public static function onupdateEmail($om, $oids, $values, $lang) {
        $identities = $om->read(self::getType(), $oids, ['partners_ids']);
        foreach($identities as $oid => $odata) {
            $om->update('identity\Partner', $odata['partners_ids'], [ 'email' => null ], $lang);
        }
    }

    public static function onupdateName($om, $oids, $values, $lang) {
        $om->callonce(self::getType(), 'reCalcIsDuplicate', $oids);

        $om->update(self::getType(), $oids, [ 'display_name' => null ], $lang);
        $res = $om->read(self::getType(), $oids, ['partners_ids']);
        $partners_ids = [];
        foreach($res as $oid => $odata) {
            $partners_ids = array_merge($partners_ids, $odata['partners_ids']);
        }
        // force re-computing of related partners names
        $om->update('identity\Partner', $partners_ids, [ 'name' => null ], $lang);
        $om->read('identity\Partner', $partners_ids, ['name'], $lang);
    }

    public static function onupdateTypeId($om, $oids, $values, $lang) {
        $res = $om->read(self::getType(), $oids, ['type_id', 'type_id.code', 'partners_ids']);
        if($res > 0) {
            $partners_ids = [];
            foreach($res as $oid => $odata) {
                $values = [ 'type' => $odata['type_id.code'], 'display_name' => null];
                if($odata['type_id'] == 1 ) {
                    $values['legal_name'] = '';
                }
                else {
                    $values['firstname'] = '';
                    $values['lastname'] = '';
                }
                $partners_ids = array_merge($partners_ids, $odata['partners_ids']);
                $om->update(self::getType(), $oid, $values, $lang);
            }
            $om->read(self::getType(), $oids, ['display_name'], $lang);
            // force re-computing of related partners names
            $om->update('identity\Partner', $partners_ids, [ 'name' => null ], $lang);
        }
    }

    /**
     * When lang_id is updated, perform cascading through the partners to update related lang_id
     */
    public static function onupdateLangId($om, $oids, $values, $lang) {
        $res = $om->read(self::getType(), $oids, ['partners_ids', 'lang_id']);

        if($res > 0 && count($res)) {
            foreach($res as $oid => $odata) {
                $om->update('identity\Partner', $odata['partners_ids'], ['lang_id' => $odata['lang_id']]);
            }
        }
    }

    /**
     * When a reference partner is given, add it to the identity's contacts list.
     */
    public static function onupdateReferencePartnerId($om, $oids, $values, $lang) {
        $identities = $om->read(self::getType(), $oids, ['reference_partner_id', 'reference_partner_id.partner_identity_id', 'contacts_ids.partner_identity_id'], $lang);

        if($identities > 0) {
            foreach($identities as $oid => $identity) {
                if(isset($identity['reference_partner_id.partner_identity_id'])
                    && !in_array($identity['reference_partner_id.partner_identity_id'], array_map( function($a) { return $a['partner_identity_id']; }, (array) $identity['contacts_ids.partner_identity_id']))
                ) {
                    // create a contact with the customer as 'booking' contact
                    $om->create('identity\Partner', [
                        'owner_identity_id'     => $oid,
                        'partner_identity_id'   => $identity['reference_partner_id.partner_identity_id'],
                        'relationship'          => 'contact'
                    ]);
                }
            }
        }
    }

    /**
     * On update address do re-calc is duplicate
     *
     * @param  \equal\orm\ObjectManager $om     Object Manager instance.
     * @param  int[]                    $ids    List of objects identifiers.
     * @param  array                    $values Associative array holding the new values to be assigned.
     * @param  string                   $lang   Language in which multilang fields are being updated.
     * @return void
     */
    public static function onupdateAddress($om, $ids, $values, $lang) {
        $om->callonce(self::getType(), 'reCalcIsDuplicate', $ids);
    }

    /**
     * Signature for single object change from views.
     *
     * @param  object   $om        Object Manager instance.
     * @param  array    $event     Associative array holding changed fields as keys, and their related new values.
     * @param  array    $values    Copy of the current (partial) state of the object (fields depend on the view).
     * @param  string   $lang      Language (char 2) in which multilang field are to be processed.
     * @return array    Associative array mapping fields with their resulting values.
     */
    public static function onchange($om, $event, $values, $lang='en') {
        $result = [];

        if(isset($event['type_id'])) {
            $types = $om->read('identity\IdentityType', $event['type_id'], ['code']);
            if($types > 0) {
                $type = reset($types);
                $result['type'] = $type['code'];
            }
        }

        $duplicate_identity_fields_sets = [
            'company'       => ['legal_name', 'address_country'],
            'individual'    => ['firstname', 'lastname', 'address_country']
        ];

        foreach($duplicate_identity_fields_sets as $duplicate_identity_fields) {
            if( count(array_intersect_key($event, array_flip($duplicate_identity_fields))) > 0
                || (isset($event['has_duplicate_clue']) && $event['has_duplicate_clue']) ) {
                $domain = [];
                foreach($duplicate_identity_fields as $field) {
                    $value = $event[$field] ?? $values[$field];
                    if(empty($value)) {
                        continue 2;
                    }
                    $domain[] = [$field, 'ilike', "%{$value}%"];
                }

                $duplicate_identity = null;
                if(!empty($domain)) {
                    $domain[] = ['id', '<>', $values['id']];
                    $identity_ids = $om->search('identity\Identity', $domain);

                    if($identity_ids > 0 && count($identity_ids)) {
                        $identities = $om->read('identity\Identity', [$identity_ids[0]], ['id', 'name']);
                        $duplicate_identity = reset($identities);
                    }
                }

                $result['has_duplicate_clue'] = !is_null($duplicate_identity);
                $result['duplicate_clue_identity_id'] = $duplicate_identity;
            }
        }

        if(isset($event['has_duplicate_clue']) && !$event['has_duplicate_clue']) {
            $result['duplicate_clue_identity_id'] = null;
        }

        if(isset($event['address_zip']) && isset($values['address_country'])) {
            $list = self::getCitiesByZip($event['address_zip'], $values['address_country'], $lang);
            if($list) {
                $result['address_city'] = [
                    'value' => '',
                    'selection' => $list
                ];
            }
        }

        return $result;
    }

    /**
     * Returns cities' names based on a zip code and a country.
     */
    private static function getCitiesByZip($zip, $country, $lang) {
        $result = null;
        $file = "packages/identity/i18n/{$lang}/zipcodes/{$country}.json";
        if(file_exists($file)) {
            $data = file_get_contents($file);
            $map_zip = json_decode($data, true);
            if(isset($map_zip[$zip])) {
                $result = $map_zip[$zip];
            }
        }
        return $result;
    }

    /**
     * Returns a region name based on a zip code and a country.
     */
    public static function getRegionByZip($zip, $country) {
        $zip = intval($zip);

        if ($country == 'BE') {
            if ($zip < 1300) {
                return "Région Bruxelles-Capitale";
            } elseif ($zip >= 1300 && $zip < 1500) {
                return "Région wallonne";
            } elseif ($zip >= 1500 && $zip < 4000) {
                return "Région flamande";
            } elseif ($zip >= 4000 && $zip < 8000) {
                return "Région wallonne";
            } elseif ($zip >= 8000 && $zip < 10000) {
                return "Région flamande";
            }
            return '';
        }

        if ($country == 'FR') {
            $first_two_digits = intval(substr($zip, 0, 2));

            if (in_array($first_two_digits, [75, 77, 78, 91, 92, 93, 94, 95])) {
                return "Île-de-France";
            } elseif (in_array($first_two_digits, [21, 58, 71, 89])) {
                return "Bourgogne-Franche-Comté";
            } elseif (in_array($first_two_digits, [22, 29, 35, 56])) {
                return "Bretagne";
            } elseif (in_array($first_two_digits, [18, 28, 36, 37, 41, 45])) {
                return "Centre-Val de Loire";
            } elseif (in_array($first_two_digits, [2])) {
                return "Corse";
            } elseif (in_array($first_two_digits, [8, 10, 51, 52])) {
                return "Grand Est";
            } elseif (in_array($first_two_digits, [59, 62])) {
                return "Hauts-de-France";
            } elseif (in_array($first_two_digits, [13, 83, 84])) {
                return "Provence-Alpes-Côte d'Azur";
            } elseif (in_array($first_two_digits, [30, 34, 48, 66])) {
                return "Occitanie";
            } elseif (in_array($first_two_digits, [14, 50, 61])) {
                return "Normandie";
            } elseif (in_array($first_two_digits, [16, 17, 19, 23, 24, 33, 40, 47, 64, 79, 86, 87])) {
                return "Nouvelle-Aquitaine";
            } elseif (in_array($first_two_digits, [9, 11, 12, 31, 32, 46, 65, 81, 82])) {
                return "Occitanie";
            } elseif (in_array($first_two_digits, [1, 3, 7, 15, 26, 38, 42, 43, 63, 69, 73, 74])) {
                return "Auvergne-Rhône-Alpes";
            } elseif (in_array($first_two_digits, [44, 49, 53, 72, 85])) {
                return "Pays de la Loire";
            } elseif (in_array($first_two_digits, [27, 28, 61, 76])) {
                return "Normandie";
            }
            return '';
        }

        return '';
    }

    /**
     * Check whether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $oids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang='en') {
        $identities = $om->read(self::getType(), $oids, ['is_readonly', 'is_duplicate'], $lang);
        foreach($identities as $identity) {
            if($identity['is_readonly']) {
                return ['id' => ['non_updateable_identity' => 'Static identities cannot be updated.']];
            }

            if(isset($values['has_duplicate_clue']) && $values['has_duplicate_clue'] && $identity['is_duplicate']) {
                return ['has_duplicate_clue' => ['might_be_duplicate' => 'Cannot save possible duplicate without unchecking.']];
            }

        }

        if(isset($values['type_id'])) {
            $identities = $om->read(self::getType(), $oids, [ 'firstname', 'lastname', 'legal_name' ], $lang);
            foreach($identities as $oid => $identity) {
                if($values['type_id'] == 1) {
                    $firstname = '';
                    $lastname = '';
                    if(isset($values['firstname'])) {
                        $firstname = $values['firstname'];
                    }
                    else {
                        $firstname = $identity['firstname'];
                    }
                    if(isset($values['lastname'])) {
                        $lastname = $values['lastname'];
                    }
                    else {
                        $lastname = $identity['lastname'];
                    }

                    if(!strlen($firstname) ) {
                        return ['firstname' => ['missing' => 'Firstname cannot be empty for natural person.']];
                    }
                    if(!strlen($lastname) ) {
                        return ['lastname' => ['missing' => 'Lastname cannot be empty for natural person.']];
                    }
                }
                else {
                    $legal_name = '';
                    if(isset($values['legal_name'])) {
                        $legal_name = $values['legal_name'];
                    }
                    else {
                        $legal_name = $identity['legal_name'];
                    }
                    if(!strlen($legal_name)) {
                        return ['legal_name' => ['missing' => 'Legal name cannot be empty for legal person.']];
                    }
                }
            }
        }
        return parent::canupdate($om, $oids, $values, $lang);
    }

    /**
     * Check whether the identity can be deleted.
     *
     * @param  \equal\orm\ObjectManager    $om        ObjectManager instance.
     * @param  array                       $ids       List of objects identifiers.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $ids) {
        $identities = $om->read(self::getType(), $ids, [ 'bookings_ids' ]);

        if($identities > 0) {
            foreach($identities as $id => $identity) {
                if($identity['bookings_ids'] && count($identity['bookings_ids']) > 0) {
                    return ['bookings_ids' => ['non_removable_identity' => 'Identities relating to one or more bookings cannot be deleted.']];
                }
            }
        }
        return parent::candelete($om, $ids);
    }

    public static function getConstraints() {
        return [
            'legal_name' =>  [
                'too_short' => [
                    'message'       => 'Legal name must be minimum 2 chars long.',
                    'function'      => function ($legal_name, $values) {
                        return !( strlen($legal_name) < 2 && isset($values['type_id']) && $values['type_id'] != 1 );
                    }
                ],
                'too_long' => [
                    'message'       => 'Legal name must be maximum 70 chars long.',
                    'function'      => function ($legal_name, $values) {
                        return !( strlen($legal_name) > 70 && isset($values['type_id']) && $values['type_id'] != 1 );
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Legal name must contain only naming glyphs.',
                    'function'      => function ($legal_name, $values) {
                        if( isset($values['type_id']) && $values['type_id'] == 1 ) {
                            return true;
                        }
                        // authorized : a-z, 0-9, '/', '-', ',', '.', ''', '&'
                        return (bool) (preg_match('/^[\w\'\-,.&][^_!¡?÷?¿\\+=@#$%ˆ*{}|~<>;:[\]]{1,}$/u', $legal_name));
                    }
                ]
            ],
            'firstname' =>  [
                'too_short' => [
                    'message'       => 'Firstname must be 2 chars long at minimum.',
                    'function'      => function ($firstname, $values) {
                        return !( strlen($firstname) < 2 && isset($values['type_id']) && $values['type_id'] == 1 );
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Firstname must contain only naming glyphs.',
                    'function'      => function ($firstname, $values) {
                        if( isset($values['type_id']) && $values['type_id'] != 1 ) {
                            return true;
                        }
                        return (bool) (preg_match('/^[\w\'\-,.][^0-9_!¡?÷?¿\/\\+=@#$%ˆ&*(){}|~<>;:[\]]{1,}$/u', $firstname));
                    }
                ]
            ],
            'lastname' =>  [
                'too_short' => [
                    'message'       => 'Lastname must be 2 chars long at minimum.',
                    'function'      => function ($lastname, $values) {
                        return !( strlen($lastname) < 2 && isset($values['type_id']) && $values['type_id'] == 1 );
                    }
                ],
                'invalid_chars' => [
                    'message'       => 'Lastname must contain only naming glyphs.',
                    'function'      => function ($lastname, $values) {
                        if( isset($values['type_id']) && $values['type_id'] != 1 ) {
                            return true;
                        }
                        return (bool) (preg_match('/^[\w\'\-,.][^0-9_!¡?÷?¿\/\\+=@#$%ˆ&*(){}|~<>;:[\]]{1,}$/u', $lastname));
                    }
                ]
            ]
        ];
    }

    public static function reCalcIsDuplicate($om, $ids, $values, $lang) {
        $om->update(self::getType(), $ids, ['is_duplicate' => null, 'duplicate_identity_id' => null]);
        $om->read(self::getType(), $ids, ['is_duplicate']);
    }

    public static function getDuplicateIdentityId($om, $ids, $values, $lang) {
        $result = [];
        $duplicate_identity_fields = ['legal_name', 'firstname', 'lastname', 'address_city', 'address_state', 'address_country'];
        $identities = $om->read(self::getType(), $ids, $duplicate_identity_fields, $lang);
        foreach($identities as $id => $identity) {
            if(empty($identity['legal_name']) && empty($identity['firstname']) && empty($identity['lastname'])) {
                continue;
            }

            $domain = [];
            foreach($duplicate_identity_fields as $field) {
                if(!empty($identity[$field])) {
                    $domain[] = [$field, 'ilike', "%{$identity[$field]}%"];
                }
            }

            if(!empty($domain)) {
                $domain[] = ['id', '<', $id];
                if(count($ids) == 1) {
                    $domain[] = ['is_duplicate', '=', false];
                }

                $identity_ids = $om->search('identity\Identity', $domain);

                if($identity_ids > 0 && count($identity_ids)) {
                    $result[$id] = $identity_ids[0];
                }
            }
        }

        return $result;
    }

    public static function calcIsDuplicate($om, $ids, $lang) {
        $result = [];

        $duplicate_identity_ids = $om->call(self::getType(), 'getDuplicateIdentityId', $ids);
        foreach($ids as $id) {
            if(!isset($duplicate_identity_ids[$id])) {
                $result[$id] = false;
            }
            else {
                $result[$id] = true;
                $om->update(self::getType(), [$id], ['duplicate_identity_id' => $duplicate_identity_ids[$id]], $lang);
            }
        }

        return $result;
    }

    public static function onupdateIsDuplicate($om, $ids, $values, $lang) {
        if(isset($values['is_duplicate']) && !$values['is_duplicate']) {
            $om->update(self::getType(), $ids, ['duplicate_identity_id' => null, 'has_duplicate_clue' => false], $lang);
        }
    }

    public static function generateShortName() {
        return DataGenerator::legalName();
    }
}

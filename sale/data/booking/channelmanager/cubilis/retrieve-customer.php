<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\Lang;
use sale\booking\channelmanager\Identity;
use sale\customer\Customer;

list($params, $providers) = eQual::announce([
    'description'   => "Resolve a customer based on its contact details. \
                        If the customer exists (exact match, case non-sensitive), its ID is returned, otherwise a new customer is created and related ID is returned.",
    'help'          => "No validation is made on received parameters, but in case of the creation of a new entry, all values are sanitized beforehand.",
    'params'        => [
        'firstname' => [
            'type'              => 'string',
            'description'       => 'Customer given name.'
        ],
        'lastname' => [
            'type'              => 'string',
            'description'       => 'Customer surname.'
        ],
        'address_street' => [
            'type'              => 'string',
            'description'       => 'Street and number.'
        ],
        'address_zip' => [
            'type'              => 'string',
            'description'       => 'Postal code.'
        ],
        'address_city' => [
            'type'              => 'string',
            'description'       => 'City.'
        ],
        'address_country' => [
            'type'              => 'string',
            'description'       => 'Country.'
        ],
        'phone' => [
            'type'              => 'string',
            'description'       => 'Phone number.'
        ],
        'email' => [
            'type'              => 'string',
            'description'       => 'Email address.'
        ],
        'lang' => [
            'type'              => 'string',
            'description'       => "Preferred language."
        ],
    ],
    'access' => [
        'visibility'    => 'protected',
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

$valid = true;

if(strlen($params['firstname']) <= 0 || strlen($params['lastname']) <= 0 || strlen($params['address_street']) <= 0 || strlen($params['address_city']) <= 0 || strlen($params['address_zip']) <= 0 || strlen($params['address_country']) <= 0) {
    $valid = false;
}

$identity = Identity::search([
        ['firstname', 'ilike', $params['firstname']],
        ['lastname', 'ilike', $params['lastname']],
        ['address_street', 'ilike', $params['address_street']],
        ['address_city', 'ilike', $params['address_city']],
        ['address_zip', 'ilike', $params['address_zip']],
        ['address_country', 'ilike', $params['address_country']]
    ])
    ->read(['id'])
    ->first(true);

$language = Lang::search(['code', '=', $params['lang']])->read(['id'])->first(true);
// #memo - id 2 is for French
$lang_id = $language['id'] ?? 2;

if($valid && $identity) {
    // lookup for a customer associated with the found identity
    $customer = Customer::search([
            ['owner_identity_id', '=', 1],
            ['partner_identity_id', '=', $identity['id']],
            ['relationship', '=', 'customer']
        ])
        ->read(['id'])
        ->first(true);
}
else {
    // customer does not exist yet
    $customer = null;
    $values = [
            'firstname'         => $params['firstname'],
            'lastname'          => $params['lastname'],
            'address_street'    => $params['address_street'],
            'address_city'      => $params['address_city'],
            'address_zip'       => $params['address_zip'],
            'address_country'   => substr($params['address_country'], 0, 2),
            'phone'             => str_replace(['.', '/', ' ', '-'], '', $params['phone']),
            'email'             => $params['email'],
            'lang_id'           => $lang_id,
            'is_ota'            => true
        ];

    // sanitize / exclude fields

    $country_iso = ['AF','AX','AL','DZ','AS','AD','AO','AI','AQ','AG','AR','AM','AW','AU','AT','AZ','BS','BH','BD','BB','BY','BE','BZ','BJ','BM','BT','BO','BA','BW','BV','BR','IO','BN','BG','BF','BI','KH','CM','CA','CV','KY','CF','TD','CL','CN','CX','CC','CO','KM','CG','CD','CK','CR','CI','HR','CU','CY','CZ','DK','DJ','DM','DO','EC','EG','SV','GQ','ER','EE','ET','FK','FO','FJ','FI','FR','GF','PF','TF','GA','GM','GE','DE','GH','GI','GR','GL','GD','GP','GU','GT','GG','GN','GW','GY','HT','HM','VA','HN','HK','HU','IS','IN','ID','IR','IQ','IE','IM','IL','IT','JM','JP','JE','JO','KZ','KE','KI','KR','KW','KG','LA','LV','LB','LS','LR','LY','LI','LT','LU','MO','MK','MG','MW','MY','MV','ML','MT','MH','MQ','MR','MU','YT','MX','FM','MD','MC','MN','ME','MS','MA','MZ','MM','NA','NR','NP','NL','AN','NC','NZ','NI','NE','NG','NU','NF','MP','NO','OM','PK','PW','PS','PA','PG','PY','PE','PH','PN','PL','PT','PR','QA','RE','RO','RU','RW','BL','SH','KN','LC','MF','PM','VC','WS','SM','ST','SA','SN','RS','SC','SL','SG','SK','SI','SB','SO','ZA','GS','ES','LK','SD','SR','SJ','SZ','SE','CH','SY','TW','TJ','TZ','TH','TL','TG','TK','TO','TT','TN','TR','TM','TC','TV','UG','UA','AE','GB','US','UM','UY','UZ','VU','VE','VN','VG','VI','WF','EH','YE','ZM','ZW'];
    if(!in_array($values['address_country'], $country_iso)) {
       $values['address_country'] = 'BE';
    }

    if(!preg_match('/^[a-zA-Z0-9+_.-]+@[a-zA-Z0-9.-]+$/', $values['email'])) {
        unset($values['email']);
    }

    if(in_array($values['address_zip'], ['na', 'n/a', '000', '0000', '00000'])) {
        unset($values['address_zip']);
    }

    // #memo - for other fields sanitizing has been removed and any values is accepted by specific entity channelmanager\Identity
    /*
    if(!(preg_match('/^[\w\'\-,.][^0-9_!¡?÷?¿\/\\+=@#$%ˆ&*(){}|~<>;:[\]]{1,}$/u', $values['firstname'])) || strlen($values['firstname']) <= 0) {
        $values['firstname'] = "prénom-invalide";
    }
    if(!(preg_match('/^[\w\'\-,.][^0-9_!¡?÷?¿\/\\+=@#$%ˆ&*(){}|~<>;:[\]]{1,}$/u', $values['lastname'])) || strlen($values['lastname']) <= 0) {
        $values['lastname'] = "nom-invalide";
    }
    $sanitized_phone = str_replace(['.', '/', ' ', '-'], '', $values['phone']);
    if(!preg_match('/^[\+]?[0-9]{6,13}$/', $sanitized_phone)) {
        unset($values['phone']);
    }
    */
    // create a new identity
    $identity = Identity::create($values)
        ->read(['id'])
        ->first(true);
}

// if no customer was found, create the related customer
if(!$customer) {
    $customer = Customer::create([
            'owner_identity_id'     => 1,
            'partner_identity_id'   => $identity['id'],
            'rate_class_id'         => 4,
            'lang_id'               => $lang_id
        ])
        ->read(['id'])
        ->first(true);
}

$result = [
    'id'                    => $customer['id'],
    'customer_identity_id'  => $identity['id']
];

$context->httpResponse()
        ->body($result)
        ->send();

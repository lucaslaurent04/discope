<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Bookings: returns a collection of Booking according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'   => 'Full name (including namespace) of the class to look into (e.g. \'core\\User\').',
            'type'          => 'string',
            'default'       => 'sale\price\Price'
        ],

        'price_list_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\price\PriceList'
        ],

        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center'
        ],

        'product_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\catalog\Product',
        ],

        'is_active' => [
            'type'              => 'boolean'
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
['context' => $context, 'orm' => $orm] = $providers;


/*
    Add conditions to the domain to consider advanced parameters
*/

$domain = $params['domain'];
$prices_ids = [];


if(isset($params['is_active']) && $params['is_active'] === true ) {
    $domain = Domain::conditionAdd($domain, ['is_active', '=', true]);
}

if(isset($params['product_id']) && ($params['product_id'] > 0 )) {
    $domain = Domain::conditionAdd($domain, ['product_id', '=', $params['product_id']]);
}

if(isset($params['price_list_id']) && ($params['price_list_id'] > 0 )) {
    $domain = Domain::conditionAdd($domain, ['price_list_id', '=', $params['price_list_id']]);
}

if(isset($params['center_id']) && $params['center_id'] > 0) {
    // add constraint on center_id
    $domain = Domain::conditionAdd($domain, ['center_id', '=', $params['center_id']]);
}

if(count($prices_ids)) {
    $domain = Domain::conditionAdd($domain, ['id', 'in', $prices_ids]);
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);


$context->httpResponse()
        ->body($result)
        ->send();

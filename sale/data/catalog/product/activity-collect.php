<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use identity\Center;
use sale\catalog\Product;
use sale\catalog\ProductModel;

[$params, $providers] = eQual::announce([
    'description'   => "Retrieves all activities products filtered by given domain. Is used by the activities-planning",
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'type'              => 'string',
            'description'       => "Full name (including namespace) of the class to look into (e.g. 'core\\User').",
            'default'           => 'sale\catalog\Product'
        ],
        'domain' => [
            'type'              => 'array',
            'description'       => "Criteria that searched products have to match (series of conjunctions)",
            'default'           => []
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => "The center to which the booking relates to.",
            'required'          => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*',
        'cacheable'     => true,
        'cache-vary'    => ['uri'],
        'expires'       => 1 * (60*60)
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$result = [];

$fields = ['id', 'name', 'sku', 'can_sell', 'product_model_id', 'groups_ids'];

// retrieve center and related catalog info
$center = Center::id($params['center_id'])
    ->read(['id', 'product_groups_ids'])
    ->first(true);

if(!$center) {
    throw new Exception("unknown_center", EQ_ERROR_UNKNOWN_OBJECT);
}

// 1) create general domain (reduce to activities)
$domain = new Domain($params['domain']);

$activities_product_models_ids = ProductModel::search([
    ['is_activity', '=', true],
    ['is_fullday', '=', false]
])
    ->ids();

$domain->addCondition(new DomainCondition('product_model_id', 'in', $activities_product_models_ids));

// 2) read products
$products = Product::search($domain->toArray())
    ->read($fields)
    ->adapt('json')
    ->get(true);

// Checks if there is an intersection between the product groups and those of the center
$center_groups = $center['product_groups_ids'];
$filtered_products = array_filter($products, function($product) use ($center_groups) {
    return !empty(array_intersect($product['groups_ids'], $center_groups));
});

// 3) sort products by name (on ascending order)
usort($filtered_products, fn ($a, $b) => strcmp($a['name'], $b['name']));

$context->httpResponse()
        ->body($filtered_products)
        ->send();

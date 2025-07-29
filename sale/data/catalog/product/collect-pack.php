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
use sale\price\Price;
use sale\price\PriceList;

[$params, $providers] = eQual::announce([
    'description'   => 'Retrieves all packs products that are currently sellable for a given center and, if related Center Office has defined Product Favorites, return those first.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'Full name (including namespace) of the class to look into (e.g. \'core\\User\').',
            'type'              => 'string',
            'default'           => 'sale\catalog\Product'
        ],
        'domain' => [
            'description'       => 'Criterias that searched products have to match (series of conjunctions)',
            'type'              => 'array',
            'default'           => []
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => "The center to which the booking relates to.",
            'required'          => true
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Start date of the queried date range.",
            'required'          => true
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "End date of the queried date range.",
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
    'providers'     => [ 'context', 'orm' ]
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

$result = [];

$fields = ['id', 'name', 'sku', 'can_sell', 'product_model_id', 'groups_ids'];

// retrieve center and related catalog info
$center = Center::id($params['center_id'])
    ->read(['id', 'price_list_category_id', 'product_groups_ids'])
    ->first(true);

if(!$center) {
    throw new Exception("unknown_center", EQ_ERROR_UNKNOWN_OBJECT);
}

// 1) create general domain for products (reduce to sellable products)
$domain = new Domain($params['domain']);
$domain->addCondition(new DomainCondition('can_sell', '=', true));
// force packs
$domain->addCondition(new DomainCondition('is_pack', '=', true));

// 2) reduce domain to products referenced by prices matching the given constraints (center, dates & status)
$products = Product::search($domain->toArray())
    ->read(['id', 'is_pack', 'has_own_price'])
    ->get(true);

// if it has_own_price = false, then allow even if no price set
$has_not_own_prices_products_ids = [];
foreach($products as $product) {
    if(!$product['has_own_price']) {
        $has_not_own_prices_products_ids[] = $product['id'];
    }
}

$products_ids = array_column($products, 'id');

$price_lists_ids = PriceList::search(
        [
            ['price_list_category_id', '=', $center['price_list_category_id']],
            ['date_from', '<=', $params['date_from']],
            ['date_to', '>=', $params['date_from']],
            ['status', 'in', ['published', 'pending']]
        ],
        ['duration' => 'asc']
    )
    ->ids();

$prices = Price::search([
            ['price_list_id', 'in', $price_lists_ids],
            ['product_id', 'in', $products_ids],
        ])
        ->read(['product_id'])
        ->get(true);

$products_ids = array_map(fn ($a) => $a['product_id'], $prices);

// if it has_own_price = false, then allow even if no price set
$products_ids = array_unique(
    array_merge($products_ids, $has_not_own_prices_products_ids)
);

// 3) read products
$products = Product::ids($products_ids)
    ->read($fields)
    ->adapt('json')
    ->get(true);

// Checks if there is an intersection between the product groups and those of the center
$center_groups = $center['product_groups_ids'];
$filtered_products = array_filter($products, function($product) use ($center_groups) {
    return !empty(array_intersect($product['groups_ids'], $center_groups));
});

// 4) sort products by name (on ascending order)
usort($filtered_products, function($a, $b) {return strcmp($a['name'], $b['name']);});

$context->httpResponse()
        ->body($filtered_products)
        ->send();

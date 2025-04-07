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
    'description'   => 'Retrieves all products that are currently sellable for a given center and, if related Center Office has defined Product Favorites, return those first.',
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

$fields = ['id', 'name', 'sku', 'can_sell', 'product_model_id'];

// retrieve center and related catalog info
$center = Center::id($params['center_id'])
    ->read(['id', 'price_list_category_id'])
    ->first(true);

if(!$center) {
    throw new Exception("unknown_center", EQ_ERROR_UNKNOWN_OBJECT);
}

// 1) create general domain for products (reduce to sellable products)
$domain = new Domain($params['domain']);
$domain->addCondition(new DomainCondition('can_sell', '=', true));

// 2) reduce domain to products referenced by prices matching the given constraints (center, dates & status)
$products_ids = Product::search($domain->toArray())->ids();
$price_lists_ids = PriceList::search(
        [
            ['price_list_category_id', '=', $center['price_list_category_id']],
            ['date_from', '<=', $params['date_from']],
            ['date_to', '>=', $params['date_from']],
            ['status', '=', ['published']]
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

// 3) read products
$products = Product::ids($products_ids)
    ->read($fields)
    ->adapt('json')
    ->get(true);

// 4) sort products by name (on ascending order)
usort($products, function($a, $b) {return strcmp($a['name'], $b['name']);});

$context->httpResponse()
        ->body($products)
        ->send();

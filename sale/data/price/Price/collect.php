<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use identity\Center;
use sale\catalog\Product;
use sale\price\PriceList;

[$params, $providers] = eQual::announce([
    'description'   => "Advanced search for Prices: returns a collection of Price according to extra parameters.",
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'type'              => 'string',
            'description'       => "Full name (including namespace) of the class to look into (e.g. 'core\\User').",
            'default'           => 'sale\price\Price'
        ],

        'name' => [
            'type'              => 'string',
            'description'       => "Search by non-contiguous keywords."
        ],

        'price_list_id' => [
            'type'              => 'many2one',
            'description'       => "The Price List the price belongs to.",
            'foreign_object'    => 'sale\price\PriceList'
        ],

        'center_id' => [
            'type'              => 'many2one',
            'description'       => 'The Center of the Price (Product -> ProductModel -> Family).',
            'foreign_object'    => 'identity\Center',
            'default'           => function() {
                return ($centers = Center::search())->count() === 1 ? current($centers->ids()) : null;
            }
        ],

        'product_id' => [
            'type'              => 'many2one',
            'description'       => "The Product (sku) the price applies to.",
            'foreign_object'    => 'sale\catalog\Product',
        ],

        'is_active' => [
            'type'              => 'boolean',
            'description'       => "Is the price currently applicable?"
        ],

        'rate_class_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\customer\RateClass',
            'description'       => "The rate class that applies to the price, defining variations based on the target audience."
        ],

        'grouping_code_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\catalog\GroupingCode',
            'description'       => "The GroupingCode of the Price (Product)."
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;


/*
    Add conditions to the domain to consider advanced parameters
*/

$domain = $params['domain'];
$prices_ids = [];

if(!empty($params['name'])) {
    $keywords = explode(' ', trim($params['name']));
    foreach($keywords as $keyword) {
        $domain = Domain::conditionAdd($domain, ['name', 'ilike', '%'.$keyword.'%']);
    }
}

if(isset($params['is_active']) && $params['is_active'] === true) {
    $domain = Domain::conditionAdd($domain, ['is_active', '=', true]);
}

if(isset($params['rate_class_id']) && $params['rate_class_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['rate_class_id', '=', $params['rate_class_id']]);
}

if(isset($params['grouping_code_id']) && $params['grouping_code_id'] > 0) {
    $products = Product::search(['grouping_code_id', 'in', $params['grouping_code_id']])
        ->read(['id'])
        ->get(true);

    $products_ids = array_column($products, 'id');

    $domain = Domain::conditionAdd($domain, ['product_id', 'in', $products_ids]);
}

if(isset($params['product_id']) && $params['product_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['product_id', '=', $params['product_id']]);
}

if(isset($params['price_list_id']) && $params['price_list_id'] > 0) {
    $domain = Domain::conditionAdd($domain, ['price_list_id', '=', $params['price_list_id']]);
}


// center_id : we need to filter by using price_list_category_id both on PriceList and Center
if(isset($params['center_id']) && $params['center_id'] > 0) {
    $center = Center::id($params['center_id'])->read(['price_list_category_id'])->first();

    if($center) {
        $price_lists_ids = PriceList::search(['price_list_category_id', '=', $center['price_list_category_id']])->ids();
        if(count($price_lists_ids)) {
            $domain = Domain::conditionAdd($domain, ['price_list_id', 'in', $price_lists_ids]);
        }
    }
}

if(count($prices_ids)) {
    $domain = Domain::conditionAdd($domain, ['id', 'in', $prices_ids]);
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

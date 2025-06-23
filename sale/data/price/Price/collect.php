<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use sale\camp\catalog\ProductModel;
use sale\camp\price\Price;
use sale\catalog\Family;

[$params, $providers] = eQual::announce([
    'description'   => "Advanced search for Prices: returns a collection of Price according to extra parameters.",
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'type'          => 'string',
            'description'   => "Full name (including namespace) of the class to look into (e.g. 'core\\User').",
            'default'       => 'sale\price\Price'
        ],

        'price_list_id' => [
            'type'              => 'many2one',
            'description'       => "The Price List the price belongs to.",
            'foreign_object'    => 'sale\price\PriceList'
        ],

        'center_id' => [
            'type'              => 'many2one',
            'description'       => "The Center ",
            'foreign_object'    => 'identity\Center'
        ],

        'product_id' => [
            'type'              => 'many2one',
            'description'       => "The Product (sku) the price applies to.",
            'foreign_object'    => 'sale\catalog\Product',
        ],

        'is_active' => [
            'type'              => 'boolean',
            'description'       => "Is the price currently applicable?"
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
 * @var \equal\orm\ObjectManager $orm
 */
['context' => $context] = $providers;


/*
    Add conditions to the domain to consider advanced parameters
*/

$domain = $params['domain'];
$prices_ids = [];


if(isset($params['is_active']) && $params['is_active'] === true) {
    $domain = Domain::conditionAdd($domain, ['is_active', '=', true]);
}

if(isset($params['product_id']) && ($params['product_id'] > 0)) {
    $domain = Domain::conditionAdd($domain, ['product_id', '=', $params['product_id']]);
}

if(isset($params['price_list_id']) && ($params['price_list_id'] > 0)) {
    $domain = Domain::conditionAdd($domain, ['price_list_id', '=', $params['price_list_id']]);
}

if(isset($params['center_id']) && $params['center_id'] > 0) {
    $prices_ids = [];

    $families = Family::search()
        ->read(['centers_ids', 'product_models_ids'])
        ->get(true);

    $family_product_models_ids = [];
    foreach($families as $family) {
        if(!in_array($params['center_id'], $family['centers_ids'])) {
            continue;
        }
        foreach($family['product_models_ids'] as $product_models_id) {
            $family_product_models_ids[$product_models_id] = true;
        }
    }
    $family_product_models_ids = array_keys($family_product_models_ids);

    if(!empty($family_product_models_ids)) {
        $product_models = ProductModel::ids($family_product_models_ids)
            ->read(['products_ids'])
            ->get(true);

        $family_products_ids = [];
        foreach($product_models as $product_model) {
            foreach($product_model['products_ids'] as $product_id) {
                $family_products_ids[$product_id] = true;
            }
        }
        $family_products_ids = array_keys($family_products_ids);

        if(!empty($family_products_ids)) {
            $prices = Price::search()
                ->read(['product_id'])
                ->get(true);

            foreach($prices as $price) {
                if(in_array($price['product_id'], $family_products_ids)) {
                    $prices_ids[] = $price['id'];
                }
            }
        }
    }

    $domain = Domain::conditionAdd($domain, ['id', 'in', $prices_ids]);
}

if(count($prices_ids)) {
    $domain = Domain::conditionAdd($domain, ['id', 'in', $prices_ids]);
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

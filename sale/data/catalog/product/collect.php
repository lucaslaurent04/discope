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
use sale\price\PriceList;

list($params, $providers) = announce([
    'description'   => 'Retrieves all products that are currently sellable for a given center and, if related Center Office has defined Product Favorites, return those first.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'       => 'Full name (including namespace) of the class to look into (e.g. \'core\\User\').',
            'type'              => 'string',
            'default'           => 'sale\catalog\Product'
        ],
        'domain' => [
            'description'       => 'Criterias that results have to match (serie of conjunctions)',
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
        ],
        'is_activity' => [
            'type'              => 'boolean',
            'description'       => "Must the product be linked to an activity product model."
        ],
        'is_transport' => [
            'type'              => 'boolean',
            'description'       => "Must the product be linked to an activity transport product model."
        ],
        'is_supply' => [
            'type'              => 'boolean',
            'description'       => "Must the product be linked to an activity supply product model."
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
list($context, $orm) = [ $providers['context'], $providers['orm'] ];

$result = [];

$fields = ['id', 'name', 'is_pack', 'sku', 'can_sell'];

// handle filter by product_model_id
if(!empty($params['domain'])) {
    foreach($params['domain'] as $dom) {
        if(is_array($dom)) {
            if(($dom[0] ?? false) === 'product_model_id') {
                $fields[] = 'product_model_id';
                break;
            }
        }
        elseif($dom === 'product_model_id') {
            $fields[] = 'product_model_id';
            break;
        }
    }
}

/*
    Keep only products that can be sold by the given Center.
    We perform a double check: by category attached to the center, and by price list defined for the center.
*/

// retrieve center and related product favorites, if any
$center = Center::id($params['center_id'])
    ->read([
        'id',
        'price_list_category_id',
        'product_groups_ids'    => ['products_ids'],
        'product_families_ids'  => ['product_models_ids'],
        'center_office_id'      => ['product_favorites_ids' => ['product_id']]
    ])
    ->first(true);

if(!$center) {
    throw new Exception("unknown_center", QN_ERROR_UNKNOWN_OBJECT);
}

// retrieve Product groups from given center
$map_groups_products_ids = [];
if(isset($center['product_groups_ids']) && $center['product_groups_ids'] > 0) {
    foreach($center['product_groups_ids'] as $group) {
        foreach($group['products_ids'] as $product_id) {
            $map_groups_products_ids[$product_id] = true;
        }
    }
}


// Check product validity against applicable price list for given date range.
$map_pricelists_products_ids = [];
// #memo #channelmanager - products used exclusively for sync with Cubilis NUIT_OTA and SEJ_OTA are marked as active but do not have a price
$price_lists = PriceList::search([
            ['price_list_category_id', '=', $center['price_list_category_id']],
            ['date_from', '<=', $params['date_from']],
            ['date_to', '>=', $params['date_from']],
            ['status', '=', ['published']]
        ],
        ['duration' => 'asc'])
    ->read(['prices_ids' => ['product_id']]);

foreach($price_lists as $price_list) {
    foreach($price_list['prices_ids'] as $price) {
        $map_pricelists_products_ids[$price['product_id']] = true;
    }
}

// #memo - workaround to find out if query is for packs (should be given as an individual param)
$filter_is_pack = false;
if($params['domain'] && is_array($params['domain'])) {
    foreach($params['domain'] as $condition) {
        if($condition[0] == 'is_pack') {
            $filter_is_pack = true;
            break;
        }
    }
}

if($filter_is_pack) {
    // no price constraint for packs
    // #todo - improve : a pack might have its own price
    $products_ids = array_keys($map_groups_products_ids);
}
else {
    // available products are both in one of the Center's group AND within a pricelist defined for the given date range
    $products_ids = array_intersect(array_keys($map_groups_products_ids), array_keys($map_pricelists_products_ids));
}

$activity_dom = [];
if(isset($params['is_activity'])) {
    $activity_dom[] = ['is_activity', '=', $params['is_activity']];
}
if(isset($params['is_transport'])) {
    $activity_dom[] = ['is_transport', '=', $params['is_transport']];
}
if(isset($params['is_supply'])) {
    $activity_dom[] = ['is_supply', '=', $params['is_supply']];
}
if(!empty($activity_dom)) {
    $product_models_ids = ProductModel::search($activity_dom)->ids();
}

// if center office has set some favorites, add related products to the result
$favorites = [];

if(isset($center['center_office_id']['product_favorites_ids'])) {
    $favorites = $center['center_office_id']['product_favorites_ids'];
    $map_favorites_ids = [];
    if($favorites > 0) {
        foreach($favorites as $favorite) {
            $map_favorites_ids[$favorite['product_id']] = true;
        }
    }

    // remove favorites from found products
    $products_ids = array_diff($products_ids, array_keys($map_favorites_ids));

    // handle is_activity, is_transport or is_supply
    $dom = [['id', 'in', array_keys($map_favorites_ids)]];
    if(!is_null($product_models_ids)) {
        $dom[] = ['product_model_id', 'in', $product_models_ids];
    }

    // read favorites
    // #memo - ProductFavorite schema specifies the field `order` for sorting
    $favorites = Product::search($dom)
        ->read($fields)
        ->adapt('json')
        ->get(true);
}

// handle is_activity, is_transport or is_supply
$dom = [['id', 'in', $products_ids]];
if(!is_null($product_models_ids)) {
    $dom[] = ['product_model_id', 'in', $product_models_ids];
}

// read products (without favorites)
$products = Product::search($dom)
    ->read($fields)
    ->adapt('json')
    ->get(true);

// sort products by name (on ascending order)
usort($products, function($a, $b) {return strcmp($a['name'], $b['name']);});

// return favorites + remaining products
$products_list = array_merge($favorites, $products);

// filter results according to received domain
$domain = new Domain($params['domain']);
$domain->addCondition(new DomainCondition('can_sell', '=', true));

foreach($products_list as $index => $product) {
    if($domain->evaluate($product)) {
        $result[] = $product;
    }
}

$context->httpResponse()
        ->body($result)
        ->send();

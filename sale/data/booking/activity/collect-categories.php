<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use sale\catalog\ProductModel;
use sale\catalog\ProductModelCategory;

[$params, $providers] = eQual::announce([
    'description'   => "Advanced search for Categories that contains at least one activity product: returns a collection of Category according to extra parameters.",
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'description'   => 'Full name (including namespace) of the class to look into (e.g. \'core\\User\').',
            'type'          => 'string',
            'default'       => 'sale\catalog\Category'
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
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$categories_ids = [];

// #memo - table sale_product_rel_productmodel_category is used as m2m, so ProductModelCategory can be filled with non-valid objects
$productModels = ProductModel::search(['is_activity', '=', true])->read(['categories_ids']);

$map_categories_ids = [];
foreach($productModels as $id => $productModel) {
    foreach($productModel['categories_ids'] as $category_id) {
        $map_categories_ids[$category_id] = true;
    }
}
$categories_ids = array_keys($map_categories_ids);

$domain = new Domain($params['domain']);
$domain->addCondition(new DomainCondition('id', 'in', $categories_ids));
$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

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

$products_models = ProductModel::search(['is_activity', '=', true])
    ->read(['id'])
    ->get(true);

$products_models_ids = array_column($products_models, 'id');

$product_models_categories = ProductModelCategory::search(['productmodel_id', 'in', $products_models_ids])
    ->read(['category_id'])
    ->get(true);

$categories_ids = array_unique(
    array_column($product_models_categories, 'category_id')
);

$domain = new Domain($params['domain']);
$domain->addCondition(new DomainCondition('id', 'in', $categories_ids));
$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

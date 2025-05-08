<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\User;
use sale\catalog\Product;
use sale\price\PriceList;

[$params, $provider] = eQual::announce([
    'description'   => "Returns products list with prices in CSV format, the goal is to add the prices to it.",
    'params'        => [

        'price_list_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\price\PriceList',
            'description'       => "Identifier of the targeted price list."
        ]

    ],
    'access'        => [
        'visibility'            => 'protected'
    ],
    'response'      => [
        'content-type'          => 'text/csv',
        'content-disposition'   => 'inline; filename="product-export.csv"',
        'charset'               => 'utf-8',
        'accept-origin'         => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'auth' => $auth] = $provider;

$user = User::id($auth->userId())
    ->read(['id', 'login'])
    ->first();

$price_list = null;
if(isset($params['price_list_id'])) {
    $price_list = PriceList::id($params['price_list_id'])
        ->read(['id'])
        ->first();
}

$products = Product::search()
    ->read([
        'id',
        'name',
        'prices_ids' => [
            'price_list_id',
            'price',
            'vat_rate',
            'rate_class_id' => ['name']
        ]
    ])
    ->get();

$data = [
    ['product_id', 'product_name', 'price', 'vat_rate', 'rate_class']
];

foreach($products as $id => $product) {
    if(!is_null($price_list)) {
        foreach($product['prices_ids'] as $price) {
            if($price['price_list_id'] !== $price_list['id']) {
                continue;
            }

            $data[] = [
                $product['id'],
                $product['name'],
                $price['price'] ?? 0,
                $price['vat_rate'] ?? 0,
                $price['rate_class_id']['name'] ?? ''
            ];
        }
    }
    else {
        $data[] = [
            $product['id'],
            $product['name']
        ];
    }
}

$tmp_file = tempnam(sys_get_temp_dir(), 'csv');

$fp = fopen($tmp_file, 'w');
foreach($data as $row) {
    fputcsv($fp, $row);
}
fclose($fp);

$output = file_get_contents($tmp_file);

$context->httpResponse()
        ->body($output)
        ->send();

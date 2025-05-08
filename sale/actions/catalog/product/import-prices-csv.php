<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\customer\RateClass;
use sale\price\Price;
use sale\price\PriceList;

[$params, $provider] = eQual::announce([
    'description'   => "Import products prices prices from a CSV formatted file.",
    'params'        => [

        'price_list_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\price\PriceList',
            'description'       => "Identifier of the targeted price list.",
            'required'          => true
        ],

        'data' => [
            'type'              => 'binary',
            'description'       => 'Payload of the file (raw file data).',
            'required'          => true
        ]

    ],
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'constants'     => ['UPLOAD_MAX_FILE_SIZE'],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $provider;

$price_list = PriceList::id($params['price_list_id'])
    ->read(['id'])
    ->first();

if(is_null($price_list)) {
    throw new Exception("unknown_price_list", EQ_ERROR_UNKNOWN_OBJECT);
}

if(strlen($params['data']) > constant('UPLOAD_MAX_FILE_SIZE')) {
    throw new Exception("maximum_size_exceeded", EQ_ERROR_INVALID_PARAM);
}

$lines = explode(PHP_EOL, $params['data']);

$row_header = str_getcsv($lines[0]);

$map_col_pos = [];
foreach($row_header as $position => $column) {
    if(in_array($column, ['product_id', 'price', 'vat_rate', 'rate_class'])) {
        $map_col_pos[$column] = $position;
    }
}
if(!isset($map_col_pos['product_id'], $map_col_pos['price'])) {
    throw new Exception("wrong_columns_titles", EQ_ERROR_INVALID_PARAM);
}

$rate_classes = RateClass::search()
    ->read(['id', 'name'])
    ->get();
$map_rate_classes_name_id = [];
foreach($rate_classes as $id => $rate_class) {
    $map_rate_classes_name_id[$rate_class['name']] = $id;
}

for($i = 1; $i < count($lines); $i++) {
    $row = str_getcsv($lines[$i]);
    if(!isset($row[$map_col_pos['product_id']], $row[$map_col_pos['price']])) {
        continue;
    }

    $rate_class_id = null;
    $rate_class = isset($map_col_pos['rate_class'], $row[$map_col_pos['rate_class']]) ? $row[$map_col_pos['rate_class']] : null;
    if(!is_null($rate_class) && isset($map_rate_classes_name_id[$rate_class])) {
        $rate_class_id = $map_rate_classes_name_id[$rate_class];
    }

    Price::create([
        'price_list_id'     => $price_list['id'],
        'product_id'        => $row[$map_col_pos['product_id']],
        'price'             => $row[$map_col_pos['price']],
        'vat_rate'          => isset($map_col_pos['vat_rate']) ? $row[$map_col_pos['vat_rate']] : 0,
        'has_rate_class'    => !is_null($rate_class_id),
        'rate_class_id'     => $rate_class_id
    ]);
}

$context->httpResponse()
        ->status(204)
        ->send();

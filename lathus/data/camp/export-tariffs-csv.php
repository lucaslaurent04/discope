<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\CampModel;

[$params, $provider] = eQual::announce([
    'description'   => "Returns tariffs list in CSV format, for the export to Lathus website.",
    'params'        => [],
    'access'        => [
        'visibility'            => 'protected'
    ],
    'response'      => [
        'content-type'          => 'text/csv',
        'content-disposition'   => 'inline; filename="camp-export.csv"',
        'charset'               => 'utf-8',
        'accept-origin'         => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $provider;

$camp_models = CampModel::search(['is_clsh', '=', false])
    ->read(['product_id' => ['name', 'prices_ids' => ['price', 'camp_class']]])
    ->get(true);

$map_tariffs = [
    'A' => '',
    'B' => '',
    'C' => ''
];
foreach($camp_models as $camp_model) {
    if(strpos($camp_model['product_id']['name'], ' A ') !== false) {
        $map_tariffs['A'] = $camp_model['product_id'];
    }
    elseif(strpos($camp_model['product_id']['name'], ' B ') !== false) {
        $map_tariffs['B'] = $camp_model['product_id'];
    }
    elseif(strpos($camp_model['product_id']['name'], ' C ') !== false) {
        $map_tariffs['C'] = $camp_model['product_id'];
    }
}

$map_camp_classes_labels = [
    'close-member'  => 'Adhérents/Partenaires Vienne/Habitants des cantons',
    'member'        => 'Habitants Vienne/Partenaires hors Vienne',
    'other'         => 'Autres'
];

$data = [];
foreach($map_tariffs as $key => $tariff) {
    usort($tariff['prices_ids'], fn($a, $b) => $a['price'] <=> $b['price']);

    $i = 0;
    foreach($tariff['prices_ids'] as $price) {
        $data[] = [
            $key.++$i,
            intval($price['price']),
            $map_camp_classes_labels[$price['camp_class']]
        ];
    }
}

$tmp_file = tempnam(sys_get_temp_dir(), 'csv');

$fp = fopen($tmp_file, 'w');
foreach($data as $row) {
    fputcsv($fp, $row, ';');
}
fclose($fp);

$output = file_get_contents($tmp_file);

$context->httpResponse()
    ->body($output)
    ->send();

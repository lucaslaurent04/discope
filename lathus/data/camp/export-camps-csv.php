<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\Camp;

[$params, $provider] = eQual::announce([
    'description'   => "Returns camps list in CSV format, for the export to Lathus website.",
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

$camps = Camp::search(
    ['is_clsh', '=', false],
    ['sort' => ['sojourn_number' => 'asc']]
)
    ->read([
        'sojourn_number',
        'date_form',
        'date_to',
        'min_age',
        'max_age',
        'max_children',
        'enrollments_qty',
        'product_id' => ['name'],
        'product_model_id' => ['name']
    ])
    ->get();

$data = [];
foreach($camps as $camp) {
    $tariff = null;
    if(strpos($camp['product_id']['name'], ' A ') !== false) {
        $tariff = 'A';
    }
    elseif(strpos($camp['product_id']['name'], ' B ') !== false) {
        $tariff = 'B';
    }
    elseif(strpos($camp['product_id']['name'], ' C ') !== false) {
        $tariff = 'C';
    }

    if(is_null($tariff)) {
        throw new Exception("camp_product_name_without_tariff_letter", EQ_ERROR_INVALID_CONFIG);
    }

    $horseriding_level = '-1';
    if(strpos(strtolower($camp['short_name']), 'galop 3') !== false) {
        $horseriding_level = '3';
    }
    elseif(strpos(strtolower($camp['short_name']), 'galop 2') !== false) {
        $horseriding_level = '2';
    }
    elseif(strpos(strtolower($camp['short_name']), 'galop 1') !== false) {
        $horseriding_level = '1';
    }

    $data[] = [
        str_pad($camp['sojourn_number'], 3, '0', STR_PAD_LEFT),
        $camp['product_model_id']['name'],
        '',
        date('d/m/Y', $camp['date_from']),
        date('d/m/Y', $camp['date_to']),
        $camp['min_age'],
        $camp['max_age'],
        $camp['sojourn_number'] < 100 ? 'Camping' : '',
        $horseriding_level,
        $tariff,
        '',
        $camp['max_children'],
        '0',
        '',
        $camp['max_children'] - $camp['enrollments_qty']
    ];
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

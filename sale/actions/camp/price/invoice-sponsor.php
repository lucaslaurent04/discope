<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\price\PriceAdapter;
use sale\camp\Sponsor;

[$params, $providers] = eQual::announce([
    'description'   => "Invoice the given price adapters to sponsors.",
    'params'        => [

        'ids' => [
            'type'              => 'array',
            'description'       => "Ids of the price adapters to invoice.",
            'required'          => true
        ]

    ],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['camp.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/zip',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

/**
 * Methods
 */

$generateZip = function($files) {
    $tmp_file = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive();
    if($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception("unable_to_create_zip_file", EQ_ERROR_UNKNOWN);
    }

    foreach($files as $file_name => $file_data) {
        $zip->addFromString($file_name, $file_data);
    }

    $zip->close();

    $data = file_get_contents($tmp_file);
    unlink($tmp_file);

    if($data === false) {
        throw new Exception("unable_retrieve_zip_file_content", EQ_ERROR_UNKNOWN);
    }

    return $data;
};

/**
 * Action
 */

if(!class_exists('ZipArchive')) {
    throw new Exception('zip_extension_not_loaded', EQ_ERROR_UNKNOWN);
}

if(empty($params['ids'])) {
    throw new Exception("invalid_ids", EQ_ERROR_INVALID_PARAM);
}

$price_adapters = PriceAdapter::ids($params['ids'])
    ->read(['sponsor_id', 'price_adapter_type', 'value'])
    ->get();

$map_sponsor_price_adapters_ids = [];
foreach($price_adapters as $price_adapter) {
    if(is_null($price_adapter['sponsor_id'])) {
        continue;
    }
    $map_sponsor_price_adapters_ids[$price_adapter['sponsor_id']][] = $price_adapter['id'];
}

$sponsors = Sponsor::ids(array_keys($map_sponsor_price_adapters_ids))
    ->read(['name'])
    ->get(true);
$map_sponsors = [];
foreach($sponsors as $sponsor) {
    $map_sponsors[$sponsor['id']] = $sponsor;
}

$pdfs = [];
foreach($map_sponsor_price_adapters_ids as $sponsor_id => $price_adapters_ids) {
    $sponsor = $map_sponsors[$sponsor_id];
    $sponsor_name = str_replace(' ', '_', $sponsor['name']);

    $pdfs[$sponsor_name.'.pdf'] = eQual::run('do', 'sale_camp_sponsor_generate-invoice-pdf', ['ids' => $price_adapters_ids]);
}

$zip = $generateZip($pdfs);

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.zip"')
        ->body($zip)
        ->send();

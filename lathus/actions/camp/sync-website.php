<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use equal\http\HttpRequest;

[$params, $provider] = eQual::announce([
    'description'   => "Syncs Lathus website with camps and/or tariffs data.",
    'params'        => [

        'sync_camps' => [
            'type'          => 'boolean',
            'description'   => "Sync the camps.",
            'default'       => false
        ],

        'sync_tariffs' => [
            'type'          => 'boolean',
            'description'   => "Sync the tariffs.",
            'default'       => false
        ]

    ],
    'access'        => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $provider;

if(!$params['sync_camps'] && !$params['sync_tariffs']) {
    throw new Exception("nothing_to_sync", EQ_ERROR_INVALID_PARAM);
}

if($params['sync_camps']) {
    eQual::run('do', 'lathus_camp_upload-camps');
}

if($params['sync_tariffs']) {
    eQual::run('do', 'lathus_camp_upload-tariffs');
}

$sync_uri = Setting::get_value('sale', 'integration', 'camp.sync_website.sync_uri');
if(is_null($sync_uri)) {
    throw new Exception("sync_uri_setting_not_defined", EQ_ERROR_INVALID_CONFIG);
}

$request = new HttpRequest('GET '.$sync_uri);
$response = $request->send();

$status = $response->getStatusCode();
if($status !== 200) {
    throw new Exception("request_rejected", QN_ERROR_INVALID_PARAM);
}

$context->httpResponse()
        ->status(200)
        ->send();



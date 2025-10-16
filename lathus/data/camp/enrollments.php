<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use equal\http\HttpRequest;

[$params, $providers] = eQual::announce([
    'description'   => "Retrieve a batch of the latest enrollments, as provided from CPA Lathus API in response to configured api_uri.",
    'params'        => [
    ],
    'access'        => [
        'visibility'    => 'protected',
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

$api_uri = Setting::get_value('sale', 'integration', 'camp.enrollments.api_uri');
if(is_null($api_uri)) {
    throw new \Exception("missing_api_uri", EQ_ERROR_INVALID_CONFIG);
}

$api_key = Setting::get_value('sale', 'integration', 'camp.enrollments.api_key');
if(is_null($api_key)) {
    throw new \Exception("missing_api_key", EQ_ERROR_INVALID_CONFIG);
}

$request = new HttpRequest('GET '.$api_uri);

$request->header('Content-Type', 'application/json');
$request->header('X-API-KEY', $api_key);

$response = $request->send();

$status = $response->getStatusCode();
if($status != 200) {
    // upon request rejection, we stop the whole job
    throw new Exception("request_rejected", QN_ERROR_INVALID_PARAM);
}

$data = $response->body();

$context->httpResponse()
        ->body($data)
        ->send();

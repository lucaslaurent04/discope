<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use identity\User;

list($params, $providers) = eQual::announce([
    'description'   => "Returns configurations of display for Booked Services.",
    'access'        => [
        'visibility'    => 'protected'
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

$user = User::id($auth->userId())
    ->read(['id', 'organisation_id'])
    ->first();

if(is_null($user)) {
    throw new Exception('unexpected_error', EQ_ERROR_INVALID_USER);
}

$result = [
    'store_folded_settings' => Setting::get_value('sale', 'booking', 'display.store.folded.settings', false),
    'identification_folded' => Setting::get_value('sale', 'booking', 'display.identification.folded', true),
    'products_folded'       => Setting::get_value('sale', 'booking', 'display.products.folded', true),
    'activities_folded'     => Setting::get_value('sale', 'booking', 'display.activities.folded', true),
    'accomodations_folded'  => Setting::get_value('sale', 'booking', 'display.accomodations.folded', true),
    'meals_folded'          => Setting::get_value('sale', 'booking', 'display.meals.folded', true)
];

if(isset($user['organisation_id'])) {
    $result['store_folded_settings'] = Setting::get_value('sale', 'booking', "display.store.folded.settings.{$user['organisation_id']}", $result['store_folded_settings']);
    $result['identification_folded'] = Setting::get_value('sale', 'booking', "display.identification.folded.{$user['organisation_id']}", $result['identification_folded']);
    $result['products_folded'] = Setting::get_value('sale', 'booking', "display.products.folded.{$user['organisation_id']}", $result['products_folded']);
    $result['activities_folded'] = Setting::get_value('sale', 'booking', "display.activities.folded.{$user['organisation_id']}", $result['activities_folded']);
    $result['accomodations_folded'] = Setting::get_value('sale', 'booking', "display.accomodations.folded.{$user['organisation_id']}", $result['accomodations_folded']);
    $result['meals_folded'] = Setting::get_value('sale', 'booking', "display.meals.folded.{$user['organisation_id']}", $result['meals_folded']);
}

file_put_contents(QN_LOG_STORAGE_DIR.'/tmp.log', json_encode($result).PHP_EOL, FILE_APPEND | LOCK_EX);

$context->httpResponse()
        ->body($result)
        ->send();

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
    'store_folded_settings' => Setting::get_value('sale', 'features', 'ui.booking.store_folded_settings', false),
    'identification_folded' => Setting::get_value('sale', 'features', 'ui.booking.identification_folded', true),
    'products_folded'       => Setting::get_value('sale', 'features', 'ui.booking.products_folded', true),
    'activities_folded'     => Setting::get_value('sale', 'features', 'ui.booking.activities_folded', true),
    'accommodations_folded' => Setting::get_value('sale', 'features', 'ui.booking.accommodations_folded', true),
    'meals_folded'          => Setting::get_value('sale', 'features', 'ui.booking.meals_folded', true),
    'activities_show'       => Setting::get_value('sale', 'features', 'booking.activity', false),
    'meals_show'            => Setting::get_value('sale', 'features', 'booking.meal', true),
];

$context->httpResponse()
        ->body($result)
        ->send();

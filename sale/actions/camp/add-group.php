<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\Camp;
use sale\camp\CampGroup;

[$params, $providers] = eQual::announce([
    'description'   => "Add a new group to the given camp.",
    'help'          => "Usually the max camp group quantity is two.",
    'params'        => [

        'id' => [
            'type'              => 'integer',
            'description'       => "Identifier of the camp that needs a new group.",
            'min'               => 1,
            'required'          => true
        ]

    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['camp.default.administrator'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$camp = Camp::id($params['id'])
    ->read(['id'])
    ->first();

if(is_null($camp)) {
    throw new Exception("unknown_camp", EQ_ERROR_UNKNOWN_OBJECT);
}

CampGroup::create(['camp_id' => $camp['id']])
    ->read(['id'])
    ->first();

$context->httpResponse()
        ->status(204)
        ->send();

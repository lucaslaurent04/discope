<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;

[$params, $providers] = eQual::announce([
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'type'              => 'string',
            'description'       => "Full name (including namespace) of the class to look into (e.g. 'core\\User').",
            'default'           => 'sale\booking\BookingMeal'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit.",
            'default'           => fn() => time()
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Date interval upper limit.",
            'default'           => fn() => time()
        ],
        'camp_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Camp',
            'description'       => "The camp the meal relates to."
        ],
        'time_slot_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\TimeSlot',
            'description'       => "Specific day time slot on which the service is delivered."
        ],
        'meal_type_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\MealType',
            'description'       => "Type of the meal being served."
        ],
        'meal_place_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\MealPlace',
            'description'       => "Place where the meal is served."
        ]
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

$result = [];

$domain = new Domain($params['domain']);

if(isset($params['date_from'])) {
    $domain->addCondition(
        new DomainCondition('date', '>=', $params['date_from'])
    );
}

if(isset($params['date_to'])) {
    $domain->addCondition(
        new DomainCondition('date', '<=', $params['date_to'])
    );
}

if(isset($params['camp_id'])) {
    $domain->addCondition(
        new DomainCondition('camp_id', '=', $params['camp_id'])
    );
}

if(isset($params['time_slot_id'])) {
    $domain->addCondition(
        new DomainCondition('time_slot_id', '=', $params['time_slot_id'])
    );
}

if(isset($params['meal_type_id'])) {
    $domain->addCondition(
        new DomainCondition('meal_type_id', '=', $params['meal_type_id'])
    );
}

if(isset($params['meal_place_id'])) {
    $domain->addCondition(
        new DomainCondition('meal_place_id', '=', $params['meal_place_id'])
    );
}

$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

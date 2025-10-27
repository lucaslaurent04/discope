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
            'default'           => 'sale\camp\Camp'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit.",
            'default'           => fn() => strtotime('last Sunday')
        ],
        'sojourn_number' => [
            'type'              => 'string',
            'description'       => "Sojourn number."
        ]
    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['camp.default.user'],
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

$domain->addCondition(new DomainCondition('status', '=', 'published'));

if(isset($params['date_from'])) {
    $day_of_week = date('w', $params['date_from']);

    // find previous Sunday
    $sunday = $params['date_from'] - ($day_of_week * 86400);

    // next Friday (+5 days)
    $friday = $sunday + (5 * 86400);

    $domain->addCondition(new DomainCondition('date_from', '>=', $sunday));
    $domain->addCondition(new DomainCondition('date_from', '<=', $friday));
}

if(!empty($params['sojourn_number'])) {
    $domain->addCondition(new DomainCondition('sojourn_number', 'like', "%{$params['sojourn_number']}%"));
}

$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

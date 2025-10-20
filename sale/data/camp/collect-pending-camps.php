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

        'age_range' => [
            'type'              => 'string',
            'description'       => "Age range of the accepted participants.",
            'selection'         => [
                'all',
                '6-to-9',
                '10-to-12',
                '13-to-16'
            ],
            'default'           => 'all'
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

$day_of_week = date('w', $params['date_from']);

// find previous Sunday
$sunday = $params['date_from'] - ($day_of_week * 86400);

// next Friday (+5 days)
$friday = $sunday + (5 * 86400);

$domain->addCondition(new DomainCondition('date_from', '>=', $sunday));
$domain->addCondition(new DomainCondition('date_from', '<=', $friday));

if($params['age_range'] !== 'all') {
    $domain->addCondition(new DomainCondition('age_range', '=', $params['age_range']));
}

$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

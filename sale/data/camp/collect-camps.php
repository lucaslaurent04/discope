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
            'default'           => function () {
                $year = (date('n') >= 10) ? date('Y') + 1 : date('Y');
                return strtotime("first day of January {$year}");
            }
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Date interval upper limit.",
            'default'           => function () {
                $year = (date('n') >= 10) ? date('Y') + 1 : date('Y');
                return strtotime("last day of December {$year}");
            }
        ],
        'status' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'draft',
                'published',
                'cancelled'
            ],
            'description'       => "Status of the camp.",
            'default'           => 'all'
        ],
        'camp_type' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'sport',
                'circus',
                'culture',
                'environment',
                'horse-riding',
                'recreation'
            ],
            'description'       => "Type of camp.",
            'default'           => 'all'
        ],
        'sojourn_type' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'camp',
                'clsh',
                'clsh-4-days',
                'clsh-5-days'
            ],
            'description'       => "Sojourn type of the camp.",
            'default'           => 'all'
        ],
        'camp_model_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\CampModel',
            'description'       => "Model that was used as a base to create this camp."
        ],
        'min_age' => [
            'type'              => 'integer',
            'description'       => "Min age of the children.",
            'default'           => 0
        ],
        'max_age' => [
            'type'              => 'integer',
            'description'       => "Max age of the children.",
            'default'           => 18
        ],
        'age_range' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                '6-to-9',
                '10-to-12',
                '13-to-16'
            ],
            'description'       => "Age range of the camp.",
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

if(isset($params['date_from'])) {
    $domain->addCondition(
        new DomainCondition('date_from', '>=', $params['date_from'])
    );
}

if(isset($params['date_to'])) {
    $domain->addCondition(
        new DomainCondition('date_from', '<=', $params['date_to'])
    );
}

if(isset($params['status']) && $params['status'] !== 'all') {
    $domain->addCondition(
        new DomainCondition('status', '=', $params['status'])
    );
}

if(isset($params['camp_type']) && $params['camp_type'] !== 'all') {
    $domain->addCondition(
        new DomainCondition('camp_type', '=', $params['camp_type'])
    );
}

if(isset($params['sojourn_type']) && $params['sojourn_type'] !== 'all') {
    $conditions = [];
    switch($params['sojourn_type']) {
        case 'camp':
            $conditions[] = new DomainCondition('is_clsh', '=', false);
            break;
        case 'clsh':
            $conditions[] = new DomainCondition('is_clsh', '=', true);
            break;
        case 'clsh-4-days':
            $conditions[] = new DomainCondition('is_clsh', '=', true);
            $conditions[] = new DomainCondition('clsh_type', '=', '4-days');
            break;
        case 'clsh-5-days':
            $conditions[] = new DomainCondition('is_clsh', '=', true);
            $conditions[] = new DomainCondition('clsh_type', '=', '5-days');
            break;
    }

    foreach($conditions as $condition) {
        $domain->addCondition($condition);
    }
}

if(isset($params['camp_model_id']) && $params['camp_model_id'] > 0) {
    $domain->addCondition(
        new DomainCondition('camp_model_id', '=', $params['camp_model_id'])
    );
}

if(isset($params['min_age']) && $params['min_age'] >= 0) {
    $domain->addCondition(
        new DomainCondition('min_age', '>=', $params['min_age'])
    );
}

if(isset($params['max_age']) && $params['max_age'] >= 0) {
    $domain->addCondition(
        new DomainCondition('max_age', '<=', $params['max_age'])
    );
}

if($params['age_range'] !== 'all') {
    $domain->addCondition(
        new DomainCondition('age_range', '=', $params['age_range'])
    );
}

$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

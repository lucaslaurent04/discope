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
            'default'           => 'sale\camp\Enrollment'
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit.",
            'default'           => function () {
                $year = (date('n') >= 11) ? date('Y') + 1 : date('Y');
                return strtotime("first day of January {$year}");
            }
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Date interval upper limit.",
            'default'           => function () {
                $year = (date('n') >= 11) ? date('Y') + 1 : date('Y');
                return strtotime("last day of December {$year}");
            }
        ],

        'status' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'pending',
                'waitlisted',
                'confirmed',
                'validated',
                'cancelled'
            ],
            'description'       => "Status of the enrollment.",
            'default'           => 'all'
        ],

        'payment_status' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'due',
                'paid'
            ],
            'description'       => "Payment status of the enrollment.",
            'default'           => 'all'
        ],

        'camp_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Camp',
            'description'       => "The camp of the enrollment."
        ],

        'is_ase' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'yes',
                'no'
            ],
            'description'       => "The camp of the enrollment.",
            'default'           => 'all'
        ],

        'domain' => [
            'type'          => 'array',
            'description'   => "Criteria that results have to match (series of conjunctions)",
            'default'       => []
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

if($params['status'] !== 'all') {
    $domain->addCondition(
        new DomainCondition('status', '=', $params['status'])
    );
}

if($params['payment_status'] !== 'all') {
    $domain->addCondition(
        new DomainCondition('payment_status', '=', $params['payment_status'])
    );
}

if(isset($params['camp_id'])) {
    $domain->addCondition(
        new DomainCondition('camp_id', '=', $params['camp_id'])
    );
}

if($params['is_ase'] !== 'all') {
    $domain->addCondition(
        new DomainCondition('is_ase', '=', $params['is_ase'] === 'yes')
    );
}

$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

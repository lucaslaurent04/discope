<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use equal\orm\DomainCondition;
use sale\camp\Camp;

[$params, $providers] = eQual::announce([
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'type'              => 'string',
            'description'       => "Full name (including namespace) of the class to look into (e.g. 'core\\User').",
            'default'           => 'sale\camp\price\PriceAdapter'
        ],
        'sponsor_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Sponsor',
            'description'       => "Sponsor that is concerned by the price adapter."
        ],
        'origin_type' => [
            'type'              => 'string',
            'description'       => "Type of price adapter.",
            'selection'         => [
                'all',
                'other',
                'commune',
                'community-of-communes',
                'department-caf',
                'department-msa',
                'loyalty-discount'
            ],
            'default' => 'all'
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit."
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Date interval upper limit.'
        ],
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

$domain->addCondition(
    new DomainCondition('price_adapter_type', '=', 'amount')
);

if(isset($params['sponsor_id'])) {
    $domain->addCondition(
        new DomainCondition('sponsor_id', '=', $params['sponsor_id'])
    );
}

if($params['origin_type'] !== 'all') {
    $domain->addCondition(
        new DomainCondition('origin_type', '=', $params['origin_type'])
    );
}

if(isset($params['date_from']) || isset($params['date_to'])) {
    $camp_dom = [];
    if(isset($params['date_from'])) {
        $camp_dom[] = ['date_from', '>=', $params['date_from']];
    }
    if(isset($params['date_to'])) {
        $camp_dom[] = ['date_to', '<=', $params['date_to']];
    }

    $camps = Camp::search($camp_dom)
        ->read(['enrollments_ids'])
        ->get(true);

    $enrollments_ids = [];
    foreach($camps as $camp) {
        $enrollments_ids = [...$enrollments_ids, ...$camp['enrollments_ids']];
    }

    $domain->addCondition(
        new DomainCondition('enrollment_id', 'in', $enrollments_ids)
    );
}

$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

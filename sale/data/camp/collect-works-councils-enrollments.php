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
            'default'           => 'sale\camp\Enrollment'
        ],
        'works_council_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\WorksCouncil',
            'description'       => "Works council that is concerned by the enrollment."
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit.",
            'default'           => fn() => strtotime('first day of January this year')
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Date interval upper limit.',
            'default'           => fn() => strtotime('last day of December this year')
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

if(isset($params['works_council_id'])) {
    $domain->addCondition(
        new DomainCondition('works_council_id', '=', $params['works_council_id'])
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
        $enrollments_ids = array_merge($enrollments_ids, $camp['enrollments_ids']);
    }

    $domain->addCondition(
        new DomainCondition('id', 'in', $enrollments_ids)
    );
}

$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

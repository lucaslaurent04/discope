<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use equal\orm\DomainClause;
use equal\orm\DomainCondition;
use sale\camp\Camp;

[$params, $providers] = eQual::announce([
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'type'              => 'string',
            'description'       => "Full name (including namespace) of the class to look into (e.g. 'core\\User').",
            'default'           => 'sale\camp\Presence'
        ],
        'child_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Child',
            'description'       => "Child concerned by the presences."
        ],
        'camp_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Camp',
            'description'       => "Camp concerned by the presences."
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
        'am_daycare' => [
            'type'              => 'boolean',
            'description'       => "Show only AM day cares presences."
        ],
        'pm_daycare' => [
            'type'              => 'boolean',
            'description'       => "Show only PM day cares presences."
        ],
        'sojourn_type' => [
            'type'              => 'string',
            'selection'         => [
                'all',
                'camp',
                'clsh',
                'clsh-4-days',
                'clsh-5-days',
            ],
            'description'       => "The camp sojourn type that was used with the enrollment.",
            'default'           => 'all'
        ],
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

// day care OR filter will not work if more than one clause
if(count($domain->getClauses()) > 1) {
    throw new Exception("only_one_clause_allowed", EQ_ERROR_INVALID_PARAM);
}

// two domain clauses if AM and PM day care are true (OR)
if(($params['am_daycare'] ?? false) && ($params['pm_daycare'] ?? false)) {
    $domain->addCondition(new DomainCondition('am_daycare', '=', true));
    $domain->addClause(new DomainClause([new DomainCondition('pm_daycare', '=', true)]));
}
elseif($params['am_daycare'] ?? false) {
    $domain->addCondition(new DomainCondition('am_daycare', '=', true));
}
elseif($params['pm_daycare'] ?? false) {
    $domain->addCondition(new DomainCondition('pm_daycare', '=', true));
}

if(isset($params['child_id'])) {
    $domain->addCondition(
        new DomainCondition('child_id', '=', $params['child_id'])
    );
}

if(isset($params['camp_id'])) {
    $domain->addCondition(
        new DomainCondition('camp_id', '=', $params['camp_id'])
    );
}

if(isset($params['date_from'])) {
    $domain->addCondition(
        new DomainCondition('presence_date', '>=', $params['date_from'])
    );
}

if(isset($params['date_to'])) {
    $domain->addCondition(
        new DomainCondition('presence_date', '<=', $params['date_to'])
    );
}

if($params['sojourn_type'] !== 'all') {
    $camp_domain = [];
    if(strpos($params['sojourn_type'], 'clsh') !== false) {
        $camp_domain[] = ['is_clsh', '=', true];
        if(in_array($params['sojourn_type'], ['clsh-4-days', 'clsh-5-days'])) {
            $clsh_type = $params['sojourn_type'] === 'clsh-4-days' ? '4-days' : '5-days';
            $camp_domain[] = ['clsh_type', '=', $clsh_type];
        }
    }
    else {
        $camp_domain = ['is_clsh', '=', false];
    }

    $camps_ids = Camp::search($camp_domain)->ids();

    $domain->addCondition(
        new DomainCondition('camp_id', 'in', $camps_ids)
    );
}

$params['domain'] = $domain->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();

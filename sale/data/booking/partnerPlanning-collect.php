<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\BookingActivity;

[$params, $providers] = eQual::announce([
    'description'   => "List activities of partners, used for reminding activities to external employees and providers.",
    'params'        => [
        /**
         * Filters
         */
        'partner_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Partner',
            'description'       => "The partner concerned by the planning.",
            'domain'            => ['relationship', 'in', ['provider', 'employee']]
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Start of the time interval of the desired plannings.",
            'default'           => fn() => time()
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "End of the time interval of the desired plannings.",
            'default'           => fn() => strtotime('+1 month')
        ],
        'domain' => [
            'type'              => 'array',
            'description'       => "Criteria that results have to match.",
            'default'           => []
        ],

        /**
         * Virtual model columns
         */
        'id' => [
            'type'              => 'integer',
            'description'       => "The id of the partner concerned by the planning."
        ],
        'employee_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'hr\employee\Employee',
            'description'       => "The employee concerned by the planning.",
            'default'           => null
        ],
        'provider_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\provider\Provider',
            'description'       => "The provider concerned by the planning.",
            'default'           => null
        ],
        'relationship' => [
            'type'              => 'string',
            'selection'         => [
                'employee',
                'provider'
            ]
        ],
        'activities_qty' => [
            'type'              => 'integer',
            'description'       => "The quantities of activities that the partner has to handle for the given time range.",
            'default'           => 0
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
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$result = [];

$domain = [
    ['activity_date', '>=', $params['date_from']],
    ['activity_date', '<=', $params['date_to']],
    ['is_cancelled', '=', false]
];

if(!isset($params['relationship']) && !empty($params['domain'])) {
    if(is_array($params['domain'][0]) && !empty($params['domain'][0]) && is_string($params['domain'][0][0])) {
        foreach($params['domain'] as $condition) {
            if($condition[0] === 'relationship' && $condition[1] === '=') {
                $params['relationship'] = $condition[2];
                break;
            }
        }
    }
    elseif(is_array($params['domain'][0]) && !empty($params['domain'][0]) && is_array($params['domain'][0][0])) {
        foreach($params['domain'] as $conditions) {
            foreach($conditions as $condition) {
                if($condition[0] === 'relationship' && $condition[1] === '=') {
                    $params['relationship'] = $condition[2];
                    break 2;
                }
            }
        }
    }
}

$show = ['employee', 'provider'];
if(isset($params['relationship'])) {
    if($params['relationship'] === 'employee') {
        $domain[] = ['has_staff_required', '=', true];
        $show = ['employee'];
    }
    elseif($params['relationship'] === 'provider') {
        $domain[] = ['has_provider', '=', true];
        $show = ['provider'];
    }
}

$activities = BookingActivity::search($domain)
    ->read([
        'employee_id'   => ['name'],
        'providers_ids' => ['name']
    ])
    ->adapt('json')
    ->get();

$map_partner_planning = [];
foreach($activities as $activity) {
    if(in_array('employee', $show) && !is_null($activity['employee_id']['id'])) {
        $employee_id = $activity['employee_id']['id'];
        if(empty($params['partner_id']) || $employee_id === $params['partner_id']) {
            if(!isset($map_partner_planning[$employee_id])) {
                $map_partner_planning[$employee_id] = [
                    'id'                => $employee_id,
                    'employee_id'       => $activity['employee_id'],
                    'provider_id'       => null,
                    'relationship'      => 'employee',
                    'activities_qty'    => 0
                ];
            }

            $map_partner_planning[$employee_id]['activities_qty']++;
        }
    }

    if(in_array('provider', $show)) {
        foreach($activity['providers_ids'] as $provider) {
            $provider_id = $provider['id'];
            if(!empty($params['partner_id']) && $provider_id !== $params['partner_id']) {
                continue;
            }

            if(!isset($map_partner_planning[$provider_id])) {
                $map_partner_planning[$provider_id] = [
                    'id'                => $provider_id,
                    'employee_id'       => null,
                    'provider_id'       => $provider,
                    'relationship'      => 'provider',
                    'activities_qty'    => 0
                ];
            }

            $map_partner_planning[$provider_id]['activities_qty']++;
        }
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($map_partner_planning))
        ->body(array_values($map_partner_planning))
        ->send();

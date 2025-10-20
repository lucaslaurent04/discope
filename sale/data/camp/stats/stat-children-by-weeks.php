<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use identity\User;
use sale\camp\Camp;
use sale\camp\Sponsor;

[$params, $providers] = eQual::announce([
    'description'   => "Data about children's participation to camps.",
    'params'        => [
        'all_centers' => [
            'type'              => 'boolean',
            'description'       => "Mark all the centers of the children quantities.",
            'default'           => false
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => "Center for the children quantities.",
            'default'           => 1
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit (defaults to first day of the current month).",
            'default'           => fn() => strtotime('first day of this month')
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Date interval upper limit (defaults to last day of the current month).",
            'default'           => fn() => strtotime('last day of this month')
        ],

        /* parameters used as properties of virtual entity */
        'center' => [
            'type'              => 'string',
            'description'       => "Name of the center for the children quantities."
        ],
        'week' => [
            'type'              => 'date',
            'description'       => "Week of the year, first day of the week."
        ],
        'qty_week_male' => [
            'type'              => 'integer',
            'description'       => "Quantity of male children during week."
        ],
        'qty_week_female' => [
            'type'              => 'integer',
            'description'       => "Quantity of female children during week."
        ],
        'qty_week' => [
            'type'              => 'integer',
            'description'       => "Quantity of children during week."
        ],
        'qty_weekend_male' => [
            'type'              => 'integer',
            'description'       => "Quantity of male children during weekend."
        ],
        'qty_weekend_female' => [
            'type'              => 'integer',
            'description'       => "Quantity of female children during weekend."
        ],
        'qty_weekend' => [
            'type'              => 'integer',
            'description'       => "Quantity of children during weekend."
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
    'providers'     => ['context', 'adapt' , 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\data\adapt\AdapterProvider   $adapter_provider
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'adapt' => $adapter_provider, 'auth' => $auth] = $providers;

/** @var \equal\data\adapt\DataAdapterJson $json_adapter */
$json_adapter = $adapter_provider->get('json');

$domain = [
    ['date_from', '>=', $params['date_from']],
    ['date_from', '<=', $params['date_to']],
    ['status', '<>', 'cancelled']
];

if($params['all_centers']) {
    $user_id = $auth->userId();
    if($user_id <= 0) {
        throw new Exception("unknown_user", EQ_ERROR_NOT_ALLOWED);
    }
    $user = User::id($user_id)->read(['centers_ids'])->first();
    if(is_null($user)) {
        throw new Exception("unexpected_error", EQ_ERROR_INVALID_USER);
    }
    $domain[] = ['center_id', 'in', $user['centers_ids']];
}
elseif(isset($params['center_id']) && $params['center_id'] > 0) {
    $domain[] = ['center_id', '=', $params['center_id']];
}

$result = [];

$camps = Camp::search($domain)
    ->read([
        'center_id',
        'date_from',
        'enrollments_ids' => [
            'status',
            'weekend_extra',
            'child_id'      => ['gender']
        ]
    ])
    ->get(true);

$map_center_weeks_children_qty = [];

foreach($camps as $camp) {
    foreach($camp['enrollments_ids'] as $enrollment) {
        if($enrollment['status'] !== 'validated') {
            continue;
        }

        if(!isset($map_center_weeks_children_qty[$camp['center_id']])) {
            $map_center_weeks_children_qty[$camp['center_id']] = [];
        }

        $week = date('W', $camp['date_from']);
        $start_on_sunday = date('w', $camp['date_from']) === '0';
        if($start_on_sunday) {
            $week = strval(intval($week) + 1);
        }

        $date = new DateTime();
        $date->setISODate(date('Y', $camp['date_from']), $week);

        $week = $date->format('Y-m-d');

        if(!isset($map_center_weeks_children_qty[$camp['center_id']][$week])) {
            $map_center_weeks_children_qty[$camp['center_id']][$week] = [
                'week'      => 0,
                'week_M'    => 0,
                'week_F'    => 0,
                'weekend'   => 0,
                'weekend_M' => 0,
                'weekend_F' => 0
            ];
        }

        $map_center_weeks_children_qty[$camp['center_id']][$week]['week']++;
        $map_center_weeks_children_qty[$camp['center_id']][$week]['week_'.$enrollment['child_id']['gender']]++;
        if($enrollment['weekend_extra'] === 'full') {
            $map_center_weeks_children_qty[$camp['center_id']][$week]['weekend']++;
            $map_center_weeks_children_qty[$camp['center_id']][$week]['weekend_'.$enrollment['child_id']['gender']]++;
        }
    }
}

$center_ids = array_keys($map_center_weeks_children_qty);

$centers = Center::search(['id', 'in', $center_ids])
    ->read(['name'])
    ->get();

foreach($map_center_weeks_children_qty as $center_id => $map_weeks_children_qty) {
    $center = null;
    foreach ($centers as $c) {
        if ($c['id'] === $center_id) {
            $center = $c['name'];
            break;
        }
    }

    foreach($map_weeks_children_qty as $week => $map_children_qty) {
        $result[] = [
            'center'                => $center,
            'week'                  => $json_adapter->adaptOut(DateTime::createFromFormat('Y-m-d', $week)->getTimestamp(), 'date'),
            'qty_week'              => $map_children_qty['week'],
            'qty_week_male'         => $map_children_qty['week_M'],
            'qty_week_female'       => $map_children_qty['week_F'],
            'qty_weekend'           => $map_children_qty['weekend'],
            'qty_weekend_male'      => $map_children_qty['weekend_M'],
            'qty_weekend_female'    => $map_children_qty['weekend_F']
        ];
    }
}

usort($result, function($a, $b) {
    $result = strcmp($a['center'], $b['center']);
    if($result === 0) {
        return strcmp($a['week'], $b['week']);
    }
    return $result;
});

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();

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
        'status' => [
            'type'              => 'string',
            'description'       => "The status of the enrollments.",
            'selection'         => [
                'all',
                'validated',
                'confirmed',
                'pending',
                'waitlisted',
                'cancelled'
            ],
            'default'           => 'validated'
        ],

        /* parameters used as properties of virtual entity */
        'center' => [
            'type'              => 'string',
            'description'       => "Name of the center for the enrollments quantities."
        ],
        'age_range' => [
            'type'              => 'string',
            'description'       => "The age range in which the child is from."
        ],
        'qty' => [
            'type'              => 'integer',
            'description'       => "Quantity of enrollments of the age range."
        ]
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

$json_adapter = $adapter_provider->get('json');

$domain = [
    ['date_from', '>=', $params['date_from']],
    ['date_from', '<=', $params['date_to']]
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
        'min_age',
        'max_age',
        'center_id',
        'enrollments_ids' => [
            'status'
        ]
    ])
    ->get(true);

$map_center_age_ranges_enrollments_qty = [];

foreach($camps as $camp) {
    $age_range_key = $camp['min_age'].' - '.$camp['max_age'];
    if(!isset($map_center_age_ranges_enrollments_qty[$camp['center_id']])) {
        $map_center_age_ranges_enrollments_qty[$camp['center_id']] = [];
    }

    foreach($camp['enrollments_ids'] as $enrollment) {
        if($params['status'] !== 'all' && $enrollment['status'] !== $params['status']) {
            continue;
        }

        if(!isset($map_center_age_ranges_enrollments_qty[$camp['center_id']][$age_range_key])) {
            $map_center_age_ranges_enrollments_qty[$camp['center_id']][$age_range_key] = 0;
        }

        $map_center_age_ranges_enrollments_qty[$camp['center_id']][$age_range_key]++;
    }
}

$center_ids = array_keys($map_center_age_ranges_enrollments_qty);

$centers = Center::search(['id', 'in', $center_ids])
    ->read(['name'])
    ->get();

foreach($map_center_age_ranges_enrollments_qty as $center_id => $map_age_ranges_qty) {
    $center = null;
    foreach ($centers as $c) {
        if ($c['id'] === $center_id) {
            $center = $c['name'];
            break;
        }
    }

    foreach($map_age_ranges_qty as $age_range => $qty) {
        $result[] = [
            'center'    => $center,
            'age_range' => $age_range,
            'qty'       => $qty
        ];
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();

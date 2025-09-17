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
use sale\camp\WorksCouncil;

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
        'works_council_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\WorksCouncil',
            'description'       => "The works council that was used with the enrollment."
        ],
        'qty' => [
            'type'              => 'integer',
            'description'       => "Quantity of enrollments helped by the works council."
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
        'enrollments_ids' => [
            'status',
            'works_council_id'
        ]
    ])
    ->get(true);

$map_center_works_councils_enrollments_qty = [];
$map_works_councils = [];

foreach($camps as $camp) {
    foreach($camp['enrollments_ids'] as $enrollment) {
        if($params['status'] !== 'all' && $enrollment['status'] !== $params['status']) {
            continue;
        }

        if(!isset($enrollment['works_council_id'])) {
            continue;
        }

        if(!isset($map_center_works_councils_enrollments_qty[$camp['center_id']])) {
            $map_center_works_councils_enrollments_qty[$camp['center_id']] = [];
        }
        if(!isset($map_center_works_councils_enrollments_qty[$camp['center_id']][$enrollment['works_council_id']])) {
            $map_center_works_councils_enrollments_qty[$camp['center_id']][$enrollment['works_council_id']] = 0;
        }

        $map_center_works_councils_enrollments_qty[$camp['center_id']][$enrollment['works_council_id']]++;
        $map_works_councils[$enrollment['works_council_id']] = true;
    }
}

$center_ids = array_keys($map_center_works_councils_enrollments_qty);

$centers = Center::search(['id', 'in', $center_ids])
    ->read(['name'])
    ->get();

$works_councils_ids = array_keys($map_works_councils);

$works_councils = WorksCouncil::search(['id', 'in', $works_councils_ids])
    ->read(['name'])
    ->get();

foreach($map_center_works_councils_enrollments_qty as $center_id => $map_works_councils_qty) {
    $center = null;
    foreach($centers as $c) {
        if($c['id'] === $center_id) {
            $center = $c['name'];
            break;
        }
    }

    foreach($map_works_councils_qty as $works_council_id => $qty) {
        $works_council = null;
        foreach($works_councils as $wc) {
            if($wc['id'] === $works_council_id) {
                $works_council = $wc['name'];
                break;
            }
        }

        $result[] = [
            'center'            => $center,
            'works_council_id'  => ['id' => $works_council_id, 'name' => $works_council],
            'qty'               => $qty
        ];
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();

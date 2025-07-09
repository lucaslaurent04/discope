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
        'sponsor_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Sponsor',
            'description'       => "The sponsor that was used with the enrollment."
        ],
        'qty' => [
            'type'              => 'integer',
            'description'       => "Quantity of enrollments helped by the sponsor."
        ],
        'amount' => [
            'type'              => 'float',
            'usage'             => 'amount/money:2',
            'description'       => "Total amount of enrollments helped by the sponsor."
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
        'center_id',
        'enrollments_ids' => [
            'status',
            'price_adapters_ids' => [
                'sponsor_id',
                'value'
            ]
        ]
    ])
    ->get(true);

$map_center_sponsors_enrollments_qty = [];
$map_center_sponsors_enrollments_amount = [];
$map_sponsors = [];

foreach($camps as $camp) {
    foreach($camp['enrollments_ids'] as $enrollment) {
        if($params['status'] !== 'all' && $enrollment['status'] !== $params['status']) {
            continue;
        }

        foreach($enrollment['price_adapters_ids'] as $price_adapter) {
            if(!isset($price_adapter['sponsor_id'])) {
                continue;
            }

            if(!isset($map_center_sponsors_enrollments_qty[$camp['center_id']])) {
                $map_center_sponsors_enrollments_qty[$camp['center_id']] = [];
            }
            if(!isset($map_center_sponsors_enrollments_qty[$camp['center_id']][$price_adapter['sponsor_id']])) {
                $map_center_sponsors_enrollments_qty[$camp['center_id']][$price_adapter['sponsor_id']] = 0;
            }

            if(!isset($map_center_sponsors_enrollments_amount[$camp['center_id']])) {
                $map_center_sponsors_enrollments_amount[$camp['center_id']] = [];
            }
            if(!isset($map_center_sponsors_enrollments_amount[$camp['center_id']][$price_adapter['sponsor_id']])) {
                $map_center_sponsors_enrollments_amount[$camp['center_id']][$price_adapter['sponsor_id']] = 0.0;
            }

            $map_center_sponsors_enrollments_qty[$camp['center_id']][$price_adapter['sponsor_id']]++;
            $map_center_sponsors_enrollments_amount[$camp['center_id']][$price_adapter['sponsor_id']] += floatval($price_adapter['value']);
            $map_sponsors[$price_adapter['sponsor_id']] = true;
        }
    }
}

$center_ids = array_keys($map_center_sponsors_enrollments_qty);

$centers = Center::search(['id', 'in', $center_ids])
    ->read(['name'])
    ->get();

$sponsors_ids = array_keys($map_sponsors);

$sponsors = Sponsor::search(['id', 'in', $sponsors_ids])
    ->read(['name'])
    ->get();

foreach($map_center_sponsors_enrollments_qty as $center_id => $map_sponsors_qty) {
    $center = null;
    foreach($centers as $c) {
        if($c['id'] === $center_id) {
            $center = $c['name'];
            break;
        }
    }

    foreach($map_sponsors_qty as $sponsor_id => $qty) {
        $sponsor = null;
        foreach($sponsors as $s) {
            if($s['id'] === $sponsor_id) {
                $sponsor = $s['name'];
                break;
            }
        }

        $result[] = [
            'center'        => $center,
            'sponsor_id'    => ['id' => $sponsor_id, 'name' => $sponsor],
            'qty'           => $qty,
            'amount'        => $json_adapter->adaptOut($map_center_sponsors_enrollments_amount[$center_id][$sponsor_id], 'amount/money:4')
        ];
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();

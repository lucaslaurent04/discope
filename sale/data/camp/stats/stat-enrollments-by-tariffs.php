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
use sale\camp\catalog\Product;

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
        'product_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\catalog\Product',
            'description'       => "The camp tariff."
        ],
        'qty' => [
            'type'              => 'integer',
            'description'       => "Quantity of enrollments of the tariff."
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
        'is_clsh',
        'product_id',
        'day_product_id',
        'center_id',
        'enrollments_ids' => [
            'status',
            'enrollment_lines_ids' => [
                'product_id',
                'qty'
            ]
        ]
    ])
    ->get(true);

$map_center_tariffs_enrollments_qty = [];
$map_products = [];

foreach($camps as $camp) {
    foreach($camp['enrollments_ids'] as $enrollment) {
        if($params['status'] !== 'all' && $enrollment['status'] !== $params['status']) {
            continue;
        }

        $camp_product_id = $camp['is_clsh'] ? $camp['day_product_id'] : $camp['product_id'];

        if(!isset($map_center_tariffs_enrollments_qty[$camp['center_id']])) {
            $map_center_tariffs_enrollments_qty[$camp['center_id']] = [];
        }
        if(!isset($map_center_tariffs_enrollments_qty[$camp['center_id']][$camp_product_id])) {
            $map_center_tariffs_enrollments_qty[$camp['center_id']][$camp_product_id] = 0;
        }

        $qty = 1;
        if($camp['is_clsh']) {
            foreach($enrollment['enrollment_lines_ids'] as $line) {
                if($line['product_id'] === $camp_product_id) {
                    $qty = $line['qty'];
                    break;
                }
            }
        }

        $map_center_tariffs_enrollments_qty[$camp['center_id']][$camp_product_id] += $qty;
        $map_products[$camp_product_id] = true;
    }
}

$center_ids = array_keys($map_center_tariffs_enrollments_qty);

$centers = Center::search(['id', 'in', $center_ids])
    ->read(['name'])
    ->get();

$products_ids = array_keys($map_products);

$products = Product::search(['id', 'in', $products_ids])
    ->read(['name'])
    ->get();

foreach($map_center_tariffs_enrollments_qty as $center_id => $map_products_qty) {
    $center = null;
    foreach($centers as $c) {
        if($c['id'] === $center_id) {
            $center = $c['name'];
            break;
        }
    }

    foreach($map_products_qty as $product_id => $qty) {
        $product = null;
        foreach($products as $p) {
            if($p['id'] === $product_id) {
                $product = $p['name'];
                break;
            }
        }

        $result[] = [
            'center'        => $center,
            'product_id'    => ['id' => $product_id, 'name' => $product],
            'qty'           => $qty
        ];
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();

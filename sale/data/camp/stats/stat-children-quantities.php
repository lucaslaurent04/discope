<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\User;
use sale\camp\Camp;

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
        'by_age' => [
            'type'              => 'boolean',
            'description'       => "Split the children quantities by age.",
            'default'           => false
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit (defaults to first day of the current week).",
            'default'           => fn() => strtotime('Monday this week')
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Date interval upper limit (defaults to last day of the current week).",
            'default'           => fn() => strtotime('Sunday this week')
        ],

        /* parameters used as properties of virtual entity */
        'center' => [
            'type'              => 'string',
            'description'       => "Name of the center for the children quantities."
        ],
        'camp' => [
            'type'              => 'string',
            'description'       => "Name of the camp for the children quantities."
        ],
        'age' => [
            'type'              => 'string',
            'description'       => "The age(s) concerned by the quantities."
        ],
        'qty_male' => [
            'type'              => 'integer',
            'description'       => "Quantity of male children attending the camp."
        ],
        'qty_female' => [
            'type'              => 'integer',
            'description'       => "Quantity of female children attending the camp."
        ],
        'qty' => [
            'type'              => 'integer',
            'description'       => "Quantity of children attending the camp."
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

$camps = Camp::search($domain, ['sort' => ['date_from' => 'asc']])
    ->read([
        'name',
        'center_id' => [
            'name'
        ],
        'enrollments_ids' => [
            'status',
            'child_age',
            'child_id' => [
                'gender'
            ]
        ]
    ])
    ->get();

foreach($camps as $camp) {
    $map_age_data = [];

    foreach($camp['enrollments_ids'] as $enrollment) {
        if(!in_array($enrollment['status'], ['confirmed', 'validated'])) {
            continue;
        }
        if(!isset($map_age_data[$enrollment['child_age']])) {
            $map_age_data[$enrollment['child_age']] = [
                'center'        => $camp['center_id']['name'],
                'camp'          => $camp['name'],
                'age'           => $enrollment['child_age'],
                'qty_male'      => 0,
                'qty_female'    => 0,
                'qty'           => 0
            ];
        }

        $map_age_data[$enrollment['child_age']]['qty']++;
        switch($enrollment['child_id']['gender']) {
            case 'M':
                $map_age_data[$enrollment['child_age']]['qty_male']++;
                break;
            case 'F':
                $map_age_data[$enrollment['child_age']]['qty_female']++;
                break;
        }
    }

    if(empty($map_age_data)) {
        continue;
    }

    if($params['by_age']) {
        $result = array_merge($result, $map_age_data);
    }
    else {
        $ages = array_keys($map_age_data);
        sort($ages);

        $result[] = [
            'center' => $camp['center_id']['name'],
            'camp' => $camp['name'],
            'age' => implode(', ', $ages),
            'qty_male' => array_sum(array_column($map_age_data, 'qty_male')),
            'qty_female' => array_sum(array_column($map_age_data, 'qty_female')),
            'qty' => array_sum(array_column($map_age_data, 'qty'))
        ];
    }
}

usort($result, function($a, $b) {
    $result = strcmp($a['center'], $b['center']);
    if($result === 0) {
        $result = strcmp($a['camp'], $b['camp']);
        if($result === 0) {
            return strcmp($a['age'], $b['age']);
        }
    }
    return $result;
});

$context->httpResponse()
        ->body($result)
        ->send();

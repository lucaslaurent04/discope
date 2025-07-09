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
        'by_municipality' => [
            'type'              => 'boolean',
            'description'       => "Split the enrollments quantities by municipality.",
            'default'           => false
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
        'area' => [
            'type'              => 'string',
            'description'       => "The area concerned by the quantities."
        ],
        'address_zip' => [
            'type'              => 'string',
            'description'       => "The zip code of the address of the child's guardian."
        ],
        'municipality' => [
            'type'              => 'string',
            'description'       => "The municipality(ies) concerned by the quantities."
        ],
        'qty' => [
            'type'              => 'integer',
            'description'       => "Quantity of enrollments to the camp."
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

$camps = Camp::search($domain, ['sort' => ['date_from' => 'asc']])
    ->read([
        'name',
        'center_id' => [
            'name'
        ],
        'enrollments_ids' => [
            'status',
            'child_id' => [
                'main_guardian_id' => [
                    'address_zip'
                ],
                'institution' => [
                    'address_zip'
                ]
            ]
        ]
    ])
    ->get(true);

$areas_france = json_decode(@file_get_contents(EQ_BASEDIR.'/packages/sale/data/camp/stats/data/areas_france.json'), true);
if(is_null($areas_france)) {
    throw new Exception("areas_france_not_found", EQ_ERROR_UNKNOWN_OBJECT);
}

$municipalities_86 = json_decode(@file_get_contents(EQ_BASEDIR.'/packages/sale/data/camp/stats/data/municipalities_86.json'), true);
if(is_null($municipalities_86)) {
    throw new Exception("municipalities_86_not_found", EQ_ERROR_UNKNOWN_OBJECT);
}

$municipalities_87 = json_decode(@file_get_contents(EQ_BASEDIR.'/packages/sale/data/camp/stats/data/municipalities_87.json'), true);
if(is_null($municipalities_87)) {
    throw new Exception("municipalities_87_not_found", EQ_ERROR_UNKNOWN_OBJECT);
}

$municipalities_86_and_87 = array_merge($municipalities_86, $municipalities_87);

$map_area_municipality = [];
foreach($camps as $camp) {
    foreach($camp['enrollments_ids'] as $enrollment) {
        if($params['status'] !== 'all' && $enrollment['status'] !== $params['status']) {
            continue;
        }

        $unknown_zip = '00000';

        $address_zip = $enrollment['child_id']['main_guardian_id']['address_zip'] ?? $enrollment['child_id']['institution_id']['address_zip'] ?? $unknown_zip;
        $area = substr($address_zip, 0, 2);

        $area_name = $area;
        foreach($areas_france as $a) {
            if($a['code'] === $area) {
                $area_name = $a['dep_name'].' ('.$area.')';
                break;
            }
        }


        if(in_array($area, ['86', '87']) && $params['by_municipality']) {
            $municipalities = [];
            foreach($municipalities_86_and_87 as $mun) {
                if($mun['zip'] === $address_zip) {
                    $municipalities[] = $mun;
                }
            }

            if(empty($municipalities)) {
                throw new Exception("unknown_municipality", EQ_ERROR_UNKNOWN_OBJECT);
            }

            if(!isset($map_area_municipality[$area][$address_zip])) {
                $map_area_municipality[$area][$address_zip] = [
                    'center'        => $camp['center_id']['name'],
                    'area'          => $area_name,
                    'address_zip'   => $address_zip,
                    'municipality'  => implode(', ', array_column($municipalities, 'name')).' ('.$address_zip.')',
                    'qty'           => 0,
                    'status'        => $enrollment['status']
                ];
            }

            $map_area_municipality[$area][$address_zip]['qty']++;
        }
        elseif(!$params['by_municipality']) {
            if(!isset($map_area_municipality[$area][$unknown_zip])) {
                $map_area_municipality[$area] = [
                    $unknown_zip => [
                        'center'        => $camp['center_id']['name'],
                        'area'          => $area_name,
                        'address_zip'   => '',
                        'municipality'  => '',
                        'qty'           => 0,
                        'status'        => $enrollment['status']
                    ]
                ];
            }

            $map_area_municipality[$area][$unknown_zip]['qty']++;
        }
    }
}

foreach($map_area_municipality as $area => $municipalities_data) {
    $result = array_merge($result, array_values($municipalities_data));
}

usort($result, function($a, $b) {
    $result = strcmp($a['center'], $b['center']);
    if($result === 0) {
        return strcmp($a['area'], $b['area']);
    }
    return $result;
});

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();

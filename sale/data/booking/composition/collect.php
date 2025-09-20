<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use identity\Center;
use identity\User;
use sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search functionality for compositions, returning a collection of compositions based on additional specific parameters.',
    'params'        => [
        'all_centers' => [
            'type'              => 'boolean',
            'description'       => 'Indicates whether to include all centers within the defined range.',
            'default'           =>  false
        ],
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => 'Unique identifier for the center associated with the sojourn.',
            'visible'           => ['all_centers', '=', false],
            'default'           => function() {
                return ($centers = Center::search())->count() === 1 ? current($centers->ids()) : null;
            }
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => 'Lower limit of the date range.',
            'default'           => strtotime('-1 month')
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Upper limit of the date range.',
            'default'           => strtotime('+1 month')
        ],
        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the associated center.',
        ],
        'booking' => [
            'type'              => 'string',
            'description'       => 'Name of the associated booking.',
        ],
        'date' => [
            'type'              => 'date',
            'description'       => 'Date associated with the booking.',
        ],
        'nb_person' => [
            'type'              => 'integer',
            'description'       => 'Total number of people included.',
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'auth' ]
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\auth\AuthenticationManager $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

$result = [];

$domain = $params['domain'];

if(isset($params['center_id']) || $params['all_centers']) {

    if($params['all_centers']) {
        $user_id = $auth->userId();
        if($user_id <= 0) {
            throw new Exception('user_unknown', QN_ERROR_NOT_ALLOWED);
        }
        $user = User::id($user_id)->read(['centers_ids'])->first();
        if(!$user) {
            throw new Exception('unexpected_error', QN_ERROR_INVALID_USER);
        }
        $domain[] = ['center_id', 'in', $user['centers_ids'] ];

    }
    elseif($params['center_id'] && $params['center_id'] > 0) {
        $domain[] = [ 'center_id', '=', $params['center_id'] ];
    }

    if(isset($params['date_from'])) {
        $domain = Domain::conditionAdd($domain, ['date_from', '>=', $params['date_from']]);
    }

    if(isset($params['date_to'])) {
        $domain = Domain::conditionAdd($domain, ['date_to', '<=', $params['date_to']]);
    }
}

$domain = Domain::conditionAdd($domain, ['guest_list_id', '>', 0]);

$bookings = Booking::search($domain, ['sort'  => ['date_from' => 'asc']])
    ->read([
            'id',
            'name',
            'date_from',
            'guest_list_id',
            'center_id' => ['id','name'],
            'composition_items_ids'
        ])
    ->get(true);

$map_centers_compositions = [];

foreach($bookings as $booking) {

    $center_id = $booking['center_id']['id'];
    $booking_id = $booking['id'];

    if(!isset($map_centers_compositions[$center_id])) {
        $map_centers_compositions[$center_id] = [];
    }

    if(!isset($map_centers_compositions[$center_id][$booking_id])) {
        $map_centers_compositions[$center_id][$booking_id] = [
            'center'                => $booking['center_id']['name'],
            'booking'               => $booking['name'],
            'date'                  => date('Y/m/d', $booking['date_from']),
            'nb_person'             => 0
        ];
    }

    $map_centers_compositions[$center_id][$booking_id]['nb_person'] += count($booking['composition_items_ids']);
}

foreach($map_centers_compositions as $center_id => $bookings) {
    foreach($bookings as $booking_id => $item) {
        $result[] = [
            'center'                => $item['center'],
            'booking'               => $item['booking'],
            'date'                  => $item['date'],
            'nb_person'             => $item['nb_person']
        ];
    }
}

if($params['all_centers']){
    usort($result, function($a, $b) {
        $result = strcmp($a['center'], $b['center']);
        if($result === 0) {
            return strcmp($a['date'], $b['date']);
        }
        return $result;
    });
}
elseif($params['center_id']) {
    usort($result, function ($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
}


$context->httpResponse()
        ->body($result)
        ->send();

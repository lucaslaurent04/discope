<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use finance\stats\StatSection;
use identity\User;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;

[$params, $providers] = eQual::announce([
    'description'   => 'List general revenues per month.',
    'params'        => [
        /* mixed-usage parameters: required both for fetching data (input) and property of virtual entity (output) */
        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => "Output: Center of the sojourn / Input: The center for which the stats are required.",
            'visible'           => ['all_centers', '=', false]
        ],
        'all_centers' => [
            'type'              => 'boolean',
            'default'           =>  false,
            'description'       => "Mark the all Center of the sojourn."
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Output: Day of arrival / Input: Date interval lower limit (defaults to first day of previous month).",
            'default'           => mktime(0, 0, 0, date("m")-1, 1)
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Output: Day of departure / Input: Date interval upper limit (defaults to last day of previous month).',
            'default'           => mktime(0, 0, 0, date("m"), 0)
        ],

        /* parameters used as properties of virtual entity */

        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],
        'aamm' => [
            'type'              => 'string',
            'description'       => 'Year and month.'
        ],
        'duration_days' => [
            'type'              => 'integer',
            'description'       => 'Total duration of all bookings in days.'
        ],
        'nb_pers' => [
            'type'              => 'integer',
            'description'       => 'Number of hosted persons.'
        ],
        'nb_nights' => [
            'type'              => 'integer',
            'description'       => 'Number of nights.'
        ],
        'nb_freebies' => [
            'type'              => 'integer',
            'description'       => 'Number of free nights.'
        ],
        'bookings' => [
            'type'              => 'float',
            'description'       => 'Name of the center.'
        ],
        'nights' => [
            'type'              => 'float',
            'description'       => 'Total revenue from nights.'
        ],
        'animations' => [
            'type'              => 'float',
            'description'       => 'Total revenue from animations.'
        ],
        'internal_animations' => [
            'type'              => 'float',
            'description'       => 'Total revenue from internal animations.'
        ],
        'external_animations' => [
            'type'              => 'float',
            'description'       => 'Total revenue from external animations.'
        ],
        'meals' => [
            'type'              => 'float',
            'description'       => 'Total revenue from meals.'
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

// #memo - we consider all bookings for which at least one sojourn starts during the given period
if($params['center_id']) {
    $domain = [
        ['date_from', '>=', $params['date_from']],
        ['date_from', '<=', $params['date_to']],
        ['state', 'in', ['instance', 'archive']],
        ['status', 'not in', ['quote', 'option']]
    ];
}

if($params['all_centers']) {
    $user_id = $auth->userId();
    if($user_id <= 0) {
        throw new Exception("user_unknown", QN_ERROR_NOT_ALLOWED);
    }

    $user = User::id($user_id)
        ->read(['centers_ids'])
        ->first(true);

    if(!$user) {
        throw new Exception("unexpected_error", QN_ERROR_INVALID_USER);
    }

    $domain[] = ['center_id', 'in', $user['centers_ids']];
}
else if($params['center_id'] && $params['center_id'] > 0) {
    $domain[] = ['center_id', '=', $params['center_id']];
}

$bookings = [];

if(!empty($domain)) {
    $bookings = Booking::search($domain)
        ->read([
            'id',
            'created',
            'name',
            'date_from',
            'date_to',
            'total',
            'price',
            'center_id' => ['id', 'name', 'center_office_id']
        ])
        ->get(true);
}

// create map for statistic sections
$stats = StatSection::search(['code', 'in', ['GITE', 'SEJ', 'RST', 'ANIM']])->read(['id', 'code'])->get(true);
// map stats mapping stats id with their code
$map_stats = [];
foreach($stats as $stat) {
    $map_stats[$stat['id']] = $stat['code'];
}


// associative array mapping centers with each date index
$map_center_values = [];

foreach($bookings as $booking) {
    $date_index = date('Ym', $booking['date_from']);
    if(!isset($map_center_values[$booking['center_id']['name']])) {
        $map_center_values[$booking['center_id']['name']] = [];
    }

    if(!isset($map_center_values[$booking['center_id']['name']][$date_index])) {
        $map_center_values[$booking['center_id']['name']][$date_index] = [
            'center'                => $booking['center_id']['name'],
            'aamm'                  => date('Y/m', $booking['date_from']),
            'duration_days'         => 0,
            'nb_pers'               => 0,
            'nb_nights'             => 0,
            'nb_freebies'           => 0,
            'bookings'              => 0,
            'nights'                => 0,
            'animations'            => 0,
            'internal_animations'   => 0,
            'external_animations'   => 0,
            'meals'                 => 0
        ];
    }

    $diff = $booking['date_to'] - $booking['date_from'];
    $day_diff = floor($diff / (60 * 60 * 24));
    $map_center_values[$booking['center_id']['name']][$date_index]['duration_days'] += $day_diff + 1;

    $map_center_values[$booking['center_id']['name']][$date_index]['bookings'] += $booking['price'];

    $groups = BookingLineGroup::search([
        ['booking_id', '=', $booking['id']]
    ])
        ->read(['id', 'has_pack', 'is_locked', 'date_from', 'date_to', 'vat_rate', 'total', 'price', 'nb_pers', 'pack_id' => ['id', 'product_model_id']])
        ->get(true);

    foreach($groups as $group) {
        $map_center_values[$booking['center_id']['name']][$date_index]['nb_pers'] += $group['nb_pers'];

        $lines = BookingLine::search([
            ['booking_line_group_id', '=', $group['id']]
        ])
            ->read([
                'id',
                'qty',
                'free_qty',
                'total',
                'price',
                'is_accomodation',
                'product_model_id' => ['id', 'stat_section_id', 'activity_scope']
            ])
            ->get(true);

        foreach($lines as $line) {
            $stat_id = $line['product_model_id']['stat_section_id'];
            if(!isset($map_stats[$stat_id])) {
                continue;
            }
            $code = $map_stats[$stat_id];
            switch($code) {
                case 'GITE':
                case 'SEJ':
                    $map_center_values[$booking['center_id']['name']][$date_index]['nights'] += $line['price'];
                    break;
                case 'RST':
                    $map_center_values[$booking['center_id']['name']][$date_index]['meals'] += $line['price'];
                    break;
                case 'ANIM':
                    $map_center_values[$booking['center_id']['name']][$date_index]['animations'] += $line['price'];
                    $map_center_values[$booking['center_id']['name']][$date_index][$line['product_model_id']['activity_scope'].'_animations'] += $line['price'];
                    break;
            }

            if($line['is_accomodation']) {
                $map_center_values[$booking['center_id']['name']][$date_index]['nb_nights'] += $line['qty'];
                $map_center_values[$booking['center_id']['name']][$date_index]['nb_freebies'] += $line['free_qty'];
            }
        }
    }
}

// build final result
$result = [];
foreach($map_center_values as $center => $dates) {
    foreach($dates as $date_index => $item) {
        $result[] = $item;
    }
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();


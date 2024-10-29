<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use finance\stats\StatSection;
use identity\User;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\catalog\Product;
use sale\catalog\ProductModel;
use sale\catalog\Category;

list($params, $providers) = announce([
    'description'   => 'Lists all animations and their related details for a given period.',
    'params'        => [
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
            'default'           => strtotime('today')
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => 'Output: Day of departure / Input: Date interval upper limit (defaults to last day of previous month).',
            'default'           => strtotime('+1 month')
        ],

        'center' => [
            'type'              => 'string',
            'description'       => 'Name of the center.'
        ],

        'client' => [
            'type'              => 'string',
            'description'       => 'Full name of the client making the reservation.'
        ],

        'code_postal' => [
            'type'              => 'string',
            'description'       => 'Postal code of the client.'
        ],

        'category_animation' => [
            'type'              => 'string',
            'description'       => 'The name of the category of the product model.'
        ],

        'nb_3_6' => [
            'type'              => 'integer',
            'description'       => 'Number of children who are between 3 and 6 years old'
        ],

        'nb_6_12' => [
            'type'              => 'integer',
            'description'       => 'Number of children who are between 6 and 12 years old'
        ],

        'nb_12_26' => [
            'type'              => 'integer',
            'description'       => 'Number of teenagers who are between 12 and 26 years old'
        ],

        'total' => [
            'type'              => 'integer',
            'description'       => 'Total number of participants across all age ranges'
        ],

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context','auth' ]
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\auth\AuthenticationManager $auth
 */
list($context, $auth) = [ $providers['context'], $providers['auth']];

$date_from = $params['date_from'];
$date_to = $params['date_to'];

$domain= [];

if ($params['all_centers']){

    $user_id = $auth->userId();
    if($user_id <= 0) {
        throw new Exception('user_unknown', QN_ERROR_NOT_ALLOWED);
    }

    $user = User::id($user_id)->read(['centers_ids'])->first(true);
    if(!$user) {
        throw new Exception('unexpected_error', QN_ERROR_INVALID_USER);
    }

    $domain = ['center_id', 'in', $user['centers_ids'] ];

}
elseif ($params['center_id'] && $params['center_id'] > 0) {
    $domain = ['center_id', '=', $params['center_id']];
}

$domain = Domain::conditionAdd($domain, ['status', 'not in', ['quote', 'option']]);

if($params['center_id']  || $params['all_centers']){
    $domain = Domain::conditionAdd($domain, [['date_from', '<', $date_to], ['date_to', '>=', $date_from]]);
}

$stats_section_animation = StatSection::search(['code', '=', 'ANIM'])->read(['id', 'code'])->first(true);

$list_anim_categories = ['SPT', 'NAT', 'HIS', 'MED', 'ART', 'MEL', 'SCI', 'VIE'];
$categories_animations_ids = Category::search(['code', 'in', $list_anim_categories])->ids();

$categories_animations = Category::ids($categories_animations_ids)->read(['product_models_ids'])->get(true);

$products_models_ids = [];
foreach($categories_animations as $category) {
    $products_models_ids = array_merge($products_models_ids, $category['product_models_ids']);
}

$products_models_ids = ProductModel::search([['id', 'in', $products_models_ids], ['stat_section_id', '=', $stats_section_animation['id']]])->ids();

$products_ids = Product::search(['product_model_id', 'in', $products_models_ids])->ids();

$bookings_ids = [];

if($domain) {
    $bookings = Booking::search($domain)->read(['id'])->get(true);
}

// limit list of bookings to bookings having at least one line related to an animation product
foreach($bookings as $booking) {
    $lines = BookingLine::search([['booking_id', '=', $booking['id']], ['product_id', 'in', $products_ids]])->ids();
    if(count($lines)) {
        $bookings_ids[] = $booking['id'];
    }
}


$result = [];

$map_centers_animations = [];


$age_ranges_map = [
    1 => ['nb_6_12' => 'T5', 'nb_3_6' => 'T7'],
    2 => 'nb_12_26',
    3 => 'nb_6_12',
    4 => 'nb_3_6',
];

$bookings = Booking::ids($bookings_ids)
    ->read([
        'id',
        'name',
        'customer_id' => ['id', 'name', 'partner_identity_id' => 'address_zip'],
        'center_id'  => ['id', 'name'],
    ])
    ->get(true);


foreach($bookings as $index => $booking) {

    $center_id =  $booking['center_id']['id'];
    $customer_id = $booking['customer_id']['id'];

    $groups = BookingLineGroup::search([
            ['booking_id', '=', $booking['id']]
        ])
        ->read([
            'id',
            'nb_pers',
            'rate_class_id' => ['id', 'name'],
            'age_range_assignments_ids' => ['id', 'qty', 'age_range_id'],
            'booking_lines_ids' => [
                'product_model_id' => ['id', 'name', 'stat_section_id',
                                        'categories_ids' => ['id','name']]
            ]
        ])
        ->get(true);

    foreach($groups as $group) {

        foreach($group['booking_lines_ids'] as $line){
            $product_model = $line['product_model_id'];

            if(!isset($map_centers_animations[$center_id])) {
                $map_centers_animations[$center_id] = [];
            }
            if (!isset($map_centers_animations[$center_id][$customer_id])) {
                $map_centers_animations[$center_id][$customer_id] = [];
            }

            foreach($product_model['categories_ids'] as $category){
                $category_id = $category['id'];

                if (in_array($category_id, $categories_animations_ids)) {

                    if (!isset($map_centers_animations[$center_id][$customer_id][$category_id])) {
                        $map_centers_animations[$center_id][$customer_id][$category_id]  = [
                            'center'                => $booking['center_id']['name'],
                            'client'                => $booking['customer_id']['name'],
                            'code_postal'           => $booking['customer_id']['partner_identity_id']['address_zip'],
                            'category_animation'    => $category['name'],
                            'nb_3_6'                => 0,
                            'nb_6_12'               => 0,
                            'nb_12_26'              => 0
                        ];
                    }

                    if (empty($group['age_range_assignments_ids'])) {
                        $qty = $group['nb_pers'];
                        $rate_class_name = $group['rate_class_id']['name'];

                        if ($rate_class_name == 'T5') {
                                $map_centers_animations[$center_id][$customer_id][$category_id]['nb_6_12'] += $qty;
                        } 
                        elseif ($rate_class_name == 'T7') {
                            $map_centers_animations[$center_id][$customer_id][$category_id]['nb_3_6'] += $qty;
                        }
                    } 
                    else {

                        foreach ($group['age_range_assignments_ids'] as $age_range_assignment){

                            $age_range_id = $age_range_assignment['age_range_id'];
                            $qty = $age_range_assignment['qty'];

                            if (isset($age_ranges_map[$age_range_id])) {
                                if ($age_range_id == 1) {
                                    $rate_class_name = $group['rate_class_id']['name'];
                                    if (isset($age_ranges_map[$age_range_id]['nb_6_12']) && $rate_class_name == 'T5') {
                                        $map_centers_animations[$center_id][$customer_id][$category_id]['nb_6_12'] += $qty;
                                    } 
                                    elseif (isset($age_ranges_map[$age_range_id]['nb_3_6']) && $rate_class_name == 'T7') {
                                        $map_centers_animations[$center_id][$customer_id][$category_id]['nb_3_6'] += $qty;
                                    }
                                } 
                                else {
                                    $key = $age_ranges_map[$age_range_id];
                                    $map_centers_animations[$center_id][$customer_id][$category_id][$key] += $qty;
                                }
                            }
                        }
                    }
                }
            }

        }
    }

}

foreach($map_centers_animations as $center_id => $customers) {
    foreach($customers as $customer_id => $categories) {
        foreach($categories as $category_id => $item) {
            $total = $item['nb_3_6'] + $item['nb_6_12'] +$item['nb_12_26'];
            if($total > 0) {
                $result[] = [
                    'center'                => $item['center'],
                    'client'                => $item['client'],
                    'code_postal'           => $item['code_postal'],
                    'category_animation'    => $item['category_animation'],
                    'nb_3_6'                => $item['nb_3_6'],
                    'nb_6_12'               => $item['nb_6_12'],
                    'nb_12_26'              => $item['nb_12_26'],
                    'total'                 => $total,
                ];
            }
        }
    }
}

if ($params['all_centers']){
    usort($result, function($a, $b) {
        $result = strcmp($a['center'], $b['center']);
        if ($result === 0) {
            return strcmp($a['client'], $b['client']);
        }
        return $result;
    });
}
elseif ($params['center_id']) {
    usort($result, function ($a, $b) {
        return strcmp($a['client'], $b['client']);
    });
}

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();

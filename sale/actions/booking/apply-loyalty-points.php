<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use equal\php\Context;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\BookingPoint;
use sale\catalog\Product;

[$params, $providers] = eQual::announce([
    'description'   => "Applies available loyalty points to the specified booking.",
    'params'        => [

        'id' => [
            'type'              => 'integer',
            'description'       => "Identifier of the booking which we want to apply the loyalty points.",
            'required'          => true
        ]

    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var Context                     $context
 * @var \equal\orm\ObjectManager    $orm
 */
['context' => $context, 'orm' => $orm] = $providers;

$loyalty_point_sku = Setting::get_value('sale', 'organization', 'sku.loyalty_points');
if(is_null($loyalty_point_sku)) {
    throw new Exception("missing_loyalty_points_sku_setting", EQ_ERROR_INVALID_CONFIG);
}

$loyalty_points_product = Product::search(['sku', '=', $loyalty_point_sku])
    ->read(['name'])
    ->first();

if(is_null($loyalty_points_product)) {
    throw new Exception("missing_loyalty_points_product", EQ_ERROR_INVALID_CONFIG);
}

$booking = Booking::id($params['id'])
    ->read(['date_from', 'booking_lines_groups_ids', 'customer_id' => ['rate_class_id']])
    ->first();

if(is_null($booking)) {
    throw new Exception("unknown_booking", EQ_ERROR_UNKNOWN_OBJECT);
}

$booking_points = BookingPoint::search([
    ['customer_id', '=', $booking['customer_id']['id']],
    ['booking_apply_id', '=', null],
    ['points_value', '>', 0]
])
    ->read(['points_value', 'is_applicable', 'date_expiry'])
    ->get(true);

$booking_points = array_filter(
    $booking_points,
    fn($bp) => $bp['is_applicable'] && (is_null($bp['date_expiry']) || $booking['date_from'] <= $bp['date_expiry'])
);

if(empty($booking_points)) {
    throw new Exception("no_booking_points_to_apply", EQ_ERROR_INVALID_PARAM);
}

$points = 0;
foreach($booking_points as $booking_point) {
    $points += $booking_point['points_value'];
}

BookingPoint::ids(array_column($booking_points, 'id'))
    ->update(['booking_apply_id' => $booking['id']]);

$group_data = [
    'booking_id'    => $booking['id'],
    'is_sojourn'    => false,
    'group_type'    => 'simple',
    'has_pack'      => false,
    'name'          => $loyalty_points_product['name'],
    'order'         => count($booking['booking_lines_groups_ids']) + 1,
    'is_extra'      => true,
    'is_event'      => false,
    'is_locked'     => false,
    'nb_pers'       => 1
];

if(!is_null($booking['customer_id']['rate_class_id'])) {
    $group_data['rate_class_id'] = $booking['customer_id']['rate_class_id'];
}

$loyalty_points_group = BookingLineGroup::create($group_data)
    ->read(['id'])
    ->first();

$loyalty_points_line = BookingLine::create([
    'order'                 => 1,
    'booking_id'            => $booking['id'],
    'booking_line_group_id' => $loyalty_points_group['id']
])
    ->read(['id'])
    ->first();

\eQual::run('do', 'sale_booking_update-bookingline-product', [
    'id'            => $loyalty_points_line['id'],
    'product_id'    => $loyalty_points_product['id']
]);

BookingLine::id($loyalty_points_line['id'])
    ->update([
        'has_manual_unit_price' => true,
        'unit_price'            => floatval(-$points)
    ]);

BookingLine::refreshPrice($orm, $loyalty_points_line['id']);
Booking::refreshPrice($orm, $booking['id']);

$context->httpResponse()
        ->status(200)
        ->send();

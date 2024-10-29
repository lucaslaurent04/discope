<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\CenterOffice;
use sale\booking\Booking;
use sale\booking\BookingInspection;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\ConsumptionMeterReading;

list($params, $providers) = announce([
    'description'   => "Creates the additional services for the consumption meter reading.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking to mark as confirmed.',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm', 'cron', 'dispatch' ]
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 */
list( $context, $orm ) = [ $providers['context'], $providers['orm'] ];

$booking = Booking::id($params['id'])
    ->read([
        'id',
        'status',
        'price',
        'center_id' => ['price_list_category_id'],
        'center_office_id',
        'bookings_inspections_ids' => [
            'id',
            'booking_id',
            'status',
            'type_inspection',
            'consumptions_meters_readings_ids' => [
                'index_value',
                'unit_price',
                'consumption_meter_id' => [
                    'id',
                    'name',
                    'coefficient',
                    'product_id' => [
                        'id',
                        'product_model_id'
                    ],
                    'booking_inspection_id' => [
                        'id',
                        'type_inspection',
                        'status'
                    ],
                ],
                'price_id' => [
                    'id',
                    'price',
                    'vat_rate'
                ]
            ]
        ]
    ])
    ->first(true);


if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] != 'checkedout') {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

$center_office_gg = CenterOffice::search(['name', 'ilike', '%'.'Gites de Groupes'.'%'])->read(['id'])->first(true);
if($booking['center_office_id'] != $center_office_gg['id'] ) {
    throw new Exception("incompatible_center_office", QN_ERROR_INVALID_PARAM);
}

if(!$booking['bookings_inspections_ids']) {
    throw new Exception("missing_bookings_inspections_ids", QN_ERROR_INVALID_PARAM);
}

foreach ($booking['bookings_inspections_ids'] as $index =>  $inspection) {
    if ($inspection['status'] === 'billed') {
        throw new Exception("incompatible_status_inspections ", QN_ERROR_INVALID_PARAM);
    }

    if (empty($inspection['consumptions_meters_readings_ids'])) {
        throw new Exception("missing_consumptions_meter_reading_ids", QN_ERROR_INVALID_PARAM);
    }
}


foreach($booking['bookings_inspections_ids'] as $index => $booking_inspection) {

    if ($booking_inspection['type_inspection'] == 'checkedout') {

        $extra_booking_line_group = BookingLineGroup::create([
            'name'          => "Consommation des relevés",
            'is_sojourn'    => false,
            'is_extra'      => true,
            'is_event'      => false,
            'has_pack'      => false,
            'is_locked'     => false,
            'nb_pers'       => 1,
            'booking_id'    => $booking['id']
        ])
        ->read(['id'])
        ->first(true);

        $all_booking_lines_created = true;
        foreach($booking_inspection['consumptions_meters_readings_ids'] as $index => $reading_checkout) {
            $meter = $reading_checkout['consumption_meter_id'];

            $reading_checkedin = ConsumptionMeterReading::search([
                    ['consumption_meter_id', '=', $meter['id']],
                    ['booking_id', '=', $booking_inspection['booking_id']],
                    ['booking_inspection_id', '<>', $booking_inspection['id']]
                ])
                ->read([
                    'id',
                    'unit_price',
                    'index_value',
                    'booking_inspection_id' => [
                        'id',
                        'type_inspection',
                        'status'
                    ],
                    'price_id' => [
                        'price',
                        'total',
                        'vat_rate'
                    ]
                ])
                ->first(true);

            if($reading_checkedin){
                if ($reading_checkedin['booking_inspection_id']['type_inspection'] == 'checkedin') {

                    $product = $reading_checkout['consumption_meter_id']['product_id'];
                    $price = $reading_checkedin['price_id'];

                    $qty = $reading_checkout['index_value'] - $reading_checkedin['index_value'];
                    if ($meter['coefficient'] != 1){
                        $qty *= $meter['coefficient'];
                    }

                    $resulting_total = round($qty * $reading_checkout['unit_price'],   2);
                    $resulting_price = round($resulting_total/(1+$price['vat_rate']), 2);

                    $booking_line = BookingLine::create([
                            'booking_id'            => $booking['id'],
                            'description'           => "Consommation de relevé {$meter['name']}",
                            'booking_line_group_id' => $extra_booking_line_group['id'],
                            'qty'                   => $qty,
                            'price_id'              => $price['id'],
                            'product_id'            => $product['id'],
                            'product_model_id'      => $product['product_model_id']
                        ])
                        ->update([
                            'unit_price'            => $reading_checkout['unit_price'],
                            'qty'                   => $qty,
                            'has_manual_unit_price' => true,
                            'price'                 => $resulting_price,
                            'total'                 => $resulting_total
                        ])
                        ->read(['id'])
                        ->first(true);

                    if (!$booking_line) {
                        $all_booking_lines_created = false;
                    }
                    break;
                }
            }

        }

        if ($all_booking_lines_created) {
            BookingInspection::id($reading_checkedin['booking_inspection_id']['id'])->update(["status" => "billed"]);
            BookingInspection::id($booking_inspection['id'])->update(["status" => "billed"]);
        }

    }

}

$context->httpResponse()
        ->status(204)
        ->send();

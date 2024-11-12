<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;
use sale\booking\SojournProductModelRentalUnitAssignement;

list($params, $providers) = announce([
    'description'   => "Checks the action required of rental units for the checkin.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking the check against unit contract validity.',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $dispatch) = [ $providers['context'], $providers['dispatch']];

$booking = Booking::id($params['id'])
    ->read([
        'id',
        'name',
        'center_office_id',
        'booking_lines_groups_ids' => [
            'group_type',
            'sojourn_product_models_ids' => [
                'product_model_id',
                'rental_unit_assignments_ids'
            ]
        ]
    ])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$booking_line_groups = $booking['booking_lines_groups_ids'];
$mismatch = false;

if($booking_line_groups) {
    foreach($booking_line_groups as $gid => $group) {

        // #todo 2024-02-21 - quick workaround for allowing camps without rental units
        if($group['group_type'] == 'camp') {
            continue;
        }

        foreach($group['sojourn_product_models_ids'] as $oid => $spm) {

            if(!count($spm['rental_unit_assignments_ids'])) {
                $mismatch = true;
                break;
            }
            $assignments = SojournProductModelRentalUnitAssignement::ids($spm['rental_unit_assignments_ids'])
                ->read([
                    'is_accomodation',  'rental_unit_id' => ['id','action_required']
                ])
                ->get();
            foreach($assignments as $assignment) {
                if(!$assignment["is_accomodation"]) {
                    continue;
                }
                if(in_array($assignment['rental_unit_id']['action_required'], ["cleanup_daily","cleanup_full"])){
                    $mismatch = true;
                    break;
                }
            }
        }

    }
}
$result = [];
$httpResponse = $context->httpResponse()->status(200);


if($mismatch) {
    $result[] = $params['id'];
    $dispatch->dispatch('lodging.booking.rental_units_ready', 'sale\booking\Booking', $params['id'], 'important', 'sale_booking_check-units-ready', ['id' => $params['id']], [], null, $booking['center_office_id']);
    $httpResponse->status(qn_error_http(QN_ERROR_NOT_ALLOWED));
}
else {
    $dispatch->cancel('lodging.booking.rental_units_ready', 'sale\booking\Booking', $params['id']);
}

$httpResponse->body($result)
             ->send();

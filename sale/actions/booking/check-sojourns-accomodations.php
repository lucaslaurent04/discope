<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'   => "Checks that each sojourn (services group) of a booking has a consistent SPMAccommodations configuration.
                        It also checks if there are no accommodation in a non-sojourn / non-event group, and if all sojourns have at least one accommodation. Several 'accommodation' product models for a same are not allowed. Only sojourn and events can have accommodations. A sojourn must have at least one accommodation (SPM).",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking for which the assignments are checked.',
            'type'          => 'integer',
            'required'      => true
        ],
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

// ensure booking object exists and is readable
$booking = Booking::id($params['id'])
    ->read([
        'id',
        'name',
        'status',
        'is_cancelled',
        'center_office_id',
        'booking_lines_groups_ids' => [
            'is_sojourn',
            'is_event',
            'group_type',
            'has_pack',
            'pack_id',
            'booking_lines_ids' => [
                'is_accomodation'
            ],
            'sojourn_product_models_ids' => [
                'is_accomodation'
            ]
        ]
    ])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

$booking_line_groups = $booking['booking_lines_groups_ids'];
$errors = [];

if(!$booking['is_cancelled'] && $booking_line_groups) {
    // we check on a per-group basis
    foreach($booking['booking_lines_groups_ids'] as $group) {
        // #todo 2024-02-21 - quick workaround for allowing camps without rental units
        if($group['group_type'] == 'camp') {
            continue;
        }
        // #memo - there is an exception for (temporary) pack [KA-SejSco0-A - 1766] (gratuité professeurs) - no accommodation needed
        if($group['has_pack'] && $group['pack_id'] == 1766) {
            continue;
        }

        $count_accommodation_lines = count(array_filter($group['booking_lines_ids'], function($a) {return $a['is_accomodation']; }));
        $multi_spm_accommodation = (bool) (count(array_filter($group['sojourn_product_models_ids'], function($a) {return $a['is_accomodation']; })) > 1);
        if($multi_spm_accommodation) {
            $check_result = false;
            $errors['multiple_spm_accommodation'] = true;
            break;
        }
        if(!$group['is_sojourn'] && !$group['is_event']) {
            if($count_accommodation_lines > 0) {
                $errors['invalid_accommodation'] = true;
                break;
            }
        }
        if($group['is_sojourn'] && $count_accommodation_lines <= 0) {
            $errors['no_accommodation'] = true;
            break;
        }
    }
}

/*
    This controller is a check: an empty response means that no alert was raised
*/

$result = [];
$httpResponse = $context->httpResponse()->status(200);

if(count($errors)) {
    $result[] = $params['id'];
    $httpResponse->status(qn_error_http(QN_ERROR_NOT_ALLOWED));
    // by convention we dispatch an alert that relates to the controller itself.
    $dispatch->dispatch('lodging.booking.sojourns_accomodations', 'sale\booking\Booking', $params['id'], 'important', 'sale_booking_check-sojourns-accomodations', ['id' => $params['id']], [], null, $booking['center_office_id']);
}
else {
    // symmetrical removal of the alert (if any)
    $dispatch->cancel('lodging.booking.sojourns_accomodations', 'sale\booking\Booking', $params['id']);
}

$httpResponse->body($result)
             ->send();

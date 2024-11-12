<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use sale\booking\Booking;
use sale\booking\Consumption;
use sale\booking\BookingLineGroup;
use sale\booking\Contract;
use sale\booking\Funding;

list($params, $providers) = eQual::announce([
    'description'   => "This will cancel the booking, whatever its current status. Balance will be adjusted if cancellation fees apply.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted booking.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
        // this must remain synced with field definition Booking::cancellation_reason
        'reason' =>  [
            'description'   => 'Reason of the booking cancellation.',
            'type'          => 'string',
            'selection'     => [
                'other',                    // customer cancelled for a non-listed reason or without mentioning the reason (cancellation fees might apply)
                'overbooking',              // the booking was cancelled due to failure in delivery of the service
                'duplicate',                // several contacts of the same group made distinct bookings for the same sojourn
                'internal_impediment',      // cancellation due to an incident impacting the rental units
                'external_impediment',      // cancellation due to external delivery failure (organization, means of transport, ...)
                'health_impediment',        // cancellation for medical or mourning reason
                'ota'                       // cancellation was made through the channel manager
            ],
            'required'       => true
        ]
    ],
    'access' => [
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\cron\Scheduler       $cron
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
['context' => $context, 'cron' => $cron, 'dispatch' => $dispatch] = $providers;

// read booking object
$booking = Booking::id($params['id'])
    ->read(['id', 'name', 'is_cancelled', 'status', 'paid_amount', 'date_from', 'date_to'])
    ->first(true);

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

// booking already cancelled
if($booking['is_cancelled']) {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

if(in_array($booking['status'], ['debit_balance', 'credit_balance', 'balanced'])) {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

// revert booking to quote if necessary
// #memo - this doesn't work for booking at advanced stage (cancelling a "checkedin" booking will raise an error)
/*
if($booking['status'] != 'quote') {
    $json = run('do', 'sale_booking_do-quote', ['id' => $params['id'], 'free_rental_units' => true]);
    $data = json_decode($json, true);
    if(isset($data['errors'])) {
        // raise an exception with returned error code
        foreach($data['errors'] as $name => $message) {
            throw new Exception($message, qn_error_code($name));
        }
    }
}
*/

$channelmanager_enabled = Setting::get_value('sale', 'channelmanager', 'enabled', false);
if($channelmanager_enabled) {
    /*
        Check if consistency must be maintained with channel manager (if booking impacts a rental unit that is linked to a channelmanager room type)
    */

    // retrieve rental units impacted by this operation
    $map_rental_units_ids = [];
    $consumptions = Consumption::search(['booking_id', '=', $params['id']])->read(['id', 'is_accomodation', 'rental_unit_id'])->get(true);

    foreach($consumptions as $consumption) {
        if($consumption['is_accomodation']) {
            $map_rental_units_ids[$consumption['rental_unit_id']] = true;
        }
    }

    // schedule an update check-contingencies
    // #memo - since there is a delay between 2 sync (during which availability might be impacted) we need to set back the channelmanager availabilities
    if(count($map_rental_units_ids) /*&& $params['reason'] != 'ota'*/) {
        $cron->schedule(
            "channelmanager.check-contingencies.{$params['id']}",
            time(),
            'sale_booking_check-contingencies',
            [
                'date_from'         => date('c', $booking['date_from']),
                'date_to'           => date('c', $booking['date_to']),
                'rental_units_ids'  => array_keys($map_rental_units_ids)
            ]
        );
    }

    // if the cancellation was made by the OTA/channel manager, cancel all contracts
    if($params['reason'] == 'ota') {
        Contract::search(['booking_id', '=', $params['id']])->update(['status' => 'cancelled']);
    }
}

// release rental units (remove consumptions, if any)
Consumption::search(['booking_id', '=', $params['id']])->delete(true);

// mark the booking as cancelled
Booking::id($params['id'])
    ->update([
        'is_noexpiry'           => false,
        'is_cancelled'          => true,
        'cancellation_reason'   => $params['reason']
    ]);

// if booking status was more advanced than quote, set it as checkedout (to allow manual modifications)
if($booking['status'] != 'quote' || round($booking['paid_amount'], 2) > 0) {
    Booking::id($params['id'])
        ->update(['status' => 'checkedout']);
}

// #memo - user is left in charge to handle cancellation fees if applicable
$booking = Booking::id($params['id'])
    ->read([
        'booking_lines_groups_ids',
        'fundings_ids' => ['is_paid', 'paid_amount']
    ])
    ->first(true);

// delete non-paid fundings
if(count($booking['fundings_ids'])) {
    foreach($booking['fundings_ids'] as $fid => $funding) {
        // if some amount has been received, leave the funding as is and let user deal with reimbursement
        if($funding['paid_amount'] > 0 || $funding['is_paid']) {
            continue;
        }
        Funding::id($fid)->delete(true);
    }
}

// mark all sojourns as 'extra' to allow custom changes (there are many possible situations between none and some of the services actually consumed)
if(count($booking['booking_lines_groups_ids'])) {
    BookingLineGroup::ids($booking['booking_lines_groups_ids'])->update(['is_extra' => true]);
}

// remove pending alerts relating to booking checks, if any
$dispatch->cancel('lodging.booking.composition', 'sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.consistency', 'sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.overbooking', 'sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.rental_units_ready', 'sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.rental_units_assignment', 'sale\booking\Booking', $booking['id']);
$dispatch->cancel('lodging.booking.sojourns_accomodations', 'sale\booking\Booking', $booking['id']);

$context->httpResponse()
        ->status(200)
        ->send();

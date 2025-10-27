<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use sale\booking\Booking;
use sale\booking\BookingActivity;
use sale\booking\BookingLine;
use sale\booking\BookingPoint;
use sale\booking\Consumption;
use sale\booking\BookingLineGroup;
use sale\booking\Contract;
use sale\booking\Invoice;
use sale\catalog\Product;

list($params, $providers) = eQual::announce([
    'description'   => "This will cancel the booking, whatever its current status. Balance will be adjusted if cancellation fees apply.",
    'params'        => [
        'id' =>  [
            'type'              => 'integer',
            'min'               => 1,
            'description'       => "Identifier of the targeted booking.",
            'required'          => true
        ],
        // this must remain synced with field definition Booking::cancellation_reason and translations in do-cancel.json
        'reason' =>  [
            'type'              => 'string',
            'description'       => "Reason of the booking cancellation.",
            'selection'         => [
                'other',                    // customer cancelled for a non-listed reason or without mentioning the reason (cancellation fees might apply)
                'overbooking',              // the booking was cancelled due to failure in delivery of the service
                'duplicate',                // several contacts of the same group made distinct bookings for the same sojourn
                'internal_impediment',      // cancellation due to an incident impacting the rental units
                'external_impediment',      // cancellation due to external delivery failure (organization, means of transport, ...)
                'health_impediment',        // cancellation for medical or mourning reason
                'ota'                       // cancellation was made through the channel manager
            ],
            'required'          => true
        ],
        'with_fee' => [
            'type'              => "boolean",
            'description'       => "Should a fee be invoiced to the customer because of the cancellation?",
            'default'           => false
        ],
        'fee_amount' => [
            'type'              => 'float',
            'usage'             => 'amount/money:2',
            'description'       => "The amount of the cancellation fee.",
            'help'              => "If the cancellation fee is 0 then the status of the booking will be set to \"cancelled\", because it doesn't need to be invoiced.",
            'default'           => 0
        ]
    ],
    'access'        => [
        'groups'        => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 * @var \equal\cron\Scheduler       $cron
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
['context' => $context, 'orm' => $orm, 'cron' => $cron, 'dispatch' => $dispatch] = $providers;

$channelmanager_enabled = Setting::get_value('sale', 'features', 'booking.channel_manager', false);
if($params['reason'] === 'ota' && !$channelmanager_enabled) {
    throw new Exception("ota_not_allowed", EQ_ERROR_INVALID_PARAM);
}

$booking = Booking::id($params['id'])
    ->read([
        'date_from',
        'date_to',
        'is_cancelled',
        'status',
        'paid_amount',
        'is_from_channelmanager',
        'booking_lines_groups_ids',
        'customer_id'       => ['rate_class_id'],
        'center_office_id'  => ['organisation_id']
    ])
    ->first(true);

if(is_null($booking)) {
    throw new Exception("unknown_booking", EQ_ERROR_UNKNOWN_OBJECT);
}

if(($params['reason'] !== 'ota' && $booking['is_from_channelmanager']) || ($params['reason'] === 'ota' && !$booking['is_from_channelmanager'])) {
    throw new Exception("incompatible_reason", EQ_ERROR_INVALID_PARAM);
}

// #todo - allow to cancel without a fee a non channel manager booking that was previously cancelled with a fee (if status is still checkedout so nothing invoiced)

// #mnemo - A previously canceled booking cannot be canceled again, except in cases where it was canceled through the channel manager with a fee, and we now want to cancel it without a fee.
if($booking['is_cancelled'] && (!$booking['is_from_channelmanager'] || $params['with_fee'])) {
    throw new Exception("incompatible_status", EQ_ERROR_INVALID_PARAM);
}

if(in_array($booking['status'], ['proforma', 'invoiced', 'debit_balance', 'credit_balance', 'balanced', 'cancelled'])) {
    throw new Exception("incompatible_status", EQ_ERROR_INVALID_PARAM);
}

if(!$params['with_fee']) {
    $proforma_credit_notes_ids = Invoice::search([
        ['booking_id', '=', $booking['id']],
        ['status', '=', 'proforma'],
        ['type', '=', 'credit_note']
    ])
        ->ids();

    if(count($proforma_credit_notes_ids) > 0) {
        throw new Exception("proforma_credit_note_exists", EQ_ERROR_INVALID_PARAM);
    }
}

$channelmanager_enabled = Setting::get_value('sale', 'features', 'booking.channel_manager', false);
if($channelmanager_enabled) {
    /*
        Check if consistency must be maintained with channel manager (if booking impacts a rental unit that is linked to a channelmanager room type)
    */

    // retrieve rental units impacted by this operation
    $map_rental_units_ids = [];
    $consumptions = Consumption::search(['booking_id', '=', $booking['id']])->read(['id', 'is_accomodation', 'rental_unit_id'])->get(true);

    foreach($consumptions as $consumption) {
        if($consumption['is_accomodation'] && $consumption['rental_unit_id'] !== null) {
            $map_rental_units_ids[$consumption['rental_unit_id']] = true;
        }
    }

    // schedule an update check-contingencies
    // #memo - since there is a delay between 2 sync (during which availability might be impacted) we need to set back the channelmanager availabilities
    if(count($map_rental_units_ids) /*&& $params['reason'] != 'ota'*/) {
        $cron->schedule(
            "channelmanager.check-contingencies.{$booking['id']}",
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
        Contract::search(['booking_id', '=', $booking['id']])->update(['status' => 'cancelled']);
    }
}

if(!$booking['is_cancelled']) {
    // release rental units (remove consumptions, if any)
    Consumption::search(['booking_id', '=', $booking['id']])->delete(true);

    // mark the booking as cancelled
    Booking::id($booking['id'])
        ->update([
            'is_noexpiry'           => false,
            'is_cancelled'          => true,
            'cancellation_reason'   => $params['reason']
        ]);
}
else {
    // Booking was canceled through the channel manager with a fee, and we now want to cancel it without a fee
}

if($params['with_fee']) {
    // if booking's status was more advanced than quote, set it as checkedout (to allow manual modifications)
    if($booking['status'] != 'quote' || round($booking['paid_amount'], 2) > 0) {
        Booking::id($booking['id'])
            ->update(['status' => 'checkedout']);
    }

    // delete fundings that have paid_amount = 0
    Booking::id($booking['id'])->do('delete_unpaid_fundings');

    // mark all sojourns as 'extra' to allow custom changes (there are many possible situations between none and some of the services actually consumed)
    if(count($booking['booking_lines_groups_ids'])) {
        BookingLineGroup::ids($booking['booking_lines_groups_ids'])->update(['is_extra' => true]);
    }

    // create a group with one line for the cancellation product
    $cancellation_fee_sku = Setting::get_value('sale', 'organization', 'sku.cancellation_fee.'.$booking['center_office_id']['organisation_id']);
    if(!is_null($cancellation_fee_sku)) {
        $cancellation_fee_product = Product::search(['sku', '=', $cancellation_fee_sku])
            ->read(['name'])
            ->first();

        if(!is_null($cancellation_fee_product)) {
            $group_data = [
                'booking_id'    => $booking['id'],
                'is_sojourn'    => false,
                'group_type'    => 'simple',
                'has_pack'      => false,
                'name'          => $cancellation_fee_product['name'],
                'order'         => count($booking['booking_lines_groups_ids']) + 1,
                'is_extra'      => true,
                'is_event'      => false,
                'is_locked'     => false,
                'nb_pers'       => 1
            ];

            if(!is_null($booking['customer_id']['rate_class_id'])) {
                $group_data['rate_class_id'] = $booking['customer_id']['rate_class_id'];
            }

            $cancellation_group = BookingLineGroup::create($group_data)
                ->read(['id'])
                ->first();

            $cancellation_line = BookingLine::create([
                'order'                 => 1,
                'booking_id'            => $booking['id'],
                'booking_line_group_id' => $cancellation_group['id']
            ])
                ->read(['id'])
                ->first();

            \eQual::run('do', 'sale_booking_update-bookingline-product', [
                'id'            => $cancellation_line['id'],
                'product_id'    => $cancellation_fee_product['id']
            ]);

            $cancellation_line = BookingLine::id($cancellation_line['id'])
                ->read(['price_id' => ['vat_rate']])
                ->first();

            $unit_price = $params['fee_amount'];
            if(isset($cancellation_line['price_id']['vat_rate']) && $cancellation_line['price_id']['vat_rate'] > 0) {
                $vat_rate = $cancellation_line['price_id']['vat_rate'];
                $unit_price = $params['fee_amount'] / (1 + $vat_rate);
            }

            BookingLine::id($cancellation_line['id'])
                ->update([
                    'has_manual_unit_price' => true,
                    'unit_price'            => round($unit_price, 4)
                ]);

            BookingLine::refreshPrice($orm, $cancellation_line['id']);
            Booking::refreshPrice($orm, $booking['id']);

            // Some groups may have been automatically added (membership fee), we remove them
            $automatically_added_groups_ids = BookingLineGroup::search([['booking_id', '=', $booking['id']], ['is_extra', '=', false]])->ids();
            $orm->delete(BookingLineGroup::getType(), $automatically_added_groups_ids);
        }
    }
}
else {
    // set booking status as "cancelled"
    Booking::id($booking['id'])->update(['status' => 'cancelled']);

    // delete fundings that have paid_amount = 0
    Booking::id($booking['id'])->do('delete_unpaid_fundings');

    // set due_amount to paid_amount value of remaining partially paid funding
    Booking::id($booking['id'])->do('update_fundings_due_to_paid');

    // set due_amount to paid_amount value of remaining partially paid funding
    Booking::id($booking['id'])->do('create_negative_funding_for_reimbursement');
}

// cancel all activities related to the booking for them to not be displayed in planning
BookingActivity::search(['booking_id', '=', $booking['id']])
    ->update(['is_cancelled' => true]);

// handle loyalty points
$loyalty_points_feature = Setting::get_value('sale', 'features', 'booking.loyalty_points', false);

if($loyalty_points_feature) {
    // remove any created points relating to this booking
    BookingPoint::search(['booking_id', '=', $booking['id']])->delete(true);
    // detach any point applied to this booking
    BookingPoint::search(['booking_apply_id', '=', $booking['id']])->update(['booking_apply_id' => null]);
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

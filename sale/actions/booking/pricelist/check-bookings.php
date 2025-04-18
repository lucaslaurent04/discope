<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\price\PriceList;

list($params, $providers) = eQual::announce([
    'description'   => "Searches for bookings that are waiting for the pricelist to be published, and updates their TBC status.",
    'help'          => "This controller is scheduled when a PriceList has its status updated to 'published'.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the pricelist whose status has changed.',
            'type'          => 'integer',
            'required'      => true
        ]
    ],
    'access' => [
        'visibility'        => 'private'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $auth, $dispatch) = [ $providers['context'], $providers['orm'], $providers['auth'], $providers['dispatch']];

// switch to root account (access is 'private')
$auth->su();

$pricelist = PriceList::id($params['id'])->read(['id', 'status', 'price_list_category_id', 'date_from', 'date_to'])->first(true);

if(!$pricelist) {
    throw new Exception("unknown_pricelist", QN_ERROR_UNKNOWN_OBJECT);
}

if($pricelist['status'] == 'published') {

    // Callbacks are defined on Booking, BookingLine, and BookingLineGroup to ensure consistency across these entities.
    // While these callbacks are useful for maintaining data integrity (they and are used in tests),
    // they need to be disabled here to prevent recursive cycles that could lead to deep cycling issues.
    $orm->disableEvents();

    // find related centers
    $centers_ids = Center::search(['price_list_category_id', '=', $pricelist['price_list_category_id']])->ids();

    // find all impacted bookings
    $bookings = Booking::search([
            ['center_id', 'in', $centers_ids],
            ['is_price_tbc', '=', true],
            ['date_from', '>=', $pricelist['date_from']],
            ['date_from', '<=', $pricelist['date_to']]
        ])
        ->read(['center_office_id', 'booking_lines_groups_ids', 'booking_lines_ids'])
        ->get();

    foreach($bookings as $booking_id => $booking) {
        $bookingLines = BookingLine::ids($booking['booking_lines_ids'])->read(['has_manual_unit_price', 'has_manual_vat_rate']);
        // reset booking lines prices and vat according to `has_manual_unit_price` & `has_manual_vat_rate`
        foreach($bookingLines as $booking_line_id => $bookingLine) {
            $values = [];
            if(!$bookingLine['has_manual_unit_price']) {
                $values['unit_price'] = null;
                $values['price'] = null;
                $values['total'] = null;
            }
            if(!$bookingLine['has_manual_vat_rate']) {
                $values['vat_rate'] = null;
            }
            BookingLine::id($booking_line_id)->update($values);
        }

        BookingLineGroup::ids($booking['booking_lines_groups_ids'])
            ->update(['unit_price' => null, 'price' => null, 'vat_rate' => null, 'total' => null]);

        Booking::id($booking_id)
            ->update(['is_price_tbc' => false, 'price' => null, 'total' => null]);

        // force recomputing fields at once
        BookingLine::ids($booking['booking_lines_ids'])->read(['unit_price', 'vat_rate', 'total', 'price']);
        BookingLineGroup::ids($booking['booking_lines_groups_ids'])->read(['unit_price', 'vat_rate', 'price', 'total']);
        Booking::id($booking_id)->read(['total', 'price']);

        // dispatch a message for notifying users
        $dispatch->dispatch('lodging.booking.ready', 'sale\booking\Booking', $booking_id, 'warning', null, [], [], null, $booking['center_office_id']);
    }

    // restore events in case this controller is chained with others
    $orm->enableEvents();
}

$context->httpResponse()
        ->status(204)
        ->send();

<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2025
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;

[$params, $providers] = eQual::announce([
    'description'	=> "Checks if there are some quote Bookings that were reverted from checkedin or checkedout. ",
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var equal\php\Context           $context
 * @var equal\dispatch\Dispatcher   $dispatcher
 */
['context' => $context, 'dispatch' => $dispatcher] = $providers;

$bookings = Booking::search([
    ['status_before_revert_to_quote', 'in', ['checkedin', 'checkedout']],
    ['status', 'not in', ['checkedout', 'invoiced', 'debit_balance', 'credit_balance', 'balanced']]
])
    ->read(['status', 'status_before_revert_to_quote', 'center_office_id'])
    ->get();

$bookings = array_filter(
    $bookings,
    fn($booking) => $booking['status_before_revert_to_quote'] !== $booking['status']
);

$result = [];

foreach($bookings as $booking) {
    $dispatcher->dispatch(
        'sale.booking.check-reverted-from-checkout',
        'sale\booking\Booking',
        $booking['id'],
        'important',
        'sale_booking_check-reverted-from-checkout',
        [],
        [],
        null,
        $booking['center_office_id']
    );

    $result[] = $booking['id'];
}

$context->httpResponse()
        ->body(['result' => $result])
        ->send();



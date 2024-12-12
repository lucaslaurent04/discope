<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;

list($params, $providers) = announce([
    'description'	=>	" Archives outdated booking records in batch. Targets bookings with  status quote, created over 12 months ago, amount paint is zero ",
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context']
]);

list($context) = [$providers['context']];

$bookings = Booking::search([
                ['status', '=', 'quote'],
                ['created', '<=' , strtotime('-12 months')],
                ['state', '=', 'instance'],
                ['paid_amount' , '=' , 0 ],
                ['deleted', '=', 0]
            ])->ids();

if($bookings){
    eQual::run('do', 'sale_booking_bulk-archive', ['ids' => $bookings]);
}

$context->httpResponse()
        ->status(204)
        ->send();

<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

list($params, $providers) = eQual::announce([
    'description'	=>	"Mark a selection of Booking as archive.",
    'params' 		=>	[
        'ids' => [
            'description'       => 'List of Booking identifiers the check against emptiness.',
            'type'              => 'array'
        ]
    ],
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

['context' => $context] = $providers;

$errors = [];

foreach($params['ids'] as $id) {
    try {
        eQual::run('do', 'sale_booking_do-archive', ['id' => $id]);
    }
    catch(Exception $e) {
        $errors[] = "unable to archive Booking {$id} : ".$e->getMessage();
    }
}

$context->httpResponse()
        ->status(204)
        ->send();

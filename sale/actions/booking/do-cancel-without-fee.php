<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

[$params, $providers] = eQual::announce([
    'description'   => "This will cancel the booking without cancellation fee.",
    'params'        => [

        'id' =>  [
            'type'              => 'integer',
            'description'       => "Identifier of the targeted booking.",
            'min'               => 1,
            'required'          => true
        ],

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
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

eQual::run('do', 'sale_booking_do-cancel', [
    'id'        => $params['id'],
    'reason'    => $params['reason']
]);

$context->httpResponse()
        ->status(200)
        ->send();

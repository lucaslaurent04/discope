<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Repairing;

list($params, $providers) = announce([
    'description'   => "This will remove the repairing episode.The rental unit will be released and made available for bookings.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted repairing.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
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
    'providers'     => ['context', 'orm', 'cron']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 */
list($context, $orm, $cron) = [$providers['context'], $providers['orm'], $providers['cron']];

// remove targeted repairing
Repairing::id($params['id'])->delete(true);

$context->httpResponse()
        // success but notify client to reset content
        ->status(205)
        ->send();

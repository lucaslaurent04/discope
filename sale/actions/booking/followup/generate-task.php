<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;
use sale\booking\followup\Task;
use sale\booking\followup\TaskModel;

[$params, $providers] = eQual::announce([
    'description'	=>	"Generate task models' tasks when a booking change status.",
    'params' 		=>	[

        'booking_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\Booking',
            'description'       => "Booking the status has just changed.",
            'required'          => true
        ]

    ],
    'access'        => [
        'visibility'        => 'private'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
['context' => $context] = $providers;

$booking = Booking::id($params['booking_id'])
    ->read(['status', 'center_office_id'])
    ->first(true);

if(is_null($booking)) {
    throw new Exception("unknown_booking", EQ_ERROR_UNKNOWN_OBJECT);
}

$task_models = TaskModel::search(['entity', '=', 'sale\booking\Booking'])
    ->read([
        'name',
        'center_offices_ids',
        'trigger_event_id' => [
            'event_type',
            'entity_status',
            'offset'
        ],
        'deadline_event_id' => [
            'event_type',
            'entity_status',
            'entity_date_field',
            'offset'
        ]
    ])
    ->get(true);

foreach($task_models as $task_model) {
    if(
        $task_model['trigger_event_id']['event_type'] === 'status_change'
        && $task_model['trigger_event_id']['entity_status'] === $booking['status']
        && in_array($booking['center_office_id'], $task_model['center_offices_ids'])
    ) {
        $visible_date = time() + (86400 * $task_model['trigger_event_id']['offset']);

        if($task_model['deadline_event_id']['event_type'] !== 'date_field') {
            continue;
        }

        $book = Booking::id($booking['id'])
            ->read([$task_model['deadline_event_id']['entity_date_field']])
            ->first(true);

        $deadline_date = $book[$task_model['deadline_event_id']['entity_date_field']] + (86400 * $task_model['deadline_event_id']['offset']);

        $task = Task::search([
            ['task_model_id', '=', $task_model['id']],
            ['entity', '=', 'sale\booking\Booking'],
            ['entity_id', '=', $booking['id']]
        ])
            ->read(['notes'])
            ->first(true);

        $notes = null;
        if(!is_null($task)) {
            // Keep notes of existing task, but remove it to be replaced
            $notes = $task['notes'];

            Task::id($task['id'])->delete();
        }

        Task::create([
            'name'          => $task_model['name'],
            'visible_date'  => $visible_date,
            'deadline_date' => $deadline_date,
            'task_model_id' => $task_model['id'],
            'entity'        => 'sale\booking\Booking',
            'entity_id'     => $booking['id'],
            'notes'         => $notes
        ]);
    }
}

$context->httpResponse()
        ->status(204)
        ->send();

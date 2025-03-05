<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2025
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;
use sale\booking\followup\Task;
use sale\booking\followup\TaskModel;

[$params, $providers] = eQual::announce([
    'description'   => "Generate task models' tasks when the date changes.",
    'help'          => "Is meant to be triggered once each day.",
    'params'        => [],
    'access'        => [
        'visibility'        => 'protected'
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

$task_models = TaskModel::search(['entity', '=', 'sale\booking\Booking'])
    ->read([
        'name',
        'center_offices_ids',
        'trigger_event_id' => [
            'event_type',
            'entity_date_field',
            'offset'
        ],
        'deadline_event_id' => [
            'event_type',
            'entity_date_field',
            'offset'
        ]
    ])
    ->get(true);

$task_models = array_filter(
    $task_models,
    function($task_model) {
        return $task_model['trigger_event_id']['event_type'] === 'date_field';
    }
);

if(!empty($task_models)) {
    $map_date_fields = [];
    foreach($task_models as $task_model) {
        $map_date_fields[$task_model['trigger_event_id']['entity_date_field']] = true;
        if(isset($map_date_fields[$task_model['deadline_event_id']['entity_date_field']])) {
            $map_date_fields[$task_model['deadline_event_id']['entity_date_field']] = true;
        }
    }

    $date_fields = array_keys($map_date_fields);

    $today = date('Y-m-d', time());

    $bookings = Booking::search()
        ->read(array_merge($date_fields, ['center_office_id']))
        ->get();

    foreach($task_models as $task_model) {
        foreach($bookings as $booking) {
            $date = $booking[$task_model['trigger_event_id']['entity_date_field']] + (86400 * $task_model['trigger_event_id']['offset']);
            if(
                date('Y-m-d', $date) !== $today
                || !in_array($booking['center_office_id'], $task_model['center_offices_ids'])
            ) {
                continue;
            }

            $visible_date = time();

            $deadline_date = null;
            if(isset($task_model['deadline_event_id'])) {
                if(isset($booking[$task_model['deadline_event_id']['entity_date_field']])) {
                    $deadline_date = $booking[$task_model['deadline_event_id']['entity_date_field']] + (86400 * $task_model['deadline_event_id']['offset']);
                }
                else {
                    // TODO: report problem date not set
                }
            }

            $task = Task::search([
                ['task_model_id', '=', $task_model['id']],
                ['booking_id', '=', $booking['id']],
            ])
                ->read(['notes'])
                ->first();

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
                'booking_id'    => $booking['id']
            ]);
        }
    }
}

$context->httpResponse()
        ->status(204)
        ->send();

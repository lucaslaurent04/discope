<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\followup\Task;
use sale\camp\followup\TaskModel;
use sale\camp\Enrollment;

[$params, $providers] = eQual::announce([
    'description'	=> "Generate task models' tasks when a enrollment status changes.",
    'params' 		=> [

        'enrollment_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Enrollment',
            'description'       => "Enrollment the status has just changed.",
            'required'          => true
        ]

    ],
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

$task_models = TaskModel::search(['entity', '=', 'sale\camp\Enrollment'])
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
            'entity_date_field',
            'offset'
        ]
    ])
    ->get(true);

$task_models = array_filter(
    $task_models,
    function($task_model) {
        return $task_model['trigger_event_id']['event_type'] === 'status_change';
    }
);

if(!empty($task_models)) {
    $map_date_fields = [];
    foreach($task_models as $task_model) {
        if(isset($task_model['deadline_event_id']['entity_date_field'])) {
            $map_date_fields[$task_model['deadline_event_id']['entity_date_field']] = true;
        }
    }

    $date_fields = array_keys($map_date_fields);

    $enrollment = Enrollment::id($params['enrollment_id'])
        ->read(array_merge($date_fields, ['center_office_id', 'status']))
        ->first(true);

    if(is_null($enrollment)) {
        throw new Exception("unknown_entity", EQ_ERROR_UNKNOWN_OBJECT);
    }

    foreach($task_models as $task_model) {
        if(
            $task_model['trigger_event_id']['entity_status'] !== $enrollment['status']
            || !in_array($enrollment['center_office_id'], $task_model['center_offices_ids'])
        ) {
            continue;
        }

        $visible_date = time() + (86400 * $task_model['trigger_event_id']['offset']);

        $deadline_date = null;
        if(isset($task_model['deadline_event_id'])) {
            if(isset($enrollment[$task_model['deadline_event_id']['entity_date_field']])) {
                $deadline_date = $enrollment[$task_model['deadline_event_id']['entity_date_field']] + (86400 * $task_model['deadline_event_id']['offset']);
            }
            else {
                // TODO: report problem date not set
            }
        }

        $task = Task::search([
                ['task_model_id', '=', $task_model['id']],
                ['enrollment_id', '=', $enrollment['id']]
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
            'enrollment_id' => $enrollment['id'],
            'notes'         => $notes
        ]);
    }
}

$context->httpResponse()
        ->status(204)
        ->send();

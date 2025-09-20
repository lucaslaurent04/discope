<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp\followup;

class TaskEvent extends \core\followup\TaskEvent {

    public static function getDescription(): string {
        return "A task event associated with a enrollment status change or a enrollment date field value.";
    }

    public static function getColumns(): array {
        return [

            'entity' => [
                'type'              => 'string',
                'description'       => "Namespace of the concerned entity.",
                'default'           => 'sale\camp\Enrollment'
            ],

            'entity_status' => [
                'type'              => 'string',
                'description'       => "Status of the enrollment the task event is associated with.",
                'selection'         => [
                    'pending',
                    'waitlisted',
                    'validated',
                    'cancelled'
                ],
                'visible'           => ['event_type', '=', 'status_change'],
                'default'           => 'pending'
            ],

            'entity_date_field' => [
                'type'              => 'string',
                'description'       => "Date field of the entity the task event is associated with.",
                'visible'           => ['event_type', '=', 'date_field'],
                'selection'         => [
                    'date_from',
                    'date_to'
                ],
                'default'           => 'date_from'
            ],

            'trigger_event_task_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\followup\TaskModel',
                'foreign_field'     => 'trigger_event_id',
                'description'       => "List of task models that uses the event as a trigger."
            ],

            'deadline_event_task_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\followup\TaskModel',
                'foreign_field'     => 'deadline_event_id',
                'description'       => "List of task models that uses the event as a deadline."
            ]

        ];
    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['event_type'])) {
            if($event['event_type'] === 'status_change') {
                $result['entity_status'] = 'pending';
            }
            elseif($event['event_type'] === 'date_field') {
                $result['entity_date_field'] = 'date_from';
            }
        }

        return $result;
    }

    public static function getConstraints(): array {
        return [

            'entity' => [
                'not_allowed' => [
                    'message'   => 'Entity must be "sale\camp\Enrollment".',
                    'function'  => function ($entity, $values) {
                        return $entity === 'sale\camp\Enrollment';
                    }
                ]
            ],

            'entity_status' => [
                'invalid' => [
                    'message'   => 'Invalid Enrollment status.',
                    'function'  => function ($entity_status, $values) {
                        return in_array($entity_status, ['pending', 'waitlisted', 'validated', 'cancelled']);
                    }
                ]
            ],

            'entity_date_field' => [
                'invalid' => [
                    'message'   => 'Invalid Enrollment status.',
                    'function'  => function ($entity_date_field, $values) {
                        return in_array($entity_date_field, ['date_from', 'date_to']);
                    }
                ]
            ]

        ];
    }
}

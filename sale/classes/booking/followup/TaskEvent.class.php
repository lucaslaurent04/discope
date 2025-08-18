<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking\followup;

class TaskEvent extends \core\followup\TaskEvent {

    public static function getDescription(): string {
        return "A task event associated with a booking status change or an booking date field value.";
    }

    public static function getColumns(): array {
        return [

            'entity' => [
                'type'              => 'string',
                'description'       => "Namespace of the concerned entity.",
                'default'           => 'sale\booking\Booking'
            ],

            'entity_status' => [
                'type'              => 'string',
                'description'       => "Status of the booking the task event is associated with.",
                'selection'         => [
                    'quote',
                    'option',
                    'confirmed',
                    'validated',
                    'checkedin',
                    'checkedout',
                    'proforma',
                    'invoiced',
                    'debit_balance',
                    'credit_balance',
                    'balanced'
                ],
                'visible'           => ['event_type', '=', 'status_change'],
                'default'           => 'quote'
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
                'foreign_object'    => 'sale\booking\followup\TaskModel',
                'foreign_field'     => 'trigger_event_id',
                'description'       => "List of task models that uses the event as a trigger."
            ],

            'deadline_event_task_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\followup\TaskModel',
                'foreign_field'     => 'deadline_event_id',
                'description'       => "List of task models that uses the event as a deadline."
            ]

        ];
    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['event_type'])) {
            if($event['event_type'] === 'status_change') {
                $result['entity_status'] = 'quote';
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
                    'message'   => 'Entity must be "sale\booking\Booking".',
                    'function'  => function ($entity, $values) {
                        return $entity === 'sale\booking\Booking';
                    }
                ]
            ],

            'entity_status' => [
                'invalid' => [
                    'message'   => 'Invalid Booking status.',
                    'function'  => function ($entity_status, $values) {
                        return in_array($entity_status, [
                            'quote', 'option', 'confirmed', 'validated', 'checkedin', 'checkedout',
                            'proforma', 'invoiced', 'debit_balance', 'credit_balance', 'balanced'
                        ]);
                    }
                ]
            ],

            'entity_date_field' => [
                'invalid' => [
                    'message'   => 'Invalid Booking status.',
                    'function'  => function ($entity_date_field, $values) {
                        return in_array($entity_date_field, ['date_from', 'date_to']);
                    }
                ]
            ]

        ];
    }
}

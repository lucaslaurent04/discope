<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking\followup;

use discope\setting\Setting;

class Task extends \core\followup\Task {

    public static function getDescription(): string {
        return "Booking task that has been or must be completed.";
    }

    public static function getLink(): string {
        return "/booking/#/task/object.id";
    }

    public static function getColumns(): array {
        return [

            'description' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Short description of the booking task.",
                'store'             => true,
                'function'          => 'calcDescription'
            ],

            'is_done' => [
                'type'              => 'boolean',
                'description'       => "Whether the task is done.",
                'default'           => false,
                'onupdate'          => 'onupdateIsDone'
            ],

            'done_by' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => "The user who completed the task."
            ],

            'task_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\followup\TaskModel',
                'description'       => 'The model used to create the task.',
                'help'              => 'Based on model or arbitrary',
                'required'          => false
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'Booking the task relates to.',
                'readonly'          => true
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Namespace of the concerned entity.',
                'required'          => false,
                'help'              => 'Overloaded to make field optional.',
                'default'           => 'sale\booking\Booking'
            ],

            'object_id' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Id of the associated entity.',
                'help'              => 'Overloaded to make field optional.',
                'store'             => true,
                'function'          => 'calcObjectId'
            ],

            // #todo - remove when update 20261019125905_followup-task-use-object-instead-of-entity applied on all instances
            'entity' => [
                'type'              => 'string',
                'description'       => 'Namespace of the concerned entity.',
                'required'          => false,
                'help'              => 'Overloaded to make field optional.',
                'default'           => 'sale\booking\Booking'
            ],

            // #todo - remove when update 20261019125905_followup-task-use-object-instead-of-entity applied on all instances
            'entity_id' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Id of the associated entity.',
                'help'              => 'Overloaded to make field optional.',
                'store'             => true,
                'function'          => 'calcObjectId'
            ]

        ];
    }

    public static function calcDescription($self): array {
        $booking_task_description_format = Setting::get_value('sale', 'booking', 'task.description_format', '');
        $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

        $result = [];
        $self->read([
            'booking_id' => [
                'name',
                'date_from',
                'date_to',
                'customer_reference',
                'customer_identity_id' => [
                    'address_city',
                    'address_country'
                ],
                'center_id' => [
                    'name'
                ],
                'center_office_id' => [
                    'name'
                ]
            ]
        ]);
        foreach($self as $id => $task) {
            if(isset($task['booking_id'])) {
                $result[$id] = Setting::parse_format($booking_task_description_format, [
                    'name'                  => $task['booking_id']['name'],
                    'date_from'             => date($date_format, $task['booking_id']['date_from']),
                    'date_to'               => date($date_format, $task['booking_id']['date_to']),
                    'customer_reference'    => $task['booking_id']['customer_reference'],
                    'address_city'          => $task['booking_id']['customer_identity_id']['address_city'],
                    'address_country'       => $task['booking_id']['customer_identity_id']['address_country'],
                    'center_name'           => $task['booking_id']['center_id']['name'],
                    'center_office_name'    => $task['booking_id']['center_office_id']['name']
                ]);
            }
        }

        return $result;
    }

    public static function calcObjectId($self): array {
        $result = [];
        $self->read(['booking_id']);
        foreach($self as $id => $task) {
            if(isset($task['booking_id'])) {
                $result[$id] = $task['booking_id'];
            }
        }

        return $result;
    }

    public static function getConstraints(): array {
        return [

            'object_class' =>  [
                'not_allowed' => [
                    'message'   => 'Entity must be "sale\booking\Booking".',
                    'function'  => function ($entity, $values) {
                        return $entity === 'sale\booking\Booking';
                    }
                ]
            ]

        ];
    }

    protected static function doCancelAlerts($self, $dispatch) {
        foreach($self as $id => $task) {
            $dispatch->cancel('sale.booking.followup.task.reminder', 'sale\booking\followup\Task', $id);
        }
    }

    public static function getActions(): array {
        return [

            'cancel_alerts' => [
                'description'   => "Cancel alerts linked to this task.",
                'policies'      => [],
                'function'      => 'doCancelAlerts'
            ]

        ];
    }

    public static function onupdateIsDone($self) {
        parent::onupdateIsDone($self);

        $self->do('cancel_alerts');
    }
}

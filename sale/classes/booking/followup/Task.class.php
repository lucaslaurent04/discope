<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking\followup;

use discope\followup\Task as DiscopeTask;

class Task extends DiscopeTask {

    public static function getDescription(): string {
        return "Booking task that must be realized.";
    }

    public static function getColumns(): array {
        return [

            'task_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\followup\TaskModel',
                'description'       => "The model used to create the task."
            ],

            'entity' => [
                'type'              => 'string',
                'description'       => "Namespace of the concerned entity.",
                'default'           => 'sale\booking\followup\Task'
            ],

            'entity_id' => [
                'type'              => 'integer',
                'description'       => "Id of the associated entity. In this case it is the booking id.",
                'required'          => true,
                'dependencies'      => ['booking_id']
            ],

            'booking_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => "Booking the task relates to.",
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcBookingId'
            ]

        ];
    }

    public static function calcBookingId($self): array {
        $result = [];
        $self->read(['entity_id']);
        foreach($self as $id => $task) {
            $result[$id] = $task['entity_id'];
        }

        return $result;
    }

    public static function getConstraints(): array {
        return [

            'entity' =>  [
                'not_allowed' => [
                    'message'       => 'Entity must be "sale\booking\Booking".',
                    'function'      => function ($entity, $values) {
                        return $entity === 'sale\booking\Booking';
                    }
                ]
            ]

        ];
    }
}

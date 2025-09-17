<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp\followup;

class Task extends \core\followup\Task {

    public static function getDescription(): string {
        return "Enrollment task that has been or must be completed.";
    }

    public static function getColumns(): array {
        return [

            'done_by' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => "The user who completed the task."
            ],

            'task_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\followup\TaskModel',
                'description'       => "The model used to create the task.",
                'help'              => "Based on model or arbitrary.",
                'required'          => false
            ],

            'entity' => [
                'type'              => 'string',
                'description'       => "Namespace of the concerned entity.",
                'default'           => 'sale\camp\Enrollment'
            ],

            'entity_id' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Id of the associated entity. In this case it is the enrollment id.',
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcEntityId',
                'readonly'          => true
            ],

            'enrollment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Enrollment',
                'description'       => 'Enrollment the task relates to.',
                'readonly'          => true
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Namespace of the concerned entity.',
                'required'          => false,
                'help'              => 'Overloaded to make field optional.'
            ],

            'object_id' => [
                'type'              => 'integer',
                'description'       => 'Id of the associated entity.',
                'required'          => false,
                'help'              => 'Overloaded to make field optional.'
            ]

        ];
    }

    public static function calcEntityId($self): array {
        $result = [];
        $self->read(['enrollment_id']);
        foreach($self as $id => $task) {
            if(isset($task['enrollment_id'])) {
                $result[$id] = $task['enrollment_id'];
            }
        }

        return $result;
    }

    public static function getConstraints(): array {
        return [

            'entity' =>  [
                'not_allowed' => [
                    'message'   => 'Entity must be "sale\camp\Enrollment".',
                    'function'  => function ($entity, $values) {
                        return $entity === 'sale\camp\Enrollment';
                    }
                ]
            ]

        ];
    }
}

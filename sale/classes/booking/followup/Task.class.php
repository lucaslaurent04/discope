<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking\followup;

use equal\orm\Model;

class Task extends Model {
    
    public static function getDescription(): string {
        return "Booking task that must be realized.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the task.",
                'required'          => true
            ],

            'is_done' => [
                'type'              => 'boolean',
                'description'       => "Whether the task is done.",
                'default'           => false,
                'onupdate'          => 'onupdateDone'
            ],

            'done_by' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'core\User',
                'description'       => "The user who completed the task.",
                'function'          => 'calcDoneBy',
                'store'             => true
            ],

            'done_date' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => "Date on which the task was completed.",
                'function'          => 'calcDoneDate',
                'store'             => true
            ],

            'visible_date' => [
                'type'              => 'date',
                'description'       => "Date on which the task must be visible.",
                'help'              => "Always visible if the date is not set."
            ],

            'deadline_date' => [
                'type'              => 'date',
                'description'       => "Date on which the task as to be completed."
            ],

            'task_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\followup\TaskModel',
                'description'       => "The model used to create the task."
            ],

            'notes' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Notes about the task."
            ],

            'entity' => [
                'type'              => 'string',
                'description'       => "Namespace of the concerned entity.",
                'required'          => true
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => "Booking the task relates to.",
                'required'          => true
            ]

        ];
    }

    public static function calcDoneDate($self): array {
        $result = [];
        $self->read(['is_done']);
        foreach($self as $id => $task) {
            $result[$id] = $task['is_done'] ? time() : null;
        }

        return $result;
    }

    public static function calcDoneBy($self): array {
        /** @var \equal\auth\AuthenticationManager $auth */
        ['auth' => $auth] = \eQual::inject(['auth']);
        $user_id = $auth->userId();

        $result = [];
        $self->read(['is_done']);
        foreach($self as $id => $task) {
            $result[$id] = $task['is_done'] ? $user_id : null;
        }

        return $result;
    }
}

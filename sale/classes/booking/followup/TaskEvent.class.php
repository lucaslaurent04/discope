<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking\followup;

use discope\followup\TaskEvent as DiscopeTaskEvent;

class TaskEvent extends DiscopeTaskEvent {

    public static function getDescription(): string {
        return "A task event associated with a booking status change or an booking date field value.";
    }

    public static function getColumns(): array {
        return [

            'entity' => [
                'type'              => 'string',
                'description'       => "Namespace of the concerned entity.",
                'required'          => true,
                'default'           => 'sale\booking\Booking'
            ],

            'entity_date_field' => [
                'type'              => 'string',
                'description'       => "Date field of the entity the task event is associated with.",
                'visible'           => ['event_type', '=', 'date_field'],
                'selection'         => [
                    'date_from',
                    'date_to'
                ]
            ],

            'trigger_event_task_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\followup\TaskModel',
                'foreign_field'     => 'trigger_event_id',
                'description'       => 'List of task models that uses the event as a trigger.'
            ],

            'deadline_event_task_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\followup\TaskModel',
                'foreign_field'     => 'deadline_event_id',
                'description'       => 'List of task models that uses the event as a deadline.'
            ]

        ];
    }
}

<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2025
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking\followup;

use equal\orm\Model;

class TaskEvent extends Model {

    public static function getDescription(): string {
        return "A task event associated with an entity status change or an entity date field value.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the task event.",
                'required'          => true
            ],

            'entity' => [
                'type'              => 'string',
                'description'       => "Namespace of the concerned entity.",
                'required'          => true
            ],

            'event_type' => [
                'type'              => 'string',
                'description'       => "Event type.",
                'selection'         => ["status_change", "date_field"],
                'default'           => 'status_change'
            ],

            'entity_status' => [
                'type'              => 'string',
                'description'       => "Status of the entity the task event is associated with.",
                'visible'           => ['event_type', '=', 'status_change']
            ],

            'entity_date_field' => [
                'type'              => 'string',
                'description'       => "Date field of the entity the task event is associated with.",
                'visible'           => ['event_type', '=', 'date_field']
            ],

            'offset' => [
                'type'              => 'integer',
                'description'       => "The offset in days from the entity status change or field value.",
                'default'           => 0
            ]

        ];
    }
}

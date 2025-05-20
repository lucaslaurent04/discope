<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use core\setting\Setting;
use equal\orm\Model;

class Presence extends Model {

    public static function getDescription(): string {
        return "A day of presence of a child to a camp.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name of the presence.",
                'store'             => true,
                'function'          => 'calcName'
            ],

            'presence_date' => [
                'type'              => 'date',
                'description'       => "Date of the presence.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'child_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Child',
                'description'       => "The child concerned by the presence.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'camp_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Camp',
                'description'       => "The camp the child is attending that day.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'am_daycare' => [
                'type'              => 'boolean',
                'description'       => "Is the child being taken care of at the morning daycare?",
                'default'           => false
            ],

            'pm_daycare' => [
                'type'              => 'boolean',
                'description'       => "Is the child being taken care of at the afternoon daycare?",
                'default'           => false
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read([
            'presence_date',
            'child_id'      => ['name'],
            'camp_id'       => ['short_name']
        ]);
        $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');
        foreach($self as $id => $presence) {
            $result[$id] = sprintf('%s - %s | %s',
                date($date_format, $presence['presence_date']),
                $presence['child_id']['name'],
                $presence['camp_id']['short_name']
            );
        }

        return $result;
    }
}

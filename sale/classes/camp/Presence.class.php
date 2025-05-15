<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class Presence extends Model {

    public static function getDescription(): string {
        return "A day of presence of a child to a camp.";
    }

    public static function getColumns(): array {
        return [

            'presence_date' => [
                'type'              => 'date',
                'description'       => "Date of the presence.",
                'required'          => true
            ],

            'child_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Child',
                'description'       => "The child concerned by the presence.",
                'required'          => true
            ],

            'camp_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Camp',
                'description'       => "The camp the child is attending that day.",
                'required'          => true
            ]

        ];
    }
}

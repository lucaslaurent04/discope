<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

class AttendingChild extends Child {

    public static function getDescription(): string {
        return "Virtual object used to list children that are attending pending camps.";
    }

    public static function getColumns(): array {
        return [

            'camp_id' => [
                'type'              => 'computed',
                'result_type'        => 'many2one',
                'foreign_object'    => 'sale\camp\Camp',
                'description'       => "The camp the child is enrolled to.",
                'store'             => false
            ],

            'weekend_extra' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    'none',
                    'full',
                    'saturday-morning'
                ],
                'description'       => "Does the child stays the weekend after the camp.",
                'help'              => "If child stays full weekend it usually means that he is enrolled to another camp the following week. If child stays saturday morning it means that its guardian cannot pick him/her up on Friday.",
                'store'             => false
            ],

            'has_camp_birthday' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'store'             => false
            ],

            'is_ase' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'store'             => false
            ]

        ];
    }
}

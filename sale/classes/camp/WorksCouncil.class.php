<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class WorksCouncil extends Model {

    public static function getDescription(): string {
        return "Works council that offer a financial help to children for summer camps. If enrollment with work council, then enhance camp_class by 1 level.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the works council.",
                'required'          => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "Code of the works council.",
                'required'          => true
            ],

            'address_street' => [
                'type'              => 'string',
                'description'       => "Street and number of the works council."
            ],

            'address_dispatch' => [
                'type'              => 'string',
                'description'       => "Optional info for mail dispatch (apartment, box, floor, ...)."
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => "Zip code of the works council.",
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => "City of the works council."
            ],

            'enrollments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Enrollment',
                'foreign_field'     => 'works_council_id',
                'description'       => "Enrollments that are using this works council."
            ]

        ];
    }
}

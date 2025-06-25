<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class Institution extends Model {

    public static function getDescription(): string {
        return "Institution of a child that participate to a camp.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the institution of the child.",
                'required'          => true
            ],

            'address_street' => [
                'type'              => 'string',
                'description'       => "Street and number of the child's institution.",
                'required'          => true
            ],

            'address_dispatch' => [
                'type'              => 'string',
                'description'       => "Optional info for mail dispatch (apartment, box, floor, ...)."
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => "Zip code of the child's institution.",
                'required'          => true
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => "City of the child's institution.",
                'required'          => true
            ],

            'email' => [
                'type'              => 'string',
                'result_type'       => 'string',
                'usage'             => 'email',
                'description'       => "Email of the institution of the child.",
                'required'          => true
            ],

            'phone' => [
                'type'              => 'string',
                'usage'             => 'phone',
                'description'       => "Phone number of the child's institution.",
                'required'          => true
            ],

            'children_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Child',
                'foreign_field'     => 'institution_id',
                'description'       => "Children that are in this institution."
            ],

            'external_ref' => [
                'type'              => 'string',
                'description'       => 'External reference for institution, if any.'
            ]

        ];
    }
}

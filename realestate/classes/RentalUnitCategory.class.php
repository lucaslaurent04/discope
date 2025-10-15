<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate;
use equal\orm\Model;

class RentalUnitCategory extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Short code of the rental unit category.",
                'function'          => 'calcName',
                'store'             => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "Short code of the rental unit category.",
                'dependents'        => ['name']
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Reason of the categorization of rental units.",
                'multilang'         => true,
                'dependents'        => ['name']
            ],

            'rental_units_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'realestate\RentalUnit',
                'foreign_field'     => 'rental_unit_category_id',
                'description'       => 'The rental units that are assigned to the category.'
            ]

        ];
    }

    public static function calcName($om, $ids, $lang) {
        $result = [];
        $categories = $om->read(self::getType(), $ids, ['code', 'description'], $lang);
        foreach($categories as $cid => $category) {
            $result[$cid] = "{$category['code']} - {$category['description']}";
        }
        return $result;
    }

}
<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\autosale;
use equal\orm\Model;

class AutosaleListCategory extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the automatic sale category."
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Reason of the categorization of children lists.",
                'multilang'         => true
            ],

            'autosale_lists_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\autosale\AutosaleList',
                'foreign_field'     => 'autosale_list_category_id',
                'description'       => 'The autosale lists that are assigned to the category.'
            ]

        ];
    }

}
<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\season;
use equal\orm\Model;

class SeasonCategory extends Model {
    public static function getColumns() {
        /**
         */

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Short label to ease identification of the category."
            ],
            
            'seasons_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\season\Season',
                'foreign_field'     => 'season_category_id',
                'description'       => "Seasons that are related to this category, if any."
            ]
        ];
    }
}
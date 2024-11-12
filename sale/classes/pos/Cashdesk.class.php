<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pos;
use equal\orm\Model;

class Cashdesk extends Model {

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Short mnemo to identify the cashdesk.",
                'required'          => true
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "The center the desk relates to.",
                'required'          => true,
                'ondelete'          => 'cascade'         // delete cashdesk when parent Center is deleted
            ],

            'establishment_id' => [
                'type'              => 'alias',
                'alias'             => 'center_id',
            ],

            'sessions_ids'  => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pos\CashdeskSession',
                'foreign_field'     => 'cashdesk_id',
                'ondetach'          => 'delete',
                'description'       => 'List of sessions of the cashdesk.'
            ]
        ];
    }

}

<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;

class User extends \core\User {

    public static function getName() {
        return 'User';
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'function'          => 'calcName',
                'result_type'       => 'string',
                'store'             => true,
                'description'       => 'The display name of the user.'
            ],

            'identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'domain'            => ['type', '=', 'I'],
                'description'       => 'The contact related to the user.',
                'dependents'        => ['name']
            ],

            'setting_values_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\setting\SettingValue',
                'foreign_field'     => 'user_id',
                'description'       => 'List of settings that relate to the user.'
            ],

            /* the organisation the user is part of (multi-company support) */
            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => "The organisation the user relates to.",
                'default'           => 1
            ],

            'centers_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'identity\Center',
                'foreign_field'     => 'users_ids',
                'rel_table'         => 'lodging_identity_rel_center_user',
                'rel_foreign_key'   => 'center_id',
                'rel_local_key'     => 'user_id'
            ],

            'center_offices_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'identity\CenterOffice',
                'foreign_field'     => 'users_ids',
                'rel_table'         => 'lodging_identity_rel_center_office_user',
                'rel_foreign_key'   => 'center_office_id',
                'rel_local_key'     => 'user_id',
                'onupdate'          => 'onupdateCenterOfficesIds'
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['login', 'identity_id' => ['name']]);
        foreach($self as $id => $user) {
            if(isset($user['identity_id']['name']) && strlen($user['identity_id']['name'])) {
                $result[$id] = $user['identity_id']['name'];
            }
            else {
                $result[$id] = $user['login'];
            }
        }
        return $result;
    }

    public static function onupdateCenterOfficesIds($om, $oids, $values, $lang) {

        $users = $om->read(__CLASS__, $oids, ['centers_ids', 'center_offices_ids.centers_ids'], $lang);
        if($users > 0) {

            foreach($users as $uid => $user) {
                // pass-1 remove previous centers_ids
                $om->write(__CLASS__, $uid, ['centers_ids' => array_map(function($id) { return "-{$id}";}, $user['centers_ids'])], $lang);
                // pass-2 add new centers_ids
                $centers_ids = [];
                foreach((array) $user['center_offices_ids.centers_ids'] as $oid => $office) {
                    $centers_ids = array_merge($centers_ids, $office['centers_ids']);
                }
                $om->write(__CLASS__, $uid, ['centers_ids' => $centers_ids], $lang);
            }
        }
    }
}

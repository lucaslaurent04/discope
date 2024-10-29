<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking\channelmanager;

class Property extends \equal\orm\Model {

    public static function getDescription() {
        return "A property is used as an interface to map Center from Discope with Property (hotel) from the channel manager.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true,
                'description'       => "Display name of the property (center)."
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => "Mark the property as active (for syncing).",
                'default'           => true
            ],

            'extref_property_id' => [
                'type'              => 'integer',
                'description'       => "External identifier of the property (from channel manager).",
                'required'          => true
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "The center to the property refers to.",
                'required'          => true
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => 'Office that manages the property.',
            ],

            'room_types_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\channelmanager\RoomType',
                'foreign_field'     => 'property_id',
                'description'       => 'Room types defined for the property.',
                'order'             => 'extref_roomtype_id'
            ],

            'extra_services_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\channelmanager\ExtraService',
                'foreign_field'     => 'property_id',
                'description'       => 'Extra services defined for the property.'
            ],

            'username' => [
                'type'              => 'string',
                'description'       => 'Username to access the Cubilis API.'
            ],

            'password' => [
                'type'              => 'string',
                'description'       => 'Password to access the Cubilis API.'
            ],

            'api_id' => [
                'type'              => 'integer',
                'description'       => 'Identifier for the Cubilis API (allows to identity the Origin).'
            ],

            'psp_provider' => [
                'type'              => 'string',
                'description'       => 'String identifier of the Payment Service Provider.',
                'default'           => 'stripe'
            ],

            'psp_key' => [
                'type'              => 'string',
                'description'       => 'Private key to use for sending requests to PSP.'
            ]

        ];
    }

    public static function calcName($om, $ids, $lang) {
        $result = [];
        $properties = $om->read(self::getType(), $ids, ['center_id.name']);
        if($properties > 0) {
            foreach($properties as $id => $property) {
                $result[$id] = $property['center_id.name'];
            }
        }
        return $result;
    }
}

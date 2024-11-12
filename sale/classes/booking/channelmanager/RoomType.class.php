<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking\channelmanager;

class RoomType extends \equal\orm\Model {

    public function getTable() {
        return 'lodging_sale_booking_channelmanager_roomtype';
    }

    public static function getDescription() {
        return "RoomTypes are used as an interface to map a Property (hotel) from the channel manager with a Center, a product Model and a list of rental units (accommodations).";
    }

    public static function getColumns() {
        return [

            /*
            'extref_property_id' => [
                'type'              => 'integer',
                'description'       => "External identifier of the property (from channel manager).",
                'required'          => true
            ],
            */

            'name' => [
                'type'              => 'string',
                'description'       => "Name used as reference (should be identical in channel manager).",
                'required'          => true
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "The center to the property refers to."
            ],

            'extref_roomtype_id' => [
                'type'              => 'integer',
                'description'       => "External identifier of the room type (from channel manager).",
                'required'          => true
            ],

            'property_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\channelmanager\Property',
                'description'       => "The property (center) the Room Type is part of.",
                'onupdate'          => 'onupdatePropertyId',
                'required'          => true,
            ],

            'product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'description'       => "Product Model to use when a room of this type is booked.",
                'required'          => true,
            ],

            'rental_units_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\RentalUnit',
                'foreign_field'     => 'room_types_ids',
                'rel_table'         => 'lodging_rental_unit_rel_room_type',
                'rel_foreign_key'   => 'rental_unit_id',
                'rel_local_key'     => 'room_type_id',
                'description'       => 'List of rental units relating to the room type.',
                'help'              => 'The listed rental units are mapped to the the room type and can be used when a reservation is made for the room type, as provided by the channel manager.'
            ],

            'is_active' => [
                'type'              => 'boolean',
                'description'       => 'Is the room type active in Cubilis.',
                'help'              => 'If active the linked rental units availabilities are synced to Cubilis.',
                'default'           => true
            ]

        ];
    }

    public static function onupdatePropertyId($om, $ids, $lang) {
        $room_types = $om->read(self::getType(), $ids, ['property_id.center_id']);

        if($room_types > 0) {
            foreach($room_types as $id => $room_type) {
                $om->update(self::getType(), $id, ['center_id' => $room_type['property_id.center_id']]);
            }
        }

    }

}

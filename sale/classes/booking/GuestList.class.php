<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use equal\orm\Model;

class GuestList extends Model {

    public static function getColumns() {
        return [

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'The booking the guest list relates to.',
                'onupdate'          => 'onupdateBookingId',
                'required'          => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'sent'
                ],
                'description'       => 'Status of the GuestList.',
                'default'           => 'pending'
            ],

            'guest_list_items_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\GuestListItem',
                'foreign_field'     => 'guest_list_id',
                'description'       => "The items that refer to the guest list."
            ]

        ];
    }

    public static function onupdateBookingId($om, $oids, $values, $lang) {
        $lines = $om->read(self::getType(), $oids, ['booking_id'], $lang);
        foreach($lines as $lid => $line) {
            Booking::id($line['booking_id'])->update(['guest_list_id' => $lid]);
        }
    }
}

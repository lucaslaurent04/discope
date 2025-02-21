<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use equal\orm\Model;

class BookingActivity extends Model {

    public static function getDescription(): string {
        return "Link between an activity booking_line and its supplies booking_lines and transport booking_line.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'function'          => 'calcName',
                'result_type'       => 'string',
                'store'             => true,
                'description'       => "The name of the booking activity."
            ],

            'activity_booking_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLine',
                'description'       => "Booking Line of the activity.",
                'help'              => "Stays empty if the booking_line_id is the main activity."
            ],

            'booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingLine',
                'foreign_field'     => 'booking_activity_id',
                'description'       => "All booking lines that are linked the activity.",
            ],

            'booking_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => "Booking the activity relates to.",
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcBookingId'
            ],

            'booking_line_group_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLineGroup',
                'description'       => "Booking line group the activity relates to.",
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcBookingLineGroup'
            ],

            'supplies_booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingLine',
                'foreign_field'     => 'booking_activity_id',
                'description'       => "All supplies booking lines that are linked the activity.",
                'domain'            => ['is_supply', '=', true]
            ],

            'transports_booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingLine',
                'foreign_field'     => 'booking_activity_id',
                'description'       => "All transport booking lines that are linked the activity.",
                'help'              => "There should be only one transport booking line for an activity.",
                'domain'            => ['is_transport', '=', true]
            ],

            'counter' => [
                'type'              => 'integer',
                'description'       => "The place of the activity in the booking sojourn, is it the first or second or ... activity of the same type in the sojourn.",
                'default'           => 1
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['activity_booking_line_id' => ['name']]);
        foreach($self as $id => $booking_activity) {
            if(isset($booking_activity['activity_booking_line_id']['name'])) {
                $result[$id] = $booking_activity['activity_booking_line_id']['name'];
            }
        }

        return $result;
    }

    public static function calcBookingId($self): array {
        $result = [];
        $self->read(['activity_booking_line_id' => ['booking_id']]);
        foreach($self as $id => $booking_activity) {
            $result[$id] = $booking_activity['activity_booking_line_id']['booking_id'];
        }

        return $result;
    }

    public static function calcBookingLineGroup($self): array {
        $result = [];
        $self->read(['activity_booking_line_id' => ['booking_line_group_id']]);
        foreach($self as $id => $booking_activity) {
            $result[$id] = $booking_activity['activity_booking_line_id']['booking_line_group_id'];
        }

        return $result;
    }

    public static function ondelete($self): void {
        $self->read(['booking_line_group_id', 'booking_lines_ids']);
        foreach($self as $booking_activity) {
            if(!empty($booking_activity['booking_lines_ids'])) {
                $booking_lines_ids_remove = array_map(
                    function ($id) { return -$id; },
                    $booking_activity['booking_lines_ids']
                );

                BookingLineGroup::id($booking_activity['booking_line_group_id'])
                    ->update(['booking_lines_ids' => $booking_lines_ids_remove]);
            }
        }
    }
}

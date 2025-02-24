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
                'help'              => "Stays empty if the booking_line_id is the main activity.",
                'readonly'          => true,
                'required'          => true
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
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Total tax-excluded price for all lines (computed).",
                'function'          => 'calcTotal',
                'store'             => true
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => "Final tax-included price for all lines (computed).",
                'function'          => 'calcPrice',
                'store'             => true
            ],

            'is_fullday_virtual' => [
                'type'              => 'boolean',
                'description'       => "Is the activity related to another for a fullday activity.",
                'help'              => "If true the activity is 'virtual' and no booking_line has a direct link to it. This activity will be mainly used for the planning.",
                'default'           => false
            ],

            'time_slot_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\booking\TimeSlot',
                'description'       => "Specific day time slot on which the service is delivered.",
                'store'             => true,
                'relation'          => ['activity_booking_line_id' => ['time_slot_id']]
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

    public static function calcTotal($self): array {
        $result = [];
        $self->read(['booking_lines_ids' => ['total']]);
        foreach($self as $id => $booking_activity) {
            $total = 0;
            foreach($booking_activity['booking_lines_ids'] as $line) {
                $total += $line['total'];
            }
            $result[$id] = $total;
        }

        return $result;
    }

    public static function calcPrice($self): array {
        $result = [];
        $self->read(['booking_lines_ids' => ['price']]);
        foreach($self as $id => $booking_activity) {
            $price = 0;
            foreach($booking_activity['booking_lines_ids'] as $line) {
                $price += $line['price'];
            }
            $result[$id] = $price;
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

    public static function _resetPrices($self) {
        // reset computed fields related to price
        $self->update(['total' => null, 'price' => null]);
    }
}

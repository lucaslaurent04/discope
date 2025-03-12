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
                'result_type'       => 'string',
                'store'             => true,
                'description'       => "The name of the booking activity.",
                'relation'          => ['activity_booking_line_id' => 'name']
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
                'relation'          => ['activity_booking_line_id' => 'booking_id']
            ],

            'booking_line_group_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLineGroup',
                'description'       => "Booking line group the activity relates to.",
                'store'             => true,
                'instant'           => true,
                'relation'          => ['activity_booking_line_id' => 'booking_line_group_id']
            ],

            'providers_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\provider\Provider',
                'foreign_field'     => 'booking_activities_ids',
                'rel_table'         => 'sale_booking_bookingactivity_rel_sale_provider_providers',
                'rel_foreign_key'   => 'provider_id',
                'rel_local_key'     => 'booking_activity_id',
                'description'       => 'The assigned providers for the activity, if required by product model.'
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

            'is_virtual' => [
                'type'              => 'boolean',
                'description'       => "Is the activity related to another for a fullday activity or an activity with a duration.",
                'help'              => "If true the activity is 'virtual' and no booking_line has a direct link to it. This activity will be mainly used for the planning.",
                'default'           => false
            ],

            'activity_date' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => 'Specific day time slot on which the service is delivered.',
                'store'             => true,
                'relation'          => ['activity_booking_line_id' => 'service_date'],
                'onupdate'          => 'onupdateActivityDate'
            ],

            'time_slot_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\booking\TimeSlot',
                'description'       => "Specific day time slot on which the service is delivered.",
                'store'             => true,
                'relation'          => ['activity_booking_line_id' => 'time_slot_id'],
                'onupdate'          => 'onupdateTimeSlotId'
            ],

            'rental_unit_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\RentalUnit',
                'description'       => "The rental unit needed for the activity to take place.",
                'onupdate'          => 'onupdateRentalUnitId'
            ]

        ];
    }

    public static function getActions(): array {
        return [
            'reset-prices' => [
                'description'   => "Reset the prices fields values so they can be re-calculated.",
                'policies'      => [],
                'function'      => 'doResetPrices'
            ],
            'update-counters' => [
                'description'   => "Re-calculate the activities counters by group.",
                'policies'      => [],
                'function'      => 'doUpdateCounters'
            ]
        ];
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

    public static function onupdateActivityDate($self) {
        $self->do('update-counters');
    }

    public static function onupdateTimeSlotId($self) {
        $self->do('update-counters');
    }

    public static function onupdateRentalUnitId($self) {
        $self->read(['activity_booking_line_id', 'rental_unit_id']);
        foreach($self as $booking_activity) {
            BookingLine::id($booking_activity['activity_booking_line_id'])
                ->update(['activity_rental_unit_id' => $booking_activity['rental_unit_id']]);
        }
    }

    public static function doResetPrices($self) {
        // reset computed fields related to price
        $self->update(['total' => null, 'price' => null]);
    }

    public static function doUpdateCounters($self) {
        $self->read(['booking_line_group_id']);

        $mapBookingLineGroupIds = [];
        foreach($self as $booking_activity) {
            $mapBookingLineGroupIds[$booking_activity['booking_line_group_id']] = true;
        }
        $bookingLineGroupIds = array_keys($mapBookingLineGroupIds);

        foreach($bookingLineGroupIds as $group_id) {
            $group_activities = BookingActivity::search(
                ['booking_line_group_id', '=', $group_id],
                ['sort' => ['activity_date' => 'asc']]
            )
                ->read(['activity_date', 'time_slot_id' => ['order']])
                ->get(true);

            usort($group_activities, function($a, $b) {
                $date_comp = $a['activity_date'] <=> $b['activity_date'];

                return $date_comp !== 0 ? $date_comp : $a['time_slot_id']['order'] <=> $b['time_slot_id']['order'];
            });

            $counter = 1;
            foreach($group_activities as $booking_activity) {
                BookingActivity::id($booking_activity['id'])
                    ->update(['counter' => $counter++]);
            }
        }
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

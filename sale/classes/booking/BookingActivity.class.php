<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use core\setting\Setting;
use equal\orm\Model;
use hr\employee\Employee;
use sale\camp\Camp;
use sale\camp\CampGroup;

class BookingActivity extends Model {

    public static function getDescription(): string {
        return "For booking, it is the link between an activity booking_line and its supplies booking_lines and transport booking_line. Can also be a camp activity.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The name of the booking activity.",
                'store'             => true,
                'function'          => 'calcName'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => "Description of the activity."
            ],

            /**
             * Booking
             */

            'activity_booking_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLine',
                'description'       => "Booking Line of the activity.",
                'help'              => "A free activity can be linked to a booking line group without being linked directly to a booking line.",
                'readonly'          => true,
                'dependents'        => ['time_slot_id', 'activity_date', 'booking_id', 'booking_line_group_id', 'product_id', 'product_model_id'],
                'ondelete'          => 'cascade'
            ],

            'is_virtual' => [
                'type'              => 'boolean',
                'description'       => "Is the activity related to another for a fullday activity or an activity with a duration.",
                'help'              => "If true the activity is 'virtual' and no booking_line has a direct link to it. This activity will be mainly used for the planning.",
                'default'           => false
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

            /**
             * Camp
             */

            'camp_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\camp\Camp',
                'description'       => "Camp the activity is organised for.",
                'store'             => true,
                'relation'          => ['camp_group_id' => 'camp_id'],
                'readonly'          => true,
                'ondelete'          => 'cascade'
            ],

            'camp_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\CampGroup',
                'description'       => "Camp group the activity is organised for.",
                'readonly'          => true,
                'ondelete'          => 'cascade',
                'dependents'        => ['camp_id']
            ],

            /**
             * Common
             */

            'product_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => "The product the activity relates to.",
                'store'             => true,
                'relation'          => ['activity_booking_line_id' => 'product_id'],
                'domain'            => [
                    ['is_activity', '=', true],
                    ['can_sell', '=', true]
                ],
                'dependents'        => ['name', 'product_model_id'],
                'onupdate'          => 'onupdateProductId'
            ],

            'product_model_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'description'       => "The product model the activity relates to.",
                'store'             => true,
                'instant'           => true,
                'relation'          => ['product_id' => 'product_model_id']
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

            'qty' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "The quantity of groups for this activity.",
                'help'              => "It is used to know the quantity of providers that need to be assigned to it.",
                'store'             => true,
                'function'          => 'calcQty'
            ],

            'counter' => [
                'type'              => 'integer',
                'description'       => "The number of the activity in the booking sojourn, is it the first or second or ... activity of the same type in the sojourn.",
                'default'           => 1
            ],

            'counter_total' => [
                'type'              => 'integer',
                'description'       => "The total of this type of activity in the booking sojourn for the group.",
                'default'           => 1
            ],

            'employee_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\employee\Employee',
                'description'       => "Employee assigned to the supervision of the activity."
            ],

            'activity_date' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => "Specific date on which the service is delivered.",
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
                'onupdate'          => 'onupdateTimeSlotId',
                'dependents'        => ['time_slot_order']
            ],

            'time_slot_order' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Order of the time slot, used to sort activities.",
                'store'             => true,
                'instant'           => true,
                'relation'          => ['time_slot_id' => 'order']
            ],

            'schedule_from' => [
                'type'              => 'computed',
                'result_type'       => 'time',
                'description'       => "Time at which the activity starts (included).",
                'store'             => true,
                'function'          => 'calcScheduleFrom'
            ],

            'schedule_to' => [
                'type'              => 'computed',
                'result_type'       => 'time',
                'description'       => "Time at which the activity ends (excluded).",
                'store'             => true,
                'function'          => 'calcScheduleTo'
            ],

            'is_exclusive' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Is the activity exclusive to the employee\'s time slot?',
                'help'              => "When activitiy is exclusive, a single activity can be assigned to the employee, whatever the start and end times.",
                'store'             => true,
                'instant'           => true,
                'relation'          => ['product_model_id' => 'is_exclusive']
            ],

            'rental_unit_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\RentalUnit',
                'description'       => "The rental unit needed for the activity to take place.",
                'onupdate'          => 'onupdateRentalUnitId'
            ],

            'has_staff_required' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Does the activity need an employee to be assigned to it?",
                'store'             => true,
                'instant'           => true,
                'relation'          => ['product_model_id' => 'has_staff_required']
            ],

            'has_provider' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Does the activity need one or multiple providers to be assigned to it?",
                'store'             => true,
                'instant'           => true,
                'relation'          => ['product_model_id' => 'has_provider']
            ],

            'group_num' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Number of the group.",
                'help'              => "For booking it is the order of the activity group, for camp it is the num of the camp group.",
                'store'             => true,
                'function'          => 'calcGroupNum'
            ],

            'is_cancelled' => [
                'type'              => 'boolean',
                'description'       => "Is the activity cancelled?",
                'default'           => false
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

    public static function calcName($self): array {
        $result = [];
        $self->read(['product_id' => ['name']]);
        foreach($self as $id => $booking_activity) {
            if(isset($booking_activity['product_id']['name'])) {
                $result[$id] = $booking_activity['product_id']['name'];
            }
        }

        return $result;
    }

    public static function calcBookingId($self): array {
        $result = [];
        $self->read(['activity_booking_line_id' => ['booking_id']]);
        foreach($self as $id => $booking_activity) {
            if(isset($booking_activity['activity_booking_line_id'])) {
                $result[$id] = $booking_activity['activity_booking_line_id']['booking_id'];
            }
        }

        return $result;
    }

    public static function calcBookingLineGroup($self): array {
        $result = [];
        $self->read(['activity_booking_line_id' => ['booking_line_group_id']]);
        foreach($self as $id => $booking_activity) {
            if(isset($booking_activity['activity_booking_line_id'])) {
                $result[$id] = $booking_activity['activity_booking_line_id']['booking_line_group_id'];
            }
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

    public static function calcQty($self): array {
        $result = [];
        $self->read(['activity_booking_line_id' => ['qty_accounting_method', 'qty']]);
        foreach($self as $id => $booking_activity) {
            $qty = 1;
            if(($booking_activity['activity_booking_line_id']['qty_accounting_method'] ?? null) === 'unit') {
                $qty = $booking_activity['activity_booking_line_id']['qty'];
            }

            $result[$id] = $qty;
        }

        return $result;
    }

    public static function calcScheduleFrom($self): array {
        $result = [];
        $self->read(['time_slot_id' => ['schedule_from']]);
        foreach($self as $id => $booking_activity) {
            if(isset($booking_activity['time_slot_id']['schedule_from'])) {
                $result[$id] = $booking_activity['time_slot_id']['schedule_from'];
            }
        }

        return $result;
    }

    public static function calcScheduleTo($self): array {
        $result = [];
        $self->read(['time_slot_id' => ['schedule_to']]);
        foreach($self as $id => $booking_activity) {
            if(isset($booking_activity['time_slot_id']['schedule_to'])) {
                $result[$id] = $booking_activity['time_slot_id']['schedule_to'];
            }
        }

        return $result;
    }

    public static function calcGroupNum($self): array {
        $result = [];
        $self->read([
            'booking_line_group_id' => ['activity_group_num'],
            'camp_group_id'         => ['activity_group_num']
        ]);
        foreach($self as $id => $booking_activity) {
            if(isset($booking_activity['booking_line_group_id']['activity_group_num'])) {
                $result[$id] = $booking_activity['booking_line_group_id']['activity_group_num'];
            }
            elseif(isset($booking_activity['camp_group_id']['activity_group_num'])) {
                $result[$id] = $booking_activity['camp_group_id']['activity_group_num'];
            }
        }

        return $result;
    }

    public static function onupdateProductId($self) {
        $self->read(['camp_group_id', 'partner_event_id', 'product_id' => ['name']]);
        foreach($self as $id => $booking_activity) {
            if(is_null($booking_activity['camp_group_id'])) {
                continue;
            }

            PartnerEvent::search(['booking_activity_id', '=', $id])
                ->update(['name' => $booking_activity['product_id']['name']]);
        }
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
        $self->read(['activity_booking_line_id', 'camp_id']);

        $booking_activities_ids = [];
        $camp_activities_ids = [];
        foreach($self as $booking_activity) {
            if(isset($booking_activity['activity_booking_line_id'])) {
                $booking_activities_ids[] = $booking_activity['id'];
            }
            else {
                $camp_activities_ids[] = $booking_activity['id'];
            }
        }

        if(!empty($booking_activities_ids)) {
            $activities = BookingActivity::ids($booking_activities_ids)
                ->read(['booking_line_group_id'])
                ->get();

            $map_booking_line_group_ids = [];
            foreach($activities as $activity) {
                $map_booking_line_group_ids[$activity['booking_line_group_id']] = true;
            }
            $booking_line_group_ids = array_keys($map_booking_line_group_ids);

            foreach($booking_line_group_ids as $group_id) {
                $map_product_counter_total = [];
                $map_activity_counter = [];

                $group_activities = BookingActivity::search(
                    ['booking_line_group_id', '=', $group_id],
                    ['sort' => ['activity_date' => 'asc']]
                )
                    ->read(['product_model_id', 'activity_date', 'time_slot_id' => ['order']])
                    ->get(true);

                usort($group_activities, function($a, $b) {
                    $date_comp = $a['activity_date'] <=> $b['activity_date'];

                    return $date_comp !== 0 ? $date_comp : $a['time_slot_id']['order'] <=> $b['time_slot_id']['order'];
                });

                foreach($group_activities as $activity) {
                    if(!isset($map_product_counter_total[$activity['product_model_id']])) {
                        $map_product_counter_total[$activity['product_model_id']] = 0;
                    }

                    $map_product_counter_total[$activity['product_model_id']] += 1;
                    $map_activity_counter[$activity['id']] = $map_product_counter_total[$activity['product_model_id']];
                }

                foreach($group_activities as $activity) {
                    BookingActivity::id($activity['id'])
                        ->update([
                            'counter'       => $map_activity_counter[$activity['id']],
                            'counter_total' => $map_product_counter_total[$activity['product_model_id']]
                        ]);
                }
            }
        }

        if(!empty($camp_activities_ids)) {
            $activities = BookingActivity::ids($camp_activities_ids)
                ->read(['camp_id'])
                ->get();

            $map_camp_ids = [];
            foreach($activities as $activity) {
                $map_camp_ids[$activity['camp_id']] = true;
            }
            $camp_ids = array_keys($map_camp_ids);

            foreach($camp_ids as $camp_id) {
                $map_product_group_counter_total = [];
                $map_activity_counter = [];

                $camp_activities = BookingActivity::search(
                    ['camp_id', '=', $camp_id],
                    ['sort' => ['activity_date' => 'asc']]
                )
                    ->read(['product_model_id', 'activity_date', 'time_slot_id' => ['order'], 'group_num'])
                    ->get(true);

                usort($camp_activities, function($a, $b) {
                    $date_comp = $a['activity_date'] <=> $b['activity_date'];

                    return $date_comp !== 0 ? $date_comp : $a['time_slot_id']['order'] <=> $b['time_slot_id']['order'];
                });

                foreach($camp_activities as $activity) {
                    if(!isset($map_product_group_counter_total[$activity['product_model_id']])) {
                        $map_product_group_counter_total[$activity['product_model_id']] = [];
                    }
                    if(!isset($map_product_group_counter_total[$activity['product_model_id']][$activity['group_num']])) {
                        $map_product_group_counter_total[$activity['product_model_id']][$activity['group_num']] = 0;
                    }

                    $map_product_group_counter_total[$activity['product_model_id']][$activity['group_num']] += 1;
                    $map_activity_counter[$activity['id']] = $map_product_group_counter_total[$activity['product_model_id']][$activity['group_num']];
                }

                foreach($camp_activities as $activity) {
                    BookingActivity::id($activity['id'])
                        ->update([
                            'counter'       => $map_activity_counter[$activity['id']],
                            'counter_total' => $map_product_group_counter_total[$activity['product_model_id']][$activity['group_num']]
                        ]);
                }
            }
        }
    }

    public static function cancreate($orm, $values, $lang): array {
        if(!isset($values['activity_booking_line_id']) && !isset($values['booking_line_group_id']) && !isset($values['camp_group_id'])) {
            // an activity should be linked to either a line, a booking line group or a camp group
            return [
                'activity_booking_line_id'  => ['missing_line_id' => "The activity must be linked to a booking line."],
                'booking_line_group_id'     => ['missing_group_id' => "The activity must be linked to a booking group."],
                'camp_group_id'             => ['missing_group_id' => "The activity must be linked to a camp group."]
            ];
        }

        // checks if the moment of the activity is free (if activity_booking_line_id the check is done in BookingLine)
        if((isset($values['booking_line_group_id']) || isset($values['camp_group_id'])) && isset($values['activity_date'], $values['time_slot_id'])) {
            $domain = [
                ['activity_date', '=', $values['activity_date']],
                ['time_slot_id', '=', $values['time_slot_id']]
            ];
            if(isset($values['booking_line_group_id'])) {
                $domain[] = ['booking_line_group_id', '=', $values['booking_line_group_id']];
            }
            else {
                $domain[] = ['camp_group_id', '=', $values['camp_group_id']];
            }

            $activity = BookingActivity::search($domain)
                ->read(['id'])
                ->first();

            if(!is_null($activity)) {
                return [
                    'booking_line_group_id' => ['group_has_activity' => "The group already has an activity for this date and time slot."],
                    'camp_group_id'         => ['group_has_activity' => "The group already has an activity for this date and time slot."]
                ];
            }
        }

        return parent::cancreate($orm, $values, $lang);
    }

    public static function canupdate($self, $values): array {
        $self->read(['activity_booking_line_id', 'booking_line_group_id', 'camp_group_id']);
        foreach($self as $booking_activity) {
            $activity_booking_line_id = $values['activity_booking_line_id'] ?? $booking_activity['activity_booking_line_id'];
            $booking_line_group_id = $values['booking_line_group_id'] ?? $booking_activity['booking_line_group_id'];
            $camp_group_id = $values['camp_group_id'] ?? $booking_activity['camp_group_id'];

            if(!isset($activity_booking_line_id) && !isset($booking_line_group_id) && !isset($camp_group_id)) {
                return [
                    'activity_booking_line_id'  => ['invalid' => "The activity needs to be related to a booking line."],
                    'booking_line_group_id'     => ['invalid' => "The activity needs to be related to a booking group."],
                    'camp_group_id'             => ['invalid' => "The activity needs to be related to a camp group."]
                ];
            }
        }

        $common_fields = [
            'name', 'description', 'providers_ids', 'counter', 'counter_total', 'employee_id', 'activity_date',
            'time_slot_id', 'schedule_from', 'schedule_to', 'rental_unit_id', 'has_staff_required', 'has_provider',
            'group_num', 'partner_planning_mails_ids', 'product_id', 'product_model_id', 'qty', 'is_cancelled'
        ];
        $booking_line_fields = [
            'activity_booking_line_id', 'is_virtual', 'booking_lines_ids', 'booking_id', 'booking_line_group_id',
            'supplies_booking_lines_ids', 'transports_booking_lines_ids', 'total', 'price'
        ];
        $booking_fields = ['booking_id', 'booking_line_group_id'];
        $camp_fields = ['camp_id', 'camp_group_id'];

        foreach($self as $booking_activity) {
            if(isset($booking_activity['activity_booking_line_id'])) {
                // booking line checks
                $updatable_fields = array_merge($common_fields, $booking_line_fields);
                foreach(array_keys($values) as $field) {
                    if(!in_array($field, $updatable_fields)) {
                        return [$field => ['not_updatable' => "This field is not updatable for an activity related to a booking line."]];
                    }
                }
            }
            elseif(isset($booking_activity['booking_line_group_id'])) {
                // booking group checks
                $updatable_fields = array_merge($common_fields, $booking_fields);
                foreach(array_keys($values) as $field) {
                    if(!in_array($field, $updatable_fields)) {
                        return [$field => ['not_updatable' => "This field is not updatable for an activity related to a booking group."]];
                    }
                }
            }
            else {
                // camp group checks
                $updatable_fields = array_merge($common_fields, $camp_fields);
                foreach(array_keys($values) as $field) {
                    if(!in_array($field, $updatable_fields)) {
                        return [$field => ['not_updatable' => "This field is not updatable for an activity related to a camp group."]];
                    }
                }

                $camp_group_id = $values['camp_group_id'] ?? $booking_activity['camp_group_id'];
                $camp_group = CampGroup::id($camp_group_id)
                    ->read(['camp_id' => ['date_from', 'date_to']])
                    ->first();

                if(isset($values['activity_date'])) {
                    if($values['activity_date'] < $camp_group['camp_id']['date_from'] || $values['activity_date'] > $camp_group['camp_id']['date_to']) {
                        return ['activity_date' => ['outside_camp_dates' => "The activity isn't inside the camp dates."]];
                    }
                }
            }
        }

        if(!isset($values['activity_date']) && !isset($values['time_slot_id']) && !isset($values['employee_id'])) {
            return parent::canupdate($self, $values);
        }

        // Common checks
        $self->read(['activity_booking_line_id', 'booking_line_group_id', 'camp_group_id', 'activity_date', 'time_slot_id', 'employee_id', 'product_model_id', 'group_num']);
        foreach($self as $booking_activity) {
            $employee_id = array_key_exists('employee_id', $values) ? $values['employee_id'] : $booking_activity['employee_id'];

            $activity_date = $values['activity_date'] ?? $booking_activity['activity_date'];
            $time_slot_id = $values['time_slot_id'] ?? $booking_activity['time_slot_id'];

            if(!is_null($employee_id)) {
                // check employee qualification for the given activity
                $activity_filter = Setting::get_value('sale', 'features', 'employee.activity_filter', false);
                if($activity_filter) {
                    $employee = Employee::id($employee_id)
                        ->read(['activity_product_models_ids'])
                        ->first();

                    if($employee && !in_array($booking_activity['product_model_id'], $employee['activity_product_models_ids'])) {
                        return ['employee_id' => ['not_allowed' => "Employee not qualified for this type of activity."]];
                    }
                }

                // check current assignment, if any
                $activities_ids = BookingActivity::search([
                    ['activity_date', '=', $activity_date],
                    ['time_slot_id', '=', $time_slot_id],
                    ['employee_id', '=', $employee_id],
                    ['is_exclusive', '=', true]
                ])
                    ->ids();

                if(!empty($activities_ids)) {
                    return ['employee_id' => ['already_assigned' => "An exclusive activity is already assigned to this employee for that moment."]];
                }
            }

            if(isset($activity_date, $time_slot_id)) {
                // check that a group doesn't have two activities at the same time (check done in BookingLine if linked to one)
                if(
                    isset($booking_activity['camp_group_id'])
                    || (isset($booking_activity['booking_line_group_id']) && !isset($booking_activity['activity_booking_line_id']))
                ) {
                    $domain = [
                        ['activity_date', '=', $activity_date],
                        ['time_slot_id', '=', $time_slot_id],
                        ['id', '<>', $booking_activity['id']]
                    ];

                    if(isset($booking_activity['camp_group_id'])) {
                        $domain[] = ['camp_group_id', '=', $booking_activity['camp_group_id']];
                    }
                    else {
                        $domain[] = ['booking_line_group_id', '=', $booking_activity['booking_line_group_id']];
                    }

                    $activity = BookingActivity::search($domain)
                        ->read(['id'])
                        ->first();

                    if(!is_null($activity)) {
                        return [
                            'camp_group_id'         => ['group_has_activity' => "The group already has an activity for this date and time slot."],
                            'booking_line_group_id' => ['group_has_activity' => "The group already has an activity for this date and time slot."],
                        ];
                    }
                }
            }
        }

        return parent::canupdate($self, $values);
    }

    public static function ondelete($self): void {
        $self->read([
            'booking_line_group_id',
            'booking_lines_ids',
            'camp_group_id',
            'activity_booking_line_id'
        ]);
        foreach($self as $booking_activity) {
            if(!empty($booking_activity['booking_lines_ids'])) {
                $booking_lines_ids_remove = array_map(
                        function ($id) { return -$id; },
                        $booking_activity['booking_lines_ids']
                    );
                BookingLineGroup::id($booking_activity['booking_line_group_id'])
                    ->update(['booking_lines_ids' => $booking_lines_ids_remove]);
            }
            if($booking_activity['activity_booking_line_id']) {
                BookingLine::id($booking_activity['activity_booking_line_id'])->delete(true);
            }
            if($booking_activity['camp_group_id']) {
                PartnerEvent::search(['booking_activity_id', '=', $booking_activity['id']])->delete(true);
            }
        }
    }
}

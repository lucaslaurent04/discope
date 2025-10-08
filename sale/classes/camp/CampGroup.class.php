<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;
use sale\booking\BookingActivity;
use sale\booking\PartnerEvent;
use sale\booking\TimeSlot;

class CampGroup extends Model {

    public static function getDescription(): string {
        return "Group of a camp that one employee will need to manage.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "The name of the camp group.",
                'store'             => true,
                'function'          => 'calcName'
            ],

            'camp_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Camp',
                'description'       => "The camp this group is part of.",
                'required'          => true,
                'readonly'          => true,
                'onupdate'          => 'onupdateCampId',
                'ondelete'          => 'cascade'
            ],

            'employee_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'hr\employee\Employee',
                'description'       => "Employee responsible of the group during the camp.",
                'onupdate'          => 'onupdateEmployeeId'
            ],

            'max_children' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Max quantity of children that can take part to the camp.",
                'store'             => true,
                'function'          => 'calcMaxChildren'
            ],

            'activity_group_num' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Identifier of the activity group in the camp.",
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcActivityGroupNum'
            ],

            'booking_activities_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingActivity',
                'foreign_field'     => 'camp_group_id',
                'description'       => "All Booking Activities this camp group relates to.",
                'ondetach'          => 'delete'
            ],

            'partner_events_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\PartnerEvent',
                'foreign_field'     => 'camp_group_id',
                'description'       => "All Booking Activities this camp group relates to.",
                'ondetach'          => 'delete'
            ]

        ];
    }

    public static function getActions(): array {
        return [

            'refresh-partner-events' => [
                'description'   => "Refresh the partners events linked to the camp group's activities.",
                'policies'      => [],
                'function'      => 'doRefreshPartnerEvents'
            ],

            'generate-activities' => [
                'description'   => "Generates the camp's activities.",
                'policies'      => [],
                'function'      => 'doGenerateActivities'
            ],

            'cancel-activities' => [
                'description'   => "Cancels the camp group's activities.",
                'policies'      => [],
                'function'      => 'doCancelActivities'
            ],

            'refresh-activities-dates' => [
                'description'   => "Refresh activities dates to match modifications of camp date_from.",
                'policies'      => [],
                'function'      => 'doRefreshActivitiesDates'
            ]

        ];
    }

    public static function doRefreshPartnerEvents($self) {
        $self->read([
            'name',
            'employee_id',
            'booking_activities_ids'    => ['name', 'activity_date', 'time_slot_id'],
            'partner_events_ids'        => ['name', 'description', 'booking_activity_id']
        ]);
        foreach($self as $camp_group) {
            PartnerEvent::search(['camp_group_id', '=', $camp_group['id']])->delete(true);

            if(is_null($camp_group['employee_id'])) {
                continue;
            }

            foreach($camp_group['booking_activities_ids'] as $booking_activity) {
                $partner_event = null;
                foreach($camp_group['partner_events_ids'] as $part_ev) {
                    if($part_ev['booking_activity_id'] === $booking_activity['id']) {
                        $partner_event = $part_ev;
                    }
                }

                $name = $camp_group['name'];
                if(!empty($partner_event['name'])) {
                    $name = $partner_event['name'];
                }
                elseif(!empty($booking_activity['name'])) {
                    $name = $booking_activity['name'];
                }

                PartnerEvent::create([
                    'name'                  => $name,
                    'description'           => $partner_event['description'] ?? null,
                    'partner_id'            => $camp_group['employee_id'],
                    'event_date'            => $booking_activity['activity_date'],
                    'time_slot_id'          => $booking_activity['time_slot_id'],
                    'event_type'            => 'camp_activity',
                    'camp_group_id'         => $camp_group['id'],
                    'booking_activity_id'   => $booking_activity['id']
                ]);
            }
        }
    }

    public static function doGenerateActivities($self) {
        $self->read(['camp_id' => ['is_clsh', 'date_from', 'date_to']]);

        $time_slots = TimeSlot::search([])
            ->read(['id', 'code'])
            ->get();
        $map_time_slots = [];
        foreach($time_slots as $time_slot) {
            $map_time_slots[$time_slot['code']] = $time_slot;
        }

        foreach($self as $id => $camp_group) {
            $camp = $camp_group['camp_id'];
            for($date = $camp['date_from']; $date <= $camp['date_to']; $date += 86400) {
                foreach(['AM', 'PM'] as $time_slot_code) {
                    if(!$camp['is_clsh'] && $date === $camp['date_from']) {
                        continue;
                    }

                    $activities_ids = BookingActivity::search([
                        ['camp_group_id', '=', $id],
                        ['activity_date', '=', $date],
                        ['time_slot_id', '=', $map_time_slots[$time_slot_code]['id']]
                    ])
                        ->ids();

                    if(count($activities_ids) === 0) {
                        BookingActivity::create([
                            'camp_id'       => $camp['id'],
                            'camp_group_id' => $id,
                            'activity_date' => $date,
                            'time_slot_id'  => $map_time_slots[$time_slot_code]['id']
                        ]);
                    }
                }
            }
        }
    }

    public static function doCancelActivities($self) {
        $self->read([]);
        foreach($self as $id => $camp_group) {
            BookingActivity::search(['camp_group_id', '=', $id])->update(['is_cancelled' => true]);
        }
    }

    public static function doRefreshActivitiesDates($self) {
        $self->read([
            'camp_id'                   => ['date_from', 'date_to', 'is_clsh'],
            'booking_activities_ids'    => ['activity_date']
        ]);

        $time_slots = TimeSlot::search([])
            ->read(['id', 'code'])
            ->get();
        $map_time_slots = [];
        foreach($time_slots as $time_slot) {
            $map_time_slots[$time_slot['code']] = $time_slot;
        }

        foreach($self as $id => $camp_group) {
            $old_date_from = null;
            foreach($camp_group['booking_activities_ids'] as $activity) {
                if(is_null($old_date_from) || $activity['activity_date'] < $old_date_from) {
                    $old_date_from = $activity['activity_date'];
                }
            }

            if(!is_null($old_date_from)) {
                if(!$camp_group['camp_id']['is_clsh']) {
                    $old_date_from -= 86400;
                }

                $dates_diff = $camp_group['camp_id']['date_from'] - $old_date_from;

                if($dates_diff !== 0) {
                    foreach($camp_group['booking_activities_ids'] as $activity_id => $activity) {
                        $shifted_activity_date = $activity['activity_date'] + $dates_diff;

                        BookingActivity::id($activity_id)->update([
                            'activity_date' => $shifted_activity_date
                        ]);
                    }
                }
            }

            if($camp_group['camp_id']['is_clsh']) {
                // if camp clsh_type was modified from 5-days to 4-days, remove unneeded activities
                BookingActivity::search([
                    ['camp_group_id', '=', $id],
                    ['activity_date', '>', $camp_group['camp_id']['date_to']]
                ])
                    ->delete(true);

                // if camp clsh_type was modified from 4-days to 5-days, create the last day activities
                $last_day_activities_ids = BookingActivity::search([
                    ['camp_group_id', '=', $id],
                    ['activity_date', '=', $camp_group['camp_id']['date_to']]
                ])
                    ->ids();

                if(empty($last_day_activities_ids)) {
                    foreach(['AM', 'PM'] as $time_slot_code) {
                        BookingActivity::create([
                            'camp_group_id' => $id,
                            'activity_date' => $camp_group['camp_id']['date_to'],
                            'time_slot_id'  => $map_time_slots[$time_slot_code]['id']
                        ]);
                    }
                }
            }
        }
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['state', 'activity_group_num', 'camp_id' => ['short_name', 'camp_groups_ids']]);
        foreach($self as $id =>  $camp_group) {
            if($camp_group['state'] === 'draft') {
                continue;
            }
            $result[$id] = $camp_group['camp_id']['short_name'].' ('.$camp_group['activity_group_num'].')';
        }

        return $result;
    }

    public static function calcMaxChildren($self): array {
        $result = [];
        $self->read(['camp_id' => ['employee_ratio']]);
        foreach($self as $id => $camp) {
            $result[$id] = $camp['camp_id']['employee_ratio'];
        }

        return $result;
    }

    public static function calcActivityGroupNum($self): array {
        $result = [];
        $self->read(['state', 'camp_id']);
        foreach($self as $id => $camp_group) {
            if($camp_group['state'] === 'draft') {
                continue;
            }

            $groups = CampGroup::search(['camp_id', '=', $camp_group['camp_id']], ['sort' => ['created' => 'asc']])
                ->read(['created'])
                ->get();

            $num = 1;
            foreach($groups as $group) {
                if($group['id'] === $camp_group['id']) {
                    $result[$id] = $num;
                    break;
                }
                $num++;
            }
        }

        return $result;
    }

    public static function canupdate($self, $values): array {
        if(isset($values['employee_id'])) {
            $self->read(['camp_id' => ['date_from', 'date_to']]);

            foreach($self as $id => $camp_group) {
                $camps = Camp::search([
                    ['date_to', '>=', $camp_group['camp_id']['date_from']],
                    ['date_from', '<=', $camp_group['camp_id']['date_to']]
                ])
                    ->read(['id'])
                    ->get(true);

                $camps_ids = array_column($camps, 'id');

                if(!empty($camps)) {
                    $group = CampGroup::search([
                        ['camp_id', 'in', $camps_ids],
                        ['employee_id', '=', $values['employee_id']],
                        ['id', '<>', $id]
                    ])
                        ->read(['id'])
                        ->first();

                    if(!is_null($group)) {
                        return ['employee_id' => ['already_assigned' => "The employee is already assigned to another camp group for this period."]];
                    }
                }
            }
        }

        return parent::canupdate($self, $values);
    }

    public static function onupdateCampId($self) {
        $self->read([
            'camp_id' => [
                'id',
                'camp_groups_ids',
                'camp_group_qty'
            ]
        ]);

        $map_camp_camp_group_qty = [];
        foreach($self as $id => $camp_group) {
            if(!isset($map_camp_camp_group_qty[$camp_group['camp_id']['id']])) {
                $map_camp_camp_group_qty[$camp_group['camp_id']['id']] = [
                    'original'  => $camp_group['camp_id']['camp_group_qty'],
                    'new'       => count($camp_group['camp_id']['camp_groups_ids'])
                ];
            }
            if(!in_array($id, $camp_group['camp_id']['camp_groups_ids'])) {
                $map_camp_camp_group_qty[$camp_group['camp_id']['id']]['new']++;
            }
        }

        foreach($map_camp_camp_group_qty as $camp_id => ['original' => $original_qty, 'new' => $new_qty]) {
            if($new_qty !== $original_qty) {
                Camp::id($camp_id)
                    ->update(['camp_group_qty' => $new_qty]);
            }
        }

        $self->do('generate-activities');
    }

    public static function onupdateEmployeeId($self) {
        $self->do('refresh-partner-events');
    }

    public static function candelete($self): array {
        $self->read(['camp_id']);

        $map_camp_groups_ids = [];
        $map_camp_ids = [];
        foreach($self as $id => $camp_group) {
            $map_camp_groups_ids[$id] = true;
            $map_camp_ids[$camp_group['camp_id']] = true;
        }

        $camps = Camp::ids(array_keys($map_camp_ids))
            ->read([
                'camp_groups_ids'   => ['max_children'],
                'enrollments_ids'   => ['status']
            ])
            ->get();

        foreach($camps as $camp) {
            $enrolled_qty = 0;
            foreach($camp['enrollments_ids'] as $enrollment) {
                if(in_array($enrollment['status'], ['pending', 'validated'])) {
                    $enrolled_qty++;
                }
            }

            $possible_qty = 0;
            foreach($camp['camp_groups_ids'] as $camp_group) {
                if(!isset($map_camp_groups_ids[$camp_group['id']])) {
                    $possible_qty += $camp_group['max_children'];
                }
            }

            if($possible_qty < $enrolled_qty) {
                return ['camp_id' => ['too_many_children' => "There is too many children enrolled in the camp to remove the group."]];
            }
        }

        return parent::candelete($self);
    }

    public static function ondelete($self) {
        $self->read([
            'camp_id' => [
                'id',
                'camp_groups_ids',
                'camp_group_qty'
            ]
        ]);

        // update camp group quantity
        $map_camp_camp_group_qty = [];
        foreach($self as $camp_group) {
            if(!isset($map_camp_camp_group_qty[$camp_group['camp_id']['id']])) {
                $map_camp_camp_group_qty[$camp_group['camp_id']['id']] = count($camp_group['camp_id']['camp_groups_ids']);
            }
            $map_camp_camp_group_qty[$camp_group['camp_id']['id']]--;
        }

        foreach($map_camp_camp_group_qty as $camp_id => $qty) {
            Camp::id($camp_id)
                ->update(['camp_group_qty' => $qty]);
        }

        // remove the activities linked to the groups
        $camp_groups_ids = [];
        foreach($self as $id => $camp_group) {
            $camp_groups_ids[] = $id;
        }

        BookingActivity::search(['camp_group_id', 'in', $camp_groups_ids])->delete(true);
    }
}

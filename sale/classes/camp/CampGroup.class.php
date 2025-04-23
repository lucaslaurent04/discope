<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

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
                'readonly'          => true
            ],

            'max_children' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Max quantity of children that can take part to the camp.",
                'store'             => true,
                'function'          => 'calcMaxChildren'
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['state', 'camp_id' => ['name', 'camp_groups_ids']]);
        foreach($self as $id =>  $camp_group) {
            $group_num = count($camp_group['camp_id']['camp_groups_ids']);
            if($camp_group['state'] === 'draft') {
                $group_num++;
            }
            $result[$id] = $camp_group['camp_id']['name'].' ('.$group_num.')';
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

    public static function onupdate($self) {
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
                    'original'  => count($camp_group['camp_id']['camp_groups_ids']),
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
    }

    public static function ondelete($self) {
        $self->read([
            'camp_id' => [
                'id',
                'camp_groups_ids',
                'camp_group_qty'
            ]
        ]);

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
                if(in_array($enrollment['status'], ['pending', 'confirmed'])) {
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
}

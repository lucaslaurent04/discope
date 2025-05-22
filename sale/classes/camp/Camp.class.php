<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use core\setting\Setting;
use equal\orm\Model;
use sale\booking\BookingMeal;
use sale\booking\TimeSlot;

class Camp extends Model {

    public static function getDescription(): string {
        return "Activity camp.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Name of the camp with dates and ages.",
                'help'              => "Complete name of the camp to distinguish it from the others.",
                'store'             => true,
                'function'          => 'calcName'
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "The center to which the booking relates to.",
                'default'           => 1
            ],

            'short_name' => [
                'type'              => 'string',
                'description'       => "Short name of the camp.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'published',
                    'canceled'
                ],
                'description'       => "Status of the camp.",
                'default'           => 'draft'
            ],

            'remarks' => [
                'type'              => 'string',
                'description'       => "Description of the camp.",
                'usage'             => 'text/plain'
            ],

            'public_description' => [
                'type'              => 'string',
                'description'       => "Public description of the camp.",
                'usage'             => 'text/plain'
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "When the camp starts.",
                'required'          => true,
                'dependents'        => ['name', 'enrollments_ids' => ['date_from']],
                'default'           => function() {
                    return strtotime('next sunday');
                }
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "When the camp ends.",
                'required'          => true,
                'dependents'        => ['name', 'enrollments_ids' => ['date_to']],
                'default'           => function() {
                    return strtotime('next sunday +5 days');
                }
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product targeted by the line.",
                'required'          => true,
                'domain'            => ['is_camp', '=', true]
            ],

            'camp_type' => [
                'type'              => 'string',
                'selection'         => [
                    'sport',
                    'circus',
                    'culture',
                    'environment',
                    'horse-riding'
                ],
                'description'       => "Type of camp.",
                'default'           => 'sport'
            ],

            'is_clsh' => [
                'type'              => 'boolean',
                'description'       => "Is \"Centre loisir sans hébergement\".",
                'help'              => "If CLSH, the enrollments are per day.",
                'default'           => false
            ],

            'clsh_type' => [
                'type'              => 'string',
                'selection'         => [
                    '5-days',
                    '4-days'
                ],
                'description'       => "Is it a camp of 5 or 4 days duration.",
                'default'           => '5-days',
                'visible'           => ['is_clsh', '=', true]
            ],

            'day_product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product targeted by the line.",
                'domain'            => ['is_camp', '=', true],
                'visible'           => ['is_clsh', '=', true]
            ],

            'camp_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\CampModel',
                'description'       => "Model that was used as a base to create this camp.",
                'onupdate'          => 'onupdateCampModelId',
                'required'          => true
            ],

            'min_age' => [
                'type'              => 'integer',
                'description'       => "Minimal age of the participants.",
                'default'           => 10,
                'dependents'        => ['name']
            ],

            'max_age' => [
                'type'              => 'integer',
                'description'       => "Maximal age of the participants.",
                'default'           => 12,
                'dependents'        => ['name']
            ],

            'employee_ratio' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'usage'             => 'number/integer{1,50}',
                'description'       => "The quantity of children one employee can handle alone.",
                'store'             => true,
                'function'          => 'calcDefaultEmployeeRatio',
                'dependents'        => ['max_children'],
                'onupdate'          => 'onupdateEmployeeRatio'
            ],

            'max_children' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Max quantity of children that can take part to the camp.",
                'store'             => true,
                'function'          => 'calcMaxChildren'
            ],

            'enrollments_qty' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Quantity of enrollments that aren't canceled or waitlisted.",
                'store'             => true,
                'function'          => 'calcEnrollmentsQty'
            ],

            'camp_group_qty' => [
                'type'              => 'integer',
                'description'       => "The quantity of camp groups.",
                'default'           => 1,
                'dependents'        => ['max_children']
            ],

            'ase_quota' => [
                'type'              => 'integer',
                'description'       => "Max quantity of children ASE per group (Aide sociale à l'enfance).",
                'default'           => 4
            ],

            'accounting_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Specific accounting code for the camp.",
                'unique'            => true,
                'store'             => true,
                'function'          => 'calcAccountingCode'
            ],

            'need_license_ffe' => [
                'type'              => 'boolean',
                'description'       => "Does the camp requires to child to have a 'licence fédération française équitation'.",
                'default'           => false
            ],

            'camp_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\CampGroup',
                'foreign_field'     => 'camp_id',
                'description'       => "The groups of children of the camp.",
                'ondetach'          => 'delete'
            ],

            'required_skills_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Skill',
                'foreign_field'     => 'camps_ids',
                'rel_table'         => 'sale_camp_rel_camp_skill',
                'rel_foreign_key'   => 'skill_id',
                'rel_local_key'     => 'camp_id',
                'description'       => "Skills needed to participate to the camp."
            ],

            'required_documents_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\camp\Document',
                'foreign_field'     => 'camps_ids',
                'rel_table'         => 'sale_camp_rel_camp_document',
                'rel_foreign_key'   => 'document_id',
                'rel_local_key'     => 'camp_id',
                'description'       => "Documents needed to participate to the camp."
            ],

            'enrollments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Enrollment',
                'foreign_field'     => 'camp_id',
                'description'       => "All the enrollments linked to camp.",
                'ondetach'          => 'delete'
            ],

            'booking_activities_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingActivity',
                'foreign_field'     => 'camp_id',
                'description'       => "All Booking Activities this camp relates to."
            ],

            'presences_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Presence',
                'foreign_field'     => 'camp_id',
                'description'       => "The children's days of presences for this camp."
            ],

            'booking_meals_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingMeal',
                'foreign_field'     => 'camp_id',
                'description'       => "The children's meals for this camp.",
                'ondetach'          => 'delete'
            ]

        ];
    }

    public static function getActions(): array {
        return [

            'generate-meals' => [
                'description'   => "Generates the camp's meals.",
                'policies'      => [],
                'function'      => 'doGenerateMeals'
            ],

            'remove-meals' => [
                'description'   => "Removes the camp's meals.",
                'policies'      => [],
                'function'      => 'doRemoveMeals'
            ]

        ];
    }

    public static function doGenerateMeals($self) {
        $self->read(['is_clsh', 'date_from', 'date_to']);

        $time_slots = TimeSlot::search([])
            ->read(['id', 'code'])
            ->get();
        $map_time_slots = [];
        foreach($time_slots as $time_slot) {
            $map_time_slots[$time_slot['code']] = $time_slot;
        }

        foreach($self as $id => $camp) {
            for($date = $camp['date_from']; $date <= $camp['date_to']; $date += 86400) {
                foreach(['B', 'L', 'D'] as $time_slot_code) {
                    if(
                        ($camp['is_clsh'] && in_array($time_slot_code, ['B', 'D']))
                        || (!$camp['is_clsh'] && $date === $camp['date_from'] && in_array($time_slot_code, ['B', 'L']))
                    ) {
                        continue;
                    }

                    $meals_ids = BookingMeal::search([
                        ['camp_id', '=', $id],
                        ['date', '=', $date],
                        ['time_slot_id', '=', $map_time_slots[$time_slot_code]['id']]
                    ])
                        ->ids();

                    if(count($meals_ids) === 0) {
                        BookingMeal::create([
                            'camp_id'       => $id,
                            'date'          => $date,
                            'time_slot_id'  => $map_time_slots[$time_slot_code]['id']
                        ]);
                    }
                }
            }
        }
    }

    public static function doRemoveMeals($self) {
        $self->read(['camp_id', 'child_id']);
        foreach($self as $id => $camp) {
            BookingMeal::search(['camp_id', '=', $id])->delete(true);
        }
    }

    public static function onafterPublish($self) {
        $self->do('generate-meals');
    }

    public static function onafterCancel($self) {
        $self->do('remove-meals');
    }

    public static function getWorkflow(): array {
        return [

            'draft' => [
                'description' => "The camp is still being configured.",
                'transitions' => [
                    'publish' => [
                        'status'        => 'published',
                        'description'   => "Publish the camp on the website.",
                        'onafter'       => 'onafterPublish'
                    ],
                    'cancel' => [
                        'status'        => 'canceled',
                        'description'   => "Cancel the camp.",
                        'onafter'       => 'onafterCancel'
                    ]
                ]
            ],

            'published' => [
                'description' => "The camp is configured and published on the website.",
                'transitions' => [
                    'cancel' => [
                        'status'        => 'canceled',
                        'description'   => "Cancel the camp.",
                        'onafter'       => 'onafterCancel'
                    ]
                ]
            ],

            'canceled' => [
                'description' => "The camp was canceled.",
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['short_name', 'date_from', 'date_to', 'min_age', 'max_age']);

        $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

        foreach($self as $id => $camp) {
            if(empty($camp['short_name'])) {
                continue;
            }

            $result[$id] = sprintf(
                '%s | %s -> %s (%d - %d)',
                $camp['short_name'],
                date($date_format, $camp['date_from']),
                date($date_format, $camp['date_to']),
                $camp['min_age'],
                $camp['max_age']
            );
        }

        return $result;
    }

    public static function calcDefaultEmployeeRatio($self): array {
        $result = [];
        $self->read(['camp_model_id' => ['default_employee_ratio']]);
        foreach($self as $id => $camp) {
            $result[$id] = $camp['camp_model_id']['default_employee_ratio'] ?? 12;
        }

        return $result;
    }

    public static function calcMaxChildren($self): array {
        $result = [];
        $self->read(['employee_ratio', 'camp_group_qty']);
        foreach($self as $id => $camp) {
            $result[$id] = $camp['employee_ratio'] * $camp['camp_group_qty'];
        }

        return $result;
    }

    public static function calcEnrollmentsQty($self): array {
        $result = [];
        $self->read(['enrollments_ids' => ['status']]);
        foreach($self as $id => $camp) {
            $enrollment_qty = 0;
            foreach($camp['enrollments_ids'] as $enrollment) {
                if(!in_array($enrollment['status'], ['canceled', 'waitlisted'])) {
                    $enrollment_qty++;
                }
            }
            $result[$id] = $enrollment_qty;
        }

        return $result;
    }

    public static function calcAccountingCode($self): array {
        $result = [];
        $last_accounting_code = self::search([], ['sort' => ['created' => 'desc']])
            ->read(['accounting_code'])
            ->first();

        $code = 0;
        if(!is_null($last_accounting_code)) {
            $code_array = explode('C', $last_accounting_code['accounting_code']);
            if(isset($code_array[1])) {
                $code = (int) $code_array[1];
            }
        }

        foreach($self as $id => $camp) {
            $result[$id] = '411C'.str_pad(++$code, 4, '0', STR_PAD_LEFT);
        }

        return $result;
    }

    public static function onchange($event, $values) {
        $result = [];
        if(isset($event['camp_model_id'])) {
            $camp_model = CampModel::id($event['camp_model_id'])
                ->read([
                    'name',
                    'camp_type',
                    'is_clsh',
                    'clsh_type',
                    'employee_ratio',
                    'need_license_ffe',
                    'ase_quota',
                    'product_id'        => ['id', 'name'],
                    'day_product_id'    => ['id', 'name']
                ])
                ->first(true);

            if(!is_null($camp_model)) {
                $result['camp_type'] = $camp_model['camp_type'];
                $result['employee_ratio'] = $camp_model['employee_ratio'];
                $result['product_id'] = $camp_model['product_id'];
                $result['need_license_ffe'] = $camp_model['need_license_ffe'];
                $result['ase_quota'] = $camp_model['ase_quota'];
                $result['is_clsh'] = $camp_model['is_clsh'];

                if($camp_model['is_clsh']) {
                    $result['clsh_type'] = $camp_model['clsh_type'];
                    $result['day_product_id'] = $camp_model['day_product_id'];
                }
                else {
                    $result['day_product_id'] = null;
                }

                if(empty($values['short_name'])) {
                    $result['short_name'] = $camp_model['name'];
                }
            }
        }
        if(isset($event['date_from'])) {
            if($values['is_clsh']) {
                $camp_duration = $values['clsh_type'] === '5-days' ? 4 : 3;
                $date_from = date('Y-m-d', $event['date_from']);
                $result['date_to'] = strtotime($date_from.' +'.$camp_duration.' days');
            }
            else {
                $date_from = date('Y-m-d', $event['date_from']);
                $result['date_to'] = strtotime($date_from.' +5 days');
            }
        }
        if(isset($event['is_clsh'])) {
            if($event['is_clsh']) {
                $camp_duration = $values['clsh_type'] === '5-days' ? 4 : 3;
                $date_from = date('Y-m-d', $values['date_from']);
                $result['date_to'] = strtotime($date_from.' +'.$camp_duration.' days');
            }
            else {
                $date_from = date('Y-m-d', $values['date_from']);
                $result['date_to'] = strtotime($date_from.' +5 days');
            }
        }
        if(isset($event['clsh_type'])) {
            $camp_duration = $event['clsh_type'] === '5-days' ? 4 : 3;
            $date_from = date('Y-m-d', $values['date_from']);
            $result['date_to'] = strtotime($date_from.' +'.$camp_duration.' days');
        }

        return $result;
    }

    /**
     * Sync camp model required skills and documents with the camp.
     */
    public static function onupdateCampModelId($self) {
        $self->read(['camp_model_id' => ['required_skills_ids', 'required_documents_ids']]);
        foreach($self as $id => $camp) {
            if(!is_null($camp['camp_model_id'])) {
                self::id($id)->update([
                    'required_skills_ids'       => $camp['camp_model_id']['required_skills_ids'],
                    'required_documents_ids'    => $camp['camp_model_id']['required_documents_ids']
                ]);
            }
        }
    }

    public static function onupdateEmployeeRatio($self) {
        $self->read(['camp_groups_ids']);
        foreach($self as $camp) {
            CampGroup::ids($camp['camp_groups_ids'])
                ->update(['max_children' => null]);
        }
    }

    /**
     * Creates the first camp group that is necessary.
     */
    public static function onupdate($self, $values) {
        $self->read(['camp_groups_ids']);
        foreach($self as $id => $camp) {
            if(count($camp['camp_groups_ids']) > 0) {
                continue;
            }

            CampGroup::create([
                'camp_id'      => $id,
                'max_children' => $camp['employee_ratio']
            ]);
        }
    }

    public static function canupdate($self, $values): array {
        $self->read([
            'is_clsh',
            'clsh_type',
            'day_product_id',
            'date_from',
            'date_to',
            'camp_groups_ids',
            'camp_group_qty',
            'enrollments_ids' => ['status']
        ]);

        foreach($self as $camp) {
            $is_clsh = $values['is_clsh'] ?? $camp['is_clsh'];
            $day_product_id = $values['day_product_id'] ?? $camp['day_product_id'];
            if($is_clsh && is_null($day_product_id)) {
                return ['day_product_id' => ['required' => "Day product required if CLSH camp."]];
            }
        }

        // Checks that modification of camp groups still allows enough enrollments
        if(isset($values['camp_groups_ids'])) {
            foreach($self as $camp) {
                $enrolled_children_qty = 0;
                foreach($camp['enrollments_ids'] as $enrollment) {
                    if(in_array($enrollment['status'], ['pending', 'confirmed'])) {
                        $enrolled_children_qty++;
                    }
                }

                $values_camp_groups_ids = array_map(
                    function($id) {
                        return (int) $id;
                    },
                    $values['camp_groups_ids']
                );

                $to_add_camp_groups_ids = [];
                $to_remove_camp_groups_ids = [];
                foreach($values_camp_groups_ids as $id) {
                    if($id > 0) {
                        $to_add_camp_groups_ids[] = $id;
                    }
                    else {
                        $to_remove_camp_groups_ids[] = $id * -1;
                    }
                }

                $final_camp_group_ids = [];
                foreach(array_merge($camp['camp_groups_ids'], $to_add_camp_groups_ids) as $id) {
                    if(!in_array($id, $to_remove_camp_groups_ids)) {
                        $final_camp_group_ids[] = $id;
                    }
                }
                $final_camp_group_ids = array_unique($final_camp_group_ids);
                if(empty($final_camp_group_ids)) {
                    return ['camp_groups_ids' => ['one_needed' => "A camp must have at least one camp group."]];
                }

                if($enrolled_children_qty === 0) {
                    continue;
                }

                $groups = CampGroup::ids($final_camp_group_ids)
                    ->read(['max_children'])
                    ->get();

                $max_children = 0;
                foreach($groups as $group) {
                    $max_children += $group['max_children'];
                }

                if($enrolled_children_qty > $max_children) {
                    return ['camp_groups_ids' => ['too_many_children' => "There is too many children enrolled in the camp groups."]];
                }
            }
        }

        // Checks that modification of employee's ratio still allows enough enrollments
        if(isset($values['employee_ratio'])) {
            foreach($self as $camp) {
                $enrolled_children_qty = 0;
                foreach($camp['enrollments_ids'] as $enrollment) {
                    if(in_array($enrollment['status'], ['pending', 'confirmed'])) {
                        $enrolled_children_qty++;
                    }
                }

                if($enrolled_children_qty > ($camp['camp_group_qty'] * $values['employee_ratio'])) {
                    return ['employee_ratio' => ['too_many_children' => "There is too many children enrolled in the camp."]];
                }
            }
        }

        // Checks the camp duration validity if CLSH camp
        if(isset($values['is_clsh']) || isset($values['clsh_type']) || isset($values['date_from']) || isset($values['date_to'])) {
            foreach($self as $camp) {
                $is_clsh = $values['is_clsh'] ?? $camp['is_clsh'];
                if(!$is_clsh) {
                    continue;
                }

                $date_from = $values['date_from'] ?? $camp['date_from'];
                $date_to = $values['date_to'] ?? $camp['date_to'];

                $day_diff = (($date_to - $date_from) / 86400) + 1;
                if(!in_array($day_diff, [4, 5])) {
                    return ['date_to' => ['wrong_duration' => "A CLSH camp must have a duration of 4 or 5 days."]];
                }

                $clsh_type = $values['clsh_type'] ?? $camp['clsh_type'];
                if($day_diff === 4 && $clsh_type === '5-days') {
                    return ['date_to' => ['not_long_enough' => "A 5 days CLSH camp must have a duration of 5 days."]];
                }
                if($day_diff === 5 && $clsh_type === '4-days') {
                    return ['date_to' => ['too_long' => "A 4 days CLSH camp must have a duration of 4 days."]];
                }
            }
        }

        return parent::canupdate($self, $values);
    }
}

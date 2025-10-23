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
use sale\booking\BookingActivity;
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
                'description'       => "The center to which the camp relates to.",
                'default'           => 1
            ],

            'center_office_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => "Office the camp relates to (for center management).",
                'store'             => true,
                'relation'          => ['center_id' => 'center_office_id']
            ],

            'short_name' => [
                'type'              => 'string',
                'description'       => "Short name of the camp.",
                'required'          => true,
                'dependents'        => ['name']
            ],

            /*
            'sojourn_number' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Sojourn number to distinguish camps.",
                'help'              => "Is handle by the setting sequence 'sale.organization.camp.sequence{center_id.center_office_id.code}'.",
                'store'             => true,
                'instant'           => true,
                'function'          => 'calcSojournNumber',
                'dependents'        => ['sojourn_code']
            ],
            */

            'sojourn_number' => [
                'type'              => 'string',
                'description'       => "Sojourn number to distinguish camps.",
                'help'              => "Is handle by the setting sequence 'sale.organization.camp.sequence{center_id.center_office_id.code}'.",
                'required'          => true,
                // #memo - should be unique per center and year
                // #todo - use a policy to ensure uniqueness
                // 'unique'            => true,
                'dependents'        => ['sojourn_code']
            ],

            'sojourn_code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Sojourn number padded to create a recognisable camp sojourn code.",
                'store'             => true,
                'function'          => 'calcCampCode'
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'draft',
                    'published',
                    'cancelled'
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
                },
                'onupdate'          => 'onupdateDateFrom'
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "When the camp ends.",
                'required'          => true,
                'dependents'        => ['name', 'enrollments_ids' => ['date_to']],
                'default'           => function() {
                    return strtotime('next sunday +5 days');
                },
                'onupdate'          => 'onupdateDateTo'
            ],

            'camp_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\CampModel',
                'description'       => "Model that was used as a base to create this camp.",
                'onupdate'          => 'onupdateCampModelId',
                'required'          => true,
                'dependents'        => [
                    'need_license_ffe',
                    'product_id',
                    'day_product_id',
                    'weekend_product_id',
                    'saturday_morning_product_id'
                ]
            ],

            'product_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product that will be added to the enrollment lines for a non CLSH camp.",
                'domain'            => ['is_camp', '=', true],
                'visible'           => ['is_clsh', '=', false],
                'store'             => true,
                'relation'          => ['camp_model_id' => 'product_id']
            ],

            'camp_type' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    'sport',
                    // 'circus',
                    'culture',
                    'environment',
                    'horse-riding',
                    'recreation',
                    'other'
                ],
                'description'       => "Type of camp.",
                'store'             => true,
                'relation'          => ['camp_model_id' => 'camp_type']
            ],

            'is_clsh' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Is \"Centre loisir sans hébergement\".",
                'help'              => "If CLSH, the enrollments are per day.",
                'store'             => true,
                'relation'          => ['camp_model_id' => 'is_clsh']
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
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product that will be added to the enrollment lines if the child enroll for specific days of the CLSH camp.",
                'domain'            => ['is_camp', '=', true],
                'visible'           => ['is_clsh', '=', true],
                'store'             => true,
                'relation'          => ['camp_model_id' => 'day_product_id']
            ],

            'weekend_product_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product that will be added to the enrollment lines if the child stays the weekend after the camp.",
                'domain'            => ['is_camp', '=', true],
                'visible'           => ['is_clsh', '=', false],
                'store'             => true,
                'relation'          => ['camp_model_id' => 'weekend_product_id']
            ],

            'saturday_morning_product_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\camp\catalog\Product',
                'description'       => "The product that will be added to the enrollment lines if the child stays the until Saturday morning after the camp.",
                'domain'            => ['is_camp', '=', true],
                'visible'           => ['is_clsh', '=', false],
                'store'             => true,
                'relation'          => ['camp_model_id' => 'saturday_morning_product_id']
            ],

            'age_range' => [
                'type'              => 'string',
                'description'       => "Age range of the accepted participants.",
                'selection'         => [
                    '6-to-9',
                    '10-to-12',
                    '13-to-16'
                ],
                'default'           => '10-to-12',
                'dependents'        => ['min_age', 'max_age']
            ],

            'min_age' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Minimal age of the participants.",
                'store'             => true,
                'function'          => 'calcMinAge',
                'dependents'        => ['name']
            ],

            'max_age' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Maximal age of the participants.",
                'store'             => true,
                'function'          => 'calcMaxAge',
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
                'description'       => "Quantity of enrollments that aren't cancelled or waitlisted.",
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
                'type'              => 'string',
                'description'       => "Specific accounting code for the camp.",
                'default'           => '411C0'
            ],

            'need_license_ffe' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Does the camp requires to child to have a 'licence fédération française équitation'.",
                'store'             => true,
                'relation'          => ['camp_model_id' => 'need_license_ffe']
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
                'description'       => "All Booking Activities this camp relates to.",
                'ondetach'          => 'delete'
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
            ],

            'generate-activities' => [
                'description'   => "Generates the camp's groups activities.",
                'policies'      => [],
                'function'      => 'doGenerateActivities'
            ],

            'cancel-activities' => [
                'description'   => "Cancels the camp's groups activities.",
                'policies'      => [],
                'function'      => 'doCancelActivities'
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
                            'time_slot_id'  => $map_time_slots[$time_slot_code]['id'],
                            // #Lathus - camps are always at 'CPIE'
                            'meal_place_id' => 2
                        ]);
                    }
                }
            }

            if(!$camp['is_clsh']) {
                // create meals for the weekend if the camp isn't CLSH, in case some children stay
                $dates = [$date, $date + 86400];
                foreach($dates as $d) {
                    foreach(['B', 'L', 'D'] as $time_slot_code) {
                        $meals_ids = BookingMeal::search([
                                ['camp_id', '=', $id],
                                ['date', '=', $d],
                                ['time_slot_id', '=', $map_time_slots[$time_slot_code]['id']]
                            ])
                            ->ids();

                        if(count($meals_ids) === 0) {
                            BookingMeal::create([
                                'camp_id'       => $id,
                                'date'          => $d,
                                'time_slot_id'  => $map_time_slots[$time_slot_code]['id'],
                                // #Lathus - camps are always at 'CPIE'
                                'meal_place_id' => 2
                            ]);
                        }
                    }
                }
            }
        }
    }

    public static function doRemoveMeals($self) {
        $self->read([]);
        foreach($self as $id => $camp) {
            BookingMeal::search(['camp_id', '=', $id])->delete(true);
        }
    }

    public static function doGenerateActivities($self) {
        $self->read(['camp_groups_ids']);
        foreach($self as $id => $camp) {
            CampGroup::search(['id', 'in', $camp['camp_groups_ids']])->do('generate-activities');
        }
    }

    public static function doCancelActivities($self) {
        $self->read(['camp_groups_ids']);
        foreach($self as $id => $camp) {
            CampGroup::search(['id', 'in', $camp['camp_groups_ids']])->do('cancel-activities');
        }
    }

    public static function policyPublish($self): array {
        $result = [];
        $self->read(['camp_groups_ids']);
        foreach($self as $camp) {
            if(count($camp['camp_groups_ids']) < 1) {
                return ['camp_groups_ids' => ['missing_group' => "The camp needs at least a group to be published."]];
            }
        }

        return $result;
    }

    public static function getPolicies(): array {
        return [

            'publish' => [
                'description'   => "Checks if the camp can be published.",
                'function'      => "policyPublish"
            ]

        ];
    }

    public static function onafterPublish($self) {
        $self->do('generate-meals');
    }

    public static function onafterCancel($self) {
        $self->do('remove-meals');
        $self->do('cancel-activities');

        $enrollments_ids = [];
        $self->read(['enrollments_ids']);
        foreach($self as $camp) {
            $enrollments_ids = array_merge($enrollments_ids, $camp['enrollments_ids']);
        }
        Enrollment::ids($enrollments_ids)->do('remove_presences');
    }

    public static function getWorkflow(): array {
        return [

            'draft' => [
                'description' => "The camp is still being configured.",
                'transitions' => [
                    'publish' => [
                        'status'        => 'published',
                        'policies'      => ['publish'],
                        'description'   => "Publish the camp on the website.",
                        'onafter'       => 'onafterPublish'
                    ],
                    'cancel' => [
                        'status'        => 'cancelled',
                        'description'   => "Cancel the camp.",
                        'onafter'       => 'onafterCancel'
                    ]
                ]
            ],

            'published' => [
                'description' => "The camp is configured and published on the website.",
                'transitions' => [
                    'cancel' => [
                        'status'        => 'cancelled',
                        'description'   => "Cancel the camp.",
                        'onafter'       => 'onafterCancel'
                    ]
                ]
            ],

            'cancelled' => [
                'description' => "The camp was cancelled.",
            ]

        ];
    }

    public static function calcName($self): array {
        $result = [];
        $self->read(['sojourn_number', 'short_name', 'date_from', 'date_to', 'min_age', 'max_age']);

        $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

        foreach($self as $id => $camp) {
            if(empty($camp['sojourn_number'])) {
                continue;
            }

            $result[$id] = sprintf(
                '%s - %s | %s -> %s (%d - %d)',
                $camp['sojourn_number'],
                $camp['short_name'] ?? '',
                date($date_format, $camp['date_from']),
                date($date_format, $camp['date_to']),
                $camp['min_age'],
                $camp['max_age']
            );
        }

        return $result;
    }

    public static function calcSojournNumber($self): array {
        $result = [];
        $self->read(['center_id' => ['center_office_id' => ['code']]]);

        foreach($self as $id => $camp) {
            $sequence_name = 'camp.sequence'.$camp['center_id']['center_office_id']['code'];
            Setting::assert_sequence('sale', 'organization', $sequence_name);

            $sequence = Setting::fetch_and_add('sale', 'organization', $sequence_name);

            $result[$id] = $sequence;
        }

        return $result;
    }

    public static function calcCampCode($self): array {
        $result = [];
        $self->read(['sojourn_number']);
        foreach($self as $id => $camp) {
            $result[$id] = str_pad($camp['sojourn_number'], 5, '0', STR_PAD_LEFT);
        }

        return $result;
    }

    public static function calcMinAge($self): array {
        $result = [];
        $self->read(['age_range']);
        foreach($self as $id => $camp) {
            switch($camp['age_range']) {
                case '6-to-9':
                    $result[$id] = 6;
                    break;
                case '10-to-12':
                    $result[$id] = 10;
                    break;
                case '13-to-16':
                    $result[$id] = 13;
                    break;
            }
        }

        return $result;
    }

    public static function calcMaxAge($self): array {
        $result = [];
        $self->read(['age_range']);
        foreach($self as $id => $camp) {
            switch($camp['age_range']) {
                case '6-to-9':
                    $result[$id] = 9;
                    break;
                case '10-to-12':
                    $result[$id] = 12;
                    break;
                case '13-to-16':
                    $result[$id] = 16;
                    break;
            }
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
                if(in_array($enrollment['status'], ['pending', 'confirmed', 'validated'])) {
                    $enrollment_qty++;
                }
            }
            $result[$id] = $enrollment_qty;
        }

        return $result;
    }

    public static function onchange($event, $values): array {
        $result = [];
        if(isset($event['camp_model_id'])) {
            $camp_model = CampModel::id($event['camp_model_id'])
                ->read(['name', 'employee_ratio', 'ase_quota', 'is_clsh'])
                ->first(true);

            if(!is_null($camp_model)) {
                $result['employee_ratio'] = $camp_model['employee_ratio'];
                $result['ase_quota'] = $camp_model['ase_quota'];

                if($camp_model['is_clsh']) {
                    $result['is_clsh'] = $camp_model['is_clsh'];
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

    public static function onupdateDateFrom($self) {
        $self->read(['camp_groups_ids']);
        foreach($self as $camp) {
            CampGroup::search(['id', 'in', $camp['camp_groups_ids']])
                ->do('refresh-activities-dates')
                ->do('refresh-partner-events');
        }
    }

    public static function onupdateDateTo($self) {
        $self->read(['camp_groups_ids']);
        foreach($self as $camp) {
            CampGroup::search(['id', 'in', $camp['camp_groups_ids']])
                ->do('refresh-activities-dates')
                ->do('refresh-partner-events');
        }
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
    public static function onafterupdate($self, $values) {
        $self->read(['camp_groups_ids']);
        foreach($self as $id => $camp) {
            if(count($camp['camp_groups_ids']) > 0) {
                continue;
            }

            CampGroup::create(['camp_id' => $id]);
        }
    }

    public static function canupdate($self, $values): array {
        $self->read([
            'status',
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
            if($camp['status'] !== 'published') {
                continue;
            }

            $allowed_fields = [
                'status', 'remarks', 'public_description', 'employee_ratio', 'max_children',
                'enrollments_qty', 'camp_group_qty', 'ase_quota', 'camp_groups_ids',
                'enrollments_ids', 'booking_activities_ids', 'presences_ids', 'booking_meals_ids',
                'required_skills_ids'
            ];
            if(count(array_diff(array_keys($values), $allowed_fields)) > 0) {
                return ['status' => ['non_editable' => "This field can't be modified on a published camp."]];
            }
        }

        // Checks that modification of camp groups still allows enough enrollments
        if(isset($values['camp_groups_ids'])) {
            foreach($self as $camp) {
                $enrolled_children_qty = 0;
                foreach($camp['enrollments_ids'] as $enrollment) {
                    if(in_array($enrollment['status'], ['pending', 'validated'])) {
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
                    if(in_array($enrollment['status'], ['pending', 'validated'])) {
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
                $date_from = $values['date_from'] ?? $camp['date_from'];
                $date_to = $values['date_to'] ?? $camp['date_to'];

                if($is_clsh) {
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
                else {
                    if(date('w', $date_from) != 0) {
                        return ['date_from' => ['must_start_sunday' => "A camp that isn't CLSH must start Sunday."]];
                    }
                    if(date('w', $date_to) != 5) {
                        return ['date_to' => ['must_end_friday' => "A camp that isn't CLSH must end Friday."]];
                    }
                }
            }
        }

        return parent::canupdate($self, $values);
    }
}

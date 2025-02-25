<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace realestate;

use equal\orm\Model;

class RentalUnit extends Model {

    public static function getDescription() {
        return "A rental unit is a resource that can be rented to a customer.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => "Name of the rental unit.",
                'required'          => true,
                'generation'        => 'generateName'
            ],

            'order' => [
                'type'              => 'integer',
                'description'       => 'Arbitrary value for ordering the rental units.',
                'default'           => 1
            ],

            'code' => [
                'type'              => 'string',
                'description'       => 'Short code for identification.',
                'generation'        => 'generateCode'
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short description of the unit.'
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => [
                    'building',
                    'bedroom',
                    'bed',
                    'meetingroom',
                    'diningroom',
                    'room',
                    'FFE'               // Furniture, Fixtures, and Equipment
                ],
                'description'       => 'Type of rental unit (that relates to capacity).',
                'required'          => true
            ],

            'category' => [
                'type'              => 'string',
                'selection'         => ['hostel', 'lodge'],         // hostel is GA, lodge is GG
                'description'       => 'Type of rental unit (that usually comes with extra accommodations, ie meals; or rented as is).',
                'default'           => 'hostel'
            ],

            'is_accomodation' => [
                'type'              => 'boolean',
                'description'       => 'The rental unit is an accommodation (having at least one bed).',
                'default'           => true
            ],

            'capacity' => [
                'type'              => 'integer',
                'description'       => 'The number of persons that may stay in the unit.',
                'default'           => 1
            ],

            'has_children' => [
                'type'              => 'boolean',
                'description'       => 'Flag to mark the unit as having sub-units.',
                'default'           => false
            ],

            'has_parent' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'function'          => 'calcHasParent',
                'description'       => 'Flag to mark the unit as having sub-units.',
                'store'             => true
            ],

            'can_rent' => [
                'type'              => 'boolean',
                'description'       => 'Flag to mark the unit as (temporarily) unavailable for renting.',
                'default'           => true
            ],

            'can_partial_rent' => [
                'type'              => 'boolean',
                'description'       => 'Flag to mark the unit as rentable partially (when children units).',
                'visible'           => [ 'has_children', '=', true ],
                'default'           => false
            ],

            'children_ids' => [
                'type'              => 'one2many',
                'description'       => "The list of rental units the current unit can be divided into, if any (i.e. a dorm might be rent as individual beds).",
                'foreign_object'    => 'realestate\RentalUnit',
                'foreign_field'     => 'parent_id',
                'domain'            => ['center_id', '=', 'object.center_id']
            ],

            'parent_id' => [
                'type'              => 'many2one',
                'description'       => "Rental Unit which current unit belongs to, if any.",
                'foreign_object'    => 'realestate\RentalUnit',
                'onupdate'          => 'onupdateParentId',
                'domain'            => ['center_id', '=', 'object.center_id']
            ],

            'repairs_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Repair',
                'foreign_field'     => 'rental_unit_id',
                'description'       => "The repairs the rental unit is assigned to."
            ],

            // Status relates to current status (NOW) of a rental unit. For availability, refer to related Consumptions
            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'ready',               // unit is available for customers
                    'empty',               // unit is no longer occupied but might require action(s)
                    'busy_full',           // unit is fully occupied
                    'busy_part',           // unit is partially occupied
                    'ooo'                  // unit is out-of-order
                ],
                'description'       => 'Status of the rental unit.',
                'default'           => 'ready',
                // cannot be set manually
                'readonly'          => true
            ],

            'action_required' => [
                'type'              => 'string',
                'selection'         => [
                    'none',                 // unit does not require any action
                    'cleanup_daily',        // unit requires a daily cleanup
                    'cleanup_full',         // unit requires a full cleanup
                    'repair'                // unit requires repair or maintenance
                ],
                'description'       => 'Action required for the rental unit.',
                'default'           => 'none'
            ],

            'consumptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Consumption',
                'foreign_field'     => 'rental_unit_id',
                'description'       => "The consumptions that relate to the rental unit."
            ],

            'composition_items_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\CompositionItem',
                'foreign_field'     => 'rental_unit_id',
                'description'       => "The composition items that relate to the rental unit."
            ],

            'rental_unit_category_id' => [
                'type'              => 'many2one',
                'description'       => "Category which current unit belongs to, if any.",
                'foreign_object'    => 'realestate\RentalUnitCategory'
            ],

            'repairings_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\booking\Repairing',
                'foreign_field'     => 'rental_units_ids',
                'rel_table'         => 'sale_rel_repairing_rentalunit',
                'rel_foreign_key'   => 'repairing_id',
                'rel_local_key'     => 'rental_unit_id',
                'description'       => 'List of scheduled repairing assigned to the rental units.'
            ],

            /*
            // center categories are just a hint at the center level, but are not applicable on rental units (rental units can be either GA or GG)
            'center_category_id' => [
                'type'              => 'many2one',
                'description'       => "Center category which current unit belongs to, if any.",
                'foreign_object'    => 'identity\CenterCategory'
            ],
            */

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => 'The center to which belongs the rental unit.'
            ],

            'sojourn_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\SojournType',
                'description'       => 'Default sojourn type of the rental unit.',
                'default'           => 'defaultFromSetting',
                'setting_default'   => 1,
                'visible'           => ['is_accomodation', '=', true]
            ],

            'color' => [
                'type'              => 'string',
                'usage'             => 'color',
                // #todo - will no longer be necessary when usage 'color' will be supported
                'selection' => [
                    'lavender',
                    'antiquewhite',
                    'moccasin',
                    'lightpink',
                    'lightgreen',
                    'paleturquoise'
                ],
                'description'       => 'Arbitrary color to use for the rental unit when rendering the calendar.'
            ],

            'room_types_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\booking\channelmanager\RoomType',
                'foreign_field'     => 'rental_units_ids',
                'rel_table'         => 'lodging_rental_unit_rel_room_type',
                'rel_foreign_key'   => 'room_type_id',
                'rel_local_key'     => 'rental_unit_id',
                'description'       => 'Room Type (from channel manager) the rental unit refers to.',
                'help'              => 'If this field is set, it means that the rental unit can be rented on OTA via the channel manager. So, in case of a local booking it must trigger an update of the availabilities.'
            ],

            'has_pmr_access' => [
                'type'        => 'boolean',
                'description' => 'The rental unit is accessible for Persons with Reduced Mobility (PMR), including wheelchair adaptations, easy access, and adapted showers.',
                'default'     => false
            ],

            'has_pdv_features' => [
                'type'        => 'boolean',
                'description' => 'The rental unit is adapted for Persons with Visual Impairment (PDV), featuring high visual contrast, tactile or Braille signage, and no hazardous obstacles.',
                'default'     => false
            ],

            'has_pda_support' => [
                'type'        => 'boolean',
                'description' => 'The rental unit is equipped for Persons with Hearing Impairment (PDA), including visual alarms, subtitles, or other suitable aids.',
                'default'     => false
            ]

        ];
    }

    public static function canupdate($om, $ids, $values, $lang='en') {

        foreach($ids as $id) {
            if(isset($values['parent_id'])) {
                $descendants_ids = [];
                $rental_units_ids = [$id];
                for($i = 0; $i < 2; ++$i) {
                    $units = $om->read(self::getType(), $rental_units_ids, ['children_ids']);
                    if($units > 0) {
                        $rental_units_ids = [];
                        foreach($units as $id => $unit) {
                            if(count($unit['children_ids'])) {
                                foreach($unit['children_ids'] as $uid) {
                                    $rental_units_ids[] = $uid;
                                    $descendants_ids[] = $uid;
                                }
                            }
                        }
                    }
                }
                if(in_array($values['parent_id'], $descendants_ids)) {
                    return ['parent_id' => ['child_cannot_be_parent' => 'Selected parent cannot be amongst rental unit children.']];
                }
            }
            if(isset($values['children_ids'])) {
                $ancestors_ids = [];
                $parent_unit_id = $id;
                for($i = 0; $i < 2; ++$i) {
                    $units = $om->read(self::getType(), $parent_unit_id, ['parent_id']);
                    if($units > 0) {
                        foreach($units as $id => $unit) {
                            if(isset($unit['parent_id']) && $unit['parent_id'] > 0) {
                                $parent_unit_id = $unit['parent_id'];
                                $ancestors_ids[] = $unit['parent_id'];
                            }
                        }
                    }
                }
                foreach($values['children_ids'] as $assignment) {
                    if($assignment > 0) {
                        if(in_array($assignment, $ancestors_ids)) {
                            return ['children_ids' => ['parent_cannot_be_child' => "Selected children cannot be amongst rental unit parents ({$assignment})."]];
                        }
                    }
                }
            }
        }
        return [];
    }

    public static function onupdateParentId($om, $ids, $values, $lang) {
        $om->update(self::getType(), $ids, ['has_parent' => null]);
    }

    public static function calcHasParent($om, $oids, $lang) {
        $result = [];
        $units = $om->read(__CLASS__, $oids, ['parent_id'], $lang);
        foreach($units as $uid => $unit) {
            $result[$uid] = (bool) (!is_null($unit['parent_id']) && $unit['parent_id'] > 0);
        }
        return $result;
    }

    public static function getConstraints() {
        return [
            'capacity' =>  [
                'lte_zero' => [
                    'message'       => 'Capacity must be a positive value.',
                    'function'      => function ($qty, $values) {
                        return ($qty > 0);
                    }
                ]
            ]

        ];
    }

    public static function getConsumptions($om, $rental_unit_id, $date_from, $date_to) {
        $result = [];

        // #memo - a consumption always spans on a single day
        $consumptions_ids = $om->search(\sale\booking\Consumption::getType(), [
            ['date', '>=', $date_from],
            ['date', '<=', $date_to],
            ['rental_unit_id', '=', $rental_unit_id]
        ], ['date' => 'asc']);

        if($consumptions_ids > 0 && count($consumptions_ids)) {
            $consumptions = $om->read(\sale\booking\Consumption::getType(), $consumptions_ids, [
                'id',
                'date',
                'rental_unit_id',
                'schedule_from',
                'schedule_to'
            ]);

            foreach($consumptions as $id => $consumption) {
                $consumption_from = $consumption['date_from'] + $consumption['schedule_from'];
                $consumption_to = $consumption['date_to'] + $consumption['schedule_to'];
                // keep all consumptions for which intersection is not empty
                if(max($date_from, $consumption_from) < min($date_to, $consumption_to)) {
                    $result[$id] = $consumption;
                }
            }
        }

        return $result;
    }

    public static function generateName() {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 2; $i++) {
            $code .= $letters[rand(0, strlen($letters) - 1)];
        }

        $number = rand(1, 150);

        return "$code - $number";
    }

    public static function generateCode() {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 2; $i++) {
            $code .= $letters[rand(0, strlen($letters) - 1)];
        }

        $number = rand(1, 150);

        return "$code$number";
    }
}

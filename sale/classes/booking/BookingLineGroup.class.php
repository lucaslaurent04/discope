<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use core\setting\Setting;
use equal\orm\Model;
use equal\orm\ObjectManager;
use identity\Center;
use sale\catalog\Product;
use sale\catalog\ProductModel;

class BookingLineGroup extends Model {

    public static function getName() {
        return "Booking line group";
    }

    public static function getDescription() {
        return "Booking line groups are related to a booking and describe one or more sojourns and their related consumptions.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Mnemo for the group.',
                'default'           => ''
            ],

            'order' => [
                'type'              => 'integer',
                'description'       => 'Order of the group in the list.',
                'default'           => 1,
                'onupdate'          => 'onupdateOrder'
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Day of arrival.",
                'onupdate'          => 'onupdateDateFrom',
                'default'           => time()
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "Day of departure.",
                'default'           => time(),
                'onupdate'          => 'onupdateDateTo'
            ],

            'time_from' => [
                'type'              => 'time',
                'description'       => "Checkin time on the day of arrival.",
                'default'           => Setting::get_value('sale', 'features', 'booking.checkin.default', 14 * 3600),
                'onupdate'          => 'onupdateTimeFrom'
            ],

            'time_to' => [
                'type'              => 'time',
                'description'       => "Checkout time on the day of departure.",
                'default'           =>  Setting::get_value('sale', 'features', 'booking.checkout.default', 10 * 3600),
                'onupdate'          => 'onupdateTimeTo'
            ],

            'group_type' => [
                'type'              => 'string',
                'description'       => 'Type of lines group.',
                'help'              => 'This value replaces is_sojourn and is_events and handles the synchronization when necessary.',
                'default'           => 'simple',
                'selection'         => [
                    'simple',
                    'sojourn',
                    'event',
                    'camp'
                ],
                'onupdate'          => 'onupdateGroupType'
            ],

            'sojourn_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\SojournType',
                'description'       => 'The kind of sojourn the group is about.',
                'default'           => 1,       // 'GA'
                'onupdate'          => 'onupdateSojournTypeId',
                'visible'           => ['is_sojourn', '=', true]
            ],

            'is_sojourn' => [
                'type'              => 'boolean',
                'description'       => 'Does the group spans over several nights and relate to accommodations?',
                'default'           => false,
                'onupdate'          => 'onupdateIsSojourn'
            ],

            'is_event' => [
                'type'              => 'boolean',
                'description'       => 'Does the group relate to an event occurring on a single day?',
                'default'           => false,
                'onupdate'          => 'onupdateIsEvent'
            ],

            'is_extra' => [
                'type'              => 'boolean',
                'description'       => 'Group relates to sales made off-contract. (ex. point of sale)',
                'default'           => false
            ],

            'is_autosale' => [
                'type'              => 'boolean',
                'description'       => 'Does the group relate to autosale products?',
                'default'           => false
            ],

            'is_locked' => [
                'type'              => 'boolean',
                'description'       => 'Are modifications disabled for the group?',
                'default'           => false,
                'visible'           => ['has_pack', '=', true],
                'onupdate'          => 'onupdateIsLocked'
            ],

            'has_schedulable_services' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Flag marking the group as holding at least one schedulable service.',
                'function'          => 'calcHasSchedulableServices'
            ],

            'has_consumptions' => [
                'type'              => 'boolean',
                'description'       => 'Have consumptions been created for extra group?',
                'help'              => 'Once an extra services group has consumptions, it can no longer be updated.',
                'default'           => false,
                'visible'           => ['is_extra', '=', true]
            ],

            'has_locked_rental_units' => [
                'type'              => 'boolean',
                'description'       => 'Can the rental units assignments be changed?',
                'default'           => false
            ],

            'has_pack' => [
                'type'              => 'boolean',
                'description'       => 'Does the group relates to a pack?',
                'default'           => false,
                'onupdate'          => 'onupdateHasPack'
            ],

            'pack_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => 'Pack (product) the group relates to, if any.',
                'visible'           => ['has_pack', '=', true],
                'onupdate'          => 'onupdatePackId'
            ],

            'nb_nights' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Amount of nights of the sojourn.',
                'function'          => 'calcNbNights',
                'store'             => true
            ],

            'nb_pers' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Amount of persons this group is about.',
                'function'          => 'calcNbPers',
                'onupdate'          => 'onupdateNbPers',
                'store'             => true
            ],

            'nb_children' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Amount of children this group is about.',
                'function'          => 'calcNbChildren',
                'store'             => true
            ],

            /* a booking can be split into several groups on which distinct rate classes apply, by default the rate_class of the customer is used */
            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "The fare class that applies to the group.",
                'default'           => 4,                       // default to 'general public'
                'onupdate'          => 'onupdateRateClassId'
            ],

            'booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingLine',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Booking lines that belong to the group.',
                'ondetach'          => 'delete',
                'order'             => 'order',
                'onupdate'          => 'onupdateBookingLinesIds'
            ],

            'consumptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Consumption',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Consumptions relating to the group.',
                'ondetach'          => 'delete'
            ],

            'price_adapters_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingPriceAdapter',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Price adapters that apply to all lines of the group (based on group settings).'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'Booking the line relates to (for consistency, lines should be accessed using the group they belong to).',
                'required'          => true,
                'ondelete'          => 'cascade'         // delete group when parent booking is deleted
            ],

            'meal_preferences_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\MealPreference',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Meal preferences relating to the group.',
                'ondetach'          => 'delete'
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => 'The price (retrieved by price list) the pack relates to.',
                'visible'           => ['has_pack', '=', true],
                'onupdate'          => 'onupdatePriceId'
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'VAT rate that applies to this group, when relating to a pack_id.',
                'function'          => 'calcVatRate',
                'store'             => true,
                'visible'           => ['has_pack', '=', true],
            ],

            'unit_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Tax-excluded unit price (with automated discounts applied).',
                'function'          => 'calcUnitPrice',
                'store'             => true,
                'visible'           => ['has_pack', '=', true]
            ],

            'qty' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Quantity of product items for the group (pack).',
                'function'          => 'calcQty',
                'visible'           => ['has_pack', '=', true]
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price for all lines (computed).',
                'function'          => 'calcTotal',
                'store'             => true
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Final tax-included price for all lines (computed).',
                'function'          => 'calcPrice',
                'store'             => true
            ],

            'fare_benefit' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total amount of the fare banefit VAT incl.',
                'function'          => 'calcFareBenefit',
                'store'             => true
            ],

            // we mean rental_units_ids (for rental units assignment)
            // #todo - deprecate
            'accomodations_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => \sale\booking\BookingLine::getType(),
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Booking lines relating to accommodations.',
                'ondetach'          => 'delete',
                'domain'            => ['is_rental_unit', '=', true]
            ],

            'sojourn_product_models_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\SojournProductModel',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => "The product models groups assigned to the sojourn (from lines).",
                'ondetach'          => 'delete'
            ],

            'rental_unit_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\SojournProductModelRentalUnitAssignement',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => "The rental units assigned to the group (from lines).",
                'ondetach'          => 'delete'
            ],

            'age_range_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingLineGroupAgeRangeAssignment',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Age range assignments defined for the group.',
                'ondetach'          => 'ondetachAgeRange'
            ],

            'booking_activities_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingActivity',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => "The booking activities that refer to the booking line group."
            ],

            'activity_group_num' => [
                'type'              => 'integer',
                'description'       => "Number of the activity group in the booking.",
                'onupdate'          => 'onupdateActivityGroupNum'
            ],

            'booking_meals_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingMeal',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => "The booking meals that refer to the booking line group."
            ],

            'has_person_with_disability' => [
                'type'              => 'boolean',
                'description'       => "At least one person from the group has a disability.",
                'default'           => false
            ],

            'person_disability_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain'
            ],

            'bed_linens' => [
                'type'              => 'boolean',
                'description'       => "Does the group include a product related to renting bed linens?",
                'help'              => "Bed linens are always provided when make_beds is true.",
                'default'           => false
            ],

            'make_beds' => [
                'type'              => 'boolean',
                'description'       => "Does the group include a product related to bed-making at the beginning of the stay?",
                'help'              => "Bed linens are always provided when make_beds is true.",
                'default'           => false
            ]

        ];
    }

    public static function calcNbPers($self) {
        $result = [];
        $self->read(['age_range_assignments_ids' => ['qty']]);
        foreach($self as $id => $bookingLineGroup) {
            $result[$id] = 0;
            foreach($bookingLineGroup['age_range_assignments_ids'] as $ageRangeAssignment) {
                $result[$id] += $ageRangeAssignment['qty'];
            }
        }
        return $result;
    }

    /**
     * @param \equal\orm\ObjectManager  $om
     */
    public static function onupdateIsSojourn($om, $oids, $values, $lang) {
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'booking_lines_ids', 'nb_pers', 'is_sojourn', 'age_range_assignments_ids'], $lang);
        if($groups > 0) {
            foreach($groups as $gid => $group) {
                if($group['is_sojourn']) {
                    // remove any previously set assignments
                    $om->delete(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], true);

                    if($group['is_sojourn']) {
                        $adults_age_range_id = Setting::get_value('sale', 'organization', 'age_range_default', 1);

                        // create default age_range assignment
                        $assignment = [
                            'age_range_id'          => $adults_age_range_id,
                            'booking_line_group_id' => $gid,
                            'booking_id'            => $group['booking_id'],
                            'qty'                   => $group['nb_pers']
                        ];
                        $om->create(BookingLineGroupAgeRangeAssignment::getType(), $assignment, $lang);
                    }
                }
                // re-compute bookinglines quantities
                $om->update(\sale\booking\BookingLine::getType(), $group['booking_lines_ids'], ['qty_vars' => null], $lang);
                $om->callonce(\sale\booking\BookingLine::getType(), 'updateQty', $group['booking_lines_ids'], [], $lang);
            }
            // update auto sales
            $om->callonce(self::getType(), 'updateAutosaleProducts', $oids, [], $lang);
        }
    }

    public static function onupdateGroupType($om, $ids, $values, $lang) {
        $groups = $om->read(self::getType(), $ids, ['group_type', 'booking_id', 'booking_activities_ids'], $lang);
        if($groups > 0) {
            foreach($groups as $id => $group) {
                if($group['group_type'] == 'simple') {
                    $om->update(self::getType(), $id, ['is_sojourn' => false]);
                    $om->update(self::getType(), $id, ['is_event' => false]);
                }
                elseif($group['group_type'] == 'sojourn') {
                    $om->update(self::getType(), $id, ['is_sojourn' => true]);
                    $om->update(self::getType(), $id, ['is_event' => false]);
                }
                elseif($group['group_type'] == 'camp') {
                    $om->update(self::getType(), $id, ['is_sojourn' => false]);
                    $om->update(self::getType(), $id, ['is_event' => false]);
                }
                elseif($group['group_type'] == 'event') {
                    $om->update(self::getType(), $id, ['is_sojourn' => false]);
                    $om->update(self::getType(), $id, ['is_event' => true]);
                }
                Booking::id($group['booking_id'])->do('refresh_groups_activity_number');
                BookingActivity::ids($group['booking_activities_ids'])->update(['group_num' => null]);
            }
        }
    }

    /**
     * Force resetting other activities 'group_num'
     */
    public static function onupdateActivityGroupNum($self) {
        $self->read(['booking_activities_ids']);
        foreach($self as $group) {
            BookingActivity::ids($group['booking_activities_ids'])->update(['group_num' => null]);
        }
    }

    /**
     * @param \equal\orm\ObjectManager  $om
     */
    public static function onupdateIsEvent($om, $oids, $values, $lang) {
        $groups = $om->read(self::getType(), $oids, ['booking_lines_ids'], $lang);
        if($groups > 0) {
            foreach($groups as $gid => $group) {
                // re-compute bookinglines quantities
                $om->update(\sale\booking\BookingLine::getType(), $group['booking_lines_ids'], ['qty_vars' => null], $lang);
                $om->callonce(\sale\booking\BookingLine::getType(), 'updateQty', $group['booking_lines_ids'], [], $lang);
            }
            // update auto sales
            $om->callonce(self::getType(), 'updateAutosaleProducts', $oids, [], $lang);
        }
    }

    public static function onupdateHasPack($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:onchangeHasPack", QN_REPORT_DEBUG);

        $groups = $om->read(self::getType(), $oids, ['has_pack', 'booking_lines_ids']);
        if($groups > 0 && count($groups)) {
            foreach($groups as $gid => $group) {
                if(!$group['has_pack']) {
                    // remove existing booking_lines
                    $om->update(self::getType(), $gid, ['booking_lines_ids' => array_map(function($a) { return "-$a";}, $group['booking_lines_ids'])]);
                    // reset lock and pack_id
                    $om->update(self::getType(), $gid, ['is_locked' => false, 'pack_id' => null ]);
                }
            }
        }
    }

    public static function onupdateIsLocked($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:onchangeIsLocked", QN_REPORT_DEBUG);
        // invalidate prices
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);
        $om->callonce(self::getType(), 'updatePriceId', $oids, [], $lang);
    }

    public static function onupdatePriceId($om, $oids, $values, $lang) {
        // invalidate prices
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);
        $om->update(self::getType(), $oids, ['vat_rate' => null, 'unit_price' => null]);
    }

    /**
     * Handler called after pack_id has changed (sale\catalog\Product).
     * Updates is_locked field according to selected pack (pack_id).
     * (is_locked can be manually set by the user afterward)
     *
     * Since this method is called, we assume that current group has 'has_pack' set to true,
     * and that pack_id relates to a product that is a pack.
     */
    public static function onupdatePackId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:onchangePackId", QN_REPORT_DEBUG);

        $groups = $om->read(self::getType(), $oids, [
            'name',
            'booking_id',
            'booking_id.center_id.name',
            'date_from',
            'nb_pers',
            'is_locked',
            'booking_lines_ids',
            'age_range_assignments_ids',
            'pack_id',
            'pack_id.has_age_range',
            'pack_id.age_range_id',
            'pack_id.product_model_id.name',
            'pack_id.product_model_id.qty_accounting_method',
            'pack_id.product_model_id.has_duration',
            'pack_id.product_model_id.duration',
            'pack_id.product_model_id.capacity',
            'pack_id.product_model_id.booking_type_id'
        ]);

        // pass-1 : update age ranges for packs with a specific age range

        /*
        // #memo - BookingLineGroupAgeRangeAssignment and BookingLineGroup.pack_id.age_range_id are distinct : it is possible to have several age ranges but consider another age range for the products of the pack
        foreach($groups as $gid => $group) {
            if($group['pack_id.has_age_range']) {
                // remove any previously set assignments
                $om->delete(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], true);
                // create default age_range assignment
                $assignment = [
                    'age_range_id'          => $group['pack_id.age_range_id'],
                    'booking_line_group_id' => $gid,
                    'booking_id'            => $group['booking_id'],
                    'qty'                   => $group['nb_pers']
                ];
                $om->create(BookingLineGroupAgeRangeAssignment::getType(), $assignment, $lang);
            }
        }
        */

        // (re)generate booking lines
        $om->callonce(self::getType(), 'updatePack', $oids, [], $lang);

        // pass-2 : update groups and related bookings, if necessary
        foreach($groups as $gid => $group) {
            // if model of chosen product has a non-generic booking type, update the booking of the group accordingly
            if(isset($group['pack_id.product_model_id.booking_type_id']) && $group['pack_id.product_model_id.booking_type_id'] != 1) {
                $om->update(Booking::getType(), $group['booking_id'], ['type_id' => $group['pack_id.product_model_id.booking_type_id']]);
            }

            $updated_fields = ['vat_rate' => null];

            $default_group_name = "Services {$group['booking_id.center_id.name']}";

            // assign the name of the selected pack as group name
            if($group['pack_id'] && isset($group['pack_id.product_model_id.name']) && $group['name'] === $default_group_name) {
                $updated_fields['name'] = $group['pack_id.product_model_id.name'];
            }

            // if targeted product model has its own duration, date_to is updated accordingly
            if($group['pack_id.product_model_id.has_duration']) {
                $updated_fields['date_to'] = $group['date_from'] + ($group['pack_id.product_model_id.duration'] * 60*60*24);
                // will update price_adapters, nb_nights
            }

            // always update nb_pers
            // to make sure to trigger self::updatePriceAdapters and BookingLine::updateQty
            $updated_fields['nb_pers'] = $group['nb_pers'];
            if($group['pack_id.product_model_id.qty_accounting_method'] == 'accomodation' && $group['pack_id.product_model_id.capacity'] > $group['nb_pers']) {
                $updated_fields['nb_pers'] = $group['pack_id.product_model_id.capacity'];
            }

            $om->update(self::getType(), $gid, $updated_fields, $lang);
        }

        // invalidate prices
        // #memo - this must be done after all other processing and should not alter price_id assignments (but only reset computed fields)
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);

    }

    public static function onupdateOrder($self) {
        $self->read(['booking_id', 'booking_activities_ids']);
        foreach($self as $group) {
            Booking::id($group['booking_id'])->do('refresh_groups_activity_number');
            BookingActivity::ids($group['booking_activities_ids'])->update(['group_num' => null]);
        }
    }

    public static function onupdateDateFrom($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:onchangeDateFrom", QN_REPORT_DEBUG);
        // invalidate prices
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);

        $om->update(self::getType(), $oids, ['nb_nights' => null ]);
        $om->callonce(self::getType(), 'updatePriceAdapters', $oids, [], $lang);
        $om->callonce(self::getType(), 'updateAutosaleProducts', $oids, [], $lang);

        // update bookinglines
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'is_sojourn', 'is_event', 'has_pack', 'nb_nights', 'booking_lines_ids']);
        if($groups > 0 && count($groups)) {
            foreach($groups as $group) {
                // notify booking lines that price_id has to be updated
                $om->callonce('sale\booking\BookingLine', 'updatePriceId', $group['booking_lines_ids'], [], $lang);
                // recompute bookinglines quantities
                $om->callonce('sale\booking\BookingLine', 'updateQty', $group['booking_lines_ids'], [], $lang);
                if($group['is_sojourn']  || $group['is_event']) {
                    // force parent booking to recompute date_from
                    $om->update('sale\booking\Booking', $group['booking_id'], ['date_from' => null]);
                }
            }
        }
    }

    public static function onupdateDateTo($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:onchangeDateTo", QN_REPORT_DEBUG);
        // invalidate prices
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);

        $om->update(self::getType(), $oids, ['nb_nights' => null ]);
        $om->callonce(self::getType(), 'updatePriceAdapters', $oids, [], $lang);
        $om->callonce(self::getType(), 'updateAutosaleProducts', $oids, [], $lang);

        // update bookinglines
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'is_sojourn', 'is_event', 'has_pack', 'nb_nights', 'nb_pers', 'booking_lines_ids']);
        if($groups > 0) {
            foreach($groups as $group) {
                // re-compute bookinglines quantities
                $om->callonce('sale\booking\BookingLine', 'updateQty', $group['booking_lines_ids'], [], $lang);
                if($group['is_sojourn'] || $group['is_event']) {
                    // force parent booking to recompute date_from
                    $om->update('sale\booking\Booking', $group['booking_id'], ['date_to' => null]);
                }
            }
        }
    }

    public static function onupdateTimeFrom($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:onupdateTimeTo", QN_REPORT_DEBUG);

        // update parent booking
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'is_sojourn', 'is_event'], $lang);
        if($groups > 0) {
            foreach($groups as $group) {
                if($group['is_sojourn'] || $group['is_event']) {
                    // force parent booking to recompute time_from
                    $om->update('sale\booking\Booking', $group['booking_id'], ['time_from' => null]);
                }
            }
        }
    }

    public static function onupdateTimeTo($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:onupdateTimeTo", QN_REPORT_DEBUG);

        // update parent booking
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'is_sojourn', 'is_event'], $lang);
        if($groups > 0) {
            foreach($groups as $group) {
                if($group['is_sojourn'] || $group['is_event']) {
                    // force parent booking to recompute time_to
                    $om->update('sale\booking\Booking', $group['booking_id'], ['time_to' => null]);
                }
            }
        }
    }

    public static function onupdateBookingLinesIds($om, $oids, $values, $lang) {
        // recompute sojourn prices
        $om->callonce(self::getType(), '_resetPrices', $oids, [], $lang);
        // reset rental units assignments
        $om->callonce(self::getType(), 'createRentalUnitsAssignments', $oids, [], $lang);
        // force parent booking to recompute times, prices and has transport
        $groups = $om->read(self::getType(), $oids, ['booking_id'], $lang);
        if($groups > 0) {
            $bookings_ids = array_map(function($a) {return $a['booking_id'];}, $groups);
            $om->update(Booking::getType(), $bookings_ids, [
                'time_from'     => null,
                'time_to'       => null,
                'total'         => null,
                'price'         => null,
                'has_transport' => null
            ]);
            // force instant recompute of has transport
            $om->read(Booking::getType(), $bookings_ids, ['has_transport']);
        }
        // refresh meals
        foreach($oids as $oid) {
            self::refreshMeals($om, $oid);
        }
    }

    /**
     * In case prices of a group are impacted, we need to reset parent booking and children lines as well.
     */
    public static function _resetPrices($om, $oids, $values, $lang) {
        // reset computed fields related to price
        $om->update(__CLASS__, $oids, ['total' => null, 'price' => null, 'fare_benefit' => null]);
        $groups = $om->read(__CLASS__, $oids, ['booking_id', 'booking_lines_ids', 'is_extra'], $lang);
        if($groups > 0) {
            $bookings_ids = array_map(function ($a) { return $a['booking_id']; }, $groups);
            // reset fields in parent bookings
            $om->callonce('sale\booking\Booking', '_resetPrices', $bookings_ids, [], $lang);
            // reset fields in children lines
            foreach($groups as $gid => $group) {
                // do not reset lines for extra-consumptions groups
                if(!$group['is_extra']) {
                    $om->callonce('sale\booking\BookingLine', '_resetPrices', $group['booking_lines_ids'], [], $lang);
                }
            }
        }
    }

    public static function onupdateRateClassId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:onchangeRateClassId", QN_REPORT_DEBUG);
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'rate_class_id.name'], $lang);
        // #todo #kaleo - add support for assigning an optional booking_type_id to each rate_class
        foreach($groups as $gid => $group) {
            // #kaleo - if model of chosen product has a non-generic booking type, update the booking of the group accordingly
            if($group['rate_class_id.name'] == 'T5' || $group['rate_class_id.name'] == 'T7') {
                $om->update(Booking::getType(), $group['booking_id'], ['type_id' => 4]);
            }
        }
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);
        $om->callonce(self::getType(), 'updatePriceAdapters', $oids, [], $lang);
    }

    public static function onupdateSojournTypeId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:onchangeSojournTypeId", QN_REPORT_DEBUG);
        $om->callonce('sale\booking\BookingLineGroup', '_resetPrices', $oids, [], $lang);
        $om->callonce(self::getType(), 'updatePriceAdapters', $oids, [], $lang);
    }

    public static function onupdateNbPers($om, $ids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:onchangeNbPers", QN_REPORT_DEBUG);

        // 0) discard non is_extra groups whose booking is in a non-modifiable status
        // #memo - this is necessary because nb_pers can be changed for GG at any time
        $groups = $om->read(self::getType(), $ids, [
            'booking_id.status',
            'is_extra'
        ]);
        $groups_ids_to_remove = [];
        foreach($groups as $gid => $group) {
            if(!$group['is_extra'] && $group['booking_id.status'] != 'quote') {
                $groups_ids_to_remove[] = $gid;
            }
        }
        $ids = array_diff($ids, $groups_ids_to_remove);

        // 1) invalidate prices
        $om->callonce(self::getType(), '_resetPrices', $ids, [], $lang);

        $groups = $om->read(self::getType(), $ids, [
            'booking_id',
            'nb_pers',
            'booking_lines_ids',
            'is_sojourn',
            'age_range_assignments_ids',
            'rate_class_id.name'
        ]);

        // 3) reset parent bookings nb_pers and update booking type
        if($groups > 0) {
            $bookings_ids = array_map(function($a) {return $a['booking_id'];}, $groups);
            $om->update(Booking::getType(), $bookings_ids, ['nb_pers' => null]);
            $om->callonce(Booking::getType(), 'updateAutosaleProducts', $bookings_ids, [], $lang);

            foreach($groups as $group) {
                // #kaleo - specific rate classes
                if($group['is_sojourn'] && $group['rate_class_id.name'] == 'T4') {
                    if($group['nb_pers'] >= 10) {
                        // booking type 'TPG' (tout public / groupe) is for booking with 10 pers. or more
                        $om->update(Booking::getType(), $group['booking_id'], ['type_id' => 6]);
                    }
                    else {
                        // booking type 'TP' (tout public) is for booking with less than 10 pers.
                        $om->update(Booking::getType(), $group['booking_id'], ['type_id' => 1]);
                    }
                }
            }
        }

        // 4) update agerange assignments (for single assignment)
        if($groups > 0) {
            $booking_lines_ids = [];
            foreach($groups as $group_id => $group) {
                // invalidate nb children
                self::refreshNbChildren($om, $group_id);

                if($group['is_sojourn'] && count($group['age_range_assignments_ids']) == 1) {
                    $age_range_assignment_id = current($group['age_range_assignments_ids']);
                    $om->update(BookingLineGroupAgeRangeAssignment::getType(), $age_range_assignment_id, ['qty' => $group['nb_pers']]);
                }
                $booking_lines_ids = array_merge($group['booking_lines_ids']);
                // trigger sibling groups nb_pers update (this is necessary since the nb_pers is based on the booking total participants)
            }
            // re-compute bookinglines quantities
            $om->update(BookingLine::getType(), $booking_lines_ids, ['qty_vars' => null], $lang);
            $om->callonce(BookingLine::getType(), 'updateQty', $booking_lines_ids, [], $lang);
        }

        // 5) update dependencies
        $om->callonce(self::getType(), 'createRentalUnitsAssignments', $ids, [], $lang);
        $om->callonce(self::getType(), 'updatePriceAdapters', $ids, [], $lang);
        $om->callonce(self::getType(), 'updateAutosaleProducts', $ids, [], $lang);
        $om->callonce(self::getType(), 'updateMealPreferences', $ids, [], $lang);
    }

    public static function refreshNbPers($om, $id) {
        $om->update(self::getType(), $id, ['nb_pers' => null]);
    }

    public static function refreshNbChildren($om, $id) {
        $om->update(self::getType(), $id, ['nb_children' => null]);
    }

    /**
     * Calculate the quantity of children in the groups
     *
     * @param \equal\orm\ObjectManager  $om
     * @param int[]                     $oids
     * @param string                    $lang
     * @return array
     */
    public static function calcNbChildren($om, $oids, $lang) {
        $result = [];
        $groups = $om->read(self::getType(), $oids, ['age_range_assignments_ids'], $lang);
        if($groups > 0) {
            foreach($groups as $gid => $group) {
                $children_qty = 0;
                $assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], ['qty', 'age_range_id.age_from'], $lang);
                foreach($assignments as $assignment) {
                    if($assignment['age_range_id.age_from'] >= 3 && $assignment['age_range_id.age_from'] < 18) {
                        $children_qty += $assignment['qty'];
                    }
                }

                $result[$gid] = $children_qty;
            }
        }

        return $result;
    }

    public static function calcVatRate($om, $oids, $lang) {
        $result = [];
        $lines = $om->read(self::getType(), $oids, ['price_id.accounting_rule_id.vat_rule_id.rate']);
        foreach($lines as $oid => $odata) {
            $result[$oid] = floatval($odata['price_id.accounting_rule_id.vat_rule_id.rate']);
        }
        return $result;
    }

    public static function calcQty($om, $oids, $lang) {
        $result = [];
        $groups = $om->read(self::getType(), $oids, [
            'has_pack',
            'is_locked',
            'is_sojourn',
            'is_event',
            'pack_id.product_model_id.qty_accounting_method',
            'pack_id.product_model_id.has_duration',
            'pack_id.product_model_id.duration',
            'nb_pers',
            'nb_nights'
        ]);
        foreach($groups as $gid => $group) {
            $result[$gid] = 1;
            // #memo - locked groups have a qty of 1
            if($group['has_pack'] && !$group['is_locked']) {
                // find the repetition factor
                $nb_repeat = 1;
                // #todo - we should test is_repeatable here
                if($group['pack_id.product_model_id.has_duration']) {
                    $nb_repeat = $group['pack_id.product_model_id.duration'];
                }
                elseif($group['is_sojourn']) {
                    $nb_repeat = $group['nb_nights'];
                }
                elseif($group['is_event']) {
                    $nb_repeat = $group['nb_nights'] + 1;
                }
                // default to nb_repeat
                $qty = $nb_repeat;
                // apply accounting method
                if(in_array($group['pack_id.product_model_id.qty_accounting_method'], ['unit', 'accomodation'])) {
                    $qty = $nb_repeat;
                }
                elseif($group['pack_id.product_model_id.qty_accounting_method'] == 'person') {
                    $qty =  $nb_repeat * $group['nb_pers'];
                }
                $result[$gid] = intval($qty);
            }
        }
        return $result;
    }

    public static function calcHasSchedulableServices($om, $oids, $lang) {
        $result = [];
        $groups = $om->read(self::gettype(), $oids, ['booking_lines_ids']);
        foreach($groups as $gid => $group) {
            $result[$gid] = false;
            $lines = $om->read(BookingLine::gettype(), $group['booking_lines_ids'], ['product_id.product_model_id.type', 'product_id.product_model_id.service_type']);
            foreach($lines as $lid => $line) {
                if($line['product_id.product_model_id.type'] == 'service' && $line['product_id.product_model_id.service_type'] == 'schedulable') {
                    $result[$gid] = true;
                    break;
                }
            }
        }
        return $result;
    }

    public static function calcNbNights($om, $oids, $lang) {
        $result = [];
        $groups = $om->read(self::gettype(), $oids, ['date_from', 'date_to']);
        foreach($groups as $gid => $group) {
            $result[$gid] = round( ($group['date_to'] - $group['date_from']) / (60*60*24) );
        }
        return $result;
    }

    /**
     * Compute the VAT excl. unit price of the group, with automated discounts applied.
     *
     */
    public static function calcUnitPrice($om, $oids, $lang) {
        $result = [];

        $groups = $om->read(self::getType(), $oids, ['price_id.price']);

        if($groups > 0 && count($groups)) {
            foreach($groups as $gid => $group) {

                $price_adapters_ids = $om->search(BookingPriceAdapter::getType(), [
                    ['booking_line_group_id', '=', $gid],
                    ['booking_line_id','=', 0],
                    ['is_manual_discount', '=', false]
                ]);

                $disc_value = 0.0;
                $disc_percent = 0.0;

                if($price_adapters_ids > 0) {
                    $adapters = $om->read(BookingPriceAdapter::getType(), $price_adapters_ids, ['type', 'value', 'discount_id.discount_list_id.rate_max']);

                    if($adapters > 0) {
                        foreach($adapters as $aid => $adata) {
                            if($adata['type'] == 'amount') {
                                $disc_value += $adata['value'];
                            }
                            else if($adata['type'] == 'percent') {
                                if($adata['discount_id.discount_list_id.rate_max'] && ($disc_percent + $adata['value']) > $adata['discount_id.discount_list_id.rate_max']) {
                                    $disc_percent = $adata['discount_id.discount_list_id.rate_max'];
                                }
                                else {
                                    $disc_percent += $adata['value'];
                                }
                            }
                        }
                    }
                }

                $result[$gid] = round(($group['price_id.price'] * (1-$disc_percent)) - $disc_value, 2);
            }
        }
        return $result;
    }

    /**
     * Get total tax-excluded price of the group, with discount applied.
     *
     */
    public static function calcTotal($om, $oids, $lang) {
        $result = [];
        $groups = $om->read(self::getType(), $oids, ['booking_id', 'booking_lines_ids', 'is_locked', 'has_pack', 'unit_price', 'qty']);
        $bookings_ids = [];

        if($groups > 0 && count($groups)) {
            foreach($groups as $gid => $group) {
                $result[$gid] = 0.0;

                $bookings_ids[] = $group['booking_id'];
                // if the group relates to a pack and the product_model targeted by the pack has its own Price, then this is the one to return
                if($group['has_pack'] && $group['is_locked']) {
                    $result[$gid] = $group['unit_price'] * $group['qty'];
                }
                // otherwise, price is the sum of bookingLines totals
                else {
                    $lines = $om->read('sale\booking\BookingLine', $group['booking_lines_ids'], ['total']);
                    if($lines > 0 && count($lines)) {
                        foreach($lines as $line) {
                            $result[$gid] += $line['total'];
                        }
                        $result[$gid] = round($result[$gid], 4);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Compute the VAT incl. total price of the group (pack), with manual and automated discounts applied.
     *
     */
    public static function calcPrice($om, $oids, $lang) {
        $result = [];

        $groups = $om->read(self::getType(), $oids, ['booking_lines_ids', 'total', 'vat_rate', 'is_locked', 'has_pack']);

        if($groups > 0 && count($groups)) {
            foreach($groups as $gid => $group) {
                $result[$gid] = 0.0;

                // if the group relates to a pack and the product_model targeted by the pack has its own Price, then this is the one to return
                if($group['has_pack'] && $group['is_locked']) {
                    $result[$gid] = round($group['total'] * (1 + $group['vat_rate']), 2);
                }
                // otherwise, price is the sum of bookingLines prices
                else {
                    $lines = $om->read('sale\booking\BookingLine', $group['booking_lines_ids'], ['price']);
                    if($lines > 0 && count($lines)) {
                        foreach($lines as $line) {
                            $result[$gid] += $line['price'];
                        }
                        $result[$gid] = round($result[$gid], 2);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Retrieve sum of fare benefits granted on booking lines.
     *
     */
    public static function calcFareBenefit($om, $oids, $lang) {
        $result = [];

        $groups = $om->read(get_called_class(), $oids, ['booking_lines_ids.fare_benefit']);

        foreach($groups as $oid => $group) {
            $result[$oid] = 0.0;
            if(count((array) $group['booking_lines_ids.fare_benefit'])) {
                $result[$oid] = array_reduce($group['booking_lines_ids.fare_benefit'], function ($c, $a) {
                        return $c + $a['fare_benefit'];
                    }, 0.0);
            }
        }

        return $result;
    }

    /**
     * Check whether an object can be created, and optionally perform additional operations.
     * These tests come in addition to the unique constraints return by method `getUnique()`.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $values     Associative array holding the values to be assigned to the new instance (not all fields might be set).
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be created.
     */
    public static function cancreate($om, $values, $lang) {
        if(isset($values['booking_id'])) {
            $bookings = $om->read(Booking::getType(), $values['booking_id'], ['status'], $lang);

            if($bookings) {
                $booking = reset($bookings);

                if( in_array($booking['status'], ['proforma', 'invoiced', 'debit_balance', 'credit_balance', 'balanced', 'cancelled'])
                    || ($booking['status'] != 'quote' && (!isset($values['is_extra']) || !$values['is_extra'])) ) {
                    return ['status' => ['non_editable' => 'Non-extra service lines cannot be changed for non-quote bookings.']];
                }
            }
        }

        return parent::cancreate($om, $values, $lang);
    }

    /**
     * Check whether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $oids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang='en') {

        // list of fields that can be updated at any time
        $allowed_fields = ['is_extra', 'activity_group_num', 'has_schedulable_services', 'has_consumptions', 'has_locked_rental_units'];

        if(count(array_diff(array_keys($values), $allowed_fields))) {

            $groups = $om->read(self::getType(), $oids, [
                    'booking_id.status',
                    'date_from',
                    'date_to',
                    'is_extra',
                    'has_schedulable_services',
                    'has_consumptions',
                    'is_sojourn',
                    'has_pack',
                    'pack_id.family_id',
                    'sojourn_type_id',
                    'age_range_assignments_ids',
                    'sojourn_product_models_ids'
                ], $lang);

            if($groups > 0) {
                foreach($groups as $group) {
                    // booking can never be updated once it has been invoiced
                    if(in_array($group['booking_id.status'], ['proforma', 'invoiced', 'debit_balance', 'credit_balance', 'balanced', 'cancelled'])) {
                        return ['status' => ['non_editable' => 'Booking services cannot be changed after invoicing.']];
                    }
                    // #memo - for GG, the number of persons does not impact the booking (GG only has pricing by_accomodation), so we allow change of nb_pers under specific circumstances
                    if($group['is_sojourn'] && count($values) == 1 && isset($values['nb_pers'])) {
                        // #todo - use a dedicated setting for the family_id to be exempted from nb_pers restriction
                        if($group['has_pack'] && $group['pack_id.family_id'] == 3) {
                            continue;
                        }
                    }
                    if($group['is_extra']) {
                        if(!in_array($group['booking_id.status'], ['confirmed', 'validated', 'checkedin', 'checkedout'])) {
                            return ['status' => ['non_editable' => 'Extra services can only be changed after confirmation and before invoicing.']];
                        }
                        if($group['has_schedulable_services'] && $group['has_consumptions']) {
                            return ['status' => ['non_editable' => 'Extra services groups with schedulable services cannot be changed once consumptions have been created.']];
                        }
                    }
                    else {
                        if($group['booking_id.status'] != 'quote') {
                            return ['status' => ['non_editable' => 'Non-extra services can only be changed for quote bookings.']];
                        }
                    }
                    /*
                    // nb_pers change must force reset of age ranges
                    if(isset($values['nb_pers']) && count($group['age_range_assignments_ids']) > 1 ) {
                        $assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], ['qty'], $lang);
                        $qty = array_reduce($assignments, function($c, $a) { return $c+$a['qty']; }, 0);
                        if($values['nb_pers'] != $qty) {
                            return ['nb_pers' => ['count_mismatch' => 'Number of persons does not match the age ranges.']];
                        }
                    }
                    */
                    if(isset($values['has_locked_rental_units']) && $values['has_locked_rental_units']) {
                        if(!count($group['sojourn_product_models_ids'])) {
                            return ['has_locked_rental_units' => ['invalid_status' => 'Cannot lock an empty assignment.']];
                        }
                    }

                    if(isset($values['date_from'], $values['date_from'])) {
                        if($values['date_from'] > $values['date_to']) {
                            return ['date_from' => ['invalid_daterange' => 'Start date must be lower or equal to Start date.']];
                        }
                        if($values['date_to'] < $values['date_from']) {
                            return ['date_to' => ['invalid_daterange' => 'End date must be greater or equal to Start date.']];
                        }
                    }
                    else {
                        if(isset($values['date_from'], $group['date_to']) && $values['date_from'] > $group['date_to']) {
                            return ['date_from' => ['invalid_daterange' => 'Start date must be lower or equal to Start date.']];
                        }

                        if(isset($values['date_to'], $group['date_from']) && $values['date_to'] < $group['date_from']) {
                            return ['date_to' => ['invalid_daterange' => 'End date must be greater or equal to Start date.']];
                        }
                    }
                }
            }
        }

        return parent::canupdate($om, $oids, $values, $lang);
    }

    public static function candelete($om, $oids) {
        $groups = $om->read(self::getType(), $oids, ['booking_id.id', 'booking_id.status', 'is_extra']);

        if($groups) {
            foreach($groups as $group) {
                if($group['is_extra']) {
                    if(!in_array($group['booking_id.status'], ['quote', 'confirmed', 'validated', 'checkedin', 'checkedout'])) {
                        return ['status' => ['non_editable' => 'Extra services can only be deleted after confirmation and before invoicing.']];
                    }
                }
                else {
                    // #memo - booking might have been deleted (this is triggered by the Booking::onafterdelete callback)
                    if($group['booking_id.status'] && $group['booking_id.status'] != 'quote') {
                        return ['status' => ['non_editable' => 'Non-extra services can only be deleted for quote bookings.']];
                    }
                }

                $om->update('sale\booking\Booking', $group['booking_id.id'], ['price' => null, 'total' => null]);
            }
        }

        return parent::candelete($om, $oids);
    }

    /**
     * Hook invoked before object deletion for performing object-specific additional operations.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return void
     */
    public static function ondelete($om, $oids) {
        // trigger an update of parent booking nb_pers + sibling groups prices adapters
        $groups = $om->read(self::getType(), $oids, ['booking_id']);
        $bookings_ids = array_map(function($a) {return $a['booking_id'];}, $groups);
        $om->update(Booking::getType(), $bookings_ids, ['nb_pers' => null]);
        return parent::ondelete($om, $oids);
    }

    /**
     * Hook invoked after object deletion for performing object-specific additional operations.
     *
     * @param  \equal\orm\ObjectManager     $orm       ObjectManager instance.
     * @param  array                        $ids       List of objects identifiers.
     * @return void
     */
    public static function onafterdelete($orm, $ids) {
        // #memo - we do this to handle case where auto products are re-created during the delete cycle
        $lines_ids = $orm->search(\sale\booking\BookingLine::getType(), ['booking_line_group_id', 'in', $ids]);
        $orm->delete(\sale\booking\BookingLine::getType(), $lines_ids, true);
    }

    public static function ondetachAgeRange($om, $oids, $values, $lang) {
        $detached_ids = $values;

        // retrieve age ranges being removed
        $age_range_ids = [];
        $assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $detached_ids, ['age_range_id'], $lang);
        if($assignments > 0) {
            $age_range_ids = array_map(function($a) {return $a['age_range_id'];}, $assignments);
        }

        // remove lines with a product_id referring to the removed age ranges
        $groups = $om->read(self::getType(), $oids, ['booking_lines_ids'], $lang);
        if($groups > 0 && count($groups)) {
            foreach($groups as $gid => $group) {
                $lines = $om->read(\sale\booking\BookingLine::getType(), $group['booking_lines_ids'], ['product_id.has_age_range', 'product_id.age_range_id'], $lang);
                $lines_ids_to_remove = [];
                if($lines > 0 && count($lines)) {
                    foreach($lines as $lid => $line) {
                        if($line['product_id.has_age_range']) {
                            if(in_array($line['product_id.age_range_id'], $age_range_ids)) {
                                $lines_ids_to_remove[] = -$lid;
                            }
                        }
                    }
                    // will trigger onupdateBookingLinesIds
                    $om->update(self::getType(), $gid, ['booking_lines_ids' => $lines_ids_to_remove], $lang);
                }
            }
        }

        // actually remove the age ranges
        $om->remove(BookingLineGroupAgeRangeAssignment::getType(), $detached_ids, true);
    }

    /**
     * Create Price adapters according to group settings.
     * Price adapters are applied only on meal and accommodation products.
     *
     */
    // #todo - use refreshPriceAdapters
    public static function updatePriceAdapters($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:updatePriceAdapters (".implode(',', $oids).")", QN_REPORT_DEBUG);
        /*
            Remove all previous price adapters that were automatically created
        */
        $price_adapters_ids = $om->search('sale\booking\BookingPriceAdapter', [['booking_line_group_id', 'in', $oids], ['is_manual_discount', '=', false]]);

        $om->delete('sale\booking\BookingPriceAdapter', $price_adapters_ids, true);

        $line_groups = $om->read(self::getType(), $oids, [
            'has_pack',
            'pack_id.allow_price_adaptation',
            'rate_class_id',
            'sojourn_type_id',
            'sojourn_type_id.season_category_id',
            'date_from',
            'date_to',
            'nb_pers',
            'nb_children',
            'nb_nights',
            'booking_id',
            'is_locked',
            'booking_lines_ids',
            'booking_id.nb_pers',
            'booking_id.customer_id',
            'booking_id.center_id.season_category_id',
            'booking_id.center_id.discount_list_category_id',
            'booking_id.center_office_id'
        ]);

        foreach($line_groups as $group_id => $group) {

            if($group['has_pack']) {
                if(!$group['pack_id.allow_price_adaptation']) {
                    // skip group if it relates to a product model that prohibits price adaptation
                    continue;
                }
            }

            /*
                Read required preferences from the Center Office
            */
            $freebies_manual_assignment = false;
            $offices_preferences = $om->read(\identity\CenterOffice::getType(), $group['booking_id.center_office_id'], ['freebies_manual_assignment']);
            if($offices_preferences > 0 && count($offices_preferences)) {
                $prefs = reset($offices_preferences);
                $freebies_manual_assignment = (bool) $prefs['freebies_manual_assignment'];
            }

            /*
                Find the first Discount List that matches the booking dates
            */

            // the discount list category to use is the one defined for the center, unless it is ('GA' or 'GG') AND sojourn_type <> category.name
            $discount_category_id = $group['booking_id.center_id.discount_list_category_id'];

            if(in_array($discount_category_id, [1 /*GA*/, 2 /*GG*/]) && $discount_category_id != $group['sojourn_type_id']) {
                $discount_category_id = $group['sojourn_type_id'];
            }

            $discount_lists_ids = $om->search('sale\discount\DiscountList', [
                ['rate_class_id', '=', $group['rate_class_id']],
                ['discount_list_category_id', '=', $discount_category_id],
                ['valid_from', '<=', $group['date_from']],
                ['valid_until', '>=', $group['date_from']]
            ]);

            $discount_lists = $om->read('sale\discount\DiscountList', $discount_lists_ids, ['id', 'discounts_ids', 'rate_min', 'rate_max']);
            $discount_list_id = 0;
            $discount_list = null;
            if($discount_lists > 0 && count($discount_lists)) {
                // use first match (there should always be only one or zero)
                $discount_list = array_pop($discount_lists);
                $discount_list_id = $discount_list['id'];
                trigger_error("ORM:: match with discount List {$discount_list_id}", QN_REPORT_DEBUG);
            }
            else {
                trigger_error("ORM:: no discount List found", QN_REPORT_DEBUG);
            }

            /*
                Search for matching Discounts within the found Discount List
            */
            if($discount_list_id) {
                $count_booking_24 = self::computeCountBooking24($om, $group['booking_id'], $group['booking_id.customer_id'], $group['date_from']);

                $operands = [
                    'count_booking_24'  => $count_booking_24,     // qty of customer bookings from 2 years ago to present
                    'duration'          => $group['nb_nights'],   // duration in nights
                    'nb_pers'           => $group['nb_pers'],     // total number of participants
                    'nb_children'       => $group['nb_children'], // number of children amongst participants
                    'nb_adults'         => $group['nb_pers'] - $group['nb_children']  // number of adults amongst participants
                ];

                $date = $group['date_from'];

                /*
                    Pick up the first season period that matches the year and the season category of the center
                */
                $cat_id = $group['booking_id.center_id.season_category_id'];
                if($cat_id == 2) { // GG
                    $cat_id = $group['sojourn_type_id.season_category_id'];
                }

                $year = date('Y', $date);
                $seasons_ids = $om->search('sale\season\SeasonPeriod', [
                    ['season_category_id', '=', $cat_id],
                    ['date_from', '<=', $group['date_from']],
                    ['date_to', '>=', $group['date_from']],
                    ['year', '=', $year]
                ]);

                $periods = $om->read('sale\season\SeasonPeriod', $seasons_ids, ['id', 'season_type_id.name']);
                if($periods > 0 && count($periods)){
                    $period = array_shift($periods);
                    $operands['season'] = $period['season_type_id.name'];
                }

                $discounts = $om->read('sale\discount\Discount', $discount_list['discounts_ids'], ['value', 'type', 'conditions_ids', 'value_max', 'age_ranges_ids']);

                // filter discounts based on related conditions
                $discounts_to_apply = [];
                // keep track of the final rate (for discounts with type 'percent')
                $rate_to_apply = 0;

                // filter discounts to be applied on booking lines
                foreach($discounts as $discount_id => $discount) {
                    $conditions = $om->read('sale\discount\Condition', $discount['conditions_ids'], ['operand', 'operator', 'value']);
                    $valid = true;
                    foreach($conditions as $c_id => $condition) {
                        if(!in_array($condition['operator'], ['>', '>=', '<', '<=', '='])) {
                            // unknown operator
                            continue;
                        }
                        $operator = $condition['operator'];
                        if($operator == '=') {
                            $operator = '==';
                        }
                        if(!isset($operands[$condition['operand']])) {
                            $valid = false;
                            break;
                        }
                        $operand = $operands[$condition['operand']];
                        $value = $condition['value'];
                        if(!is_numeric($operand)) {
                            $operand = "'$operand'";
                        }
                        if(!is_numeric($value)) {
                            $value = "'$value'";
                        }
                        trigger_error(" testing {$operand} {$operator} {$value}", QN_REPORT_DEBUG);
                        $valid = $valid && (bool) eval("return ( {$operand} {$operator} {$value});");
                        if(!$valid) break;
                    }
                    if($valid) {
                        trigger_error("ORM:: all conditions fulfilled, applying {$discount['value']} {$discount['type']}", QN_REPORT_DEBUG);
                        $discounts_to_apply[$discount_id] = $discount;
                        if($discount['type'] == 'percent') {
                            $rate_to_apply += $discount['value'];
                        }
                    }
                }

                // guaranteed rate (rate_min) is always granted
                if($discount_list['rate_min'] > 0) {
                    $rate_to_apply += $discount_list['rate_min'];
                    $discounts_to_apply[0] = [
                        'type'      => 'percent',
                        'value'     => $discount_list['rate_min']
                    ];
                }

                // if max rate (rate_max) has been reached, use max instead
                if($rate_to_apply > $discount_list['rate_max'] ) {
                    // remove all 'percent' discounts
                    foreach($discounts_to_apply as $discount_id => $discount) {
                        if($discount['type'] == 'percent') {
                            unset($discounts_to_apply[$discount_id]);
                        }
                    }
                    // add a custom discount with maximal rate
                    $discounts_to_apply[0] = [
                        'type'      => 'percent',
                        'value'     => $discount_list['rate_max']
                    ];
                }

                // apply all applicable discounts on BookingLine Group
                foreach($discounts_to_apply as $discount_id => $discount) {
                    /*
                        create price adapter for group only, according to discount and group settings
                        (needed in case group targets a pack with own price)
                    */
                    $price_adapters_ids = $om->create('sale\booking\BookingPriceAdapter', [
                        'is_manual_discount'    => false,
                        'booking_id'            => $group['booking_id'],
                        'booking_line_group_id' => $group_id,
                        'booking_line_id'       => 0,
                        'discount_id'           => $discount_id,
                        'discount_list_id'      => $discount_list_id,
                        'type'                  => $discount['type'],
                        'value'                 => $discount['value']
                    ]);

                    /*
                        create related price adapter for all lines, according to discount and group settings
                    */

                    // read all lines from group
                    $lines = $om->read('sale\booking\BookingLine', $group['booking_lines_ids'], [
                        'product_id',
                        'product_id.product_model_id',
                        'product_id.product_model_id.has_duration',
                        'product_id.product_model_id.duration',
                        'product_id.age_range_id',
                        'is_meal',
                        'is_accomodation'
                    ]);

                    foreach($lines as $line_id => $line) {
                        // do not apply discount on lines that cannot have a price
                        if($group['is_locked']) {
                            continue;
                        }
                        // do not apply freebies if manual assignment is requested
                        if($discount['type'] == 'freebie' && $freebies_manual_assignment) {
                            continue;
                        }
                        // do not apply discount if it does not concern the product age range
                        if(isset($discount['age_ranges_ids']) && count($discount['age_ranges_ids']) && isset($line['product_id.age_range_id']) && !in_array($line['product_id.age_range_id'], $discount['age_ranges_ids'])) {
                            continue;
                        }
                        if( // for GG: apply discounts only on accommodations
                            (
                                $group['sojourn_type_id'] == 2 /*'GG'*/ && $line['is_accomodation']
                            )
                            ||
                            // for GA: apply discounts on meals and accommodations
                            (
                                $group['sojourn_type_id'] == 1 /*'GA'*/
                                &&
                                (
                                    $line['is_accomodation'] || $line['is_meal']
                                )
                            )
                        ) {
                            trigger_error("ORM:: creating price adapter", QN_REPORT_DEBUG);
                            $factor = $group['nb_nights'];

                            if($line['product_id.product_model_id.has_duration']) {
                                $factor = $line['product_id.product_model_id.duration'];
                            }

                            $discount_value = $discount['value'];
                            // ceil freebies amount according to value referenced by value_max (nb_pers by default)
                            if($discount['type'] == 'freebie') {
                                if(isset($discount['value_max']) && $discount_value > $operands[$discount['value_max']]) {
                                    $discount_value = $operands[$discount['value_max']];
                                }
                                $discount_value *= $factor;
                            }

                            // current discount must be applied on the line: create a price adapter
                            $price_adapters_ids = $om->create('sale\booking\BookingPriceAdapter', [
                                'is_manual_discount'    => false,
                                'booking_id'            => $group['booking_id'],
                                'booking_line_group_id' => $group_id,
                                'booking_line_id'       => $line_id,
                                'discount_id'           => $discount_id,
                                'discount_list_id'      => $discount_list_id,
                                'type'                  => $discount['type'],
                                'value'                 => $discount_value
                            ]);
                        }
                    }
                }

            }
            else {
                $date = date('Y-m-d', $group['date_from']);
                trigger_error("ORM::no matching discount list found for date {$date}", QN_REPORT_DEBUG);
            }
        }
    }

    private static function computeCountBooking24($om, $booking_id, $customer_id, $date_from) {

        $bookings_ids = $om->search(Booking::getType(),[
            ['id', '<>', $booking_id],
            ['customer_id', '=', $customer_id],
            ['date_from', '>=', strtotime('-2 years', $date_from)],
            ['is_cancelled', '=', false],
            ['status', 'not in', ['quote', 'option']]
        ]);

        return ($bookings_ids > 0)?count($bookings_ids):0;
    }

    private static function computeCountBooking12($om, $booking_id, $customer_id, $date_from) {

        $bookings_ids = $om->search(Booking::getType(), [
            ['id', '<>', $booking_id],
            ['customer_id', '=', $customer_id],
            ['date_from', '>=', strtotime('-365 days', $date_from)],
            ['is_cancelled', '=', false],
            ['status', 'not in', ['quote', 'option']]
        ]);

        return ($bookings_ids > 0)?0:count($bookings_ids);
    }

    public static function updatePriceAdaptersFromLines($om, $oids, $values, $lang) {
        $booking_lines_ids = $values;

        /*
            Remove all previous price adapters relating to given lines were automatically created
        */
        $price_adapters_ids = $om->search('sale\booking\BookingPriceAdapter', [['booking_line_id', 'in', $booking_lines_ids], ['is_manual_discount', '=', false]]);
        $om->remove('sale\booking\BookingPriceAdapter', $price_adapters_ids, true);

        $line_groups = $om->read(self::getType(), $oids, [
            'has_pack',
            'pack_id.allow_price_adaptation',
            'rate_class_id',
            'sojourn_type_id',
            'sojourn_type_id.season_category_id',
            'date_from',
            'date_to',
            'nb_pers',
            'nb_children',
            'nb_nights',
            'booking_id',
            'is_locked',
            'booking_lines_ids',
            'booking_id.nb_pers',
            'booking_id.customer_id',
            'booking_id.center_id.season_category_id',
            'booking_id.center_id.discount_list_category_id',
            'booking_id.center_office_id'
        ]);

        foreach($line_groups as $group_id => $group) {
            if($group['has_pack']) {
                if(!$group['pack_id.allow_price_adaptation']) {
                    // skip group if it relates to a product model that prohibits price adaptation
                    continue;
                }
            }

            /*
                Find the first Discount List that matches the booking dates
            */

            // the discount list category to use is the one defined for the center, unless it is ('GA' or 'GG') AND sojourn_type <> category.name
            $discount_category_id = $group['booking_id.center_id.discount_list_category_id'];

            if(in_array($discount_category_id, [1 /*GA*/, 2 /*GG*/]) && $discount_category_id != $group['sojourn_type_id']) {
                $discount_category_id = $group['sojourn_type_id'];
            }

            $discount_lists_ids = $om->search('sale\discount\DiscountList', [
                ['rate_class_id', '=', $group['rate_class_id']],
                ['discount_list_category_id', '=', $discount_category_id],
                ['valid_from', '<=', $group['date_from']],
                ['valid_until', '>=', $group['date_from']]
            ]);

            $discount_lists = $om->read('sale\discount\DiscountList', $discount_lists_ids, ['id', 'discounts_ids', 'rate_min', 'rate_max']);
            $discount_list_id = 0;
            $discount_list = null;
            if($discount_lists > 0 && count($discount_lists)) {
                // use first match (there should always be only one or zero)
                $discount_list = array_pop($discount_lists);
                $discount_list_id = $discount_list['id'];
                trigger_error("ORM:: match with discount List {$discount_list_id}", QN_REPORT_DEBUG);
            }
            else {
                trigger_error("ORM:: no discount List found", QN_REPORT_DEBUG);
            }

            /*
                Read required preferences from the Center Office
            */
            $freebies_manual_assignment = false;
            $offices_preferences = $om->read(\identity\CenterOffice::getType(), $group['booking_id.center_office_id'], ['freebies_manual_assignment']);
            if($offices_preferences > 0 && count($offices_preferences)) {
                $prefs = reset($offices_preferences);
                $freebies_manual_assignment = (bool) $prefs['freebies_manual_assignment'];
            }

            /*
                Search for matching Discounts within the found Discount List
            */
            if($discount_list_id) {
                $count_booking_24 = self::computeCountBooking24($om, $group['booking_id'], $group['booking_id.customer_id'], $group['date_from']);

                $operands = [
                    'count_booking_24'  => $count_booking_24,     // qty of customer bookings from 2 years ago to present
                    'duration'          => $group['nb_nights'],   // duration in nights
                    'nb_pers'           => $group['nb_pers'],     // total number of participants
                    'nb_children'       => $group['nb_children'], // number of children amongst participants
                    'nb_adults'         => $group['nb_pers'] - $group['nb_children']  // number of adults amongst participants
                ];

                $date = $group['date_from'];

                /*
                    Pick up the first season period that matches the year and the season category of the center
                */
                $cat_id = $group['booking_id.center_id.season_category_id'];
                if($cat_id == 2) { // GG
                    $cat_id = $group['sojourn_type_id.season_category_id'];
                }

                $year = date('Y', $date);
                $seasons_ids = $om->search('sale\season\SeasonPeriod', [
                    ['season_category_id', '=', $cat_id],
                    ['date_from', '<=', $group['date_from']],
                    ['date_to', '>=', $group['date_from']],
                    ['year', '=', $year]
                ]);

                $periods = $om->read('sale\season\SeasonPeriod', $seasons_ids, ['id', 'season_type_id.name']);
                if($periods > 0 && count($periods)){
                    $period = array_shift($periods);
                    $operands['season'] = $period['season_type_id.name'];
                }

                $discounts = $om->read('sale\discount\Discount', $discount_list['discounts_ids'], ['value', 'type', 'conditions_ids', 'value_max', 'age_ranges_ids']);

                // filter discounts based on related conditions
                $discounts_to_apply = [];
                // keep track of the final rate (for discounts with type 'percent')
                $rate_to_apply = 0;

                // filter discounts to be applied on booking lines
                foreach($discounts as $discount_id => $discount) {
                    $conditions = $om->read('sale\discount\Condition', $discount['conditions_ids'], ['operand', 'operator', 'value']);
                    $valid = true;
                    foreach($conditions as $c_id => $condition) {
                        if(!in_array($condition['operator'], ['>', '>=', '<', '<=', '='])) {
                            // unknown operator
                            continue;
                        }
                        $operator = $condition['operator'];
                        if($operator == '=') {
                            $operator = '==';
                        }
                        if(!isset($operands[$condition['operand']])) {
                            $valid = false;
                            break;
                        }
                        $operand = $operands[$condition['operand']];
                        $value = $condition['value'];
                        if(!is_numeric($operand)) {
                            $operand = "'$operand'";
                        }
                        if(!is_numeric($value)) {
                            $value = "'$value'";
                        }
                        trigger_error(" testing {$operand} {$operator} {$value}", QN_REPORT_DEBUG);
                        $valid = $valid && (bool) eval("return ( {$operand} {$operator} {$value});");
                        if(!$valid) break;
                    }
                    if($valid) {
                        trigger_error("ORM:: all conditions fullfilled, applying {$discount['value']} {$discount['type']}", QN_REPORT_DEBUG);
                        $discounts_to_apply[$discount_id] = $discount;
                        if($discount['type'] == 'percent') {
                            $rate_to_apply += $discount['value'];
                        }
                    }
                }

                // guaranteed rate (rate_min) is always granted
                if($discount_list['rate_min'] > 0) {
                    $rate_to_apply += $discount_list['rate_min'];
                    $discounts_to_apply[0] = [
                        'type'      => 'percent',
                        'value'     => $discount_list['rate_min']
                    ];
                }

                // if max rate (rate_max) has been reached, use max instead
                if($rate_to_apply > $discount_list['rate_max'] ) {
                    // remove all 'percent' discounts
                    foreach($discounts_to_apply as $discount_id => $discount) {
                        if($discount['type'] == 'percent') {
                            unset($discounts_to_apply[$discount_id]);
                        }
                    }
                    // add a custom discount with maximal rate
                    $discounts_to_apply[0] = [
                        'type'      => 'percent',
                        'value'     => $discount_list['rate_max']
                    ];
                }

                // apply all applicable discounts
                foreach($discounts_to_apply as $discount_id => $discount) {

                    /*
                        create related price adapter for all lines, according to discount and group settings
                    */

                    // read all lines from group
                    $lines = $om->read(\sale\booking\BookingLine::getType(), $booking_lines_ids, [
                        'product_id',
                        'product_id.product_model_id',
                        'product_id.product_model_id.has_duration',
                        'product_id.product_model_id.duration',
                        'product_id.age_range_id',
                        'is_meal',
                        'is_accomodation',
                        'qty_accounting_method'
                    ]);

                    foreach($lines as $line_id => $line) {
                        // do not apply discount on lines that cannot have a price
                        if($group['is_locked']) {
                            continue;
                        }
                        // do not apply freebies on accommodations for groups
                        if($discount['type'] == 'freebie' && $line['qty_accounting_method'] == 'accomodation') {
                            continue;
                        }
                        // do not apply freebies if manual assignment is requested
                        if($discount['type'] == 'freebie' && $freebies_manual_assignment) {
                            continue;
                        }
                        // do not apply discount if it does not concern the product age range
                        if(isset($discount['age_ranges_ids']) && count($discount['age_ranges_ids']) && isset($line['product_id.age_range_id']) && !in_array($line['product_id.age_range_id'], $discount['age_ranges_ids'])) {
                            continue;
                        }
                        if(// for GG: apply discounts only on accommodations
                            (
                                $group['sojourn_type_id'] == 2 /*'GG'*/ && $line['is_accomodation']
                            )
                            ||
                            // for GA: apply discounts on meals and accommodations
                            (
                                $group['sojourn_type_id'] == 1 /*'GA'*/
                                &&
                                (
                                    $line['is_accomodation'] || $line['is_meal']
                                )
                            ) ) {
                            trigger_error("ORM:: creating price adapter", QN_REPORT_DEBUG);
                            $factor = $group['nb_nights'];

                            if($line['product_id.product_model_id.has_duration']) {
                                $factor = $line['product_id.product_model_id.duration'];
                            }

                            $discount_value = $discount['value'];
                            // ceil freebies amount according to value referenced by value_max (nb_pers by default)
                            if($discount['type'] == 'freebie') {
                                if(isset($discount['value_max']) && $discount_value > $operands[$discount['value_max']]) {
                                    $discount_value = $operands[$discount['value_max']];
                                }
                                $discount_value *= $factor;
                            }

                            // current discount must be applied on the line: create a price adapter
                            $price_adapters_ids = $om->create('sale\booking\BookingPriceAdapter', [
                                'is_manual_discount'    => false,
                                'booking_id'            => $group['booking_id'],
                                'booking_line_group_id' => $group_id,
                                'booking_line_id'       => $line_id,
                                'discount_id'           => $discount_id,
                                'discount_list_id'      => $discount_list_id,
                                'type'                  => $discount['type'],
                                'value'                 => $discount_value
                            ]);
                        }
                    }
                }

            }
            else {
                $date = date('Y-m-d', $group['date_from']);
                trigger_error("ORM::no matching discount list found for date {$date}", QN_REPORT_DEBUG);
            }
        }
    }


    /**
     * Update pack_id and re-create booking lines accordingly.
     *
     */
    public static function updatePack($om, $ids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:updatePack", QN_REPORT_DEBUG);

        foreach($ids as $id) {
            self::refreshPack($om, $id);
        }

        // update dependencies
        $om->callonce(self::getType(), 'createRentalUnitsAssignments', $ids, [], $lang);
        $om->callonce(self::getType(), 'updatePriceAdapters', $ids, [], $lang);
        $om->callonce(self::getType(), 'updateAutosaleProducts', $ids, [], $lang);
        $om->callonce(self::getType(), 'updateMealPreferences', $ids, [], $lang);
    }

    /**
     * Resets all rental unit assignments and process each line for auto-assignment, if possible.
     *
     *   1) decrement nb_pers for lines accounted by 'accomodation' (capacity)
     *   2) create missing SPM
     *
     *  qty_accounting_method = 'accomodation'
     *    (we consider product and unit to have is_accomodation to true)
     *    1) find a free accomodation  (capacity >= product_model.capacity)
     *    2) create assignment @capacity
     *
     *  qty_accounting_method = 'person'
     *  if is_accomodation
     *      1) find a free accomodation
     *      2) create assignment @nb_pers
     *        (ignore next lines accounted by 'person')
     *  otherwise
     *       1) find a free rental unit
     *       2) create assignment @group.nb_pers
     *
     *  qty_accounting_method = 'unit'
     *      1) find a free rental unit
     *      2) create assignment @group.nb_pers
     */
    public static function createRentalUnitsAssignments($om, $oids, $values, $lang) {
        /*
            Update of the rental-units assignments

            ## when we "add" a booking line (onupdateProductId)
            * we create new rental-unit assignments depending on the product_model of the line

            ## when we remove a booking line (onupdateBookingLinesIds)
            * we do a reset of the rental-unit assignments

            ## when we update nb_pers (onupdateNbPers) or age range qty fields
            * we do a reset of the rental-unit assignments

            ## when we update a pack (`onupdatePackId`)

            * we reset rental-unit assignments
            * we create an assignment for all line at once (_createRentalUnitsAssignements)

            ## when we remove an age-range (ondelete)
            * we remove all lines whose product_id relates to that age-range
        */

        /* find existing SPM (for resetting) */

        $groups = $om->read(self::getType(), $oids, [
            'booking_id.center_office_id',
            'has_locked_rental_units',
            'booking_lines_ids',
            'sojourn_product_models_ids'
        ]);

        foreach($groups as $gid => $group) {
            // retrieve rental unit assignment preference
            $rentalunits_manual_assignment = false;
            $offices_preferences = $om->read(\identity\CenterOffice::getType(), $group['booking_id.center_office_id'], ['rentalunits_manual_assignment']);
            if($offices_preferences > 0 && count($offices_preferences)) {
                $prefs = reset($offices_preferences);
                $rentalunits_manual_assignment = (bool) $prefs['rentalunits_manual_assignment'];
            }
            // ignore groups with explicitly locked rental unit assignments
            if(!$rentalunits_manual_assignment && $group['has_locked_rental_units']) {
                continue;
            }
            if(!$rentalunits_manual_assignment) {
                // remove all previous SPM and rental_unit assignments
                $om->update(self::getType(), $gid, ['sojourn_product_models_ids' => array_map(function($a) { return "-$a";}, $group['sojourn_product_models_ids'])]);
            }
            // attempt to auto-assign rental units
            $om->callonce(self::getType(), 'createRentalUnitsAssignmentsFromLines', $gid, $group['booking_lines_ids'], $lang);
        }

    }

    /**
     * Resets and recreates consumptions that relate to the given groups.
     * This method is meant to be used for 'extra' groups, that require immediate update of the consumptions.
     * #memo - When a whole booking must (re)create its consumptions, use Booking::createConsumptions()
     * #memo - consumptions are used in the planning.
     *
     */
    public static function createConsumptions($om, $ids, $values, $lang) {

        $groups = $om->read(self::getType(), $ids, ['booking_id', 'booking_id.status', 'consumptions_ids'], $lang);

        // remove previous consumptions
        $bookings_ids = [];
        foreach($groups as $gid => $group) {
            $om->delete(\sale\booking\Consumption::getType(), $group['consumptions_ids'], true);
            if($group['booking_id.status'] != 'quote') {
                $bookings_ids[] = $group['booking_id'];
            }
        }

        // recreate consumptions for all impacted extra groups

        // get in-memory list of consumptions for all groups
        $consumptions = $om->call(self::getType(), 'getResultingConsumptions', $ids, [], $lang);
        foreach($consumptions as $consumption) {
            $om->create(\sale\booking\Consumption::getType(), $consumption, $lang);
        }

        // schedule a check for non quote bookings
        $cron = $om->getContainer()->get('cron');
        foreach($bookings_ids as $booking_id) {
            // add a task to the CRON for updating status of bookings waiting for the pricelist
            $cron->schedule(
                "booking.assign.units.{$booking_id}",
                // run as soon as possible
                time() + 60,
                'sale_booking_check-units-assignments',
                [ 'id' => $booking_id ]
            );

        }

    }

    public static function getResultingConsumptions($om, $oids, $values, $lang) {
        $consumptions = [];
        $groups = $om->read(self::getType(), $oids, [
            'booking_id',
            'booking_id.center_id',
            'booking_lines_ids',
            'nb_pers',
            'nb_nights',
            'is_event',
            'is_sojourn',
            'date_from',
            'time_from',
            'time_to',
            'age_range_assignments_ids',
            'rental_unit_assignments_ids',
            'meal_preferences_ids'
        ],
            $lang);

        if($groups > 0) {

            // pass-1 : create consumptions for rental_units
            foreach($groups as $gid => $group) {
                // retrieve assigned rental units (assigned during booking)

                $sojourn_products_models_ids = $om->search(SojournProductModel::getType(), ['booking_line_group_id', '=', $gid]);
                if($sojourn_products_models_ids <= 0) {
                    continue;
                }
                $sojourn_product_models = $om->read(SojournProductModel::getType(), $sojourn_products_models_ids, [
                    'product_model_id',
                    'product_model_id.is_accomodation',
                    'product_model_id.qty_accounting_method',
                    'product_model_id.schedule_offset',
                    'product_model_id.schedule_default_value',
                    'rental_unit_assignments_ids'
                ]);
                if($sojourn_product_models <= 0) {
                    continue;
                }
                foreach($sojourn_product_models as $spid => $spm) {
                    $rental_units_assignments = $om->read(SojournProductModelRentalUnitAssignement::getType(), $spm['rental_unit_assignments_ids'], ['rental_unit_id','qty']);
                    // retrieve all involved rental units (limited to 2 levels above and 2 levels below)
                    $rental_units = [];
                    if($rental_units_assignments > 0) {
                        $rental_units_ids = array_map(function ($a) { return $a['rental_unit_id']; }, array_values($rental_units_assignments));

                        // fetch 2 levels of rental units identifiers
                        for($i = 0; $i < 2; ++$i) {
                            $units = $om->read('realestate\RentalUnit', $rental_units_ids, ['parent_id', 'children_ids', 'can_partial_rent']);
                            if($units > 0) {
                                foreach($units as $uid => $unit) {
                                    if($unit['parent_id'] > 0) {
                                        $rental_units_ids[] = $unit['parent_id'];
                                    }
                                    if(count($unit['children_ids'])) {
                                        foreach($unit['children_ids'] as $uid) {
                                            $rental_units_ids[] = $uid;
                                        }
                                    }
                                }
                            }
                        }
                        // read all involved rental units
                        $rental_units = $om->read('realestate\RentalUnit', $rental_units_ids, ['parent_id', 'children_ids', 'can_partial_rent']);
                    }

                    // being an accomodation or not, the rental unit will be (partially) occupied on range of nb_night+1 day(s)
                    $nb_nights = $group['nb_nights']+1;

                    if($spm['product_model_id.qty_accounting_method'] == 'person') {
                        // #todo - we don't check (yet) for daily variations (from booking lines)
                        // rental_units_assignments.qty should be adapted on a daily basis
                    }

                    list($day, $month, $year) = [ date('j', $group['date_from']), date('n', $group['date_from']), date('Y', $group['date_from']) ];

                    // retrieve default time for consumption
                    list($hour_from, $minute_from, $hour_to, $minute_to) = [12, 0, 13, 0];
                    $schedule_default_value = $spm['product_model_id.schedule_default_value'];
                    if(strpos($schedule_default_value, ':')) {
                        $parts = explode('-', $schedule_default_value);
                        list($hour_from, $minute_from) = explode(':', $parts[0]);
                        list($hour_to, $minute_to) = [$hour_from+1, $minute_from];
                        if(count($parts) > 1) {
                            list($hour_to, $minute_to) = explode(':', $parts[1]);
                        }
                    }

                    $schedule_from  = $hour_from * 3600 + $minute_from * 60;
                    $schedule_to    = $hour_to * 3600 + $minute_to * 60;

                    // fetch the offset, in days, for the scheduling (applies only on sojourns)
                    $offset = ($group['is_sojourn'])?$spm['product_model_id.schedule_offset']:0;
                    $is_accomodation = $spm['product_model_id.is_accomodation'];

                    // for events, non-accommodations are scheduled according to the event (group)
                    if($group['is_event'] && !$is_accomodation) {
                        $schedule_from = $group['time_from'];
                        $schedule_to = $group['time_to'];
                    }

                    $is_first = true;
                    for($i = 0; $i < $nb_nights; ++$i) {
                        $c_date = mktime(0, 0, 0, $month, $day+$i+$offset, $year);
                        $c_schedule_from = $schedule_from;
                        $c_schedule_to = $schedule_to;

                        // first accomodation has to match the checkin time of the sojourn (from group)
                        if($is_first && $is_accomodation) {
                            $is_first = false;
                            $diff = $c_schedule_to - $schedule_from;
                            $c_schedule_from = $group['time_from'];
                            $c_schedule_to = $c_schedule_from + $diff;
                        }

                        // if day is not the arrival day
                        if($i > 0) {
                            $c_schedule_from = 0;                       // midnight same day
                        }

                        if($i == ($nb_nights-1) || !$is_accomodation) { // last day
                            $c_schedule_to = $group['time_to'];
                        }
                        else {
                            $c_schedule_to = 24 * 3600;                 // midnight next day
                        }

                        if($rental_units_assignments > 0) {
                            foreach($rental_units_assignments as $assignment) {
                                $rental_unit_id = $assignment['rental_unit_id'];
                                $consumption = [
                                    'booking_id'            => $group['booking_id'],
                                    'booking_line_group_id' => $gid,
                                    'center_id'             => $group['booking_id.center_id'],
                                    'date'                  => $c_date,
                                    'schedule_from'         => $c_schedule_from,
                                    'schedule_to'           => $c_schedule_to,
                                    'product_model_id'      => $spm['product_model_id'],
                                    'age_range_id'          => null,
                                    'is_rental_unit'        => true,
                                    'is_accomodation'       => $spm['product_model_id.is_accomodation'],
                                    'is_meal'               => false,
                                    'is_activity'           => false,
                                    'rental_unit_id'        => $rental_unit_id,
                                    'qty'                   => $assignment['qty'],
                                    'type'                  => 'book'
                                ];
                                $consumptions[] = $consumption;

                                // 1) recurse through children : all child units are blocked as 'link'
                                $children_ids = [];
                                $children_stack = (isset($rental_units[$rental_unit_id]) && isset($rental_units[$rental_unit_id]['children_ids']))?$rental_units[$rental_unit_id]['children_ids']:[];
                                while(count($children_stack)) {
                                    $unit_id = array_pop($children_stack);
                                    $children_ids[] = $unit_id;
                                    if(isset($rental_units[$unit_id]) && $rental_units[$unit_id]['children_ids']) {
                                        foreach($units[$unit_id]['children_ids'] as $child_id) {
                                            $children_stack[] = $child_id;
                                        }
                                    }
                                }

                                foreach($children_ids as $child_id) {
                                    $consumption['type'] = 'link';
                                    $consumption['rental_unit_id'] = $child_id;
                                    $consumptions[] = $consumption;
                                }

                                // 2) loop through parents : if a parent has 'can_partial_rent', it is partially blocked as 'part', otherwise fully blocked as 'link'
                                $parents_ids = [];
                                $unit_id = $rental_unit_id;

                                while( isset($rental_units[$unit_id]) ) {
                                    $parent_id = $rental_units[$unit_id]['parent_id'];
                                    if($parent_id > 0) {
                                        $parents_ids[] = $parent_id;
                                    }
                                    $unit_id = $parent_id;
                                }

                                foreach($parents_ids as $parent_id) {
                                    $consumption['type'] = ($rental_units[$parent_id]['can_partial_rent'])?'part':'link';
                                    $consumption['rental_unit_id'] = $parent_id;
                                    $consumptions[] = $consumption;
                                }
                            }
                        }
                    }
                }
            }

            // pass-2 : create consumptions for activities rental units products
            foreach($groups as $gid => $group) {
                $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], [
                    'qty',
                    'is_activity',
                    'activity_rental_unit_id',
                    'product_id.product_model_id',
                    'booking_activities_ids.activity_date',
                    'booking_activities_ids.time_slot_id.schedule_from',
                    'booking_activities_ids.time_slot_id.schedule_to'
                ],
                    $lang
                );

                if($lines > 0 && count($lines)) {
                    foreach($lines as $lid => $line) {
                        if(!$line['is_activity'] || !$line['activity_rental_unit_id']) {
                            continue;
                        }

                        $activities = [];
                        foreach($line['booking_activities_ids.activity_date'] as $index => $activity) {
                            $activities[$index] = ['activity_date' => $activity['activity_date']];
                        }
                        foreach($line['booking_activities_ids.time_slot_id.schedule_from'] as $index => $activity) {
                            $activities[$index]['schedule_from'] = $activity['time_slot_id.schedule_from'];
                        }
                        foreach($line['booking_activities_ids.time_slot_id.schedule_to'] as $index => $activity) {
                            $activities[$index]['schedule_to'] = $activity['time_slot_id.schedule_to'];
                        }

                        foreach($activities as $activity) {
                            $consumptions[] = [
                                'booking_id'            => $group['booking_id'],
                                'booking_line_group_id' => $gid,
                                'center_id'             => $group['booking_id.center_id'],
                                'date'                  => $activity['activity_date'],
                                'schedule_from'         => $activity['schedule_from'],
                                'schedule_to'           => $activity['schedule_to'],
                                'product_model_id'      => $line['product_id.product_model_id'],
                                'age_range_id'          => null,
                                'is_rental_unit'        => true,
                                'is_accomodation'       => false,
                                'is_meal'               => false,
                                'is_activity'           => true,
                                'rental_unit_id'        => $line['activity_rental_unit_id'],
                                'qty'                   => $line['qty'],
                                'type'                  => 'book'
                            ];
                        }
                    }
                }
            }

            // pass-3 : create consumptions for booking lines targeting non-rental_unit products (any other schedulable product, e.g. meals or activity)
            foreach($groups as $gid => $group) {

                // create meals map, to add their specifications (type and place) to the consumptions
                $meals = BookingMeal::search(['booking_line_group_id', '=', $gid])
                    ->read(['date', 'time_slot_id', 'meal_type_id', 'meal_place_id'])
                    ->get();

                $map_meals = [];
                foreach($meals as $meal) {
                    $map_meals[$meal['date']][$meal['time_slot_id']] = $meal;
                }

                $lines = $om->read(\sale\booking\BookingLine::getType(), $group['booking_lines_ids'], [
                    'product_id',
                    'qty',
                    'qty_vars',
                    'service_date',
                    'activity_rental_unit_id',
                    'product_id',
                    'product_id.product_model_id',
                    'product_id.has_age_range',
                    'product_id.age_range_id',
                    'product_id.description',
                    'time_slot_id',
                    'time_slot_id.schedule_from',
                    'time_slot_id.schedule_to',
                ],
                    $lang);

                if($lines > 0 && count($lines)) {

                    // read all related product models beforehand
                    $product_models_ids = array_map(function($oid) use($lines) {return $lines[$oid]['product_id.product_model_id'];}, array_keys($lines));
                    $product_models = $om->read(\sale\catalog\ProductModel::getType(), $product_models_ids, [
                        'type',
                        'service_type',
                        'schedule_offset',
                        'schedule_type',
                        'schedule_default_value',
                        'qty_accounting_method',
                        'has_duration',
                        'duration',
                        'is_rental_unit',
                        'is_accomodation',
                        'is_meal',
                        'is_snack',
                        'is_repeatable',
                        'is_activity',
                        'is_transport',
                        'is_supply'
                    ]);

                    // create consumptions according to each line product and quantity
                    foreach($lines as $lid => $line) {

                        if($line['qty'] <= 0) {
                            continue;
                        }
                        // ignore rental units : these have already been handled for the booking (grouped in SPM rental unit assignments)
                        // ignore activities : these have already been handled for the booking (rental units of activities)
                        // ignore transports and supplies : these don't generate consumptions
                        if(
                            $product_models[$line['product_id.product_model_id']]['is_rental_unit']
                            || $product_models[$line['product_id.product_model_id']]['is_activity']
                            || $product_models[$line['product_id.product_model_id']]['is_transport']
                            || $product_models[$line['product_id.product_model_id']]['is_supply']
                        ) {
                            continue;
                        }

                        $product_type = $product_models[$line['product_id.product_model_id']]['type'];
                        $service_type = $product_models[$line['product_id.product_model_id']]['service_type'];
                        $has_duration = $product_models[$line['product_id.product_model_id']]['has_duration'];

                        // consumptions are schedulable services
                        if($product_type != 'service' || $service_type != 'schedulable') {
                            continue;
                        }

                        // retrieve default time for consumption
                        if(!isset($line['time_slot_id'])) {
                            list($hour_from, $minute_from, $hour_to, $minute_to) = [12, 0, 13, 0];
                            $schedule_default_value = $product_models[$line['product_id.product_model_id']]['schedule_default_value'];
                            if(strpos($schedule_default_value, ':')) {
                                $parts = explode('-', $schedule_default_value);
                                list($hour_from, $minute_from) = explode(':', $parts[0]);
                                list($hour_to, $minute_to) = [$hour_from+1, $minute_from];
                                if(count($parts) > 1) {
                                    list($hour_to, $minute_to) = explode(':', $parts[1]);
                                }
                            }
                            $schedule_from  = $hour_from * 3600 + $minute_from * 60;
                            $schedule_to    = $hour_to * 3600 + $minute_to * 60;
                        }
                        else {
                            $schedule_from  = $line['time_slot_id.schedule_from'];
                            $schedule_to    = $line['time_slot_id.schedule_to'];
                        }

                        $is_repeatable = $product_models[$line['product_id.product_model_id']]['is_repeatable'];
                        $is_meal = $product_models[$line['product_id.product_model_id']]['is_meal'];
                        $is_snack = $product_models[$line['product_id.product_model_id']]['is_snack'];
                        $qty_accounting_method = $product_models[$line['product_id.product_model_id']]['qty_accounting_method'];

                        // #memo - number of consumptions differs for accommodations (rooms are occupied nb_nights + 1, until some time in the morning)
                        // #memo - sojourns are accounted in nights, while events are accounted in days
                        $nb_products = ($group['is_sojourn']) ? $group['nb_nights'] : (($group['is_event']) ? ($group['nb_nights']+1) : 1);
                        if(!$is_repeatable) {
                            $nb_products = 1;
                        }
                        $nb_times = $group['nb_pers'];

                        // adapt nb_times based on if product relating to line is marked with has_age_range
                        if($qty_accounting_method == 'person') {
                            $age_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], ['age_range_id', 'qty']);
                            if($line['product_id.has_age_range']) {
                                foreach($age_assignments as $aid => $assignment) {
                                    if($assignment['age_range_id'] == $line['product_id.age_range_id']) {
                                        $nb_times = $assignment['qty'];
                                    }
                                }
                            }
                        }
                        // adapt duration for products with fixed duration
                        if($has_duration) {
                            $nb_products = $product_models[$line['product_id.product_model_id']]['duration'];
                        }
                        // #memo - service_date might be set, only for schedulable non-repeatable services
                        $date_from = $line['service_date'] ?? $group['date_from'];

                        [$day, $month, $year] = [ date('j', $date_from), date('n', $date_from), date('Y', $date_from) ];
                        // fetch the offset, in days, for the scheduling (only applies on sojourns)
                        $offset = 0;

                        if($group['is_sojourn']) {
                            // #memo - schedule offset can be negative. By convention, offset = -1 refers to the departure day (i.e., the last date of the stay, not including a night)
                            $offset = $product_models[$line['product_id.product_model_id']]['schedule_offset'];
                        }

                        // by default, assign a quantity of $nb_times to each day
                        $days_nb_times = array_fill(0, $nb_products, $nb_times);

                        if($qty_accounting_method == 'person') {

                            $qty_vars = json_decode($line['qty_vars']);
                            $has_variations = is_array($qty_vars) && array_filter($qty_vars, function($v) { return $v !== 0; });

                            // $nb_times varies from one day to another : load specific days_nb_times array
                            if($has_variations) {
                                $i = 0;
                                foreach($qty_vars as $variation) {
                                    if($i >= $nb_products) {
                                        break;
                                    }
                                    $days_nb_times[$i] = $nb_times + $variation;
                                    ++$i;
                                }
                            }
                        }

                        // $nb_products represent each day of the stay
                        for($i = 0; $i < $nb_products; ++$i) {
                            // discard consumption with a resulting qty of 0
                            if($days_nb_times[$i] == 0) {
                                continue;
                            }

                            $day_index = $i + $offset;

                            // support for negative offset: count backwards from the end of the stay (ex: offset = -1 => departure day)
                            if($offset < 0 && !$is_repeatable) {
                                $day_index = $group['nb_nights'] + $offset;
                            }

                            // ignore invalid offset
                            if($day_index < 0 || $day_index > $group['nb_nights']) {
                                continue;
                            }

                            $c_date = mktime(0, 0, 0, $month, $day + $day_index, $year);

                            $c_time_slot_id = $line['time_slot_id'];
                            $c_schedule_from = $schedule_from;
                            $c_schedule_to = $schedule_to;

                            // create a single consumption with the quantity set accordingly (may vary from one day to another)
                            // #todo - if the sojourn (BookingLineGroup) has a single age range assignment, then the related age_range_id prevails over product_id.age_range_id
                            $consumption = [
                                'booking_id'            => $group['booking_id'],
                                'booking_line_group_id' => $gid,
                                'booking_line_id'       => $lid,
                                'center_id'             => $group['booking_id.center_id'],
                                'date'                  => $c_date,
                                'time_slot_id'          => $c_time_slot_id,
                                'schedule_from'         => $c_schedule_from,
                                'schedule_to'           => $c_schedule_to,
                                'product_model_id'      => $line['product_id.product_model_id'],
                                'age_range_id'          => $line['product_id.age_range_id'],
                                'is_rental_unit'        => false,
                                'is_accomodation'       => false,
                                'is_activity'           => false,
                                'is_meal'               => $is_meal,
                                'is_snack'              => $is_snack,
                                'qty'                   => $days_nb_times[$i],
                                'type'                  => 'book'
                            ];
                            // for meals, we store the age ranges and prefs within the description field
                            if($is_meal) {
                                $description = '';
                                $age_range_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], ['age_range_id.name','qty'], $lang);
                                foreach($age_range_assignments as $oid => $assignment) {
                                    $description .= "<p>{$assignment['age_range_id.name']} : {$assignment['qty']} ; </p>";
                                }
                                $meal_preferences = $om->read(\sale\booking\MealPreference::getType(), $group['meal_preferences_ids'], ['type','pref', 'qty'], $lang);
                                foreach($meal_preferences as $oid => $preference) {
                                    // #todo #i18n - use translation file
                                    $type = ($preference['type'] == '3_courses')?'3 services':'2 services';
                                    $pref = ($preference['pref'] == 'veggie')?'végétarien':(($preference['pref'] == 'allergen_free')?'sans allergène':'normal');
                                    $description .= "<p>{$type} / {$pref} : {$preference['qty']} ; </p>";
                                }
                                $consumption['description'] = $description;
                            }
                            // for meals/snack we add the meal_type and meal_place, if any
                            if(($is_meal || $is_snack) && isset($map_meals[$c_date][$c_time_slot_id])) {
                                $consumption['meal_type_id'] = $map_meals[$c_date][$c_time_slot_id]['meal_type_id'];
                                $consumption['meal_place_id'] = $map_meals[$c_date][$c_time_slot_id]['meal_place_id'];
                            }
                            $consumptions[] = $consumption;
                        }
                    }
                }
            }
        }

        return $consumptions;
    }

    /**
     * Updates rental unit assignments from a set of booking lines (called by BookingLine::onupdateProductId).
     * The references booking_lines_ids are expected to be identifiers of lines that have just been modified and to belong to a same sojourn (BookingLineGroup).
     */
    public static function createRentalUnitsAssignmentsFromLines($om, $oids, $values, $lang) {
        $booking_lines_ids = $values;

        // Attempt to auto-assign rental units.
        $groups = $om->read(self::getType(), $oids, [
            'booking_id',
            'booking_id.center_office_id',
            'nb_pers',
            'has_locked_rental_units',
            'booking_lines_ids',
            'date_from',
            'date_to',
            'time_from',
            'time_to',
            'sojourn_product_models_ids',
            'rental_unit_assignments_ids.rental_unit_id'
        ]);

        // 1-st pass: check assignment prefs and try to auto-assign if necessary
        foreach($groups as $gid => $group) {

            /*
                Read required preferences from the Center Office
            */
            $rentalunits_manual_assignment = false;
            $offices_preferences = $om->read(\identity\CenterOffice::getType(), $group['booking_id.center_office_id'], ['rentalunits_manual_assignment']);
            if($offices_preferences > 0 && count($offices_preferences)) {
                $prefs = reset($offices_preferences);
                $rentalunits_manual_assignment = (bool) $prefs['rentalunits_manual_assignment'];
            }

            if(!$rentalunits_manual_assignment && $group['has_locked_rental_units'] && count($group['sojourn_product_models_ids'])) {
                continue;
            }

            $nb_pers = $group['nb_pers'];
            $date_from = $group['date_from'] + $group['time_from'];
            $date_to = $group['date_to'] + $group['time_to'];

            // retrieve rental units that are already assigned by other groups within the same time range, if any
            // (we need to withdraw those from available units)
            $booking_assigned_rental_units_ids = [];
            $bookings = $om->read(Booking::getType(), $group['booking_id'], ['booking_lines_groups_ids', 'rental_unit_assignments_ids'], $lang);
            if($bookings > 0 && count($bookings)) {
                $booking = reset($bookings);
                $groups = $om->read(self::getType(), $booking['booking_lines_groups_ids'], ['id', 'date_from', 'date_to', 'time_from', 'time_to'], $lang);
                $assignments = $om->read(SojournProductModelRentalUnitAssignement::getType(), $booking['rental_unit_assignments_ids'], ['rental_unit_id', 'booking_line_group_id'], $lang);
                foreach($assignments as $oid => $assignment) {
                    // process rental units from other groups
                    if($assignment['booking_line_group_id'] != $gid) {
                        $group_id = $assignment['booking_line_group_id'];
                        $group_date_from = $groups[$group_id]['date_from'] + $groups[$group_id]['time_from'];
                        $group_date_to = $groups[$group_id]['date_to'] + $groups[$group_id]['time_to'];
                        // if groups have a time range intersection, mark the rental unit as assigned
                        if($group_date_from >= $date_from && $group_date_from <= $date_to
                            || $group_date_to >= $date_from && $group_date_to <= $date_to) {
                            $booking_assigned_rental_units_ids[] = $assignment['rental_unit_id'];
                        }
                    }
                }
            }

            // create a map with all product_model_id within the group
            $group_product_models_ids = [];

            $sojourn_product_models = $om->read(SojournProductModel::getType(), $group['sojourn_product_models_ids'], ['product_model_id'], $lang);
            foreach($sojourn_product_models as $spid => $spm){
                $group_product_models_ids[$spm['product_model_id']] = $spid;
            }

            // read children booking lines
            $lines = $om->read(\sale\booking\BookingLine::getType(), $group['booking_lines_ids'], [
                'booking_id.center_id',
                'product_id',
                'product_id.product_model_id',
                'qty_accounting_method',
                'is_rental_unit'
            ],
                $lang);

            // drop lines that do not relate to rental units
            $lines = array_filter($lines, function($a) { return $a['is_rental_unit']; });

            if(count($lines)) {

                // read all related product models at once
                $product_models_ids = array_map(function($oid) use($lines) {return $lines[$oid]['product_id.product_model_id'];}, array_keys($lines));
                $product_models = $om->read('sale\catalog\ProductModel', $product_models_ids, ['is_accomodation', 'qty_accounting_method', 'rental_unit_assignement', 'capacity'], $lang);

                // pass-1 : withdraw persons assigned to units accounted by 'accomodation' from nb_pers, and create SPMs
                foreach($lines as $lid => $line) {
                    $product_model_id = $line['product_id.product_model_id'];
                    if($product_models[$product_model_id]['qty_accounting_method'] == 'accomodation') {
                        $nb_pers -= $product_models[$product_model_id]['capacity'];
                    }
                    if(!isset($group_product_models_ids[$product_model_id])) {
                        $sojourn_product_model_id = $om->create(SojournProductModel::getType(), [
                            'booking_id'            => $group['booking_id'],
                            'booking_line_group_id' => $gid,
                            'product_model_id'      => $product_model_id
                        ]);
                        $group_product_models_ids[$product_model_id] = $sojourn_product_model_id;
                    }
                }
            }

            // read targeted booking lines (received as method param)
            $lines = $om->read(\sale\booking\BookingLine::getType(), $booking_lines_ids,
                [
                    'booking_id.center_id',
                    'product_id',
                    'product_id.product_model_id',
                    'product_id.product_model_id.rental_unit_assignement',
                    'product_id.product_model_id.rental_unit_id',
                    'qty_accounting_method',
                    'is_rental_unit'
                ],
                $lang
            );

            // drop lines that do not relate to rental units
            $lines = array_filter($lines, function($a) { return $a['is_rental_unit']; });

            if(count($lines)) {
                // pass-2 : process lines
                $group_assigned_rental_units_ids = [];
                $has_processed_accomodation_by_person = false;
                foreach($lines as $lid => $line) {
                    if($rentalunits_manual_assignment) {
                        // do not auto-assign rental units if manual assignment is set in prefs, except if specific rental unit is defined on product model
                        if($line['product_id.product_model_id.rental_unit_assignement'] !== 'unit' || is_null($line['product_id.product_model_id.rental_unit_id'])) {
                            continue;
                        }
                    }

                    $center_id = $line['booking_id.center_id'];

                    $is_accomodation = $product_models[$line['product_id.product_model_id']]['is_accomodation'];
                    // 'accomodation', 'person', 'unit'
                    $qty_accounting_method = $product_models[$line['product_id.product_model_id']]['qty_accounting_method'];

                    // 'category', 'capacity', 'auto'
                    // #memo - the assignment-based filtering is done in `Consumption::getAvailableRentalUnits`
                    $rental_unit_assignment = $product_models[$line['product_id.product_model_id']]['rental_unit_assignement'];

                    // all lines with same product_model are processed at the first line, remaining lines must be ignored
                    if($qty_accounting_method == 'person' && $is_accomodation && $has_processed_accomodation_by_person) {
                        continue;
                    }

                    $nb_pers_to_assign = $nb_pers;

                    if($qty_accounting_method == 'accomodation') {
                        $nb_pers_to_assign = min($product_models[$line['product_id.product_model_id']]['capacity'], $group['nb_pers']);
                    }
                    elseif($qty_accounting_method == 'unit') {
                        $nb_pers_to_assign = $group['nb_pers'];
                    }

                    // find available rental units (sorted by capacity, desc; filtered on product model category)
                    $rental_units_ids = \sale\booking\Consumption::getAvailableRentalUnits($om, $center_id, $line['product_id.product_model_id'], $date_from, $date_to);

                    // #memo - we cannot append rental units from consumptions of own booking :this leads to an edge case
                    // (use case "come and go between 'quote' and 'option'" is handled with 'realease-rentalunits' action)

                    // remove rental units that are no longer unavailable
                    $rental_units_ids = array_diff($rental_units_ids,
                        $group_assigned_rental_units_ids,               // assigned to other lines (current loop)
                        $booking_assigned_rental_units_ids              // assigned within other groups
                    );

                    // retrieve rental units with matching capacities (best match first)
                    $rental_units = self::_getRentalUnitsMatches($om, $rental_units_ids, $nb_pers_to_assign);

                    $remaining = $nb_pers_to_assign;
                    $assigned_rental_units = [];

                    // min serie for available capacity starts from max(0, i-1)
                    for($j = 0, $n = count($rental_units) ;$j < $n; ++$j) {
                        $rental_unit = $rental_units[$j];
                        $assigned = min($rental_unit['capacity'], $remaining);
                        $rental_unit['assigned'] = $assigned;
                        $assigned_rental_units[] = $rental_unit;
                        $remaining -= $assigned;
                        if($remaining <= 0) break;
                    }

                    if($remaining > 0) {
                        // no availability !
                        trigger_error("ORM::no availability", QN_REPORT_DEBUG);
                    }
                    else {
                        foreach($assigned_rental_units as $rental_unit) {
                            $assignement = [
                                'booking_id'                    => $group['booking_id'],
                                'booking_line_group_id'         => $gid,
                                'sojourn_product_model_id'      => $group_product_models_ids[$line['product_id.product_model_id']],
                                'qty'                           => $rental_unit['assigned'],
                                'rental_unit_id'                => $rental_unit['id']
                            ];
                            trigger_error("ORM::assigning {$rental_unit['assigned']} p. to {$rental_unit['id']}", QN_REPORT_DEBUG);
                            $om->create(SojournProductModelRentalUnitAssignement::getType(), $assignement);
                            // remember assigned rental units (for next lines processing)
                            $group_assigned_rental_units_ids[]= $rental_unit['id'];
                        }

                        if($qty_accounting_method == 'person' && $is_accomodation) {
                            $has_processed_accomodation_by_person = true;
                        }
                    }
                }
            }
        }

        // 2-nd pass: in any situation, if the group targets additional services (is_extra), we dispatch a notification about required assignment
        $groups = $om->read(self::getType(), $oids, [
            'booking_id',
            'is_extra',
            'booking_lines_ids'
        ]);

        $bookings_ids_map = [];
        foreach($groups as $gid => $group) {
            if($group['is_extra']) {
                // read children booking lines
                $lines = $om->read(\sale\booking\BookingLine::getType(), $group['booking_lines_ids'], [
                    'is_rental_unit'
                ],
                    $lang);

                // drop lines that do not relate to rental units
                $lines = array_filter($lines, function($a) { return $a['is_rental_unit']; });
                if(count($lines)) {
                    $bookings_ids_map[$group['booking_id']] = true;
                }
            }
        }

        if(count($bookings_ids_map)) {
            $cron = $om->getContainer()->get('cron');
            $bookings_ids = array_keys($bookings_ids_map);
            foreach($bookings_ids as $booking_id) {
                // add a task to the CRON for updating status of bookings waiting for the pricelist
                $cron->schedule(
                    "booking.assign.units.{$booking_id}",
                    // run as soon as possible
                    time() + 60,
                    'sale_booking_check-units-assignments',
                    [ 'id' => $booking_id ]
                );

            }
        }

    }

    /**
     * Find and set price according to group settings.
     * This only applies when group targets a Pack with own price.
     *
     * Should only be called when is_locked == true
     *
     * updatePriceId is called upon change on: pack_id, is_locked, date_from, center_id
     */
    public static function updatePriceId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:updatePriceId", QN_REPORT_DEBUG);

        $groups = $om->read(self::getType(), $oids, [
            'has_pack',
            'date_from',
            'pack_id',
            'booking_id',
            'booking_id.center_id.price_list_category_id'
        ]);

        foreach($groups as $gid => $group) {
            if(!$group['has_pack']) {
                continue;
            }

            // #todo - we shouldn't perform this search if pack is not marked as having its own price

            /*
                Find the Price List that matches the criteria from the booking with the shortest duration
            */
            $price_lists_ids = $om->search(
                'sale\price\PriceList', [
                [
                    ['price_list_category_id', '=', $group['booking_id.center_id.price_list_category_id']],
                    ['date_from', '<=', $group['date_from']],
                    ['date_to', '>=', $group['date_from']],
                    ['status', 'in', ['pending', 'published']]
                ],
                // #todo - quick workaround for inclusion of GA pricelist
                [
                    ['price_list_category_id', '=', 9],
                    ['date_from', '<=', $group['date_from']],
                    ['date_to', '>=', $group['date_from']],
                    ['status', 'in', ['pending', 'published']]
                ]
            ],
                ['duration' => 'asc']
            );

            $is_tbc = false;
            $selected_price_id = 0;

            /*
                Search for a matching Price within the found Price Lists
            */
            if($price_lists_ids > 0 && count($price_lists_ids)) {
                // check status and, if 'pending', evaluate if there is a 'published' alternative
                $price_lists = $om->read(\sale\price\PriceList::getType(), $price_lists_ids, [ 'status' ]);

                foreach($price_lists as $price_list_id => $price_list) {
                    $prices_ids = $om->search(\sale\price\Price::getType(), [ ['price_list_id', '=', $price_list_id], ['product_id', '=', $group['pack_id']] ]);
                    if($prices_ids > 0 && count($prices_ids)) {
                        $selected_price_id = reset($prices_ids);
                        if($price_list['status'] == 'pending') {
                            $is_tbc = true;
                        }
                        else {
                            // first matching published price is always preferred: stop searching
                            break;
                        }
                        // keep on looping until we reach end of candidates or we find one with status 'published'
                    }
                }
            }
            else {
                $date = date('Y-m-d', $group['date_from']);
                trigger_error("ORM::no price list candidates for group pack {$group['pack_id']} for date {$date}", QN_REPORT_WARNING);
            }

            if($selected_price_id > 0) {
                // assign found Price to current group
                $om->update(self::getType(), $gid, ['price_id' => $selected_price_id]);
                if($is_tbc) {
                    // found price is TBC: mark booking as to be confirmed
                    $om->update(Booking::getType(), $group['booking_id'], ['is_price_tbc' => true]);
                }
            }
            else {
                $om->update(self::getType(), $gid, ['price_id' => null, 'vat_rate' => 0, 'unit_price' => 0]);
                $date = date('Y-m-d', $group['date_from']);
                trigger_error("ORM::no matching price found for group pack {$group['pack_id']} for date {$date}", QN_REPORT_WARNING);
            }
        }
    }

    /**
     * Generate one or more lines for products sold automatically.
     * We generate services groups related to autosales when the following fields are updated:
     * customer, date_from, date_to, center_id
     *
     */
    public static function updateAutosaleProducts($om, $ids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:updateAutosaleProducts", QN_REPORT_DEBUG);

        // loop through groups and create lines for autosale products, if any
        foreach($ids as $id) {
            self::refreshAutosaleProducts($om, $id);
        }
    }


    public static function updateMealPreferences($om, $ids, $values, $lang) {
        foreach($ids as $id) {
            self::refreshMealPreferences($om, $id);
        }
    }

    public static function updateBedLinensAndMakeBeds($om, $oids, $values, $lang) {
        $ignored_lines_ids = $values['ignored_lines_ids'] ?? [];
        $groups = $om->read(self::getType(), $oids, ['booking_lines_ids.product_id']);

        $bed_linens_skus = Setting::get_value('sale', 'organization', 'sku.bed_linens', false);
        if($bed_linens_skus) {
            $bed_linens_skus = explode(',', $bed_linens_skus);
        }
        else {
            $bed_linens_skus = [];
        }

        $make_beds_skus = Setting::get_value('sale', 'organization', 'sku.make_beds', false);
        if($make_beds_skus) {
            $make_beds_skus = explode(',', $make_beds_skus);
        }
        else {
            $make_beds_skus = [];
        }

        foreach($groups as $id => $group) {
            $products_ids = [];
            foreach($group['booking_lines_ids.product_id'] as $lid => $line) {
                if(!in_array($lid, $ignored_lines_ids)) {
                    $products_ids[] = $line['product_id'];
                }
            }

            $data = ['bed_linens' => false, 'make_beds' => false];

            $products = $om->read(Product::getType(), $products_ids, ['sku']);
            foreach($products as $product) {
                if(in_array($product['sku'], $bed_linens_skus)) {
                    $data['bed_linens'] = true;
                }
                if(in_array($product['sku'], $make_beds_skus)) {
                    $data['bed_linens'] = true;
                    $data['make_beds'] = true;
                }
            }

            $om->update(self::getType(), $id, $data);
        }
    }

    protected static function _getRentalUnitsCombinations($list, $target, $start, $sum, $collect) {
        $result = [];

        // current sum matches target
        if($sum == $target) {
            return [$collect];
        }

        // try sub-combinations
        for($i = $start, $n = count($list); $i < $n; ++$i) {

            // check if the sum exceeds target
            if( ($sum + $list[$i]['capacity']) > $target ) {
                continue;
            }

            // check if it is repeated or not
            if( ($i > $start) && ($list[$i]['capacity'] == $list[$i-1]['capacity']) ) {
                continue;
            }

            // take the element into the combination
            $collect[] = $list[$i];

            // recursive call
            $res = self::_getRentalUnitsCombinations($list, $target, $i + 1, $sum + $list[$i]['capacity'], $collect);

            if(count($res)) {
                foreach($res as $r) {
                    $result[] = $r;
                }
            }

            // Remove element from the combination
            array_pop($collect);
        }

        return $result;
    }


    protected static function _getRentalUnitsMatches($om, $rental_units_ids, $nb_pers_to_assign) {
        // retrieve rental units capacities
        $rental_units = [];

        if($rental_units_ids > 0 && count($rental_units_ids)) {
            $rental_units = array_values($om->read('realestate\RentalUnit', $rental_units_ids, ['id', 'capacity']));
        }

        $found = false;
        // pass-1 - search for an exact capacity match
        for($i = 0, $n = count($rental_units); $i < $n; ++$i) {
            if($rental_units[$i]['capacity'] == $nb_pers_to_assign) {
                $rental_units = [$rental_units[$i]];
                $found = true;
                break;
            }
        }
        // pass-2 - no exact match: choose between min matching capacity and spreading pers across units
        if(!$found && count($rental_units)) {
            // handle special case : smallest rental unit has bigger capacity than nb_pers
            if($nb_pers_to_assign < $rental_units[$n-1]['capacity']) {
                $rental_units = [$rental_units[$n-1]];
            }
            else {
                $i = 0;
                while($rental_units[$i]['capacity'] > $nb_pers_to_assign) {
                    // we should reach $n-2 at maximum
                    ++$i;
                }
                $alternate_index = $i-1;
                $alternate = 0;
                if($alternate_index >= 0) {
                    $rental_unit = $rental_units[$alternate_index];
                    $alternate = $rental_unit['capacity'];
                }

                $collect = [];
                $list = array_slice($rental_units, $i);

                $combinations = self::_getRentalUnitsCombinations($list, $nb_pers_to_assign, 0, 0, $collect);

                if(count($combinations)) {
                    $min_index = -1;
                    // $D = abs($alternate - $nb_pers);
                    // favour a single accomodation
                    $D = abs($alternate - $nb_pers_to_assign) / 2;

                    foreach($combinations as $index => $combination) {
                        // $R = floor($nb_pers / count($combination));
                        $R = count($combination);

                        if($R <= $D) {
                            if($min_index >= 0) {
                                if(count($combinations[$min_index]) > count($combination)) {
                                    $min_index = $index;
                                }
                            }
                            else {
                                $min_index = $index;
                            }
                        }
                    }
                    // we found at least one combination
                    if($min_index >= 0) {
                        $rental_units = $combinations[$min_index];
                    }
                    else if($alternate_index >= 0) {
                        $rental_units = [$rental_units[$alternate_index]];
                    }
                    else {
                        $rental_units = [];
                    }
                }
                else {
                    $rental_units = [];
                }
            }
        }
        return $rental_units;
    }


    /**
     * This method is used to remove all SPM relating to 'accomodation' product model no longer present in sojourn lines.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return void
     */
    public static function updateSPM($om, $oids, $values=[], $lang='en') {
        $groups = $om->read(self::getType(), $oids, ['booking_lines_ids', 'sojourn_product_models_ids']);
        if($groups > 0 && count($groups)) {

            foreach($groups as $gid => $group) {
                $spms = $om->read(SojournProductModel::getType(), $group['sojourn_product_models_ids'], ['is_accomodation', 'product_model_id']);
                $lines = $om->read(\sale\booking\BookingLine::getType(), $group['booking_lines_ids'], ['id', 'is_accomodation', 'product_model_id']);

                // ignore lines that are about to be deleted, if any
                if(isset($values['deleted'])) {
                    $lines = array_filter($lines, function ($a) use($values) { return !in_array($a['id'], $values['deleted']);} );
                }

                foreach($spms as $sid => $spm) {
                    // #memo - all rental units must be handled, even non-accomodation (ex.: meeting rooms)
                    /*
                    // ignore non-accomodation spm
                    if(!$spm['is_accomodation']) {
                        continue;
                    }
                    */
                    foreach($lines as $lid => $line) {
                        if($line['product_model_id'] == $spm['product_model_id']) {
                            continue 2;
                        }
                    }
                    $om->delete(SojournProductModel::getType(), $sid, true);
                }
            }
        }
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Resets `total` and `price` computed fields.
     */
    public static function refreshPrice($om, $id) {
        $om->update(self::getType(), $id, ['total' => null, 'price' => null, 'fare_benefit' => null]);
    }

    /**
     * This applies only to groups (sojourns) that act as a single product (i.e. locked pack with own price)
     *
     * Attempts to assign a 'price_id' on a given group, and resets 'vat_rate' and 'unit_price'.
     * This applies only to groups marked as Pack (`has_pack`).
     *
     * Notes:
     *  - The selected price list might be marked as 'pending' / to be confirmed).
     *  - After a call to this method, `refreshIsTbc()` should be applied on the Parent Booking.
     */
    public static function refreshPriceId($om, $id) {
        $groups = $om->read(self::getType(), $id, [
            'has_pack',
            'date_from',
            'pack_id',
            'pack_id.product_model_id.has_own_price',
            'booking_id',
            'booking_id.center_id.price_list_category_id'
        ]);

        if($groups <= 0) {
            return;
        }

        $group = reset($groups);

        // price_id is only relevant for packs
        if(!$group['has_pack']) {
            return;
        }

        // ignore if pack is not marked as having its own price
        if(!isset($group['pack_id.product_model_id.has_own_price']) || !$group['pack_id.product_model_id.has_own_price']) {
            return;
        }

        /*
            Find the Price List that matches the criteria from the booking with the shortest duration
        */
        $price_lists_ids = $om->search(
            'sale\price\PriceList', [
                [
                    ['price_list_category_id', '=', $group['booking_id.center_id.price_list_category_id']],
                    ['date_from', '<=', $group['date_from']],
                    ['date_to', '>=', $group['date_from']],
                    ['status', 'in', ['pending', 'published']]
                ],
                // #todo - quick workaround for inclusion of GA pricelist
                [
                    ['price_list_category_id', '=', 9],
                    ['date_from', '<=', $group['date_from']],
                    ['date_to', '>=', $group['date_from']],
                    ['status', 'in', ['pending', 'published']]
                ]
            ],
            ['duration' => 'asc']
        );

        $selected_price_id = 0;

        /*
            Search for a matching Price within the found Price Lists
        */
        if($price_lists_ids > 0 && count($price_lists_ids)) {
            // check status and, if 'pending', evaluate if there is a 'published' alternative
            $price_lists = $om->read(\sale\price\PriceList::getType(), $price_lists_ids, [ 'status' ]);

            foreach($price_lists as $price_list_id => $price_list) {
                $prices_ids = $om->search(\sale\price\Price::getType(), [ ['price_list_id', '=', $price_list_id], ['product_id', '=', $group['pack_id']] ]);
                if($prices_ids > 0 && count($prices_ids)) {
                    $selected_price_id = reset($prices_ids);
                    if($price_list['status'] != 'pending') {
                        // first matching published price is always preferred: stop searching
                        break;
                    }
                    // keep on looping until we reach end of candidates or we find one with status 'published'
                }
            }
        }
        else {
            $date = date('Y-m-d', $group['date_from']);
            trigger_error("ORM::no price list candidates for group pack {$group['pack_id']} for date {$date}", QN_REPORT_WARNING);
        }

        if($selected_price_id > 0) {
            // assign found Price to current group
            $om->update(self::getType(), $id, ['price_id' => $selected_price_id, 'vat_rate' => null, 'unit_price' => null]);
        }
        else {
            $om->update(self::getType(), $id, ['price_id' => null, 'vat_rate' => 0.00, 'unit_price' => 0.00]);
            $date = date('Y-m-d', $group['date_from']);
            trigger_error("ORM::no matching price found for group pack {$group['pack_id']} for date {$date}", QN_REPORT_WARNING);
        }

    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Resets the nb_night computed field.
     */
    public static function refreshNbNights($om, $id) {
        $om->update(self::getType(), $id, ['nb_nights' => null]);
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Attempts to assign `is_sojourn` and `is_event` to consistent values, based on `group_type`.
     */
    public static function refreshType($om, $id) {
        $groups = $om->read(self::getType(), $id, ['group_type']);

        if($groups <= 0) {
            return;
        }

        $group = reset($groups);
        $values = [];

        if($group['group_type'] == 'simple') {
            $values['is_sojourn'] = false;
            $values['is_event'] = false;
        }
        elseif($group['group_type'] == 'sojourn' || $group['group_type'] == 'camp') {
            $values['is_sojourn'] = true;
            $values['is_event'] = false;
        }
        elseif($group['group_type'] == 'event') {
            $values['is_sojourn'] = false;
            $values['is_event'] = true;
        }

        $om->update(self::getType(), $id, $values);
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * @param ObjectManager $om
     * @param int           $id id of the group
     */
    public static function refreshMeals($om, $id) {
        /*
        For all bookingLines of type meal (is_meal & is_snack), we check if a bookingMeal exists for this group (for this reservation) and for the corresponding time_slot, for each date of the stay.
            If not yet: we create a bookingMeal
            (the line is linked to the bookingMeal via the booking_meals_ids relation)
            (there can be multiple meal products for the same time slot, as variations of the same model [variation based on age group or other criteria])
        */

        $groups = $om->read(self::getType(), $id, ['booking_id', 'date_from', 'date_to', 'booking_lines_ids']);
        if($groups <= 0) {
            return;
        }
        $group = reset($groups);

        $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], [
            'is_meal',
            'is_snack',
            'time_slot_id',
            'qty',
            'qty_vars',
            'product_model_id.schedule_offset',
            'product_model_id.is_repeatable',
            'booking_line_group_id.nb_pers',
            'product_id.has_age_range',
            'booking_line_group_id.has_pack',
            'booking_line_group_id.pack_id.has_age_range',
            'booking_line_group_id.age_range_assignments_ids',
            'product_id.age_range_id'
        ]);
        if(empty($lines)) {
            // no need of meal if no booking lines
            $booking_meals_ids = $om->search(BookingMeal::getType(), ['booking_line_group_id', '=', $id]);
            $om->delete(BookingMeal::getType(), $booking_meals_ids, true);
            return;
        }

        $map_timeslots_ids = [];

        $map_date_timeslot_meal = [];
        foreach($lines as $line_id => $line) {
            if(!$line['is_meal'] && !$line['is_snack']) {
                continue;
            }

            $map_timeslots_ids[$line['time_slot_id']] = true;

            $day_index = 0;
            $days_qty = (($group['date_to'] - $group['date_from']) / 86400) + 1;
            for($date = $group['date_from']; $date <= $group['date_to']; $date += 86400) {
                $is_self_provided = true;
                if($line['product_model_id.is_repeatable']) {
                    $qty_vars = json_decode($line['qty_vars']);
                    if($qty_vars && $day_index >= $line['product_model_id.schedule_offset']) {
                        $nb_pers = $line['booking_line_group_id.nb_pers'];
                        if($line['product_id.has_age_range'] && !($line['booking_line_group_id.has_pack'] && $line['booking_line_group_id.pack_id.has_age_range'])) {
                            $age_range_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $line['booking_line_group_id.age_range_assignments_ids'], ['age_range_id', 'qty']);
                            foreach($age_range_assignments as $assignment) {
                                if($assignment['age_range_id'] == $line['product_id.age_range_id']) {
                                    $nb_pers = $assignment['qty'];
                                    break;
                                }
                            }
                        }

                        $variation = $qty_vars[$day_index - $line['product_model_id.schedule_offset']] ?? -$nb_pers;
                        if(($nb_pers + $variation) > 0) {
                            $is_self_provided = false;
                        }
                    }
                }
                elseif($line['qty'] > 0) {
                    if($line['product_model_id.schedule_offset'] >= 0) {
                        if($line['product_model_id.schedule_offset'] === $day_index) {
                            $is_self_provided = false;
                        }
                    }
                    else {
                        if(($days_qty + $line['product_model_id.schedule_offset']) === $day_index) {
                            $is_self_provided = false;
                        }
                    }
                }

                $day_index++;

                if(!isset($map_date_timeslot_meal[$date][$line['time_slot_id']])) {
                    $map_date_timeslot_meal[$date][$line['time_slot_id']] = [
                        'booking_id'            => $group['booking_id'],
                        'booking_line_group_id' => $id,
                        'booking_lines_ids'     => !$is_self_provided ? [$line_id] : [],
                        'date'                  => $date,
                        'time_slot_id'          => $line['time_slot_id'],
                        'is_self_provided'      => $is_self_provided
                    ];
                }
                elseif(!$is_self_provided) {
                    $map_date_timeslot_meal[$date][$line['time_slot_id']]['booking_lines_ids'][] = $line_id;
                    $map_date_timeslot_meal[$date][$line['time_slot_id']]['is_self_provided'] = false;
                }
            }
        }

        foreach($map_date_timeslot_meal as $date => $map_timeslot_meal) {
            foreach($map_timeslot_meal as $time_slot_id => $meal) {
                $meals_ids = $om->search(BookingMeal::getType(), [
                    ['booking_line_group_id', '=', $meal['booking_line_group_id']],
                    ['time_slot_id', '=', $time_slot_id],
                    ['date', '=', $date]
                ]);

                if(!count($meals_ids)) {
                    $om->create(BookingMeal::getType(), $meal);
                }
                else {
                    $om->update(BookingMeal::getType(), $meals_ids, $meal);
                }
            }
        }

        $outside_dates_meals_ids = $om->search(BookingMeal::getType(), [
                [['booking_line_group_id', '=', $id], ['date', '<', $group['date_from']]],
                [['booking_line_group_id', '=', $id], ['date', '>', $group['date_to']]]
            ]);

        $non_existing_timeslots_ids = [];
        $timeslot_ids = array_keys($map_timeslots_ids);
        if(!empty($timeslot_ids)) {
            $non_existing_timeslots_ids = $om->search(BookingMeal::getType(), [
                ['booking_line_group_id', '=', $id],
                ['time_slot_id', 'not in', $timeslot_ids]
            ]);
        }

        $meals_to_delete_ids = array_merge($outside_dates_meals_ids, $non_existing_timeslots_ids);
        if(!empty($meals_to_delete_ids)) {
            $om->delete(BookingMeal::getType(), $meals_to_delete_ids, true);
        }
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Modifies date of the group's meals to match new date of the group
     *
     * @param ObjectManager $om
     * @param int           $id         id of the group
     * @param int           $dates_diff difference between the new date_from and the old one ($new_date_from - $old_date_from)
     */
    public static function refreshMealsDates($om, $id, $dates_diff) {
        $groups = $om->read(self::getType(), $id, ['booking_meals_ids']);

        if($groups <= 0) {
            return;
        }

        $group = reset($groups);

        $meals = $om->read(BookingMeal::getType(), $group['booking_meals_ids'], [
            'date'
        ]);

        if($meals <= 0) {
            return;
        }

        foreach($meals as $meal_id => $meal) {
            $shifted_meal_date = $meal['date'] + $dates_diff;

            $om->update(BookingMeal::getType(), $meal_id, [
                'date' => $shifted_meal_date
            ]);
        }

        self::refreshMeals($om, $id);
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Resets lines according to PackLines related to assigned pack_id, according to `pack_id`.
     * This only applies to groups marked as Pack (`has_pack`).
     */
    public static function refreshPack($om, $id) {
        trigger_error("ORM::calling sale\booking\BookingLineGroup:updatePack", QN_REPORT_DEBUG);

        $groups = $om->read(self::getType(), $id, [
            'booking_id',
            'booking_lines_ids',
            'age_range_assignments_ids',
            'nb_pers',
            'has_pack',
            'rate_class_id',
            'pack_id.is_locked',
            'pack_id.has_age_range',
            'pack_id.age_range_id',
            'pack_id.pack_lines_ids',
            'pack_id.product_model_id.has_own_price',
            'pack_id.product_model_id.booking_type_id.code'
        ]);

        if($groups <= 0) {
            return;
        }

        $group = reset($groups);

        // this is only relevant for groups marked as Pack
        if(!$group['has_pack']) {
            return;
        }

        // 1) Update current group according to selected pack

        // might need to update price_id
        if($group['pack_id.product_model_id.has_own_price']) {
            $om->update(self::getType(), $id, ['is_locked' => true]);
        }
        else {
            $om->update(self::getType(), $id, ['is_locked' => $group['pack_id.is_locked'] ]);
        }

        // retrieve the composition of the pack
        $pack_lines = $om->read('sale\catalog\PackLine', $group['pack_id.pack_lines_ids'], [
            'child_product_model_id',
            'has_own_qty',
            'own_qty',
            'has_own_duration',
            'own_duration',
            'child_product_model_id.qty_accounting_method'
        ]);

        $pack_product_models_ids = array_map(function($a) {return $a['child_product_model_id'];}, $pack_lines);

        // remove booking lines that are part of the pack (others might have been added manually, we leave them untouched)
        $booking_lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], ['product_id.product_model_id']);
        if($booking_lines > 0) {
            $filtered_lines_ids = [];
            foreach($booking_lines as $lid => $line) {
                if(in_array($line['product_id.product_model_id'], $pack_product_models_ids) ) {
                    $filtered_lines_ids[] = $lid;
                }
            }
            // remove existing booking_lines (updating booking_lines_ids will trigger ondetach events)
            $om->update(self::getType(), $id, ['booking_lines_ids' => array_map(function($a) { return "-$a";}, $filtered_lines_ids)]);
        }


        // 2) Create booking lines according to pack composition.

        $order = 1;
        $children_age_range_id = 0;

        // retrieve age_range assignments (there must be at least one)
        $age_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], ['age_range_id']);

        if($age_assignments < 0) {
            $age_assignments = [];
        }

        // #todo - temporary solution - remove and deprecate
        if($group['pack_id.has_age_range'] && isset($group['pack_id.age_range_id'])) {
            $age_assignments = ['age_range_id' => $group['pack_id.age_range_id']];
        }

        // special case for school sojourn (#kaleo)
        if($group['pack_id.product_model_id.booking_type_id.code'] == 'SEJ') {
            foreach($age_assignments as $age_assignment) {
                if($age_assignment['age_range_id'] != 1) {
                    $children_age_range_id = $age_assignment['age_range_id'];
                    break;
                }
            }
        }

        $new_lines_ids = [];
        // associative array mapping product_model_id with price_id
        $map_prices = [];

        // pass-1 : create lines
        foreach($pack_lines as $pid => $pack_line) {
            /*
                retrieve the product(s) to add, based on child_product_model_id and group age_ranges, if set
                if no specific product with age_range, use nb_pers
                if no product for a specific age_range, use "all age" product
            */
            // we expect any group to have at min. 1 age range (default)
            foreach($age_assignments as $age_assignment) {

                $line = [
                    'order'                     => $order++,
                    'qty_accounting_method'     => $pack_line['child_product_model_id.qty_accounting_method']
                ];

                // handle products with no age_range (group must have only one line for those)
                $product_id = null;
                $has_single_range = false;
                $age_range_id = $age_assignment['age_range_id'];

                $base_domain = [
                    ['product_model_id', '=', $pack_line['child_product_model_id']],
                    ['can_sell', '=', true],
                ];

                // build list of domains to try, ordered by priority
                $domains_to_try = [];

                // a) product with specific age range AND matching rate class
                $domains_to_try[] = array_merge($base_domain, [
                        ['age_range_id', '=', $age_range_id],
                        ['rate_class_id', '=', $group['rate_class_id']]
                    ]);

                // b) product with specific age range but no rate class requirement
                $domains_to_try[] = array_merge($base_domain, [
                        ['age_range_id', '=', $age_range_id],
                    ]);

                // c) product without age range AND matching rate class
                $domains_to_try[] = array_merge($base_domain, [
                        ['has_age_range', '=', false],
                        ['rate_class_id', '=', $group['rate_class_id']]
                    ]);

                // d) product without age range and no rate class requirement
                $domains_to_try[] = array_merge($base_domain, [
                        ['has_age_range', '=', false]
                    ]);

                // try each domain until a matching product is found
                foreach($domains_to_try as $domain) {
                    $products_ids = $om->search('sale\catalog\Product', $domain);
                    if(is_array($products_ids) && count($products_ids)) {
                        $product_id = reset($products_ids);
                        // Check if fallback to "all ages" product was used
                        if (in_array(['has_age_range', '=', false], $domain)) {
                            $has_single_range = true;
                        }
                        break;
                    }
                }

                // no product found: issue a warning and skip
                if(!$product_id) {
                    trigger_error("ORM::no match for age range {$age_range_id} and no 'all ages' product found for model {$pack_line['child_product_model_id']}", QN_REPORT_WARNING);
                    continue;
                }

                // create a booking line with found product
                $line['product_id'] = $product_id;

                if($pack_line['has_own_qty']) {
                    $line['has_own_qty'] = true;
                    $line['qty'] = $pack_line['own_qty'];
                }
                if($pack_line['has_own_duration']) {
                    $line['has_own_duration'] = true;
                    $line['own_duration'] = $pack_line['own_duration'];
                }
                $lid = $om->create(BookingLine::getType(), [
                    'booking_id'                => $group['booking_id'],
                    'booking_line_group_id'     => $id,
                ]);

                if($lid > 0) {
                    $new_lines_ids[] = $lid;
                    // #memo - price_id and qty are auto assigned upon line assignation to a product
                    $om->update(BookingLine::getType(), $lid, $line);
                    $om->update(self::getType(), $id, ['booking_lines_ids' => ["+$lid"] ]);
                    // #kaleo - special case for school sojourns (adults use children prices)
                    if($age_range_id == $children_age_range_id) {
                        $lines = $om->read(BookingLine::getType(), $lid, ['price_id']);
                        $line = reset($lines);
                        $map_prices[$pack_line['child_product_model_id']] = $line['price_id'];
                    }
                }
                // do not loop to other age ranges
                if($has_single_range) {
                    break;
                }
            }
        }

        // #kaleo - special case for school sojourns
        // pass-2 : for school sojourns only - update price_id of the lines according to their product model (adults use children prices)
        if($group['pack_id.product_model_id.booking_type_id.code'] == 'SEJ') {
            $lines = $om->read(BookingLine::getType(), $new_lines_ids, ['product_model_id', 'price_id']);
            foreach($lines as $lid => $line) {
                if(isset($map_prices[$line['product_model_id']]) && $line['price_id'] != $map_prices[$line['product_model_id']]) {
                    $om->update(BookingLine::getType(), $lid, ['price_id' => $map_prices[$line['product_model_id']] ]);
                }
            }
        }

    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Attempts to assign Group `time_from` and `time_to` based on ProductModels relating to BookingLines marked as rental units.
     */
    public static function refreshTime($om, $id) {
        $groups = $om->read(self::getType(), $id, ['booking_lines_ids']);
        if($groups <= 0) {
            return;
        }

        $group = reset($groups);

        $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], [
                'is_rental_unit',
                'product_id.product_model_id'
            ]);

        foreach($lines as $lid => $line) {

            // if line is a rental unit, use its related product info to update parent group schedule, if possible
            if($line['is_rental_unit']) {
                $models = $om->read(ProductModel::getType(), $line['product_id.product_model_id'], ['type', 'service_type', 'schedule_type', 'schedule_default_value']);
                if($models <= 0 ) {
                    continue;
                }
                $model = reset($models);
                if($model['type'] == 'service' && $model['service_type'] == 'schedulable' && $model['schedule_type'] == 'timerange') {
                    // retrieve relative timestamps
                    $schedule = $model['schedule_default_value'];
                    if(strlen($schedule)) {
                        $times = explode('-', $schedule);
                        $parts = explode(':', $times[0]);
                        $schedule_from = ($parts[0] * 3600) + ($parts[1] * 60);
                        $parts = explode(':', $times[1]);
                        $schedule_to = ($parts[0] * 3600) + ($parts[1] * 60);
                        // update the parent group schedule
                        $om->update(self::getType(), $id, ['time_from' => $schedule_from, 'time_to' => $schedule_to]);
                        break;
                    }
                }
            }
        }
    }


    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Resets the Age range assignments to a single assignment (adults) according to nb_pers.
     */
    public static function refreshAgeRangeAssignments($om, $id) {
        $groups = $om->read(self::getType(), $id, ['booking_id', 'nb_pers', 'is_sojourn', 'age_range_assignments_ids', 'age_range_assignments_ids.age_range_id', 'age_range_assignments_ids.age_from', 'age_range_assignments_ids.age_to']);
        if($groups <= 0) {
            return;
        }

        $group = reset($groups);

        if($group['is_sojourn']) {
            // remove any previously set assignments
            $om->delete(BookingLineGroupAgeRangeAssignment::getType(), $group['age_range_assignments_ids'], true);
            // reset nb_children
            $om->update(self::getType(), $id, ['nb_children' => null]);

            $age_from = $age_to = null;
            if(count($group['age_range_assignments_ids']) === 1) {
                // keep previous age range if only one
                $age_range_id = array_values($group['age_range_assignments_ids.age_range_id'])[0]['age_range_id'];
                $age_from = array_values($group['age_range_assignments_ids.age_from'])[0]['age_from'];
                $age_to = array_values($group['age_range_assignments_ids.age_to'])[0]['age_to'];
            }
            else {
                // else use default 'adult' age range from setting
                $age_range_id = Setting::get_value('sale', 'organization', 'age_range_default', 1);
            }

            // create age_range assignment
            $assignment = [
                'age_range_id'          => $age_range_id,
                'booking_line_group_id' => $id,
                'booking_id'            => $group['booking_id'],
                'qty'                   => $group['nb_pers']
            ];
            if(!is_null($age_from)) {
                $assignment['age_from'] = $age_from;
            }
            if(!is_null($age_to)) {
                $assignment['age_to'] = $age_to;
            }
            $om->create(BookingLineGroupAgeRangeAssignment::getType(), $assignment);
        }
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Creates Booking lines for auto sale products (scope 'group'), based on AutosaleList associated to the Center.
     */
    public static function refreshAutosaleProducts($om, $id) {
        /*
            re-create lines related to autosales
        */
        $groups = $om->read(self::getType(), $id, [
                'is_autosale',
                'is_sojourn',
                'nb_pers',
                'nb_children',
                'nb_nights',
                'date_from',
                'date_to',
                'booking_id',
                'has_pack',
                'rate_class_id',
                'pack_id.product_model_id.booking_type_id.code',
                'booking_id.center_id.autosale_list_category_id',
                'booking_id.customer_id',
                'booking_id.center_id',
                'booking_id.customer_id.count_booking_12',
                'booking_lines_ids'
            ]);

        if($groups <= 0) {
            return;
        }

        $group = reset($groups);

        $center = Center::id($group['booking_id.center_id'])->read(['has_citytax_school'])->first(true);

        // reset previously set autosale products
        $lines_ids_to_delete = [];
        $booking_lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], ['is_autosale']);
        if($booking_lines > 0) {
            foreach($booking_lines as $lid => $line) {
                if($line['is_autosale']) {
                    $lines_ids_to_delete[] = -$lid;
                }
            }
            $om->update(self::getType(), $id, ['booking_lines_ids' => $lines_ids_to_delete]);
        }

        // autosale groups are handled at the Booking level
        if($group['is_autosale']) {
            return;
        }
        // autosales only apply on sojourns
        if(!$group['is_sojourn']) {
            return;
        }

        /*
            Find the first Autosale List that matches the booking dates
        */

        $autosale_lists_ids = $om->search('sale\autosale\AutosaleList', [
            ['autosale_list_category_id', '=', $group['booking_id.center_id.autosale_list_category_id']],
            ['date_from', '<=', $group['date_from']],
            ['date_to', '>=', $group['date_from']]
        ]);

        $autosale_lists = $om->read('sale\autosale\AutosaleList', $autosale_lists_ids, ['id', 'autosale_lines_ids']);
        $autosale_list_id = 0;
        $autosale_list = null;
        if($autosale_lists > 0 && count($autosale_lists)) {
            // use first match (there should always be only one or zero)
            $autosale_list = array_pop($autosale_lists);
            $autosale_list_id = $autosale_list['id'];
            trigger_error("ORM:: match with autosale List {$autosale_list_id}", QN_REPORT_DEBUG);
        }
        else {
            trigger_error("ORM:: no autosale List found", QN_REPORT_DEBUG);
        }
        /*
            Search for matching Autosale products within the found List
        */
        if($autosale_list_id) {
            $operands = [];

            // for now, we only support member cards for customer that haven't booked a service for more thant 12 months

            $operands['count_booking_12'] = self::computeCountBooking12($om, $group['booking_id'], $group['booking_id.customer_id'], $group['date_from']);
            $operands['nb_pers'] = $group['nb_pers'];
            $operands['nb_nights'] = $group['nb_nights'];
            $operands['nb_adults'] = $group['nb_pers'] - $group['nb_children'];

            $autosales = $om->read('sale\autosale\AutosaleLine', $autosale_list['autosale_lines_ids'], [
                'product_id.id',
                'product_id.name',
                'product_id.sku',
                'has_own_qty',
                'qty',
                'scope',
                'rate_class_id',
                'conditions_ids'
            ]);

            // filter discounts based on related conditions
            $products_to_apply = [];

            // pass-1: filter discounts to be applied on booking lines
            foreach($autosales as $autosale_id => $autosale) {
                if($autosale['scope'] != 'group') {
                    continue;
                }
                if(isset($autosale['rate_class_id']) && $group['rate_class_id'] !== $autosale['rate_class_id']) {
                    continue;
                }
                // #kaleo - do not apply city tax for school sojourns
                if( $group['has_pack']
                    && isset($group['pack_id.product_model_id.booking_type_id.code'])
                    && $group['pack_id.product_model_id.booking_type_id.code'] == 'SEJ'
                    && $autosale['product_id.sku'] == 'KA-CTaxSej-A'
                    && !$center['has_citytax_school']
                ) {
                    continue;
                }

                $conditions = $om->read('sale\autosale\Condition', $autosale['conditions_ids'], ['operand', 'operator', 'value']);
                $valid = true;
                foreach($conditions as $c_id => $condition) {
                    if(!in_array($condition['operator'], ['>', '>=', '<', '<=', '='])) {
                        // unknown operator
                        continue;
                    }
                    $operator = $condition['operator'];
                    if($operator == '=') {
                        $operator = '==';
                    }
                    if(!isset($operands[$condition['operand']])) {
                        $valid = false;
                        break;
                    }
                    $operand = $operands[$condition['operand']];
                    $value = $condition['value'];
                    if(!is_numeric($operand)) {
                        $operand = "'$operand'";
                    }
                    if(!is_numeric($value)) {
                        $value = "'$value'";
                    }
                    trigger_error(" testing {$operand} {$operator} {$value}", QN_REPORT_DEBUG);
                    $valid = $valid && (bool) eval("return ( {$operand} {$operator} {$value});");
                    if(!$valid) {
                        break;
                    }
                }
                if($valid) {
                    trigger_error("ORM:: all conditions fullfilled", QN_REPORT_DEBUG);
                    $products_to_apply[$autosale_id] = [
                        'id'            => $autosale['product_id.id'],
                        'name'          => $autosale['product_id.name'],
                        'has_own_qty'   => $autosale['has_own_qty'],
                        'qty'           => $autosale['qty']
                    ];
                }
            }

            // pass-2: apply all applicable products
            $count = count($products_to_apply);

            if($count) {
                // add all applicable products at the end of the group
                $order = 1000;
                foreach($products_to_apply as $autosale_id => $product) {
                    $line = [
                        'order'                     => $order++,
                        'booking_id'                => $group['booking_id'],
                        'booking_line_group_id'     => $id,
                        'is_autosale'               => true,
                        'has_own_qty'               => $product['has_own_qty']
                    ];
                    $line_id = $om->create(BookingLine::getType(), $line);
                    // set product_id (#memo - we're in a refresh method called with disabled events - this does not trigger recompute)
                    $om->update(BookingLine::getType(), $line_id, ['product_id' => $product['id']]);
                    $booking_line =  BookingLine::id($line_id)->read(['product_id'=>['id','sku']])->first(true);
                    $has_specific_city_tax_calculation = Setting::get_value('sale', 'organization', 'has_specific_city_tax_calculation', 0);
                    $city_tax_sku = Setting::get_value('sale', 'organization', 'sku.city_tax');
                    if ($has_specific_city_tax_calculation && $city_tax_sku == $booking_line['product_id']['sku'] ){
                        $om->update(BookingLine::getType(), $line_id, ['qty' => $group['nb_nights'] *  ( $group['nb_pers'] - $group['nb_children'])]);
                    }
                    BookingLine::refreshPriceId($om, $line_id);
                    // read the resulting product
                    $lines = $om->read(BookingLine::getType(), $line_id, ['price_id', 'price_id.price']);
                    // prevent adding autosale products for which a price could not be retrieved (invoices with lines without accounting rule are invalid)
                    if($lines > 0 && count($lines)) {
                        $line = reset($lines);
                        if(!isset($line['price_id']) || is_null($line['price_id']) || $line['price_id.price'] <= 0.01) {
                            $om->delete(BookingLine::getType(), $line_id, true);
                        }
                    }
                }
            }
        }
        else {
            $date = date('Y-m-d', $group['date_from']);
            trigger_error("ORM::no matching autosale list found for date {$date}", QN_REPORT_DEBUG);
        }

    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Cascade calls of `refresh[...]()` methods (price_id, qty and price) for all lines present in the group/sojourn.
     */
    public static function refreshLines($om, $id) {
        $groups = $om->read(self::getType(), $id, [
            'booking_lines_ids'
        ]);

        if($groups <= 0) {
            return;
        }

        $group = reset($groups);

        foreach($group['booking_lines_ids'] as $line_id) {
            BookingLine::refreshPriceId($om, $line_id);
            BookingLine::refreshFreeQty($om, $line_id);
            BookingLine::refreshQty($om, $line_id);
            BookingLine::refreshPrice($om, $line_id);
        }
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Create or update a single meal preferences according to nb_pers.
     */
    public static function refreshMealPreferences($om, $id) {

        $groups = $om->read(self::getType(), $id, [
            'is_sojourn',
            'is_event',
            'nb_pers',
            'meal_preferences_ids'
        ]);

        if($groups <= 0) {
            return;
        }

        $group = reset($groups);


        if($group['is_sojourn'] || $group['is_event']) {
            if(count($group['meal_preferences_ids']) == 0)  {
                // create a meal preference
                $pref = [
                    'booking_line_group_id'     => $id,
                    'qty'                       => $group['nb_pers'],
                    'type'                      => '2_courses',
                    'pref'                      => 'regular'
                ];
                $om->create('sale\booking\MealPreference', $pref);
            }
            elseif(count($group['meal_preferences_ids']) == 1)  {
                $om->update('sale\booking\MealPreference', $group['meal_preferences_ids'], ['qty' => $group['nb_pers']]);
            }
        }


    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Resets all PriceAdapters for the group itself and its lines.
     */
    public static function refreshPriceAdapters($om, $id) {
        /*
            Remove all previous price adapters that were automatically created
        */
        $price_adapters_ids = $om->search('sale\booking\BookingPriceAdapter', [['booking_line_group_id', 'in', $id], ['is_manual_discount', '=', false]]);

        $om->delete('sale\booking\BookingPriceAdapter', $price_adapters_ids, true);

        $groups = $om->read(self::getType(), $id, [
            'has_pack',
            'pack_id.allow_price_adaptation',
            'rate_class_id',
            'sojourn_type_id',
            'sojourn_type_id.season_category_id',
            'date_from',
            'date_to',
            'nb_pers',
            'nb_children',
            'nb_nights',
            'booking_id',
            'is_locked',
            'booking_lines_ids',
            'booking_id.nb_pers',
            'booking_id.customer_id',
            'booking_id.center_id.season_category_id',
            'booking_id.center_id.discount_list_category_id',
            'booking_id.center_office_id'
        ]);

        if($groups <= 0) {
            return;
        }

        $group = reset($groups);


        if($group['has_pack']) {
            if(!$group['pack_id.allow_price_adaptation']) {
                // skip group if it relates to a product model that prohibits price adaptation
                return;
            }
        }

        /*
            Read required preferences from the Center Office
        */
        $freebies_manual_assignment = false;
        $offices_preferences = $om->read(\identity\CenterOffice::getType(), $group['booking_id.center_office_id'], ['freebies_manual_assignment']);
        if($offices_preferences > 0 && count($offices_preferences)) {
            $prefs = reset($offices_preferences);
            $freebies_manual_assignment = (bool) $prefs['freebies_manual_assignment'];
        }

        /*
            Find the first Discount List that matches the booking dates
        */

        // the discount list category to use is the one defined for the center, unless it is ('GA' or 'GG') AND sojourn_type <> category.name
        $discount_category_id = $group['booking_id.center_id.discount_list_category_id'];

        if(in_array($discount_category_id, [1 /*GA*/, 2 /*GG*/]) && $discount_category_id != $group['sojourn_type_id']) {
            $discount_category_id = $group['sojourn_type_id'];
        }

        $discount_lists_ids = $om->search('sale\discount\DiscountList', [
            ['rate_class_id', '=', $group['rate_class_id']],
            ['discount_list_category_id', '=', $discount_category_id],
            ['valid_from', '<=', $group['date_from']],
            ['valid_until', '>=', $group['date_from']]
        ]);

        $discount_lists = $om->read('sale\discount\DiscountList', $discount_lists_ids, ['id', 'discounts_ids', 'rate_min', 'rate_max']);
        $discount_list_id = 0;
        $discount_list = null;
        if($discount_lists > 0 && count($discount_lists)) {
            // use first match (there should always be only one or zero)
            $discount_list = array_pop($discount_lists);
            $discount_list_id = $discount_list['id'];
            trigger_error("ORM:: match with discount List {$discount_list_id}", QN_REPORT_DEBUG);
        }
        else {
            trigger_error("ORM:: no discount List found", QN_REPORT_DEBUG);
        }

        /*
            Search for matching Discounts within the found Discount List
        */
        if($discount_list_id) {
            $count_booking_24 = self::computeCountBooking24($om, $group['booking_id'], $group['booking_id.customer_id'], $group['date_from']);

            $operands = [
                'count_booking_24'  => $count_booking_24,     // qty of customer bookings from 2 years ago to present
                'duration'          => $group['nb_nights'],   // duration in nights
                'nb_pers'           => $group['nb_pers'],     // total number of participants
                'nb_children'       => $group['nb_children'], // number of children amongst participants
                'nb_adults'         => $group['nb_pers'] - $group['nb_children']  // number of adults amongst participants
            ];

            $date = $group['date_from'];

            /*
                Pick up the first season period that matches the year and the season category of the center
            */
            $cat_id = $group['booking_id.center_id.season_category_id'];
            if($cat_id == 2) { // GG
                $cat_id = $group['sojourn_type_id.season_category_id'];
            }

            $year = date('Y', $date);
            $seasons_ids = $om->search('sale\season\SeasonPeriod', [
                ['season_category_id', '=', $cat_id],
                ['date_from', '<=', $group['date_from']],
                ['date_to', '>=', $group['date_from']],
                ['year', '=', $year]
            ]);

            $periods = $om->read('sale\season\SeasonPeriod', $seasons_ids, ['id', 'season_type_id.name']);
            if($periods > 0 && count($periods)){
                $period = array_shift($periods);
                $operands['season'] = $period['season_type_id.name'];
            }

            $discounts = $om->read('sale\discount\Discount', $discount_list['discounts_ids'], ['value', 'type', 'conditions_ids', 'value_max', 'age_ranges_ids']);

            // filter discounts based on related conditions
            $discounts_to_apply = [];
            // keep track of the final rate (for discounts with type 'percent')
            $rate_to_apply = 0;

            // filter discounts to be applied on booking lines
            foreach($discounts as $discount_id => $discount) {
                $conditions = $om->read('sale\discount\Condition', $discount['conditions_ids'], ['operand', 'operator', 'value']);
                $valid = true;
                foreach($conditions as $c_id => $condition) {
                    if(!in_array($condition['operator'], ['>', '>=', '<', '<=', '='])) {
                        // unknown operator
                        continue;
                    }
                    $operator = $condition['operator'];
                    if($operator == '=') {
                        $operator = '==';
                    }
                    if(!isset($operands[$condition['operand']])) {
                        $valid = false;
                        break;
                    }
                    $operand = $operands[$condition['operand']];
                    $value = $condition['value'];
                    if(!is_numeric($operand)) {
                        $operand = "'$operand'";
                    }
                    if(!is_numeric($value)) {
                        $value = "'$value'";
                    }
                    trigger_error(" testing {$operand} {$operator} {$value}", QN_REPORT_DEBUG);
                    $valid = $valid && (bool) eval("return ( {$operand} {$operator} {$value});");
                    if(!$valid) break;
                }
                if($valid) {
                    trigger_error("ORM:: all conditions fulfilled, applying {$discount['value']} {$discount['type']}", QN_REPORT_DEBUG);
                    $discounts_to_apply[$discount_id] = $discount;
                    if($discount['type'] == 'percent') {
                        $rate_to_apply += $discount['value'];
                    }
                }
            }

            // guaranteed rate (rate_min) is always granted
            if($discount_list['rate_min'] > 0.01) {
                $rate_to_apply += $discount_list['rate_min'];
                $discounts_to_apply[0] = [
                    'type'      => 'percent',
                    'value'     => $discount_list['rate_min']
                ];
            }

            // if max rate (rate_max) has been reached, use max instead
            if($rate_to_apply > $discount_list['rate_max'] ) {
                // remove all 'percent' discounts
                foreach($discounts_to_apply as $discount_id => $discount) {
                    if($discount['type'] == 'percent') {
                        unset($discounts_to_apply[$discount_id]);
                    }
                }
                // add a custom discount with maximal rate
                $discounts_to_apply[0] = [
                    'type'      => 'percent',
                    'value'     => $discount_list['rate_max']
                ];
            }

            // apply all applicable discounts on BookingLine Group
            foreach($discounts_to_apply as $discount_id => $discount) {
                /*
                    create price adapter for group only, according to discount and group settings
                    (needed in case group targets a pack with own price)
                */
                $price_adapters_ids = $om->create('sale\booking\BookingPriceAdapter', [
                    'is_manual_discount'    => false,
                    'booking_id'            => $group['booking_id'],
                    'booking_line_group_id' => $id,
                    'booking_line_id'       => 0,
                    'discount_id'           => $discount_id,
                    'discount_list_id'      => $discount_list_id,
                    'type'                  => $discount['type'],
                    'value'                 => $discount['value']
                ]);

                /*
                    create related price adapter for all lines, according to discount and group settings
                */

                // read all lines from group
                $lines = $om->read('sale\booking\BookingLine', $group['booking_lines_ids'], [
                    'product_id',
                    'product_id.product_model_id',
                    'product_id.product_model_id.has_duration',
                    'product_id.product_model_id.duration',
                    'product_id.age_range_id',
                    'is_meal',
                    'is_accomodation',
                    'is_snack'
                ]);

                foreach($lines as $line_id => $line) {
                    // do not apply discount on lines that cannot have a price
                    if($group['is_locked']) {
                        continue;
                    }
                    // do not apply freebies if manual assignment is requested
                    if($discount['type'] == 'freebie' && $freebies_manual_assignment) {
                        continue;
                    }
                    // do not apply discount if it does not concern the product age range
                    if(isset($discount['age_ranges_ids']) && count($discount['age_ranges_ids']) && isset($line['product_id.age_range_id']) && !in_array($line['product_id.age_range_id'], $discount['age_ranges_ids'])) {
                        continue;
                    }
                    if( // for GG: apply discounts only on accommodations
                        (
                            $group['sojourn_type_id'] == 2 /*'GG'*/ && $line['is_accomodation']
                        )
                        ||
                        // for GA: apply discounts on meals, accommodations and snacks
                        (
                            $group['sojourn_type_id'] == 1 /*'GA'*/
                            &&
                            (
                                $line['is_accomodation'] || $line['is_meal'] || $line['is_snack']
                            )
                        )
                    ) {
                        trigger_error("ORM:: creating price adapter", QN_REPORT_DEBUG);
                        $factor = $group['nb_nights'];

                        if($line['product_id.product_model_id.has_duration']) {
                            $factor = $line['product_id.product_model_id.duration'];
                        }

                        $discount_value = $discount['value'];
                        // ceil freebies amount according to value referenced by value_max (nb_pers by default)
                        if($discount['type'] == 'freebie') {
                            if(isset($discount['value_max']) && $discount_value > $operands[$discount['value_max']]) {
                                $discount_value = $operands[$discount['value_max']];
                            }
                            $discount_value *= $factor;
                        }

                        // current discount must be applied on the line: create a price adapter
                        $price_adapters_ids = $om->create('sale\booking\BookingPriceAdapter', [
                            'is_manual_discount'    => false,
                            'booking_id'            => $group['booking_id'],
                            'booking_line_group_id' => $id,
                            'booking_line_id'       => $line_id,
                            'discount_id'           => $discount_id,
                            'discount_list_id'      => $discount_list_id,
                            'type'                  => $discount['type'],
                            'value'                 => $discount_value
                        ]);
                    }
                }
            }

        }
        else {
            $date = date('Y-m-d', $group['date_from']);
            trigger_error("ORM::no matching discount list found for date {$date}", QN_REPORT_DEBUG);
        }

    }

    public static function refreshSPM($om, $id) {
        $groups = $om->read(self::getType(), $id, ['booking_lines_ids', 'sojourn_product_models_ids']);

        if($groups <= 0) {
            return;
        }

        $group = reset($groups);


        $spms = $om->read(SojournProductModel::getType(), $group['sojourn_product_models_ids'], ['is_accomodation', 'product_model_id']);
        $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], ['id', 'is_accomodation', 'product_model_id']);

        foreach($spms as $sid => $spm) {
            // #memo - all rental units must be handled, even non-accommodation (ex.: meeting rooms)
            // if at least one line matches the product model, keep the SPM
            foreach($lines as $line) {
                if($line['product_model_id'] == $spm['product_model_id']) {
                    continue 2;
                }
            }
            // otherwise, remove SPM
            $om->delete(SojournProductModel::getType(), $sid, true);
        }

        // reset qty computed field (required when resulting from a refreshRentalUnitsAssignments)
        $om->update(SojournProductModel::getType(), $group['sojourn_product_models_ids'], ['qty' => null]);

    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Resets all rental unit assignments and process each line for auto-assignment, if possible.
     *
     *   1) decrement nb_pers for lines accounted by 'accomodation' (capacity)
     *   2) create missing SPM
     *
     *  qty_accounting_method = 'accomodation'
     *    (we consider product and unit to have is_accomodation to true)
     *    1) find a free accomodation  (capacity >= product_model.capacity)
     *    2) create assignment @capacity
     *
     *  qty_accounting_method = 'person'
     *  if is_accomodation
     *      1) find a free accomodation
     *      2) create assignment @nb_pers
     *        (ignore next lines accounted by 'person')
     *  otherwise
     *       1) find a free rental unit
     *       2) create assignment @group.nb_pers
     *
     *  qty_accounting_method = 'unit'
     *      1) find a free rental unit
     *      2) create assignment @group.nb_pers
     */
    public static function refreshRentalUnitsAssignments($om, $id) {
        // #memo - this is a merge of createRentalUnitsAssignments and createRentalUnitsAssignmentsFromLines
        /*
            rental-units assignments must be updated when:

            ## adding a booking line (onupdateProductId)
            * create new rental-unit assignments depending on the product_model of the line

            ## removing a booking line (onupdateBookingLinesIds)
            * do a reset of the rental-unit assignments

            ## updating nb_pers (onupdateNbPers) or age range qty fields
            * do a reset of the rental-unit assignments

            ## updating a pack (`onupdatePackId`)
            * reset rental-unit assignments
            * create an assignment for all line at once (_createRentalUnitsAssignements)

            ## removing an age-range (ondelete)
            * remove all lines whose product_id relates to that age-range
        */

        /* find existing SPM (for resetting) */

        $groups = $om->read(self::getType(), $id, [

            'booking_id',
            'booking_id.center_office_id',
            'is_extra',
            'nb_pers',
            'has_locked_rental_units',
            'booking_lines_ids',
            'date_from',
            'date_to',
            'time_from',
            'time_to',
            'sojourn_product_models_ids',
            'rental_unit_assignments_ids'
        ]);

        if($groups <= 0) {
            return;
        }

        $group = reset($groups);


        // retrieve rental unit assignment preference
        $rentalunits_manual_assignment = false;
        $offices_preferences = $om->read(\identity\CenterOffice::getType(), $group['booking_id.center_office_id'], ['rentalunits_manual_assignment']);
        if($offices_preferences > 0 && count($offices_preferences)) {
            $prefs = reset($offices_preferences);
            $rentalunits_manual_assignment = (bool) $prefs['rentalunits_manual_assignment'];
        }

        // ignore groups with explicitly locked rental unit assignments
        if($group['has_locked_rental_units'] && count($group['sojourn_product_models_ids'])) {
            return;
        }

        // #memo - we cannot do that otherwise we loose data
        // remove all previous SPM and rental_unit assignments (cascade)
        // $om->update(self::getType(), $id, ['sojourn_product_models_ids' => array_map(function($a) { return "-$a";}, $group['sojourn_product_models_ids'])]);
        self::refreshSPM($om, $id);

        $nb_pers = $group['nb_pers'];
        $date_from = $group['date_from'] + $group['time_from'];
        $date_to = $group['date_to'] + $group['time_to'];

        // retrieve rental units that are already assigned by other groups within the same time range, if any (we need to withdraw those from available units)
        $booking_assigned_rental_units_ids = [];
        $bookings = $om->read(Booking::getType(), $group['booking_id'], ['booking_lines_groups_ids', 'rental_unit_assignments_ids']);
        if($bookings > 0 && count($bookings)) {
            $booking = reset($bookings);
            $groups = $om->read(self::getType(), $booking['booking_lines_groups_ids'], ['id', 'date_from', 'date_to', 'time_from', 'time_to']);
            $booking_assignments = $om->read(SojournProductModelRentalUnitAssignement::getType(), $booking['rental_unit_assignments_ids'], ['rental_unit_id', 'qty', 'booking_line_group_id']);
            foreach($booking_assignments as $oid => $assignment) {
                // process rental units from other groups
                if($assignment['booking_line_group_id'] != $id) {
                    $group_id = $assignment['booking_line_group_id'];
                    $group_date_from = $groups[$group_id]['date_from'] + $groups[$group_id]['time_from'];
                    $group_date_to = $groups[$group_id]['date_to'] + $groups[$group_id]['time_to'];
                    // if groups have a time range intersection, mark the rental unit as assigned
                    if($group_date_from >= $date_from && $group_date_from <= $date_to
                        || $group_date_to >= $date_from && $group_date_to <= $date_to) {
                        $booking_assigned_rental_units_ids[] = $assignment['rental_unit_id'];
                    }
                }
            }
        }

        // create a map with all product_model_id within the group
        $group_product_models_ids = [];

        $sojourn_product_models = $om->read(SojournProductModel::getType(), $group['sojourn_product_models_ids'], ['product_model_id']);
        foreach($sojourn_product_models as $spid => $spm){
            $group_product_models_ids[$spm['product_model_id']] = $spid;
        }

        // read children booking lines
        $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], [
            'booking_id.center_id',
            'product_id',
            'product_model_id',
            'qty_accounting_method',
            'is_rental_unit'
        ]);

        // drop lines that do not relate to rental units
        $lines = array_filter($lines, function($a) { return $a['is_rental_unit']; });

        if(count($lines)) {

            // read all related product models at once
            $product_models_ids = array_map(function($oid) use($lines) {return $lines[$oid]['product_model_id'];}, array_keys($lines));
            $product_models = $om->read('sale\catalog\ProductModel', $product_models_ids, ['is_accomodation', 'qty_accounting_method', 'rental_unit_assignement', 'capacity']);

            // pass-1 : withdraw persons assigned to units accounted by 'accomodation' from nb_pers, and create SPMs
            foreach($lines as $lid => $line) {
                $product_model_id = $line['product_model_id'];
                if($product_models[$product_model_id]['qty_accounting_method'] == 'accomodation') {
                    $nb_pers -= $product_models[$product_model_id]['capacity'];
                }
                if(!isset($group_product_models_ids[$product_model_id])) {
                    $sojourn_product_model_id = $om->create(SojournProductModel::getType(), [
                        'booking_id'            => $group['booking_id'],
                        'booking_line_group_id' => $id,
                        'product_model_id'      => $product_model_id
                    ]);
                    $group_product_models_ids[$product_model_id] = $sojourn_product_model_id;
                }
            }
        }

        // do not auto-assign rental units if manual assignment is set in prefs
        if(!$rentalunits_manual_assignment) {

            // exclude complex situations (force refresh)
            if(count($group['rental_unit_assignments_ids']) > 1) {
                $om->delete(SojournProductModelRentalUnitAssignement::getType(), $group['rental_unit_assignments_ids'], true);
            }
            elseif(count($group['rental_unit_assignments_ids']) == 1) {
                // group current assignment, if any
                $group_assignments = $om->read(SojournProductModelRentalUnitAssignement::getType(), $group['rental_unit_assignments_ids'], ['id', 'capacity', 'qty']);
                $group_assignment = reset($group_assignments);
                // if capacity of assignment (rental unit) is higher than nb_pers
                if($group_assignment['capacity'] >= $group['nb_pers']) {
                    // assign nb_pers to it
                    $om->update(SojournProductModelRentalUnitAssignement::getType(), $group_assignment['id'], ['qty' => $group['nb_pers']]);
                    // stop processing
                    return;
                }
                else {
                    // remove assignment and refresh (continue)
                    $om->delete(SojournProductModelRentalUnitAssignement::getType(), $group['rental_unit_assignments_ids'], true);
                }
            }

            // read targeted booking lines (received as method param)
            $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], [
                'booking_id.center_id',
                'product_id',
                'product_id.product_model_id',
                'qty_accounting_method',
                'is_rental_unit'
            ]);

            // drop lines that do not relate to rental units
            $lines = array_filter($lines, function($a) { return $a['is_rental_unit']; });

            if(count($lines)) {
                // pass-2 : process lines
                $group_assigned_rental_units_ids = [];
                $has_processed_accomodation_by_person = false;
                foreach($lines as $lid => $line) {

                    $center_id = $line['booking_id.center_id'];

                    $is_accomodation = $product_models[$line['product_id.product_model_id']]['is_accomodation'];
                    // 'accomodation', 'person', 'unit'
                    $qty_accounting_method = $product_models[$line['product_id.product_model_id']]['qty_accounting_method'];

                    // 'category', 'capacity', 'auto'
                    // #memo - the assignment-based filtering is done in `Consumption::getAvailableRentalUnits`
                    $rental_unit_assignment = $product_models[$line['product_id.product_model_id']]['rental_unit_assignement'];

                    // all lines with same product_model are processed at the first line, remaining lines must be ignored
                    if($qty_accounting_method == 'person' && $is_accomodation && $has_processed_accomodation_by_person) {
                        continue;
                    }

                    $nb_pers_to_assign = $nb_pers;

                    if($qty_accounting_method == 'accomodation') {
                        $nb_pers_to_assign = min($product_models[$line['product_id.product_model_id']]['capacity'], $group['nb_pers']);
                    }
                    elseif($qty_accounting_method == 'unit') {
                        $nb_pers_to_assign = $group['nb_pers'];
                    }

                    // find available rental units (sorted by capacity, desc; filtered on product model category)
                    $rental_units_ids = Consumption::getAvailableRentalUnits($om, $center_id, $line['product_id.product_model_id'], $date_from, $date_to);

                    // #memo - we cannot append rental units from consumptions of own booking :this leads to an edge case
                    // (use case "come and go between 'quote' and 'option'" is handled with 'realease-rentalunits' action)

                    // remove rental units that are no longer unavailable
                    $rental_units_ids = array_diff($rental_units_ids,
                        $group_assigned_rental_units_ids,               // assigned to other lines (current loop)
                        $booking_assigned_rental_units_ids              // assigned within other groups
                    );

                    // retrieve rental units with matching capacities (best match first)
                    $rental_units = self::_getRentalUnitsMatches($om, $rental_units_ids, $nb_pers_to_assign);

                    $remaining = $nb_pers_to_assign;
                    $assigned_rental_units = [];

                    // min serie for available capacity starts from max(0, i-1)
                    for($j = 0, $n = count($rental_units) ;$j < $n; ++$j) {
                        $rental_unit = $rental_units[$j];
                        $assigned = min($rental_unit['capacity'], $remaining);
                        $rental_unit['assigned'] = $assigned;
                        $assigned_rental_units[] = $rental_unit;
                        $remaining -= $assigned;
                        if($remaining <= 0) break;
                    }

                    if($remaining > 0) {
                        // no availability !
                        trigger_error("ORM::no availability", QN_REPORT_DEBUG);
                    }
                    else {
                        foreach($assigned_rental_units as $rental_unit) {
                            $assignement = [
                                'booking_id'                    => $group['booking_id'],
                                'booking_line_group_id'         => $id,
                                'sojourn_product_model_id'      => $group_product_models_ids[$line['product_id.product_model_id']],
                                'qty'                           => $rental_unit['assigned'],
                                'rental_unit_id'                => $rental_unit['id']
                            ];
                            trigger_error("ORM::assigning {$rental_unit['assigned']} p. to {$rental_unit['id']}", QN_REPORT_DEBUG);
                            $om->create(SojournProductModelRentalUnitAssignement::getType(), $assignement);
                            // remember assigned rental units (for next lines processing)
                            $group_assigned_rental_units_ids[]= $rental_unit['id'];
                        }

                        if($qty_accounting_method == 'person' && $is_accomodation) {
                            $has_processed_accomodation_by_person = true;
                        }
                    }
                }
            }

        }

        // 2-nd pass: in any situation, if the group targets additional services (is_extra), we dispatch a notification about required assignment

        if($group['is_extra']) {
            // read children booking lines
            $lines = $om->read(BookingLine::getType(), $group['booking_lines_ids'], [
                'is_rental_unit'
            ]);

            // drop lines that do not relate to rental units
            $lines = array_filter($lines, function($a) { return $a['is_rental_unit']; });
            if(count($lines)) {
                $cron = $om->getContainer()->get('cron');
                // add a task to the CRON for updating status of bookings waiting for the pricelist
                $cron->schedule(
                    "booking.assign.units.{$group['booking_id']}",
                    // run as soon as possible
                    time() + 60,
                    'sale_booking_check-units-assignments',
                    [ 'id' => $group['booking_id'] ]
                );

            }

        }
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Modifies date of the group's activities to match new date of the group
     *
     * @param ObjectManager $om
     * @param int           $id         id of the group
     * @param int           $dates_diff difference between the new date_from and the old one ($new_date_from - $old_date_from)
     */
    public static function refreshActivitiesDates($om, $id, $dates_diff) {
        $groups = $om->read(self::getType(), $id, ['booking_activities_ids']);

        if($groups <= 0) {
            return;
        }

        $group = reset($groups);

        $activities = $om->read(BookingActivity::getType(), $group['booking_activities_ids'], [
            'activity_date'
        ]);

        if($activities <= 0) {
            return;
        }

        foreach($activities as $id => $activity) {
            $shifted_activity_date = $activity['activity_date'] + $dates_diff;

            $om->update(BookingActivity::getType(), $id, [
                'activity_date' => $shifted_activity_date
            ]);
        }
    }
}

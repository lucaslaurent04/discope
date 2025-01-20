<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use equal\orm\Model;
use sale\booking\BookingLineGroup;

class Consumption extends Model {

    public static function getName() {
        return 'Consumption';
    }

    public static function getDescription() {
        return "A Consumption is a service delivery that can be scheduled, relates to a booking, and is independant from the fare rate and the invoicing.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'computed',
                'function'          => 'calcName',
                'result_type'       => 'string',
                'store'             => true,
                'readonly'          => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => 'Additional note about the consumption, if any.'
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "The center to which the consumption relates.",
                'required'          => true,
                'ondelete'          => 'cascade',         // delete consumption when parent Center is deleted
                'readonly'          => true
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'The booking the consumption relates to.',
                'ondelete'          => 'cascade',        // delete consumption when parent booking is deleted
                'readonly'          => true,
                'dependents'        => ['customer_id']
            ],

            'customer_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'relation'          => ['booking_id' => ['customer_id']],
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => "The customer whom the consumption relates to (computed).",
            ],

            // #memo - this field actually belong to Repair objects, we need it to be able to fetch both kind of consumptions
            'repairing_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Repairing',
                'description'       => 'The booking the consumption relates to.',
                'ondelete'          => 'cascade'        // delete repair when parent repairing is deleted
            ],

            // #todo - deprecate : relation between consumptions and lines might be indirect
            'booking_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLine',
                'description'       => 'The booking line the consumption relates to.',
                'ondelete'          => 'cascade',        // delete consumption when parent line is deleted
                'readonly'          => true
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLineGroup',
                'description'       => 'The booking line group the consumption relates to.',
                'ondelete'          => 'cascade',        // delete consumption when parent group is deleted
                'readonly'          => true
            ],

            'time_slot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\TimeSlot',
                'description'       => 'Indicator of the moment of the day when the consumption occurs (from schedule).',
            ],

            'date' => [
                'type'              => 'date',
                'description'       => 'Date at which the event is planed.',
                'readonly'          => true
            ],

            'schedule_from' => [
                'type'              => 'time',
                'description'       => 'Moment of the day at which the events starts.',
                'default'           => 0,
                'onupdate'          => 'onupdateScheduleFrom'
            ],

            'schedule_to' => [
                'type'              => 'time',
                'description'       => 'Moment of the day at which the event stops, if applicable.',
                'default'           => 24 * 3600,
                'onupdate'          => 'onupdateScheduleTo'
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => [
                    'ooo',           // out-of-order (repair & maintenance)
                    'book',          // consumption relates to a booking
                    'link',          // rental unit is a child of another booked unit or cannot be partially booked (i.e. parent unit)
                    'part'           // rental unit is the parent of another booked unit and can partially booked (non-blocking: only for info on the planning)
                ],
                'description'       => 'The reason the unit is reserved (mostly applies to accomodations).',
                'default'           => 'book',
                'readonly'          => true
            ],

            'product_model_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'description'       => "The Product the consumption relates to.",
                'required'          => true,
                'readonly'          => true
            ],

            // #todo - deprecate : only the rental_unit_id matters, and consumptions are created based on product_model (not products)
            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => "The Product the consumption relates to.",
                'readonly'          => true
            ],

            'is_rental_unit' => [
                'type'              => 'boolean',
                'description'       => 'Does the consumption relate to a rental unit?',
                'default'           => false
            ],

            'rental_unit_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'realestate\RentalUnit',
                'description'       => "The rental unit the consumption is assigned to.",
                'readonly'          => true,
                'onupdate'          => 'onupdateRentalUnitId'
            ],

            'disclaimed' => [
                'type'              => 'boolean',
                'description'       => 'Delivery is planed by the customer has explicitely renounced to it.',
                'default'           => false
            ],

            'is_meal' => [
                'type'              => 'boolean',
                'description'       => 'Does the consumption relate to a meal?',
                'default'           => false
            ],

            'is_snack' => [
                'type'              => 'boolean',
                'description'       => 'Does the consumption relate to a snack?',
                'default'           => false
            ],

            'is_accomodation' => [
                'type'              => 'boolean',
                'description'       => 'Does the consumption relate to an accomodation (from rental unit)?',
                'visible'           => ['is_rental_unit', '=', true],
                'default'           => false
            ],

            'qty' => [
                'type'              => 'integer',
                'description'       => "How many times the consumption is booked for.",
                'required'          => true
            ],

            'cleanup_type' => [
                'type'              => 'string',
                'selection'         => [
                    'none',
                    'daily',
                    'full'
                ],
                'visible'           => ['is_accomodation', '=', true],
                'default'           => 'none'
            ],

            'age_range_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\AgeRange',
                'description'       => 'Customers age range the product is intended for.'
            ]

        ];
    }


    public static function calcName($om, $oids, $lang) {
        $result = [];
        $consumptions = $om->read(get_called_class(), $oids, ['booking_id.customer_id.name', 'booking_id.description', 'product_id.name', 'date', 'schedule_from']);
        if($consumptions) {
            foreach($consumptions as $oid => $odata) {
                $datetime = $odata['date'] + $odata['schedule_from'];
                $moment = date("d/m/Y H:i:s", $datetime);
                $result[$oid] = substr("{$odata['booking_id.customer_id.name']} {$odata['product_id.name']} {$moment}", 0, 255);
            }
        }
        return $result;
    }

    public static function onupdateRentalUnitId($om, $oids, $values, $lang) {
        $consumptions = $om->read(get_called_class(), $oids, ['rental_unit_id', 'rental_unit_id.is_accomodation', 'date', 'booking_line_group_id.date_from', 'booking_line_group_id.date_to'], $lang);

        if($consumptions > 0) {
            foreach($consumptions as $cid => $consumption) {
                if($consumption['rental_unit_id']) {
                    $cleanup_type = 'none';
                    if($consumption['rental_unit_id.is_accomodation']) {
                        $cleanup_type = 'daily';
                        if($consumption['booking_line_group_id.date_from'] == $consumption['date']) {
                            // no cleanup the day of arrival
                            $cleanup_type = 'none';
                            continue;
                        }
                        if($consumption['booking_line_group_id.date_to'] == $consumption['date']) {
                            // full cleanup on checkout day
                            $cleanup_type = 'full';
                        }
                    }
                    $om->update(self::getType(), $oids, ['is_rental_unit' => true, 'is_accomodation' => $consumption['rental_unit_id.is_accomodation'], 'cleanup_type' => $cleanup_type]);
                }
            }
        }
    }

    /**
     * Hook invoked before object update for performing object-specific additional operations.
     * Current values of the object can still be read for comparing with new values.
     *
     * @param  \equal\orm\ObjectManager   $om         ObjectManager instance.
     * @param  array                      $oids       List of objects identifiers.
     * @param  array                      $values     Associative array holding the new values that have been assigned.
     * @param  string                     $lang       Language in which multilang fields are being updated.
     * @return void
     */
    public static function onupdate($om, $oids, $values, $lang) {
        if(isset($values['qty'])) {
            $consumptions = $om->read(__CLASS__, $oids, ['qty', 'booking_id', 'booking_line_id.product_id', 'booking_line_id.unit_price', 'booking_line_id.vat_rate', 'booking_line_id.qty_accounting_method'], $lang);
            foreach($consumptions as $cid => $consumption) {
                if($consumption['qty'] < $values['qty'] && in_array($consumption['booking_line_id.qty_accounting_method'], ['person', 'unit'])) {
                    $diff = $values['qty'] - $consumption['qty'];
                    // in is_extra group, add a new line with same product as targeted booking_line
                    $groups_ids = $om->search('sale\booking\BookingLineGroup', [['booking_id', '=', $consumption['booking_id']], ['is_extra', '=', true]]);
                    if($groups_ids > 0 && count($groups_ids)) {
                        $group_id = reset(($groups_ids));
                    }
                    else {
                        // create extra group
                        $group_id = $om->create('sale\booking\BookingLineGroup', ['name' => 'Suppléments', 'booking_id' => $consumption['booking_id'], 'is_extra' => true]);
                    }
                    // create a new bookingLine
                    $line_id = $om->create('sale\booking\BookingLine', ['booking_id' => $consumption['booking_id'], 'booking_line_group_id' => $group_id, 'product_id' => $consumption['booking_line_id.product_id']], $lang);
                    // #memo - at creation booking_line qty is always set accordingly to its parent group nb_pers
                    $om->update('sale\booking\BookingLine', $line_id, ['qty' => $diff, 'unit_price' => $consumption['booking_line_id.unit_price'], 'vat_rate' => $consumption['booking_line_id.vat_rate']], $lang);
                }
            }
        }
        parent::onupdate($om, $oids, $values, $lang);
    }

    /**
     * Hook invoked after updates on field `schedule_from`.
     * Adapt time_slot_id according to new moment.
     * Update siblings consumptions (same day same line) relating to rental units to use the same value for schedule_from.
     *
     * @param  \equal\orm\ObjectManager   $om         ObjectManager instance.
     * @param  int[]                      $oids       List of objects identifiers in the collection.
     * @param  array                      $values     Associative array holding the values newly assigned to the new instance (not all fields might be set).
     * @param  string                     $lang       Language in which multilang fields are being updated.
     * @return void
     */
    public static function onupdateScheduleFrom($om, $oids, $values, $lang) {
        // booking_id is only assigned upon creation, so hook is called because of an update (not a creation)
        if(!isset($values['booking_id'])) {
            $consumptions = $om->read(self::getType(), $oids, ['is_rental_unit', 'date', 'schedule_from', 'booking_line_id'], $lang);
            if($consumptions > 0) {
                foreach($consumptions as $oid => $consumption) {
                    if($consumption['is_rental_unit']) {
                        $siblings_ids = $om->search(self::getType(), [['id', '<>', $oid], ['is_rental_unit', '=', true], ['booking_line_id', '=', $consumption['booking_line_id']], ['date', '=', $consumption['date']] ]);
                        if($siblings_ids > 0 && count($siblings_ids)) {
                            $om->update(self::getType(), $siblings_ids, ['schedule_from' => $consumption['schedule_from']]);
                        }
                    }
                }
            }
        }
        $om->callonce(self::getType(), 'updateTimeSlotId', $oids, $values, $lang);
    }

    /**
     * Hook invoked after updates on field `schedule_to`.
     * Adapt time_slot_id according to new moment.
     * Update siblings consumptions (same day same line) relating to rental units to use the same value for schedule_to.
     *
     * @param  \equal\orm\ObjectManager   $om         ObjectManager instance.
     * @param  int[]                      $oids       List of objects identifiers in the collection.
     * @param  array                      $values     Associative array holding the values to be assigned to the new instance (not all fields might be set).
     * @param  string                     $lang       Language in which multilang fields are being updated.
     * @return void
     */
    public static function onupdateScheduleTo($om, $oids, $values, $lang) {
        // booking_id is only assigned upon creation, so hook is called because of an update (not a creation)
        if(!isset($values['booking_id'])) {
            $consumptions = $om->read(self::getType(), $oids, ['is_rental_unit', 'date', 'schedule_to', 'booking_line_id'], $lang);
            if($consumptions > 0) {
                foreach($consumptions as $oid => $consumption) {
                    if($consumption['is_rental_unit']) {
                        $siblings_ids = $om->search(self::getType(), [['id', '<>', $oid], ['is_rental_unit', '=', true], ['booking_line_id', '=', $consumption['booking_line_id']], ['date', '=', $consumption['date']] ]);
                        if($siblings_ids > 0 && count($siblings_ids)) {
                            $om->update(self::getType(), $siblings_ids, ['schedule_to' => $consumption['schedule_to']]);
                        }
                    }
                }
            }
        }
        $om->callonce(self::getType(), 'updateTimeSlotId', $oids, $values, $lang);
    }

    /**
     * #todo - make this method private (cannot while called through callonce)
     */
    public static function updateTimeSlotId($om, $oids, $values, $lang) {
        $consumptions = $om->read(self::getType(), $oids, ['schedule_from', 'schedule_to', 'is_meal']);
        if($consumptions > 0) {
            $moments_ids = $om->search('sale\booking\TimeSlot', [], ['order' => 'asc']);
            $moments = $om->read('sale\booking\TimeSlot', $moments_ids, ['schedule_from', 'schedule_to', 'is_meal']);
            foreach($consumptions as $cid => $consumption) {
                // #todo - use timeslot_id if available in product model
                // retrieve timeslot according to schedule_from
                $moment_id = 1;
                foreach($moments as $mid => $moment) {
                    if($consumption['schedule_from'] >= $moment['schedule_from'] && $consumption['schedule_to'] <= $moment['schedule_to']) {
                        $moment_id = $mid;
                        if($moment['is_meal'] && $consumption['is_meal']) {
                            break;
                        }
                    }
                }
                $om->update(self::getType(), $cid, ['time_slot_id' => $moment_id]);
            }
        }
    }

    /**
     *
     * #memo - used in controllers
     * @param \equal\orm\ObjectManager $om
     */
    public static function getExistingConsumptions($om, $centers_ids, $date_from, $date_to) {
        // read all consumptions and repairs (book, ooo, link, part)
        $consumptions_ids = $om->search(self::getType(), [
            ['date', '>=', $date_from],
            ['date', '<=', $date_to],
            ['center_id', 'in',  $centers_ids],
            ['is_rental_unit', '=', true]
        ], ['date' => 'asc']);

        $consumptions = $om->read(self::getType(), $consumptions_ids, [
            'id',
            'date',
            'type',
            'booking_id',
            'rental_unit_id',
            'booking_line_group_id',
            'repairing_id',
            'schedule_from',
            'schedule_to'
        ]);

        /*
            Result is a 2-level associative array, mapping consumptions by rental unit and date
        */
        $result = [];

        $bookings_map = [];
        $repairings_map = [];

        if($consumptions > 0) {
            /*
                Join consecutive consumptions of a same booking_line_group for using as same rental unit.
                All consumptions are enriched with additional fields `date_from`and `date_to`.
                Field schedule_from and schedule_to are adapted consequently.
            */

            $booking_line_groups = $om->read(BookingLineGroup::getType(),
                array_map(function($a) {return (int) $a['booking_line_group_id'];}, $consumptions),
                ['id', 'date_from', 'date_to', 'time_from', 'time_to']
            );

            $repairings = $om->read(\sale\booking\Repairing::getType(),
                array_map(function($a) {return (int) $a['repairing_id'];}, $consumptions),
                ['id', 'date_from', 'date_to', 'time_from', 'time_to']
            );

            // pass-1 : group consumptions by rental unit and booking (line group) or repairing
            foreach($consumptions as $cid => $consumption) {
                if(!isset($consumption['rental_unit_id']) || empty($consumption['rental_unit_id'])) {
                    // ignore consumptions not relating to a rental unit
                    unset($consumptions[$cid]);
                    continue;
                }

                $rental_unit_id = $consumption['rental_unit_id'];

                if(!isset($bookings_map[$rental_unit_id])) {
                    $bookings_map[$rental_unit_id] = [];
                }

                if(!isset($repairings_map[$rental_unit_id])) {
                    $repairings_map[$rental_unit_id] = [];
                }

                if(isset($consumption['booking_line_group_id'])) {
                    $booking_line_group_id = $consumption['booking_line_group_id'];
                    if(!isset($bookings_map[$rental_unit_id][$booking_line_group_id])) {
                        $bookings_map[$rental_unit_id][$booking_line_group_id] = [];
                    }
                    $bookings_map[$rental_unit_id][$booking_line_group_id][] = $cid;
                }
                elseif(isset($consumption['repairing_id'])) {
                    $repairing_id = $consumption['repairing_id'];
                    if(!isset($repairings_map[$rental_unit_id][$repairing_id])) {
                        $repairings_map[$rental_unit_id][$repairing_id] = [];
                    }
                    $repairings_map[$rental_unit_id][$repairing_id][] = $cid;
                }
            }

            // pass-2 : generate map

            // associative array for mapping processed consumptions: each consumption is present only once in the result set
            $processed_consumptions = [];

            // generate a map associating dates to rental_units_ids, and having only one consumption for each first visible date
            foreach($consumptions as $consumption) {

                if(isset($processed_consumptions[$consumption['id']])) {
                    continue;
                }

                // convert to date index
                $moment = $consumption['date'] + $consumption['schedule_from'];
                $date_index = substr(date('c', $moment), 0, 10);

                $rental_unit_id = $consumption['rental_unit_id'];

                if(!isset($result[$rental_unit_id])) {
                    $result[$rental_unit_id] = [];
                }

                if(!isset($result[$rental_unit_id][$date_index])) {
                    $result[$rental_unit_id][$date_index] = [];
                }

                // handle consumptions from bookings
                if(isset($consumption['booking_line_group_id'])) {
                    $booking_line_group_id = $consumption['booking_line_group_id'];

                    foreach($bookings_map[$rental_unit_id][$booking_line_group_id] as $cid) {
                        $processed_consumptions[$cid] = true;
                    }

                    $consumption['date_from'] = $booking_line_groups[$booking_line_group_id]['date_from'];
                    // #todo - date_to should be the latest date from all consumptions relating to the group (the sojourn might be shorter than initially set, in case of partial cancellation)
                    $consumption['date_to'] = $booking_line_groups[$booking_line_group_id]['date_to'];
                    $consumption['schedule_from'] = $booking_line_groups[$booking_line_group_id]['time_from'];
                    $consumption['schedule_to'] = $booking_line_groups[$booking_line_group_id]['time_to'];
                }
                // handle consumptions from repairings
                elseif(isset($consumption['repairing_id'])) {
                    $repairing_id = $consumption['repairing_id'];

                    foreach($repairings_map[$rental_unit_id][$repairing_id] as $cid) {
                        $processed_consumptions[$cid] = true;
                    }

                    $consumption['date_from'] = $repairings[$repairing_id]['date_from'];
                    $consumption['date_to'] = $repairings[$repairing_id]['date_to'];
                    $consumption['schedule_from'] = $repairings[$repairing_id]['time_from'];
                    $consumption['schedule_to'] = $repairings[$repairing_id]['time_to'];
                }
                // #memo - there might be several consumptions for a same rental_unit within a same day
                $result[$rental_unit_id][$date_index][] = $consumption;
            }

        }
        return $result;
    }


    /**
     *
     * #memo - This method is used in controllers
     *
     * @param \equal\orm\ObjectManager  $om                 Instance of Object Manager service.
     * @param int                       $center_id          Identifier of the center for which to perform the lookup.
     * @param int                       $product_model_id   Identifier of the product model for which we are looking for rental units.
     * @param int                       $date_from          Timestamp of the first day of the lookup.
     * @param int                       $date_to            Timestamp of the last day of the lookup.
     */
    public static function getAvailableRentalUnits($om, $center_id, $product_model_id, $date_from, $date_to) {
        trigger_error("ORM::calling sale\booking\Consumption:getAvailableRentalUnits", QN_REPORT_DEBUG);

        $models = $om->read(\sale\catalog\ProductModel::getType(), $product_model_id, [
            'type',
            'service_type',
            'is_accomodation',
            'rental_unit_assignement',
            'rental_unit_category_id',
            'rental_unit_id',
            'capacity'
        ]);

        if($models <= 0 || count($models) < 1) {
            return [];
        }

        $product_model = reset($models);

        $product_type = $product_model['type'];
        $service_type = $product_model['service_type'];
        $rental_unit_assignement = $product_model['rental_unit_assignement'];

        if($product_type != 'service' || $service_type != 'schedulable') {
            return [];
        }

        if($rental_unit_assignement == 'unit') {
            $rental_units_ids = [$product_model['rental_unit_id']];
        }
        else {
            $domain = [ ['center_id', '=', $center_id] ];

            if($product_model['is_accomodation']) {
                $domain[] = ['is_accomodation', '=', true];
            }

            if($rental_unit_assignement == 'category' && $product_model['rental_unit_category_id']) {
                $domain[] = ['rental_unit_category_id', '=', $product_model['rental_unit_category_id']];
            }
            // retrieve list of possible rental_units based on center_id
            $rental_units_ids = $om->search('realestate\RentalUnit', $domain, ['capacity' => 'desc']);
        }

        /*
            If there are consumptions in the range for some of the found rental units, remove those
        */
        $existing_consumptions_map = self::getExistingConsumptions($om, [$center_id], $date_from, $date_to);

        $booked_rental_units_ids = [];

        foreach($existing_consumptions_map as $rental_unit_id => $dates) {
            foreach($dates as $date_index => $consumptions) {
                foreach($consumptions as $consumption) {
                    $consumption_from = $consumption['date_from'] + $consumption['schedule_from'];
                    $consumption_to = $consumption['date_to'] + $consumption['schedule_to'];
                    // #memo - we don't allow instant transition (i.e. checkin time of a booking equals checkout time of a previous booking)
                    if(max($date_from, $consumption_from) < min($date_to, $consumption_to)) {
                        $booked_rental_units_ids[] = $rental_unit_id;
                        continue 3;
                    }
                }
            }
        }

        return array_diff($rental_units_ids, $booked_rental_units_ids);
    }
}

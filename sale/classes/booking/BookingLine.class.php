<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use core\setting\Setting;
use equal\orm\Collection;
use equal\orm\Model;
use sale\catalog\Product;

class BookingLine extends Model {

    public static function getName() {
        return "Booking line";
    }

    public static function getDescription() {
        return "Booking lines describe the products and quantities that are part of a booking.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Line name relates to its product.',
                'function'          => 'calcName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Complementary description of the line. If set, replaces the product name.',
                'default'           => ''
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLineGroup',
                'description'       => 'Group the line relates to (in turn, groups relate to their booking).',
                'onupdate'          => 'onupdateBookingLineGroupId',
                'ondelete'          => 'cascade',        // delete line when parent group is deleted
                'required'          => true              // must be set at creation
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'The booking the line relates to (for consistency, lines should be accessed using the group they belong to).',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => 'The product (SKU) the line relates to.',
                'onupdate'          => 'onupdateProductId',
                'dependents'        => ['product_model_id', 'time_slot_id']
            ],

            'product_model_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\catalog\ProductModel',
                'relation'          => ['product_id' => 'product_model_id'],
                'store'             => true,
                'description'       => 'The product model the line relates to (from product).',
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => 'The price the line relates to (retrieved by price list).',
                'onupdate'          => 'onupdatePriceId'
            ],

            'consumptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Consumption',
                'foreign_field'     => 'booking_line_id',
                'description'       => 'Consumptions related to the booking line.',
                'ondetach'          => 'delete'
            ],

            'price_adapters_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingPriceAdapter',
                'foreign_field'     => 'booking_line_id',
                'description'       => 'All price adapters: auto and manual discounts applied on the line.',
                'onupdate'          => 'onupdatePriceAdaptersIds'
            ],

            // automatic price adapters are used for computing the unit_price
            'auto_discounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingPriceAdapter',
                'foreign_field'     => 'booking_line_id',
                'domain'            => ['is_manual_discount', '=', false],
                'description'       => 'Price adapters relating to auto discounts only.'
            ],

            // manual discounts are used for computing the resulting discount rate (except freebies)
            'manual_discounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingPriceAdapter',
                'foreign_field'     => 'booking_line_id',
                'domain'            => ['is_manual_discount', '=', true],
                'description'       => 'Price adapters relating to manual discounts only.',
                'onupdate'          => 'onupdatePriceAdaptersIds'
            ],

            'qty' => [
                'type'              => 'float',
                'description'       => 'Quantity of product items for the line.',
                'onupdate'          => 'onupdateQty',
                'default'           => 1.0
            ],

            'has_own_qty' => [
                'type'              => 'boolean',
                'description'       => 'Set according to related pack line.',
                'default'           => false
            ],

            'has_own_duration' => [
                'type'              => 'boolean',
                'description'       => 'Set according to related pack line.',
                'default'           => false
            ],

            'own_duration' => [
                'type'              => 'integer',
                'description'       => "Self assigned duration, in days (from pack line).",
                'visible'           => ['has_own_duration', '=', true]
            ],

            'order' => [
                'type'              => 'integer',
                'description'       => 'Order by which the line have to be sorted when presented visually.',
                'default'           => 1
            ],

            // #todo #deprecate - it seems this is no longer used
            'payment_mode' => [
                'type'              => 'string',
                'selection'         => [
                    'invoice',                  // consumption has to be added to an invoice
                    'cash',                     // consumption is paid in cash (money or bank transfer)
                    'free'                      // related consumption is a gift
                ],
                'default'           => 'invoice',
                'description'       => 'The way the line is intended to be paid.',
            ],

            'is_contractual' => [
                'type'              => 'boolean',
                'description'       => 'Is the line part of the original contract (or added afterward)?',
                'default'           => false
            ],

            'is_invoiced' => [
                'type'              => 'boolean',
                'description'       => 'Is the line part of the original contract (or added afterward)?',
                'default'           => false
            ],

            // freebies are from both automatic price adapters and manual discounts
            'free_qty' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Free quantity.',
                'function'          => 'calcFreeQty',
                'store'             => true
            ],

            // #memo - important: to allow the maximum flexibility, percent values can hold 4 decimal digits (must not be rounded, except for display)
            'discount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/rate',
                'description'       => 'Total amount of manual discount to apply, if any.',
                'function'          => 'calcDiscount',
                'store'             => true
            ],

            'unit_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Tax-excluded unit price (with automated discounts applied).',
                'function'          => 'calcUnitPrice',
                'store'             => true,
                'onupdate'          => 'onupdateUnitPrice'
            ],

            'has_manual_unit_price' => [
                'type'              => 'boolean',
                'description'       => 'Flag indicating that the unit price has been set manually and must not be reset in case of price reset.',
                'default'           => false
            ],

            'has_manual_vat_rate' => [
                'type'              => 'boolean',
                'description'       => 'Flag indicating that the vat rate price has been set manually and must not be reset in case of price reset.',
                'default'           => false
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price of the line (computed).',
                'function'          => 'calcTotal',
                'store'             => true
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Final tax-included price (computed).',
                'function'          => 'calcPrice',
                'store'             => true
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'VAT rate that applies to this line.',
                'function'          => 'calcVatRate',
                'store'             => true,
                'onupdate'          => 'onupdateVatRate'
            ],

            'fare_benefit' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Total amount of the fare benefit VAT incl.',
                'function'          => 'calcFareBenefit',
                'store'             => true
            ],

            'is_rental_unit' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Line relates to a rental unit (from product_model).',
                'function'          => 'calcIsRentalUnit',
                'store'             => true
            ],

            'is_accomodation' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Line relates to an accommodation (from product_model).',
                'function'          => 'calcIsAccomodation',
                'store'             => true
            ],

            'is_meal' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Line relates to a meal (from product_model).',
                'function'          => 'calcIsMeal',
                'store'             => true
            ],

            'is_snack' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Line relates to a snack (from product_model).',
                'function'          => 'calcIsSnack',
                'store'             => true
            ],

            'qty_accounting_method' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Quantity accounting method (from product_model).',
                'function'          => 'calcQtyAccountingMethod',
                'store'             => true
            ],

            'qty_vars' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'JSON array holding qty variation deltas (for \'by person\' products), if any.',
                'onupdate'          => 'onupdateQtyVars'
            ],

            'is_autosale' => [
                'type'              => 'boolean',
                'description'       => 'Does the line relate to an autosale product?',
                'default'           => false
            ],

            'is_activity' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Does the line relate to an activity product?',
                'store'             => true,
                'function'          => 'calcIsActivity'
            ],

            'is_transport' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Does the line relate to a transport product?',
                'store'             => true,
                'function'          => 'calcIsTransport'
            ],

            'is_supply' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Does the line relate to a supply product?',
                'store'             => true,
                'function'          => 'calcIsSupply'
            ],

            'is_fullday' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Does the line relate to a fullday activity product?',
                'store'             => true,
                'function'          => 'calcIsFullday'
            ],

            'booking_activity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingActivity',
                'description'       => 'Main Booking Activity this line relates to (non virtual activity).',
                'help'              => 'If the line refers to a transport/supply, it means that the transport/supply is needed for a specific activity.',
                'onupdate'          => 'onupdateBookingActivityId'
            ],

            'booking_activities_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingActivity',
                'foreign_field'     => 'activity_booking_line_id',
                'description'       => 'All Booking Activities this line relates to (non virtual activity and virtual activities).'
            ],

            'booking_meals_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\booking\BookingMeal',
                'foreign_field'     => 'booking_lines_ids',
                'rel_table'         => 'sale_booking_line_rel_booking_meal',
                'rel_foreign_key'   => 'booking_meal_id',
                'rel_local_key'     => 'booking_line_id',
                'description'       => "All booking meals that are linked the line (moment).",
            ],

            'service_date' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'description'       => 'Specific date on which the service is delivered.',
                'help'              => 'Only needed when the ProductModel is schedulable and not repeatable.',
                'store'             => true,
                'function'          => 'calcServiceDate',
                'onupdate'          => 'onupdateServiceDate'
            ],

            'time_slot_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\booking\TimeSlot',
                'description'       => 'Specific day time slot on which the service is delivered.',
                'store'             => true,
                'function'          => 'calcTimeSlotId',
                'onupdate'          => 'onupdateTimeSlotId'
            ],

            'meal_location' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    'inside',
                    'outside',
                    'takeaway'
                ],
                'store'             => true,
                'relation'          => ['product_id' => ['product_model_id' => ['meal_location']]],
                'visible'           => ['is_meal', '=', true]
            ],

            'activity_rental_unit_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'realestate\RentalUnit',
                'description'       => "The rental unit needed for the activity to take place.",
                'relation'          => ['booking_activity_id' => 'rental_unit_id'],
                'store'             => true
            ]

        ];
    }

    /**
     * Check whether an object can be created, and optionally perform additional operations.
     * These tests come in addition to the unique constraints return by method `getUnique()`.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager   $om         ObjectManager instance.
     * @param  array                      $values     Associative array holding the values to be assigned to the new instance (not all fields might be set).
     * @param  string                     $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be created.
     */
    public static function cancreate($om, $values, $lang) {
        if(isset($values['booking_id']) && isset($values['booking_line_group_id'])) {
            $bookings = $om->read(Booking::getType(), $values['booking_id'], ['status'], $lang);
            $groups = $om->read(BookingLineGroup::getType(), $values['booking_line_group_id'], ['is_extra', 'has_schedulable_services', 'has_consumptions'], $lang);

            if($bookings > 0 && $groups > 0) {
                $booking = reset($bookings);
                $group = reset($groups);

                if( in_array($booking['status'], ['invoiced', 'debit_balance', 'credit_balance', 'balanced'])
                    || ($booking['status'] != 'quote' && !$group['is_extra']) ) {
                    return ['status' => ['non_editable' => 'Non-extra service lines cannot be changed for non-quote bookings.']];
                }
                if( $group['is_extra'] && $group['has_schedulable_services'] && $group['has_consumptions']) {
                    return ['status' => ['non_editable' => 'Lines from extra services cannot be added once consumptions have been created.']];
                }
            }
        }

        return parent::cancreate($om, $values, $lang);
    }

    /**
     * Check whether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  Collection       $self       Collection instance.
     * @param  array            $values     Associative array holding the new values to be assigned.
     * @param  string           $lang       Language in which multilang fields are being updated.
     * @return array            Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($self, $values, $lang = 'en') {

        // handle exceptions for fields that can always be updated
        $allowed = ['is_contractual', 'is_invoiced'];
        $count_non_allowed = 0;

        foreach($values as $field => $value) {
            if(!in_array($field, $allowed)) {
                ++$count_non_allowed;
            }
        }

        if($count_non_allowed > 0) {
            $self->read([
                    'booking_id' => ['status'],
                    'booking_line_group_id' => [
                        'is_extra',
                        'has_schedulable_services',
                        'has_consumptions'
                    ]
                ]);

            foreach($self as $line) {
                if($line['booking_id']['status'] != 'quote' && !$line['booking_line_group_id']['is_extra']) {
                    return ['booking_id' => ['non_editable' => 'Services cannot be updated for non-quote bookings.']];
                }
                if($line['booking_line_group_id']['is_extra'] && $line['booking_line_group_id']['has_schedulable_services'] && $line['booking_line_group_id']['has_consumptions']) {
                    return ['booking_id' => ['non_editable' => 'Lines from extra services cannot be changed once consumptions have been created.']];
                }
            }
        }

        // checks that a fullday activity can only be on AM or PM time slots
        if(isset($values['product_id'])) {
            $product = Product::id($values['product_id'])
                ->read(['product_model_id' => ['is_activity', 'is_fullday', 'has_duration', 'duration']])
                ->first();

            if($product['product_model_id']['is_activity']) {
                $AM_PM_time_slot_ids = TimeSlot::search(['code', 'in', ['AM', 'PM']])->ids();

                $self->read([
                    'service_date',
                    'time_slot_id',
                    'booking_line_group_id' => ['id', 'date_from', 'date_to']
                ]);

                if($product['product_model_id']['is_fullday']) {
                    foreach($self as $id => $line) {
                        if(!in_array($line['time_slot_id'], $AM_PM_time_slot_ids)) {

                            // #todo - this should be done by the front-end with a distinct call (`canupdate` should not perform any data update)
                            // remove empty line because cannot set to activity
                            self::id($id)->delete(true);

                            return ['time_slot_id' => ['not_allowed' => 'A full day activity can only happen on AM or PM time slots.']];
                        }
                    }
                }

                foreach($self as $id => $line) {

                    // #memo - prevent in case of direct assignment of an activity (product with is_activity) on the BookingLine
                    if(!isset($line['service_date'], $line['time_slot_id'])) {
                        continue;
                    }

                    $activities_to_check = self::computeLineActivities(
                        $line['service_date'],
                        $line['time_slot_id'],
                        $product['product_model_id']['is_fullday'],
                        $product['product_model_id']['has_duration'],
                        $product['product_model_id']['duration']
                    );

                    foreach($activities_to_check as $activity_to_check) {
                        if(
                            $activity_to_check['activity_date'] < $line['booking_line_group_id']['date_from']
                            || $activity_to_check['activity_date'] > $line['booking_line_group_id']['date_to']
                        ) {

                            // #todo - this should be done by the front-end with a distinct call (`canupdate` should not perform any data update)
                            // remove empty line because cannot set to activity
                            self::id($id)->delete(true);

                            return ['service_date' => ['outside_of_group_dates' => 'The activity has to be planned inside the group period.']];
                        }

                        $activity = BookingActivity::search([
                                ['booking_line_group_id', '=',  $line['booking_line_group_id']['id']],
                                ['activity_date', '=', $activity_to_check['activity_date']],
                                ['time_slot_id', '=', $activity_to_check['time_slot_id']]
                            ])
                            ->read(['id'])
                            ->first();

                        if(!is_null($activity)) {

                            // #todo - this should be done by the front-end with a distinct call (`canupdate` should not perform any data update)
                            // remove empty line because cannot set to activity
                            self::id($id)->delete(true);

                            return ['service_date' => ['already_used' => 'Moment conflict with another activity of the group.']];
                        }
                    }
                }
            }
        }

        // checks if moment does not conflict with another activity of the booking line group
        if(isset($values['service_date']) || isset($values['time_slot_id'])) {
            $self->read([
                'id',
                'is_activity',
                'service_date',
                'time_slot_id',
                'is_fullday',
                'booking_line_group_id' => ['id', 'date_from', 'date_to']
            ]);

            foreach($self as $line) {
                if(!$line['is_activity']) {
                    continue;
                }

                $booking_activities = BookingActivity::search(['activity_booking_line_id', '=', $line['id']], ['sort' => ['activity_date' => 'asc']])
                    ->read(['activity_date', 'time_slot_id'])
                    ->get(true);

                $shift_days = ($values['service_date'] ?? $line['service_date']) - $booking_activities[0]['activity_date'];

                foreach($booking_activities as $booking_activity) {
                    $activity_date = $booking_activity['activity_date'] + $shift_days;
                    if($activity_date < $line['booking_line_group_id']['date_from'] || $activity_date > $line['booking_line_group_id']['date_to']) {
                        return ['service_date' => ['already_used' => 'Moment conflict with another activity of the group.']];
                    }

                    $existing_activity = BookingActivity::search([
                            ['booking_line_group_id', '=', $line['booking_line_group_id']['id']],
                            ['activity_date', '=', $activity_date],
                            ['time_slot_id', '=', ($values['time_slot_id'] ?? $booking_activity['time_slot_id'])],
                            ['activity_booking_line_id', '<>', $line['id']]
                        ])
                        ->read(['id'])
                        ->first();

                    if(!is_null($existing_activity)) {
                        return ['service_date' => ['already_used' => 'Moment conflict with another activity of the group.']];
                    }
                }
            }

        }

        return parent::canupdate($self, $values, $lang);
    }

    /**
     * Update the price_id according to booking line settings.
     *
     * This method is called at booking line creation if product_id is amongst the fields.
     */
    public static function onupdateProductId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:onupdateProductId", QN_REPORT_DEBUG);

        /*
            update product model according to newly set product
        */
        $lines = $om->read(self::getType(), $oids, ['product_id.product_model_id', 'booking_line_group_id'], $lang);
        foreach($lines as $lid => $line) {
            $om->update(self::getType(), $lid, ['product_model_id' => $line['product_id.product_model_id']]);
        }

        /*
            reset computed fields related to product model
        */
        $om->update(self::getType(), $oids, ['name' => null, 'qty_accounting_method' => null, 'is_rental_unit' => null, 'is_accomodation' => null, 'is_meal' => null]);

        /*
            update SPM, if necessary
        */
        $om->callonce(self::getType(), 'updateSPM', $oids);

        /*
            resolve price_id according to new product_id
        */
        $om->callonce(self::getType(), 'updatePriceId', $oids, [], $lang);

        /*
            check booking type and checkin/out times dependencies, and auto-assign qty if required
        */

        $lines = $om->read(self::getType(), $oids, [
            'product_id.product_model_id',
            'product_id.product_model_id.booking_type_id',
            'product_id.product_model_id.capacity',
            'product_id.product_model_id.has_duration',
            'product_id.product_model_id.duration',
            'product_id.product_model_id.is_repeatable',
            'product_id.product_model_id.is_activity',
            'product_id.product_model_id.is_fullday',
            'product_id.product_model_id.has_duration',
            'product_id.product_model_id.duration',
            'product_id.product_model_id.has_transport_required',
            'product_id.product_model_id.transport_product_model_id',
            'product_id.product_model_id.has_supply',
            'product_id.product_model_id.supplies_ids',
            'product_id.product_model_id.has_provider',
            'product_id.product_model_id.providers_ids',
            'product_id.product_model_id.has_rental_unit',
            'product_id.product_model_id.activity_rental_units_ids',
            'product_id.has_age_range',
            'product_id.age_range_id',
            'product_id.description',
            'booking_id',
            'booking_id.center_id',
            'booking_id.date_from',
            'booking_id.date_to',
            'booking_line_group_id',
            'booking_line_group_id.is_sojourn',
            'booking_line_group_id.is_event',
            'booking_line_group_id.nb_pers',
            'booking_line_group_id.nb_nights',
            'booking_line_group_id.has_pack',
            'booking_line_group_id.pack_id.has_age_range',
            'booking_line_group_id.age_range_assignments_ids',
            'name',
            'order',
            'qty',
            'has_own_qty',
            'is_rental_unit',
            'is_accomodation',
            'is_meal',
            'qty_accounting_method',
            'service_date',
            'time_slot_id',
            'time_slot_id.name'
        ], $lang);

        foreach($lines as $lid => $line) {
            // if model of chosen product has a non-generic booking type, update the booking of the line accordingly
            if(isset($line['product_id.product_model_id.booking_type_id']) && $line['product_id.product_model_id.booking_type_id'] != 1) {
                $om->update(Booking::getType(), $line['booking_id'], ['type_id' => $line['product_id.product_model_id.booking_type_id']]);
            }

            // if line is a rental unit, use its related product info to update parent group schedule, if possible
            if($line['is_rental_unit']) {
                $models = $om->read(\sale\catalog\ProductModel::getType(), $line['product_id.product_model_id'], ['type', 'service_type', 'schedule_type', 'schedule_default_value'], $lang);
                if($models > 0 && count($models)) {
                    $model = reset($models);
                    if($model['type'] == 'service' && $model['service_type'] == 'schedulable' && $model['schedule_type'] == 'timerange') {
                        // retrieve relative timestamps
                        $schedule = $model['schedule_default_value'];
                        if(strlen($schedule)) {
                            $times = explode('-', $schedule);
                            $parts = explode(':', $times[0]);
                            $schedule_from = $parts[0]*3600 + $parts[1]*60;
                            $parts = explode(':', $times[1]);
                            $schedule_to = $parts[0]*3600 + $parts[1]*60;
                            // update the parent group schedule
                            $om->update(BookingLineGroup::getType(), $line['booking_line_group_id'], ['time_from' => $schedule_from, 'time_to' => $schedule_to], $lang);
                        }
                    }
                }
            }

            if($line['product_id.product_model_id.is_activity']) {
                // #memo - prevent in case of direct assignment of an activity (product with is_activity) on the BookingLine
                if(!isset($line['service_date'], $line['time_slot_id'])) {
                    continue;
                }

                $booking_activities = self::computeLineActivities(
                    $line['service_date'],
                    $line['time_slot_id'],
                    $line['product_id.product_model_id.is_fullday'] ?? false,
                    $line['product_id.product_model_id.has_duration'] ?? false,
                    $line['product_id.product_model_id.duration'] ?? 0,
                    $line['product_id.description'] ?? ''
                );

                $main_activity_id = null;
                foreach($booking_activities as $index => $activity) {
                    $activity = BookingActivity::create(array_merge($activity, ['activity_booking_line_id' => $lid]))
                        ->read(['id'])
                        ->first();

                    if($index === 0) {
                        $main_activity_id = $activity['id'];
                    }
                }

                BookingLine::id($lid)->update(['booking_activity_id' => $main_activity_id]);

                BookingActivity::id($main_activity_id)->do('update-counters');

                $line_order = $line['order'];

                // create transport related lines
                if($line['product_id.product_model_id.has_transport_required'] && $line['product_id.product_model_id.transport_product_model_id']) {
                    $res = \eQual::run('get', 'sale_catalog_product_collect', [
                        'center_id' => $line['booking_id.center_id'],
                        'domain'    => ['product_model_id', '=', $line['product_id.product_model_id.transport_product_model_id']],
                        'date_from' => $line['booking_id.date_from'],
                        'date_to'   => $line['booking_id.date_to']
                    ]);

                    if(!empty($res)) {
                        $description = sprintf('Transport (%s - %s) : %s',
                            date('d/m/Y', $line['service_date']),
                            $line['time_slot_id.name'],
                            $line['name']
                        );

                        $booking_line = self::create([
                            'order'                 => ++$line_order,
                            'booking_id'            => $line['booking_id'],
                            'booking_line_group_id' => $line['booking_line_group_id'],
                            'service_date'          => $line['service_date'],
                            'time_slot_id'          => $line['time_slot_id'],
                            'booking_activity_id'   => $main_activity_id,
                            'description'           => $description
                        ])
                            ->read(['id'])
                            ->first();

                        \eQual::run('do', 'sale_booking_update-bookingline-product', [
                            'id'            => $booking_line['id'],
                            'product_id'    => $res[0]['id']
                        ]);
                    }
                }

                // create supplies related lines
                if($line['product_id.product_model_id.has_supply']) {
                    foreach($line['product_id.product_model_id.supplies_ids'] as $supply_product_model_id) {
                        $res = \eQual::run('get', 'sale_catalog_product_collect', [
                            'center_id' => $line['booking_id.center_id'],
                            'domain'    => ['product_model_id', '=', $supply_product_model_id],
                            'date_from' => $line['booking_id.date_from'],
                            'date_to'   => $line['booking_id.date_to']
                        ]);

                        if(empty($res)) {
                            continue;
                        }

                        $booking_line = self::create([
                                'order'                 => ++$line_order,
                                'booking_id'            => $line['booking_id'],
                                'booking_line_group_id' => $line['booking_line_group_id'],
                                'service_date'          => $line['service_date'],
                                'time_slot_id'          => $line['time_slot_id'],
                                'booking_activity_id'   => $main_activity_id
                            ])
                            ->read(['id'])
                            ->first();

                        \eQual::run('do', 'sale_booking_update-bookingline-product', [
                            'id'            => $booking_line['id'],
                            'product_id'    => $res[0]['id']
                        ]);
                    }
                }

                $booking_activity_data = [];
                if($line['product_id.product_model_id.has_provider'] && count($line['product_id.product_model_id.providers_ids']) === 1) {
                    $booking_activity_data['providers_ids'] = $line['product_id.product_model_id.providers_ids'];
                }
                if($line['product_id.product_model_id.has_rental_unit'] && count($line['product_id.product_model_id.activity_rental_units_ids']) === 1) {
                    $booking_activity_data['rental_unit_id'] = $line['product_id.product_model_id.activity_rental_units_ids'][0];
                }
                if(!empty($booking_activity_data)) {
                    BookingActivity::id($main_activity_id)->update($booking_activity_data);
                }
            }
        }

        // #memo - qty must always be recomputed, even if given amongst (updated) $values (when a new line is created the default qty is 1.0)
        foreach($lines as $lid => $line) {
            $qty = $line['qty'];
            if(!$line['has_own_qty']) {
                // retrieve number of persons to whom the product will be delivered (either nb_pers or age_range.qty)
                $nb_pers = $line['booking_line_group_id.nb_pers'];
                // retrieve nb_pers from age range
                // #memo - if parent group has a age_range set, keep `booking_line_group_id.nb_pers`
                if($line['product_id.has_age_range'] && !($line['booking_line_group_id.has_pack'] && $line['booking_line_group_id.pack_id.has_age_range'])) {
                    $age_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $line['booking_line_group_id.age_range_assignments_ids'], ['age_range_id', 'qty']);
                    foreach($age_assignments as $assignment) {
                        if($assignment['age_range_id'] == $line['product_id.age_range_id']) {
                            $nb_pers = $assignment['qty'];
                            break;
                        }
                    }
                }
                // default number of times the product is repeated (accounting method = 'unit' with no own quantity and non-repeatable)
                $nb_repeat = 1;
                if($line['product_id.product_model_id.has_duration']) {
                    $nb_repeat = $line['product_id.product_model_id.duration'];
                }
                elseif($line['booking_line_group_id.is_sojourn']) {
                    if($line['product_id.product_model_id.is_repeatable']) {
                        $nb_repeat = max(1, $line['booking_line_group_id.nb_nights']);
                    }
                }
                elseif($line['booking_line_group_id.is_event']) {
                    if($line['product_id.product_model_id.is_repeatable']) {
                        $nb_repeat = $line['booking_line_group_id.nb_nights'] + 1;
                    }
                }
                // retrieve quantity to consider
                $qty = self::computeLineQty(
                    $line['qty_accounting_method'],
                    $nb_repeat,
                    $nb_pers,
                    $line['product_id.product_model_id.is_repeatable'],
                    $line['is_accomodation'],
                    $line['product_id.product_model_id.capacity']
                );
            }
            if($qty != $line['qty'] || $line['is_rental_unit']) {
                $om->update(self::getType(), $lid, ['qty' => $qty]);
            }
        }

        /*
            qty might have been updated: make sure qty_var is consistent
        */
        $om->callonce(self::getType(), 'updateQty', $oids, [], $lang);

        /*
            update parent groups rental unit assignments
        */

        // group lines by booking_line_group
        $sojourns = [];
        foreach($lines as $lid => $line) {
            $gid = $line['booking_line_group_id'];
            if(!isset($sojourns[$gid])) {
                $sojourns[$gid] = [];
            }
            $sojourns[$gid][] = $lid;
        }
        foreach($sojourns as $gid => $lines_ids) {
            $groups = $om->read(BookingLineGroup::getType(), $gid, ['has_locked_rental_units', 'booking_id.center_office_id']);
            if($groups > 0 && count($groups)) {
                $group = reset($groups);
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
            }
            // retrieve all impacted product_models
            $olines = $om->read(self::getType(), $lines_ids, ['product_id.product_model_id'], $lang);
            $product_models_ids = array_map(function($a) { return $a['product_id.product_model_id'];}, $olines);
            if(!$rentalunits_manual_assignment) {
                // remove all assignments from the group that relate to found product_model
                $spm_ids = $om->search(SojournProductModel::getType(), [['booking_line_group_id', '=', $gid], ['product_model_id', 'in', $product_models_ids]]);
                $om->remove(SojournProductModel::getType(), $spm_ids, true);
            }
            // retrieve all lines from parent group that need to be reassigned
            // #memo - we need to handle these all at a time to avoid assigning a same rental unit twice
            $lines_ids = $om->search(self::getType(), [['booking_line_group_id', '=', $gid], ['product_model_id', 'in', $product_models_ids]]);
            // recreate rental unit assignments
            $om->callonce(BookingLineGroup::getType(), 'createRentalUnitsAssignmentsFromLines', $gid, $lines_ids, $lang);
        }


        /*
            update parent groups price adapters
        */

        // group lines by booking_line_group
        $sojourns = [];
        foreach($lines as $lid => $line) {
            $gid = $line['booking_line_group_id'];
            if(!isset($sojourns[$gid])) {
                $sojourns[$gid] = [];
            }
            $sojourns[$gid][$lid] = true;
        }
        foreach($sojourns as $gid => $map_lines_ids) {
            $om->callonce(BookingLineGroup::getType(), 'updatePriceAdaptersFromLines', $gid, array_keys($map_lines_ids), $lang);
        }

        /*
            update bed_linens and make_beds
        */
        $map_groups_ids = [];
        foreach($lines as $line) {
            $map_groups_ids[$line['booking_line_group_id']] = true;
        }
        $om->callonce(BookingLineGroup::getType(), 'updateBedLinensAndMakeBeds', array_keys($map_groups_ids), [], $lang);

        /*
            reset computed fields related to price
        */
        $om->callonce(self::getType(), '_resetPrices', $oids, [], $lang);
    }

    private static function computeLineActivities(int $service_date, int $time_slot_id, bool $is_fullday, bool $has_duration, int $duration, string $description=''): array {
        $booking_activities = [
            [
                'activity_date' => $service_date,
                'time_slot_id'  => $time_slot_id,
                'description'   => $description,
                'is_virtual'    => false
            ]
        ];

        $map_codes_time_slot_ids = [];
        if($is_fullday) {
            $time_slots = TimeSlot::search(['code', 'in', ['AM', 'PM']])
                ->read(['id', 'code'])
                ->get();

            foreach($time_slots as $slot) {
                $map_codes_time_slot_ids[$slot['code']] = $slot['id'];
            }

            $booking_activities[] = [
                'activity_date' => $service_date,
                'time_slot_id'  => $map_codes_time_slot_ids['AM'] === $time_slot_id ? $map_codes_time_slot_ids['PM'] : $map_codes_time_slot_ids['AM'],
                'description'   => $description,
                'is_virtual'    => true
            ];
        }

        if($has_duration) {
            $remaining_days_qty = $duration - 1;

            for($i = 1; $i <= $remaining_days_qty; $i++) {
                $booking_activities[] = [
                    'activity_date' => $service_date + $i * 86400,
                    'time_slot_id'  => $time_slot_id,
                    'description'   => $description,
                    'is_virtual'    => true
                ];

                if($is_fullday) {
                    $booking_activities[] = [
                        'activity_date' => $service_date + $i * 86400,
                        'time_slot_id'  => $map_codes_time_slot_ids['AM'] === $time_slot_id ? $map_codes_time_slot_ids['PM'] : $map_codes_time_slot_ids['AM'],
                        'description'   => $description,
                        'is_virtual'    => true
                    ];
                }
            }
        }

        return $booking_activities;
    }

    /**
     * Update the quantity of products.
     *
     * This handler is called at booking line creation and all subsequent qty updates.
     * It is in charge of updating the rental units assignments related to the line.
     */
    public static function onupdateQty($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:onupdateQty", QN_REPORT_DEBUG);

        $lines = $om->read(self::getType(), $oids, [
            'qty',
            'product_id.product_model_id.has_provider',
            'booking_activity_id',
            'booking_activity_id.providers_ids'
        ]);
        if($lines > 0) {
            foreach($lines as $line) {
                $booking_activity_updates = ['qty' => null];
                if($line['product_id.product_model_id.has_provider'] && count($line['booking_activity_id.providers_ids']) > $line['qty']) {
                    $providers_ids_to_remove = [];
                    for($i = count($line['booking_activity_id.providers_ids']) - 1; $i >= $line['qty']; $i--) {
                        $providers_ids_to_remove[] = -$line['booking_activity_id.providers_ids'][$i];
                    }

                    $booking_activity_updates['providers_ids'] = $providers_ids_to_remove;
                }

                BookingActivity::id($line['booking_activity_id'])->update($booking_activity_updates);
            }
        }

        // Reset computed fields related to price (because they depend on qty)
        $om->callonce(self::getType(), '_resetPrices', $oids, [], $lang);
    }

    public static function onupdateQtyVars($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:onupdateQtyVars", QN_REPORT_DEBUG);

        // reset computed fields related to price
        $om->callonce(self::getType(), '_resetPrices', $oids, [], $lang);

        $lines = $om->read(self::getType(), $oids, [
            'qty_vars',
            'has_own_qty',
            'is_meal',
            'is_rental_unit',
            'is_accomodation',
            'qty_accounting_method',
            'booking_line_group_id.nb_pers',
            'booking_line_group_id.nb_nights',
            'booking_line_group_id.age_range_assignments_ids',
            'booking_line_group_id.is_sojourn',
            'booking_line_group_id.is_event',
            'booking_line_group_id.has_pack',
            'booking_line_group_id.pack_id.has_age_range',
            'product_id.product_model_id.capacity',
            'product_id.product_model_id.has_duration',
            'product_id.product_model_id.duration',
            'product_id.product_model_id.is_repeatable',
            'product_id.has_age_range',
            'product_id.age_range_id'
        ]);

        if($lines > 0) {
            // set quantities according to qty_vars arrays
            foreach($lines as $lid => $line) {
                // qty_vars should be a JSON array holding a series of deltas
                $qty_vars = json_decode($line['qty_vars']);
                if($qty_vars && !$line['has_own_qty']) {
                    // retrieve number of persons to whom the product will be delivered (either nb_pers or age_range.qty)
                    $nb_pers = $line['booking_line_group_id.nb_pers'];
                    // #memo - if parent group has a age_range set, keep `booking_line_group_id.nb_pers`
                    if($line['product_id.has_age_range'] && !($line['booking_line_group_id.has_pack'] && $line['booking_line_group_id.pack_id.has_age_range'])) {
                        $age_range_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $line['booking_line_group_id.age_range_assignments_ids'], ['age_range_id', 'qty']);
                        foreach($age_range_assignments as $assignment) {
                            if($assignment['age_range_id'] == $line['product_id.age_range_id']) {
                                $nb_pers = $assignment['qty'];
                                break;
                            }
                        }
                    }
                    // default number of times the product is repeated (accounting method = 'unit' with no own quantity and non-repeatable)
                    $nb_repeat = 1;
                    if($line['product_id.product_model_id.has_duration']) {
                        $nb_repeat = $line['product_id.product_model_id.duration'];
                    }
                    elseif($line['booking_line_group_id.is_sojourn']) {
                        if($line['product_id.product_model_id.is_repeatable']) {
                            $nb_repeat = max(1, $line['booking_line_group_id.nb_nights']);
                        }
                    }
                    elseif($line['booking_line_group_id.is_event']) {
                        if($line['product_id.product_model_id.is_repeatable']) {
                            $nb_repeat = $line['booking_line_group_id.nb_nights'] + 1;
                        }
                    }
                    // retrieve quantity to consider
                    $qty = self::computeLineQty(
                        $line['qty_accounting_method'],
                        $nb_repeat,
                        $nb_pers,
                        $line['product_id.product_model_id.is_repeatable'],
                        $line['is_accomodation'],
                        $line['product_id.product_model_id.capacity']
                    );
                    // adapt final qty according to variations
                    foreach($qty_vars as $variation) {
                        $qty += $variation;
                    }
                    $om->update(self::getType(), $lid, ['qty' => $qty]);
                }
                else {
                    $om->callonce(self::getType(), 'updateQty', $oids, [], $lang);
                }
            }
        }
    }

    /**
     * Handler for unit_price field update.
     * Resets computed fields related to price.
     */
    public static function onupdateUnitPrice($om, $oids, $values, $lang) {
        $om->update(self::getType(), $oids, ['has_manual_unit_price' => true], $lang);
        $om->callonce(self::getType(), '_resetPrices', $oids, $values, $lang);
    }

    public static function onupdateVatRate($om, $oids, $values, $lang) {
        // mark line with manual vat_rate
        $om->update(self::getType(), $oids, ['has_manual_vat_rate' => true], $lang);
        // reset computed fields related to price
        $om->callonce(self::getType(), '_resetPrices', $oids, $values, $lang);
    }

    public static function onupdatePriceId($om, $oids, $values, $lang) {
        // reset computed fields related to price
        $om->callonce(self::getType(), '_resetPrices', $oids, $values, $lang);
    }

    public static function onupdatePriceAdaptersIds($om, $oids, $values, $lang) {
        // reset computed fields related to price
        $om->callonce(self::getType(), '_resetPrices', $oids, $values, $lang);
    }

    /**
     * New group assignment (should be called upon creation only)
     */
    public static function onupdateBookingLineGroupId($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:onupdateBookingLineGroupId", QN_REPORT_DEBUG);
    }

    /**
     * Reset computed fields related to price.
     */
    public static function _resetPrices($om, $oids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:_resetPrices", QN_REPORT_DEBUG);

        $lines = $om->read(self::getType(), $oids, ['price_id', 'has_manual_unit_price', 'has_manual_vat_rate', 'booking_line_group_id', 'booking_activity_id'], $lang);

        if($lines > 0) {
            $new_values = ['vat_rate' => null, 'unit_price' => null, 'total' => null, 'price' => null, 'fare_benefit' => null, 'discount' => null, 'free_qty' => null];
            // #memo - computed fields (eg. vat_rate and unit_price) can also be set manually, in such case we don't want to overwrite the assigned value
            if(count($values)) {
                $fields = array_keys($new_values);
                foreach($values as $field => $value) {
                    if(in_array($field, $fields) && !is_null($value)) {
                        $new_values[$field] = $value;
                    }
                }
            }

            // update lines
            foreach($lines as $lid => $line) {
                $assigned_values = $new_values;
                // don't reset unit_price for products that have a manual unit price set or that are not linked to a Price object
                if($line['has_manual_unit_price'] || !$line['price_id']) {
                    unset($assigned_values['unit_price']);
                }
                // don't reset vat_rate for products that have a manual vat rate set or that are not linked to a Price object
                if($line['has_manual_vat_rate'] || !$line['price_id']) {
                    unset($assigned_values['vat_rate']);
                }
                $om->update(self::getType(), $lid, $assigned_values);
            }

            // update parent objects
            $booking_line_groups_ids = array_map(function ($a) { return $a['booking_line_group_id']; }, array_values($lines));
            $om->callonce(\sale\booking\BookingLineGroup::getType(), '_resetPrices', $booking_line_groups_ids, [], $lang);

            $booking_activities_ids = array_map(function ($a) { return $a['booking_activity_id']; }, array_values($lines));
            BookingActivity::ids($booking_activities_ids)->do('reset-prices');
        }
    }

    /**
     * Update the quantity according to parent group (pack_id, nb_pers, nb_nights) and variation array.
     * This method is triggered on fields update from BookingLineGroup or onupdateQtyVars from BookingLine.
     *
     */
    public static function updateQty($om, $ids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:updateQty", QN_REPORT_DEBUG);

        foreach($ids as $id) {
            self::refreshQty($om, $id);
        }
    }

    /**
     * Try to assign the price_id according to the current product_id.
     * Resolve the price from the first applicable price list, based on booking_line_group settings and booking center.
     * If found price list is pending, marks the booking as TBC.
     *
     * #memo - updatePriceId is also called upon change on booking_id.center_id and booking_line_group_id.date_from.
     *
     * @param \equal\orm\ObjectManager $om
     */
    public static function updatePriceId($om, $ids, $values, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:updatePriceId", QN_REPORT_DEBUG);

        foreach($ids as $id) {
            self::refreshPriceId($om, $id);
        }
    }

    /**
     * Search for a Price within the matching published Price Lists of the given lines.
     * If no value is found for a line, the result is not set.
     * #memo This method has the same format and behavior as regular calc_() methods but `price_id` is not a computed field.
     *
     */
    public static function searchPriceId($om, $ids, $product_id) {
        $result = [];
        $lines = $om->read(self::getType(), $ids, [
            'booking_line_group_id.date_from',
            'booking_line_group_id.rate_class_id',
            'booking_id.center_id.price_list_category_id',
        ]);

        foreach($lines as $line_id => $line) {
            // search for matching price lists by starting with the one having the shortest duration
            $price_lists_ids = $om->search(
                \sale\price\PriceList::getType(),
                [
                    [
                        ['price_list_category_id', '=', $line['booking_id.center_id.price_list_category_id']],
                        ['date_from', '<=', $line['booking_line_group_id.date_from']],
                        ['date_to', '>=', $line['booking_line_group_id.date_from']],
                        ['status', '=', 'published']
                    ]
                ],
                ['duration' => 'asc']
            );

            if($price_lists_ids > 0 && count($price_lists_ids)) {
                foreach($price_lists_ids as $price_list_id) {

                    $prices_ids = $om->search(\sale\price\Price::getType(), [
                        ['price_list_id', '=', $price_list_id],
                        ['product_id', '=', $product_id],
                        ['has_rate_class' , '=', true],
                        ['rate_class_id', '=', $line['booking_line_group_id.rate_class_id']]
                    ]);

                    if(!empty($prices_ids)) {
                        $result[$line_id] = reset($prices_ids);
                        break;
                    }

                    $prices_ids = $om->search(\sale\price\Price::getType(), [
                        ['price_list_id', '=', $price_list_id],
                        ['product_id', '=', $product_id]
                    ]);

                    if(!empty($prices_ids)) {
                        $result[$line_id] = reset($prices_ids);
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Search for a Price within the matching unpublished/pending Price Lists of the given lines.
     * If no value is found for a line, the result is not set.
     * #memo This method has the same format and behavior as regular calc_() methods but `price_id` is not a computed field.
     *
     * #todo - should be deprecated and use refresh...() methods instead
     *
     */
    public static function searchPriceIdUnpublished($om, $ids, $product_id) {
        $result = [];
        $lines = $om->read(self::getType(), $ids, [
            'booking_line_group_id.date_from',
            'booking_line_group_id.rate_class_id',
            'booking_id.center_id.price_list_category_id',
        ]);

        foreach($lines as $line_id => $line) {
            // search for matching price lists by starting with the one having the shortest duration
            $price_lists_ids = $om->search(
                \sale\price\PriceList::getType(),
                [
                    [
                        ['price_list_category_id', '=', $line['booking_id.center_id.price_list_category_id']],
                        ['date_from', '<=', $line['booking_line_group_id.date_from']],
                        ['date_to', '>=', $line['booking_line_group_id.date_from']],
                        ['status', '=', 'pending']
                    ]
                ],
                ['duration' => 'asc']
            );

            if($price_lists_ids > 0 && count($price_lists_ids)) {
                foreach($price_lists_ids as $price_list_id) {
                    $prices_ids = $om->search(\sale\price\Price::getType(), [
                        ['price_list_id', '=', $price_list_id],
                        ['product_id', '=', $product_id],
                        [ 'has_rate_class' , '=', true],
                        ['rate_class_id', '=', $line['booking_line_group_id.rate_class_id']],
                    ]);

                    if (!empty($prices_ids)) {
                        $result[$line_id] = reset($prices_ids);
                        break;
                    }

                    $prices_ids = $om->search(\sale\price\Price::getType(), [
                        ['price_list_id', '=', $price_list_id],
                        ['product_id', '=', $product_id],
                    ]);

                    if (!empty($prices_ids)) {
                        $result[$line_id] = reset($prices_ids);
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Retrieve quantity to consider for a line, according to context.
     *
     * @param string    $method             Accounting method, according to qty_accounting_method of the related Product Model ('accomodation', 'person' or 'unit').
     * @param integer   $nb_repeat          Number of times the product must be repeated.
     * @param integer   $nb_pers            Number of persons the line refers to (from parent group).
     * @param boolean   $is_repeatable      Flag marking the line as to be repeat for the duration relating to the parent group.
     * @param boolean   $is_accommodation   Flag marking the line as an accommodation.
     * @param boolean   $capacity           Capacity of the product model the line refers to.
     */
    private static function computeLineQty($method, $nb_repeat, $nb_pers, $is_repeatable, $is_accommodation, $capacity) {
        // default quantity (duration of the group or own quantity or method = 'unit')
        $qty = $nb_repeat;
        // service is accounted by accommodation
        if($method == 'accomodation') {
            $qty = $nb_repeat;
            if($is_repeatable) {
                // lines having a product 'by accommodation' have a qty assigned to the computed duration of the group (events are accounted in days, and sojourns in nights)
                if($capacity < $nb_pers && $capacity > 0) {
                    $qty = $nb_repeat * ceil($nb_pers / $capacity);
                }
                else {
                    $qty = $nb_repeat;
                }
            }
            else {
                if($capacity < $nb_pers && $capacity > 0) {
                    $qty = ceil($nb_pers / $capacity);
                }
                else {
                    $qty = 1;
                }
            }
        }
        // service is accounted by person
        elseif($method == 'person') {
            if($is_repeatable) {
                if($is_accommodation && $capacity > 0) {
                    // either 1 accomodation, or as many accommodations as necessary to host the number of persons
                    $qty = $nb_repeat * ceil($nb_pers / $capacity);
                }
                else {
                    // other repeatable services (meeting rooms, meals, animations, ...)
                    $qty = $nb_pers * $nb_repeat;
                }
            }
            else {
                $qty = $nb_pers;
            }
        }
        return $qty;
    }

    /**
     * This method is used to remove all SPM relating to the product model if parent group does not hold a similar product anymore.
     *
     * @param  \equal\orm\ObjectManager     $om        ObjectManager instance.
     * @param  array                        $ids       List of objects identifiers.
     * @return void
     */
    public static function updateSPM($om, $ids, $values=[], $lang='en') {
        $lines = $om->read(self::getType(), $ids, ['booking_line_group_id']);
        if($lines > 0 && count($lines)) {
            $groups_ids = array_map(function($a) {return $a['booking_line_group_id'];}, $lines);
            $om->callonce(BookingLineGroup::getType(), 'updateSPM', $groups_ids, $values);
        }
    }

    /**
     * Check whether an object can be deleted, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $oids       List of objects identifiers.
     * @return boolean  Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $oids) {
        $lines = $om->read(self::getType(), $oids, ['booking_id.status', 'booking_line_group_id.is_extra', 'booking_line_group_id.has_schedulable_services', 'booking_line_group_id.has_consumptions']);

        if($lines > 0) {
            foreach($lines as $line) {
                // #memo - booking might have been deleted (this is triggered by the Booking::onafterdelete callback)
                if($line['booking_line_group_id.is_extra']) {
                    if($line['booking_id.status'] && !in_array($line['booking_id.status'], ['quote', 'confirmed', 'validated', 'checkedin', 'checkedout'])) {
                        return ['booking_id' => ['non_deletable' => 'Extra Services can only be updated after confirmation and before invoicing.']];
                    }
                }
                else {
                    if($line['booking_id.status'] && $line['booking_id.status'] != 'quote') {
                        return ['booking_id' => ['non_deletable' => 'Services cannot be updated for non-quote bookings.']];
                    }
                    if($line['booking_line_group_id.is_extra'] && $line['booking_line_group_id.has_schedulable_services'] && $line['booking_line_group_id.has_consumptions']) {
                        return ['booking_id' => ['non_editable' => 'Lines from extra services cannot be removed once consumptions have been created.']];
                    }
                }
            }
        }

        return parent::candelete($om, $oids);
    }

    /**
     * Hook invoked before object deletion for performing object-specific additional operations.
     * This hook is used:
     *   - to remove all SPM relating to the product model if parent group does not hold a similar product anymore
     *   - to update the booking activity counters (place of activity type in sojourn)
     *   - to update the bed_linens or make_beds flag of the booking line group
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return void
     */
    public static function onbeforedelete($self, $om, $ids) {
        $self->read(['booking_line_group_id', 'product_id' => ['product_model_id' => ['is_activity']]]);

        $map_groups_ids = [];
        foreach($self as $id => $bookingLine) {
            if($bookingLine['booking_line_group_id']) {
                $map_groups_ids[$bookingLine['booking_line_group_id']] = true;
            }

            if($bookingLine['product_id']['product_model_id']['is_activity'] ?? false) {
                BookingActivity::search(['booking_line_group_id', '=', $bookingLine['booking_line_group_id']])
                    ->do('update-counters');
            }
        }

        if(count($map_groups_ids)) {
            $om->callonce(BookingLineGroup::getType(), 'updateBedLinensAndMakeBeds', array_keys($map_groups_ids), ['ignored_lines_ids' => $self->ids()]);
        }

        $om->callonce(self::getType(), 'updateSPM', $ids, ['deleted' => $ids]);
    }

    /**
     * For BookingLines the display name is the name of the product it relates to.
     *
     */
    public static function calcName($om, $oids, $lang) {
        $result = [];
        $res = $om->read(get_called_class(), $oids, ['product_id.name'], $lang);
        foreach($res as $oid => $odata) {
            $result[$oid] = $odata['product_id.name'];
        }
        return $result;
    }

    /**
     * Compute the VAT excl. unit price of the line, with automated discounts applied.
     *
     */
    public static function calcUnitPrice($om, $oids, $lang) {
        $result = [];
        $lines = $om->read(get_called_class(), $oids, [
                    'price_id.price',
                    'auto_discounts_ids'
                ]);
        if($lines > 0) {
            foreach($lines as $oid => $odata) {
                $price = 0;
                if($odata['price_id.price']) {
                    $price = (float) $odata['price_id.price'];
                }
                $disc_percent = 0.0;
                $disc_value = 0.0;
                if(isset($odata['auto_discounts_ids']) && $odata['auto_discounts_ids']) {
                    $adapters = $om->read('sale\booking\BookingPriceAdapter', $odata['auto_discounts_ids'], ['type', 'value', 'discount_id.discount_list_id.rate_max']);
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
                    // #memo - when price is adapted, it no longer holds more than 2 decimals (so that unit_price x qty = displayed price)
                    $price = max(0, round(($price * (1 - $disc_percent)) - $disc_value, 2));
                }
                // if no adapters, leave price given from price_id (might have more than 2 decimal digits)
                $result[$oid] = $price;
            }
        }
        return $result;
    }

    public static function calcFreeQty($om, $oids, $lang) {
        $result = [];
        $lines = $om->read(self::getType(), $oids, [
            'qty',
            'auto_discounts_ids',
            'manual_discounts_ids',
            'qty_accounting_method',
            'is_accomodation',
            'is_meal',
            'is_snack',
            'product_id.product_model_id.has_duration',
            'product_id.product_model_id.duration',
            'product_id.product_model_id.is_repeatable',
            'product_id.product_model_id.capacity',
            'product_id.product_model_id.is_repeatable',
            'product_id.has_age_range',
            'product_id.age_range_id',
            'booking_line_group_id.is_sojourn',
            'booking_line_group_id.is_event',
            'booking_line_group_id.nb_nights',
            'booking_line_group_id.age_range_assignments_ids.age_range_id',
            'booking_line_group_id.age_range_assignments_ids.free_qty'
        ]);

        $age_range_freebies = Setting::get_value('sale', 'features', 'booking.age_range.freebies', false);

        foreach($lines as $id => $line) {
            $free_qty = 0;

            $adapters = $om->read('sale\booking\BookingPriceAdapter', $line['auto_discounts_ids'], ['type', 'value']);
            foreach($adapters as $adapter_id => $adapter) {
                if($adapter['type'] == 'freebie') {
                    $free_qty += $adapter['value'];
                }
            }
            // check additional manual discounts
            $discounts = $om->read('sale\booking\BookingPriceAdapter', $line['manual_discounts_ids'], ['type', 'value']);
            foreach($discounts as $discount_id => $discount) {
                if($discount['type'] == 'freebie') {
                    $free_qty += $discount['value'];
                }
            }

            // #memo - for all sojourn types, freebies apply only on meals and accommodations
            if($age_range_freebies && ($line['is_accomodation'] || $line['is_meal'] || $line['is_snack'])) {
                $nb_repeat = 1;
                if($line['product_id.product_model_id.has_duration']) {
                    $nb_repeat = $line['product_id.product_model_id.duration'];
                }
                elseif($line['booking_line_group_id.is_sojourn']) {
                    if($line['product_id.product_model_id.is_repeatable']) {
                        $nb_repeat = max(1, $line['booking_line_group_id.nb_nights']);
                    }
                }
                elseif($line['booking_line_group_id.is_event']) {
                    if($line['product_id.product_model_id.is_repeatable']) {
                        $nb_repeat = $line['booking_line_group_id.nb_nights'] + 1;
                    }
                }

                foreach($line['booking_line_group_id.age_range_assignments_ids.age_range_id'] as $index => $assignment) {
                    if(!$line['product_id.has_age_range'] || $line['product_id.age_range_id'] === $assignment['age_range_id']) {
                        $free_qty += self::computeLineQty(
                            $line['qty_accounting_method'],
                            $nb_repeat,
                            $line['booking_line_group_id.age_range_assignments_ids.free_qty'][$index]['free_qty'],
                            $line['product_id.product_model_id.is_repeatable'],
                            $line['is_accomodation'],
                            $line['product_id.product_model_id.capacity']
                        );
                    }
                    if($line['product_id.age_range_id'] === $assignment['age_range_id']) {
                        break;
                    }
                }
            }

            $result[$id] = min($free_qty, $line['qty']);
        }
        return $result;
    }

    public static function calcDiscount($om, $oids, $lang) {
        $result = [];

        $lines = $om->read(self::getType(), $oids, ['manual_discounts_ids', 'unit_price']);

        foreach($lines as $oid => $line) {
            $result[$oid] = (float) 0.0;
            // apply additional manual discounts
            $discounts = $om->read('sale\booking\BookingPriceAdapter', $line['manual_discounts_ids'], ['type', 'value']);
            foreach($discounts as $aid => $adata) {
                if($adata['type'] == 'percent') {
                    $result[$oid] += $adata['value'];
                }
                else if($adata['type'] == 'amount' && $line['unit_price'] != 0) {
                    // amount discount is converted to a rate
                    $result[$oid] += round($adata['value'] / $line['unit_price'], 4);
                }
            }
        }
        return $result;
    }

    public static function calcFareBenefit($om, $oids, $lang) {
        $result = [];
        // #memo - price adapters are already applied on unit_price, so we need price_id
        $lines = $om->read(get_called_class(), $oids, ['free_qty', 'qty', 'price_id.price', 'vat_rate', 'unit_price']);
        if($lines) {
            foreach($lines as $lid => $line) {
                // delta between final price and catalog price
                $catalog_price = $line['price_id.price'] * $line['qty'] * (1.0 + $line['vat_rate']);
                $qty = max(0, $line['qty'] - $line['free_qty']);
                $fare_price = $line['unit_price'] * ($qty) * (1.0 + $line['vat_rate']);
                $benefit = round($catalog_price - $fare_price, 2);
                $result[$lid] = max(0.0, $benefit);
            }
        }
        return $result;
    }

    /**
     * Get final tax-included price of the line.
     *
     */
    public static function calcPrice($om, $oids, $lang) {
        $result = [];

        $lines = $om->read(get_called_class(), $oids, ['total','vat_rate']);

        foreach($lines as $oid => $odata) {
            $result[$oid] = round($odata['total'] * (1.0 + $odata['vat_rate']), 2);
        }
        return $result;
    }

    /**
     * Get total tax-excluded price of the line, with all discounts applied.
     *
     */
    public static function calcTotal($om, $ids, $lang) {
        $result = [];
        $lines = $om->read(self::getType(), $ids, [
                    'qty',
                    'unit_price',
                    'free_qty',
                    'discount',
                    'payment_mode'
                ]);
        if($lines > 0) {
            foreach($lines as $id => $line) {

                if($line['payment_mode'] == 'free') {
                    $result[$id] = 0.0;
                    continue;
                }
                $qty = max(0, $line['qty'] - $line['free_qty']);
                $result[$id] = round($line['unit_price'] * (1.0 - $line['discount']) * ($qty), 4);
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

    public static function calcIsAccomodation($om, $oids, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:calcIsAccomodation", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(self::getType(), $oids, [
            'product_id.product_model_id.is_accomodation'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_id.product_model_id.is_accomodation'];
            }
        }
        return $result;
    }

    public static function calcIsRentalUnit($om, $oids, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:calcIsRentalUnit", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(self::getType(), $oids, [
            'product_id.product_model_id.is_rental_unit'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_id.product_model_id.is_rental_unit'];
            }
        }
        return $result;
    }

    public static function calcIsMeal($om, $oids, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:calcIsMeal", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(self::getType(), $oids, [
            'product_id.product_model_id.is_meal'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                if(isset($odata['product_id.product_model_id.is_meal'])) {
                    $result[$oid] = $odata['product_id.product_model_id.is_meal'];
                }
            }
        }
        return $result;
    }

    public static function calcIsSnack($om, $oids, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:calcIsSnack", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(self::getType(), $oids, [
            'product_id.product_model_id.is_snack'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_id.product_model_id.is_snack'];
            }
        }
        return $result;
    }

    public static function calcIsActivity($self): array {
        $result = [];
        $self->read(['product_id' => ['product_model_id' => ['is_activity']]]);
        foreach($self as $id => $booking_line) {
            if(isset($booking_line['product_id']['product_model_id']['is_activity'])) {
                $result[$id] = $booking_line['product_id']['product_model_id']['is_activity'];
            }
        }

        return $result;
    }

    public static function calcIsTransport($self): array {
        $result = [];
        $self->read(['product_id' => ['product_model_id' => ['is_transport']]]);
        foreach($self as $id => $booking_line) {
            if(isset($booking_line['product_id']['product_model_id']['is_transport'])) {
                $result[$id] = $booking_line['product_id']['product_model_id']['is_transport'];
            }
        }

        return $result;
    }

    public static function calcIsSupply($self): array {
        $result = [];
        $self->read(['product_id' => ['product_model_id' => ['is_supply']]]);
        foreach($self as $id => $booking_line) {
            if(isset($booking_line['product_id']['product_model_id']['is_supply'])) {
                $result[$id] = $booking_line['product_id']['product_model_id']['is_supply'];
            }
        }

        return $result;
    }

    public static function calcIsFullday($self): array {
        $result = [];
        $self->read(['product_id' => ['product_model_id' => ['is_fullday']]]);
        foreach($self as $id => $booking_line) {
            if(isset($booking_line['product_id']['product_model_id']['is_fullday'])) {
                $result[$id] = $booking_line['product_id']['product_model_id']['is_fullday'];
            }
        }

        return $result;
    }

    public static function calcQtyAccountingMethod($om, $oids, $lang) {
        trigger_error("ORM::calling sale\booking\BookingLine:calcQtyAccountingMethod", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(self::getType(), $oids, [
            'product_id.product_model_id.qty_accounting_method'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_id.product_model_id.qty_accounting_method'];
            }
        }
        return $result;
    }

    public static function calcServiceDate($self): array {
        $result = [];
        $self->read([
            'booking_line_group_id' => ['date_from', 'date_to'],
            'product_model_id'      => ['type', 'service_type', 'is_repeatable', 'schedule_offset']
        ]);
        foreach($self as $id => $booking_line) {
            if(!$booking_line['product_model_id']) {
                continue;
            }
            $product_model = $booking_line['product_model_id'];
            if(
                $product_model['type'] === 'service'
                && $product_model['service_type'] === 'schedulable'
                && !$product_model['is_repeatable']
            ) {
                $offset_seconds = $product_model['schedule_offset'] * 86400;
                $service_date = $booking_line['booking_line_group_id']['date_from'] + $offset_seconds;
                if($service_date > $booking_line['booking_line_group_id']['date_to']) {
                    $service_date = $booking_line['booking_line_group_id']['date_to'];
                }

                $result[$id] = $service_date;
            }
        }

        return $result;
    }

    public static function calcTimeSlotId($self): array {
        $result = [];
        $self->read([
            'is_activity',
            'is_fullday',
            'booking_activity_id'   => ['time_slot_id'],
            'product_model_id'      => ['type', 'service_type', 'time_slot_id', 'time_slots_ids']
        ]);
        foreach($self as $id => $booking_line) {
            if(!$booking_line['product_model_id']) {
                continue;
            }
            if($booking_line['is_activity']) {
                if(!$booking_line['is_fullday']) {
                    $result[$id] = $booking_line['booking_activity_id']['time_slot_id'];
                }
            }
            else {
                $product_model = $booking_line['product_model_id'];
                if($product_model['type'] !== 'service' || $product_model['service_type'] !== 'schedulable') {
                    continue;
                }
                if($product_model['time_slot_id']) {
                    $result[$id] = $product_model['time_slot_id'];
                }
                elseif(!empty($product_model['time_slots_ids'])) {
                    $result[$id] = current($product_model['time_slots_ids']);
                }
            }
        }
        return $result;
    }

    public static function getConstraints() {
        return [
            /*
            // #memo - qty can be negative for cancelling/adapting initially booked services (typically in is_extra groups)
            'qty' =>  [
                'lte_zero' => [
                    'message'       => 'Quantity must be a positive value.',
                    'function'      => function ($qty, $values) {
                        return ($qty > 0);
                    }
                ]
            ]
            */
        ];
    }

    public static function refreshFreeQty($om, $id) {
        $om->update(self::getType(), $id, ['free_qty' => null]);
    }

    public static function refreshPrice($om, $id) {
        $lines = $om->read(self::getType(), $id, ['has_manual_unit_price']);

        if($lines <= 0) {
            return;
        }

        $line = reset($lines);

        if(!$line['has_manual_unit_price']) {
            // #memo - unit_price depends on applied PriceAdapters, unless if assigned manually
            $om->update(self::getType(), $id, ['unit_price' => null]);
        }

        $om->update(self::getType(), $id, ['price' => null, 'total' => null, 'fare_benefit' => null]);
    }

    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Refresh quantity of the line, based on the product model and the group characteristics.
     */
    public static function refreshQty($om, $id) {

        $lines = $om->read(self::getType(), $id, [
                'product_id.has_age_range',
                'product_id.age_range_id',
                'product_id.product_model_id.capacity',
                'product_id.product_model_id.has_duration',
                'product_id.product_model_id.duration',
                'product_id.product_model_id.is_repeatable',
                'booking_line_group_id.is_sojourn',
                'booking_line_group_id.is_event',
                'booking_line_group_id.nb_pers',
                'booking_line_group_id.nb_nights',
                'booking_line_group_id.age_range_assignments_ids',
                'booking_line_group_id.has_pack',
                'booking_line_group_id.pack_id.has_age_range',
                'qty_vars',
                'has_own_qty',
                'is_rental_unit',
                'is_accomodation',
                'is_meal',
                'qty_accounting_method'
            ]);

        if($lines <= 0) {
            return;
        }

        $line = reset($lines);

        if($line['has_own_qty']) {
            // own quantity has been assigned in onupdateProductId
            return;
        }

        // #memo - since something impacted qty, qty_var is reset

        // retrieve number of persons to whom the product will be delivered (either nb_pers or age_range.qty)
        $nb_pers = $line['booking_line_group_id.nb_pers'];
        // #memo - if parent group has a age_range set, keep `booking_line_group_id.nb_pers`
        if($line['product_id.has_age_range'] && !($line['booking_line_group_id.has_pack'] && $line['booking_line_group_id.pack_id.has_age_range'])) {
            $age_range_assignments = $om->read(BookingLineGroupAgeRangeAssignment::getType(), $line['booking_line_group_id.age_range_assignments_ids'], ['age_range_id', 'qty']);
            foreach($age_range_assignments as $assignment) {
                if($assignment['age_range_id'] == $line['product_id.age_range_id']) {
                    $nb_pers = $assignment['qty'];
                    break;
                }
            }
        }
        // default number of times the product is repeated (accounting method = 'unit' with no own quantity and non-repeatable)
        $nb_repeat = 1;
        if($line['product_id.product_model_id.has_duration']) {
            $nb_repeat = $line['product_id.product_model_id.duration'];
        }
        elseif($line['booking_line_group_id.is_sojourn']) {
            if($line['product_id.product_model_id.is_repeatable']) {
                $nb_repeat = max(1, $line['booking_line_group_id.nb_nights']);
            }
        }
        elseif($line['booking_line_group_id.is_event']) {
            if($line['product_id.product_model_id.is_repeatable']) {
                $nb_repeat = $line['booking_line_group_id.nb_nights'] + 1;
            }
        }
        // retrieve quantity to consider
        $qty = self::computeLineQty(
                $line['qty_accounting_method'],
                $nb_repeat,
                $nb_pers,
                $line['product_id.product_model_id.is_repeatable'],
                $line['is_accomodation'],
                $line['product_id.product_model_id.capacity']
            );
        // fill qty_vars with zeros
        $qty_vars = array_fill(0, $nb_repeat, 0);
        // #memo - if events are enalbled, this triggers onupdateQty and onupdateQtyVar
        $om->update(self::getType(), $id, ['qty' => $qty, 'qty_vars' => json_encode($qty_vars)]);
    }


    /**
     * This method is called by `update-sojourn-[...]` controllers.
     * It is meant to be called in a context not triggering change events (using `ORM::disableEvents()`).
     *
     * Search for an applicable price_id for the line, based on product and group.date_from, and assign it to the line.
     */
    public static function refreshPriceId($om, $id) {

        /*
            There are 2 situations :

            1) either the booking is not locked by a contract, in which case, we perform a regular lookup for an applicable pricelist
            2) or the booking has a locked contract, then we start by looking for a price amongst existing line targeting the same product (if not found, fallback to regular pricelist search)
        */

        $lines = $om->read(self::getType(), $id, [
            'booking_line_group_id.date_from',
            'product_id',
            'booking_id',
            'booking_id.is_locked',
            'booking_id.center_id.price_list_category_id',
            'has_manual_unit_price',
            'has_manual_vat_rate'
        ]);

        if($lines <= 0) {
            return;
        }

        $line = reset($lines);


        $found = false;

        /**
         * Locked booking relate to a contract that has been locked : this guarantees that additional services must be billed at the same price
         * than equivalent services subscribed when the contract was established, whatever the current price lists
         */
        if($line['booking_id.is_locked']) {
            // search booking line from same booking, targeting the same product
            $booking_lines_ids = $om->search(self::getType(), [['booking_id', '=', $line['booking_id']],  ['product_id', '=', $line['product_id']], ['id', '<>', $id]]);
            if($booking_lines_ids > 0 && count($booking_lines_ids)) {
                $booking_lines = $om->read(self::getType(), $booking_lines_ids, ['product_id', 'price_id', 'unit_price', 'vat_rate']);
                if($booking_lines > 0 && count($booking_lines)) {
                    $booking_line = reset($booking_lines);
                    $found = true;
                    // #memo - price_id is set for consistency, but since we want to force the same price regardless of the advantages linked to the group, we copy unit_price and vat_rate
                    $om->update(self::getType(), $id, ['price_id' => $booking_line['price_id']]);
                    // #memo - this will set the has_manual_unit_price and has_manual_vat_rate to true
                    $om->update(self::getType(), $id, ['unit_price' => $booking_line['unit_price'], 'vat_rate' => $booking_line['vat_rate']]);
                    trigger_error("ORM::assigned price from {$booking_line['product_id']}", QN_REPORT_WARNING);
                }
            }
        }

        /*
            Find the Price List that matches the criteria from the booking (shortest duration first)
        */
        if(!$found) {
            // #todo - leave TBC handling to Booking::refreshIsTbc() - we must leave it for now since updatePriceId uses it.
            $is_tbc = false;
            $selected_price_id = 0;

            $product_id = $line['product_id'];

            // 1) use searchPriceId (published price lists)
            $prices = self::searchPriceId($om, [$id], $product_id);

            if(isset($prices[$id])) {
                $selected_price_id = $prices[$id];
            }
            // 2) if not found, search for a matching Price within the pending Price Lists
            else {
                $prices = self::searchPriceIdUnpublished($om, [$id], $product_id);
                if(isset($prices[$id])) {
                    $is_tbc = true;
                    $selected_price_id = $prices[$id];
                }
            }

            if($selected_price_id > 0) {
                // assign found Price to current line
                $om->update(self::getType(), $id, ['price_id' => $selected_price_id]);
                if($is_tbc) {
                    // found price is TBC: mark booking as to be confirmed
                    $om->update(Booking::getType(), $line['booking_id'], ['is_price_tbc' => true]);
                }
                $date = date('Y-m-d', $line['booking_line_group_id.date_from']);
                trigger_error("ORM::assigned price {$selected_price_id} ({$is_tbc}) for product {$line['product_id']} for date {$date}", QN_REPORT_INFO);
            }
            else {
                $om->update(self::getType(), $id, ['price_id' => null, 'price' => null]);
                if(!$line['has_manual_unit_price']) {
                    $om->update(self::getType(), $id, ['unit_price' => 0]);
                }
                if(!$line['has_manual_vat_rate']) {
                    $om->update(self::getType(), $id, ['vat_rate' => 0]);
                }
                $date = date('Y-m-d', $line['booking_line_group_id.date_from']);
                trigger_error("ORM::no matching price found for product {$line['product_id']} for date {$date}", QN_REPORT_WARNING);
            }
        }

    }

    public static function onupdateBookingActivityId($self) {
        $self->read([
            'is_transport',
            'service_date',
            'time_slot_id'          => ['name'],
            'booking_activity_id'   => ['name']
        ]);

        foreach($self as $id => $line) {
            if(!isset($line['is_transport'], $line['service_date'], $line['time_slot_id']['name'], $line['booking_activity_id']['name']) || !$line['is_transport']) {
                continue;
            }

            $description = sprintf('Transport (%s - %s) : %s',
                date('d/m/Y', $line['service_date']),
                $line['time_slot_id']['name'],
                $line['booking_activity_id']['name']
            );

            self::id($id)->update(['description' => $description]);
        }
    }

    public static function onupdateServiceDate($self) {
        $self->read(['is_activity', 'service_date', 'booking_activity_id' => ['supplies_booking_lines_ids', 'transports_booking_lines_ids']]);
        foreach($self as $booking_line) {
            if(!$booking_line['is_activity']) {
                continue;
            }

            $sub_booking_lines_ids = array_merge(
                $booking_line['booking_activity_id']['supplies_booking_lines_ids'] ?? [],
                $booking_line['booking_activity_id']['transports_booking_lines_ids'] ?? []
            );

            if(!empty($sub_booking_lines_ids)) {
                BookingLine::ids($sub_booking_lines_ids)
                    ->update(['service_date' => $booking_line['service_date']]);
            }

            $booking_activities = BookingActivity::search(['activity_booking_line_id', '=', $booking_line['id']], ['sort' => ['activity_date' => 'asc']])
                ->read(['id', 'activity_date'])
                ->get(true);

            if(!empty($booking_activities)) {
                $shift_days = $booking_line['service_date'] - $booking_activities[0]['activity_date'];

                foreach($booking_activities as $activity) {
                    BookingActivity::id($activity['id'])->update(['activity_date' => $activity['activity_date'] + $shift_days]);
                }
            }
        }
    }

    public static function onupdateTimeSlotId($self) {
        $self->read(['is_activity', 'time_slot_id', 'booking_activity_id' => ['supplies_booking_lines_ids', 'transports_booking_lines_ids']]);
        foreach($self as $booking_line) {
            if(!$booking_line['is_activity']) {
                continue;
            }

            $sub_booking_lines_ids = array_merge(
                $booking_line['booking_activity_id']['supplies_booking_lines_ids'] ?? [],
                $booking_line['booking_activity_id']['transports_booking_lines_ids'] ?? []
            );

            if(!empty($sub_booking_lines_ids)) {
                BookingLine::ids($sub_booking_lines_ids)->update(['time_slot_id' => $booking_line['time_slot_id']]);
            }

            BookingActivity::id($booking_line['booking_activity_id']['id'])
                ->update(['time_slot_id' => $booking_line['time_slot_id']]);
        }
    }
}

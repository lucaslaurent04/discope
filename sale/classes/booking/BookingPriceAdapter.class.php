<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use equal\orm\Model;

class BookingPriceAdapter extends Model {

    public static function getName() {
        return "Price Adapter";
    }

    public static function getDescription() {
        return "Adapters allow to adapt the final price of the booking lines, either by performing a direct computation, or by using a discount definition.";
    }

    public static function getColumns() {
        return [

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'Booking the adapter relates to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLineGroup',
                'description'       => 'Booking Line Group the adapter relates to, if any.',
                'ondelete'          => 'cascade'
            ],

            'booking_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLine',
                'description'       => 'Booking Line the adapter relates to, if any.',
                'ondelete'          => 'cascade'
            ],

            'is_manual_discount' => [
                'type'              => 'boolean',
                'description'       => "Flag to set the adapter as manual or related to a discount.",
                'default'           => true
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => [
                    'percent',
                    'amount',
                    'freebie'
                ],
                'description'       => 'Type of manual discount (fixed amount or percentage of the price).',
                'visible'           => ['is_manual_discount', '=', true],
                'default'           => 'percent',
                'onupdate'          => 'onupdateValue'
            ],

            // #memo - important: to allow the maximum flexibility, percent values can hold 4 decimal digits (must not be rounded, except for display)
            'value' => [
                'type'              => 'float',
                'usage'             => 'amount/rate',
                'description'       => "Value of the discount (monetary amount or percentage).",
                'visible'           => ['is_manual_discount', '=', true],
                'default'           => 0.0,
                'onupdate'          => 'onupdateValue'
            ],

            'discount_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\discount\Discount',
                'description'       => 'Discount related to the adapter, if any.',
                'visible'           => ['is_manual_discount', '=', false]
            ],

            'discount_list_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\discount\DiscountList',
                'description'       => 'Discount List related to the adapter, if any.',
                'visible'           => ['is_manual_discount', '=', false]
            ]
        ];
    }

    /**
     * Check whether an object can be updated, and perform some additional operations if necessary.
     * This method can be overridden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @param  array                        $values     Associative array holding the new values to be assigned.
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array                        Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang='en') {
        if(isset($values['value'])) {
            $adapters = $om->read(self::getType(), $oids, [ 'type' ], $lang);
            foreach($adapters as $id => $adapter) {
                // #memo - price adapters cannot void a line. To give customer 100% discount, user must use the discount product on a distinct line (KA-Remise-A) with qty of 1 and negative value.
                if($adapter['type'] == 'percent' && $values['value'] >= 0.9999) {
                    return ['value' => ['exceeded_amount' => 'Percent discount cannot be 100%.']];
                }
            }
        }
        return parent::canupdate($om, $oids, $values, $lang);
    }

    public static function onupdateValue($om, $oids, $values, $lang) {
        // reset computed price for related bookings and booking_line_groups
        $discounts = $om->read(__CLASS__, $oids, ['booking_id', 'booking_line_id', 'booking_line_group_id']);

        if($discounts > 0) {
            $bookings_ids = array_map( function($a) { return $a['booking_id']; }, $discounts);
            $booking_lines_ids = array_map( function($a) { return $a['booking_line_id']; }, $discounts);
            $booking_line_groups_ids = array_map( function($a) { return $a['booking_line_group_id']; }, $discounts);
            $om->update(Booking::getType(), $bookings_ids, ['price' => null, 'total' => null]);
            $om->callonce(BookingLine::getType(), '_resetPrices', $booking_lines_ids, [], $lang);
            $om->callonce(BookingLineGroup::getType(), '_resetPrices', $booking_line_groups_ids, [], $lang);
        }
    }

    public static function getConstraints() {
        return [
            'booking_line_id' =>  [
                'missing_relation' => [
                    'message'       => 'booking_line_id or booking_line_group_id must be set.',
                    'function'      => function ($booking_line_id, $values) {
                        return ($values['booking_line_id'] >= 0 || $values['booking_line_group_id'] >=0);
                    }
                ]
            ]
        ];
    }

}

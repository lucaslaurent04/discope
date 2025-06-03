<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\customer;

class Customer extends \identity\Partner {

    public static function getName() {
        return 'Customer';
    }

    public static function getDescription() {
        return "A customer is a partner from who originates one or more bookings.";
    }

    public static function getColumns() {

        return [

            'partner_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'The targeted identity (the partner).',
                'onupdate'          => 'onupdatePartnerIdentityId',
                'required'          => true
            ],

            'relationship' => [
                'type'              => 'string',
                'default'           => 'customer',
                'description'       => 'Force relationship to Customer'
            ],

            'is_tour_operator' => [
                'type'              => 'boolean',
                'description'       => 'Mark the customer as a Tour Operator.',
                'default'           => false
            ],

            // #memo  count must be relative to booking not customer
            'count_booking_12' => [
                'type'              => 'computed',
                'deprecated'        => true,
                'result_type'       => 'integer',
                'function'          => 'calcCountBooking12',
                'description'       => 'Number of bookings made during last 12 months (one year).'
            ],

            'count_booking_24' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'calcCountBooking24',
                'description'       => 'Number of bookings made during last 24 months (2 years).'
            ],

            'address' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcAddress',
                'description'       => 'Main address from related Identity.',
                'store'             => true
            ],

            'bookings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Booking',
                'foreign_field'     => 'customer_id',
                'description'       => "The bookings history of the customer.",
            ],

            'ref_account' => [
                'type'              => 'string',
                'usage'             => 'uri/urn.iban',
                'description'       => 'Bank account number for identifying the customer in external accounting softwares.',
                'readonly'          => true
            ],

            'email_secondary' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'email',
                'description'       => "Identity secondary email address.",
                'function'          => 'calcEmailSecondary'
            ],

            'bookings_points_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingPoint',
                'foreign_field'     => 'customer_id',
                'description'       => "The bookings points of the customer."
            ],

        ];
    }

    public static function onupdateCustomerNatureId($om, $oids, $values, $lang) {
        /*
        // #memo - this has been removed to allow manual setting of rate class
        $customers = $om->read(__CLASS__, $oids, ['customer_nature_id.rate_class_id', 'customer_nature_id.customer_type_id']);
        if($customers > 0 && count($customers)) {
            foreach($customers as $cid => $customer) {
                $customer_type_id = $customer['customer_nature_id.customer_type_id'];
                $rate_class_id = $customer['customer_nature_id.rate_class_id'];
                if(!empty($customer_type_id) && !empty($rate_class_id)) {
                    $om->write(__CLASS__, $oids, ['rate_class_id' => $rate_class_id, 'customer_type_id' => $customer_type_id]);
                }
            }
        }
        */
    }

    /**
     * Computes the number of bookings made by the customer during the last two years.
     *
     */
    public static function calcAddress($om, $oids, $lang) {
        $result = [];

        $customers = $om->read(__CLASS__, $oids, ['partner_identity_id.address_street', 'partner_identity_id.address_city'], $lang);
        foreach($customers as $oid => $customer) {
            $result[$oid] = "{$customer['partner_identity_id.address_street']} {$customer['partner_identity_id.address_city']}";
        }
        return $result;
    }

    /**
     * Computes the number of bookings made by the customer during the last 12 months.
     *
     */
    public static function calcCountBooking12($om, $oids, $lang) {
        $result = [];
        $time = time();
        $from = mktime(0, 0, 0, date('m', $time)-12, date('d', $time), date('Y', $time));
        foreach($oids as $oid) {
            $bookings_ids = $om->search('sale\booking\Booking', [
                ['customer_id', '=', $oid],
                ['date_from', '>=', $from],
                ['is_cancelled', '=', false],
                ['status', 'not in', ['quote', 'option']]
            ]);
            $result[$oid] = count($bookings_ids);
        }
        return $result;
    }

    /**
     * Computes the number of bookings made by the customer during the last two years.
     *
     */
    public static function calcCountBooking24($om, $oids, $lang) {
        $result = [];
        $time = time();
        $from = mktime(0, 0, 0, date('m', $time)-24, date('d', $time), date('Y', $time));
        foreach($oids as $oid) {
            $bookings_ids = $om->search('sale\booking\Booking', [
                ['customer_id', '=', $oid],
                ['date_from', '>=', $from],
                ['is_cancelled', '=', false],
                ['status', 'not in', ['quote', 'option']]
            ]);
            $result[$oid] = count($bookings_ids);
        }
        return $result;
    }

    public static function calcEmailSecondary($om, $ids, $lang) {
        $result = [];
        $customers = $om->read(self::getType(), $ids, ['partner_identity_id.email_secondary']);
        foreach($customers as $id => $customer) {
            $result[$id] = $customer['partner_identity_id.email_secondary'];
        }
        return $result;
    }

    /**
     * Check whether the customer can be deleted.
     *
     * @param  \equal\orm\ObjectManager    $om        ObjectManager instance.
     * @param  array                       $ids       List of objects identifiers.
     * @return array                       Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be deleted.
     */
    public static function candelete($om, $ids) {
        $customers = $om->read(self::getType(), $ids, [ 'bookings_ids' ]);

        if($customers > 0) {
            foreach($customers as $id => $customer) {
                if($customer['bookings_ids'] && count($customer['bookings_ids']) > 0) {
                    return ['bookings_ids' => ['non_removable_customer' => 'Customers relating to one or more bookings cannot be deleted.']];
                }
            }
        }
        return parent::candelete($om, $ids);
    }
}

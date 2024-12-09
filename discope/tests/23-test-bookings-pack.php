<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use identity\Identity;
use sale\booking\Booking;
use sale\booking\BookingLineGroup;
use sale\booking\BookingType;
use sale\catalog\Product;
use sale\customer\CustomerNature;
use sale\booking\BookingLineGroupAgeRangeAssignment;

$services = eQual::inject(['orm']);

/** @var \equal\orm\ObjectManager    $orm */
$orm = $services['orm'];

$tests = [

    '2301' => [
        'description'       =>  'Create a booking for pack who is the locked.',
        'help'              =>  "
            Creates a booking with configuration below and test the consistency between booking price and sum of groups prices. \n \n
            Dates from: 20-01-2023
            Dates to: 22-01-2023 (2 nights)
            Numbers pers: 1
            Pack: Pack Wonderbox 79,90€",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) use ($orm) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $orm->disableEvents();

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-20'),
                    'date_to'               => strtotime('2023-01-22'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Booking test for the pack locked'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1,
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to']
                ])
                ->read(['id'])
                ->first(true);


            $pack = Product::search(['sku','=','GA-ChBBWo79-A'])
                ->read(['id','label'])
                ->first(true);

            $orm->disableEvents();

            try {
                eQual::run('do', 'sale_booking_update-sojourn-nbpers',
                        ['id' => $booking_line_group['id'],'nb_pers' => 2 ]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_update-sojourn-pack-set',
                        ['id' => $booking_line_group['id'],'pack_id' => $pack['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $orm->enableEvents();

            $booking = Booking::id($booking['id'])
                ->read(['id', 'price',
                    'booking_lines_ids' => ['id', 'price'],
                    'booking_lines_groups_ids' => ['id', 'has_pack' ,'is_locked', 'price']
                ])->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {

            $total_price_bl = array_reduce($booking['booking_lines_ids'], function($sum, $line) {
                return $sum + $line['price'];
            }, 0);


            $total_price_blg = array_reduce($booking['booking_lines_groups_ids'], function($sum, $group) {
                return $sum + $group['price'];
            }, 0);

            $has_locked_pack_group = array_reduce($booking['booking_lines_groups_ids'], function($carry, $group) {
                return $carry || ($group['is_locked'] === true && $group['has_pack'] === true);
            }, false);

            return (
                $booking['price'] == 74.9 &&
                $booking['price'] == $total_price_blg &&
                $booking['price'] != $total_price_bl &&
                $has_locked_pack_group
            );
        },
        'rollback'          =>  function () {
            Booking::search(['description', 'like', '%'. 'Booking test for the pack locked'.'%'])->delete(true);
        }

    ],

    '2302' => [
        'description'       =>  'Create a booking for a pack whose price depends on the price of the booking line.',
        'help'              =>  "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices and sum of the lines. \n \n
            Dates from: 25-01-2023
            Dates to: 28-01-2023 (3 nights)
            Children: 10
            Adults: 1
            Pack: Séjour scolaire GA ",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) use ($orm) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-25'),
                    'date_to'               => strtotime('2023-01-28'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Create a booking for a pack whose price depends on the price of the booking line'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'rate_class_id'  => 5,
                    'sojourn_type_id'=> 1
                ])
                ->read(['id'])
                ->first(true);

            $orm->disableEvents();
            try {
                eQual::run('do', 'sale_booking_update-sojourn-dates',
                        ['id'           => $booking_line_group['id'],
                               'date_from'    => $booking['date_from'],
                               'date_to'      => $booking['date_to']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $pack = Product::search(['sku','=','GA-SejScoSec-A'])
                ->read(['id','label'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_update-sojourn-pack-set',
                        ['id' => $booking_line_group['id'],'pack_id' => $pack['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_update-sojourn-agerange-add',
                        ['id' => $booking_line_group['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $age_range_assignment = BookingLineGroupAgeRangeAssignment::search([
                    ['booking_id', '=', $booking['id']],
                    ['qty', '=', 0 ]
                ])
                ->read(['id'])
                ->first(true);

            $age_range_id = 3; //Primaire (6-12)
            try {
                eQual::run('do', 'sale_booking_update-sojourn-agerange-set',
                        ['id'                       => $booking_line_group['id'] ,
                               'age_range_assignment_id'  => $age_range_assignment['id'],
                               'age_range_id'             => $age_range_id,
                               'qty'                      => 10 ]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $orm->enableEvents();

            $booking = Booking::id($booking['id'])
                ->read(['id', 'price',
                    'booking_lines_ids' => [
                        'id',
                        'product_id' => ['id', 'name'] ,
                        'product_model_id' => ['id', 'name'] ,
                        'price_id',
                        'unit_price',
                        'qty',
                        'total',
                        'price'
                    ],
                    'booking_lines_groups_ids' => ['id', 'price']
                ])->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {

            $total_price_bl = array_reduce($booking['booking_lines_ids'], function($sum, $line) {
                return $sum + $line['price'];
            }, 0);


            $total_price_blg = array_reduce($booking['booking_lines_groups_ids'], function($sum, $group) {
                return $sum + $group['price'];
            }, 0);


            return (
                $booking['price'] == round($total_price_blg,2) &&
                $booking['price'] == round($total_price_bl,2)
            );
        },
        'rollback'          =>  function () {
           Booking::search(['description', 'like', '%'. 'Create a booking for a pack whose price depends on the price of the booking line'.'%'])->delete(true);
        }

    ],

    '2303' => [
        'description'       =>  'Create a booking for the pack, including an overnight stay of type logement.',
        'help'              =>  "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices and sum of the lines. \n \n
            Dates from: 02-08-2023
            Dates to: 08-08-2023 (3 nights)
            Persons: 52",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) use ($orm) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-08-02'),
                    'date_to'               => strtotime('2023-08-08'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Booking for Daverdisse Pack with Logement'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 2,
                    'nb_pers'        => 52
                ])
                ->read(['id'])
                ->first(true);

            $orm->disableEvents();
            try {
                eQual::run('do', 'sale_booking_update-sojourn-dates',
                        ['id'           => $booking_line_group['id'],
                                'date_from'    => $booking['date_from'],
                                'date_to'      => $booking['date_to']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $pack = Product::search(['sku','=','AP-ArbPM-A'])
                ->read(['id','label'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_update-sojourn-pack-set',
                        ['id' => $booking_line_group['id'],'pack_id' => $pack['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $orm->enableEvents();

            $booking = Booking::id($booking['id'])
                ->read(['id', 'price',
                    'booking_lines_ids' => ['id', 'price'],
                    'booking_lines_groups_ids' => ['id', 'price']
                ])->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {

            $total_price_bl = array_reduce($booking['booking_lines_ids'], function($sum, $line) {
                return $sum + $line['price'];
            }, 0);


            $total_price_blg = array_reduce($booking['booking_lines_groups_ids'], function($sum, $group) {
                return $sum + $group['price'];
            }, 0);


            return (
                $booking['price'] == 1054.1  &&
                $booking['price'] == $total_price_blg &&
                $booking['price'] == $total_price_bl
            );
        },
        'rollback'          =>  function () {
            Booking::search(['description', 'like', '%'. 'Booking for Pack with Logement'.'%'])->delete(true);
        }

    ]
];
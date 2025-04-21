<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use identity\Identity;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\BookingLineGroupAgeRangeAssignment;
use sale\booking\BookingType;
use sale\catalog\Product;
use sale\customer\CustomerNature;

$tests = [
    '1501' => [
        'description'       =>  'Create a booking for the school stay at the center that has an active city tax.',
        'help'              =>  "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices, and sum of lines prices. \n
            The created booking is subject to city tax; and total is expected to be 850.6 EUR VAT incl. \n
            Center: Louvain-la-neuve
            Dates from: 01-01-2023
            Dates to: 03-01-2023
            Night: 2 nights
            Children: 10s",
        'arrange'           =>  function () {

            Center::id(1)->update(['has_citytax_school' => true]);
            $center =  Center::search(['name', 'like', '%Your Establisment%'])->read(['id', 'center_office_id', 'has_citytax_school'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-01'),
                    'date_to'               => strtotime('2023-01-03'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Booking test for the city tax.'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $pack_children = Product::search(['sku','=','GA-SejScoSec-A'])
                    ->read(['id','label'])
                    ->first(true);

            BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => true,
                    'pack_id'        => $pack_children['id'],
                    'rate_class_id'  => 5, //Ecoles primaires et secondaire
                    'sojourn_type_id'=> 1, //'GA'
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'       => 10
                ]);

            $secondary_age_range_id = 2;
            BookingLineGroupAgeRangeAssignment::search([
                ['booking_id', '=', $booking['id']],
            ])
                ->update(['age_range_id' => $secondary_age_range_id]);

            $booking = Booking::id($booking['id'])->read(['id','price'])->first(true);

            return($booking);
        },
        'assert'            =>  function ($booking) {

            $bookingLines = BookingLine::search(['booking_id', '=', $booking['id']])
                    ->read(['name', 'price']);

            $bookingLineGroups = BookingLineGroup::search(['booking_id', '=', $booking['id']])
                ->read(['id','price'])
                ->get(true);

            $total_price_bl = 0;
            $total_price_blg = array_sum(array_column($bookingLineGroups, 'price'));
            $city_tax_found = false;

            foreach ($bookingLines as $bookingLine) {
                $total_price_bl += $bookingLine['price'];
                if (!$city_tax_found && $bookingLine['name'] === 'Taxe Séjour (KA-CTaxSej-A)') {
                    $city_tax_found = true;
                }
            }

            return ($city_tax_found &&
                    $booking['price'] == $total_price_bl &&
                    $booking['price'] == $total_price_blg);
        },
        'rollback'          =>  function () {

           Booking::search(['description', 'like', '%'. 'Booking test for the city tax'.'%'])->delete(true);

        }
    ],
    '1502' => [
        'description'       =>  'Create a booking for the school stay at the center that does not have an active city tax',
        'help'              =>  "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices, and sum of lines prices. \n
            The created booking is subject to without city tax; and total is expected to be 827,6 EUR VAT incl. \n
            Dates from: 01-01-2023
            Dates to: 03-01-2023
            Night: 2 night
            Children: 10",
        'arrange'           =>  function () {

            Center::id(1)->update(['has_citytax_school' => false]);
            $center =  Center::search(['name', 'like', '%Your Establisment%'])->read(['id', 'center_office_id', 'has_citytax_school'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-01'),
                    'date_to'               => strtotime('2023-01-03'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Booking test without the city tax.'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $pack_children = Product::search(['sku','=','GA-SejScoSec-A'])
                    ->read(['id','label'])
                    ->first(true);

            BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => true,
                    'pack_id'        => $pack_children['id'],
                    'rate_class_id'  => 5, //Ecoles primaires et secondaire
                    'sojourn_type_id'=> 1, //'GA'
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'       => 10
                ]);

            $secondary_age_range_id = 2;
            BookingLineGroupAgeRangeAssignment::search([
                ['booking_id', '=', $booking['id']],
            ])
                ->update(['age_range_id' => $secondary_age_range_id]);

            $booking = Booking::id($booking['id'])->read(['id','price'])->first(true);

            return($booking);
        },
        'assert'            =>  function ($booking) {

            $bookingLines = BookingLine::search(['booking_id', '=', $booking['id']])
                ->read(['name', 'price'])
                ->get(true);

            $bookingLineGroups = BookingLineGroup::search(['booking_id', '=', $booking['id']])
                ->read(['price'])
                ->get(true);

            $total_price_bl = 0;
            $city_tax_found = false;

            foreach ($bookingLines as $bookingLine) {
                $total_price_bl += $bookingLine['price'];
                if ($bookingLine['name'] === 'Taxe Séjour (KA-CTaxSej-A)') {
                    $city_tax_found = true;
                }
            }

            $total_price_blg = array_sum(array_column($bookingLineGroups, 'price'));

            return (!$city_tax_found &&
                    $booking['price'] == $total_price_bl &&
                    $booking['price'] == $total_price_blg);
        },
        'rollback'          =>  function () {
            Booking::search(['description', 'like', '%'. 'Booking test without the city tax.'.'%'])->delete(true);
            Center::id(1)->update(['has_citytax_school' => true]);

        }
    ]
];

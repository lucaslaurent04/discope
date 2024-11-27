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
use sale\booking\SojournType;
use sale\catalog\Product;
use sale\customer\CustomerNature;


$tests = [

    '0001' => [
        'description'       =>  'Create a booking for a single client and multiple days.',
        'help'              =>  "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices, and sum of lines prices. \n
            Dates from: 01-01-2023
            Dates to: 15-01-2023 (14 nights)
            Numbers pers: 1
            Product: Nuit Chambre 1 pers
            Product Autosale:  Taxe Séjour
            ",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-01'),
                    'date_to'               => strtotime('2023-01-15'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Booking test for a single client and multiple days'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $bookingLineGroup = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour 1 pers',
                    'order'          => 1,
                    'rate_class_id'  => 4, //'general public'
                    'sojourn_type_id'=> 1,
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 1,
                ])
                ->read(['id'])
                ->first(true);


            $product = Product::search(['sku','=', 'GA-NuitCh1-A'])->read(['id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $bookingLineGroup['id'],
                    'product_id'            => $product['id']
                ]);

            $booking = Booking::id($booking['id'])
                ->read(['id',
                    'is_locked',
                    'is_price_tbc',
                    'center_id' => ['price_list_category_id'],
                    'price',
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
                    'booking_lines_groups_ids' => [
                        'id',
                        'date_from',
                        'nb_pers',
                        'qty',
                        'unit_price',
                        'total',
                        'price'
                    ]
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

            return ($booking['price'] == 725.9 &&
                    $booking['price'] == $total_price_bl &&
                    $booking['price'] == $total_price_blg);
        },
        'rollback'          =>  function () {
           Booking::search(['description', 'like', '%'. 'Booking test for a single client and multiple days'.'%'])->delete(true);
        }

    ],

    '0002' => [
        'description'       =>  'Create a booking for 10 persons only for 1 day.',
        'help'              =>  "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices, and sum of lines prices. \n
            Dates from: 01-01-2023
            Dates to: 02-01-2023
            Night: 1 night
            Numbers pers: 10 adults
        ",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            if ($data){
                list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;
            }

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-01'),
                    'date_to'               => strtotime('2023-01-02'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Booking test for a multiple persons by only day'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $bookingLineGroup = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour 1 pers',
                    'order'          => 1,
                    'rate_class_id'  => 4, //'general public'
                    'sojourn_type_id'=> 1,
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 10
                ])
                ->read(['id'])->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])
                ->read(['id'])
                ->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $bookingLineGroup['id'],
                    'product_id'            => $product['id']
                ]);

            $booking = Booking::id($booking['id'])
                ->read(['id',
                    'is_locked',
                    'is_price_tbc',
                    'center_id' => ['price_list_category_id'],
                    'price',
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
                    'booking_lines_groups_ids' => [
                        'id',
                        'date_from',
                        'nb_pers',
                        'unit_price',
                        'total',
                        'price'
                    ]
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

            return ($booking['price'] == $total_price_bl &&
                    $total_price_bl == $total_price_blg &&
                    $booking['price']== 518.5);

        },
        'rollback'          =>  function () {

           Booking::search(['description', 'like','%'. 'Booking test for a multiple persons by only day'.'%'])->delete(true);

        }

    ],

    '0003' => [
        'description' => 'Create a reservation for children aged 12 and 2 adults and above for 3 days.',
        'help' => "
            Creates a booking with the following configuration and verify the consistency between the booking price and the sum of group prices  \n
            Dates from: 01-01-2023
            Dates to: 03-01-2023
            Numbers pers: 22 (20 children + 2 adults)
            Age: 12 years (children)
            Packs: 'Séjour scolaire Secondaire' ",

        'arrange' =>  function () {
            $center = Center::search(['name', 'like', '%Your Establisment%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];
        },

        'act' =>  function ($data) {
            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-01'),
                    'date_to'               => strtotime('2023-01-03'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Booking test for 20 children aged 12 and above for 3 days'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $pack_children = Product::search(['sku','=','GA-SejScoSec-A'])
                ->read(['id','label'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => true,
                    'pack_id'        => $pack_children['id'],
                    'rate_class_id'  => 5,
                    'sojourn_type_id'=> 1,
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to']
                ])
                ->read(['id'])
                ->first(true);

            BookingLineGroupAgeRangeAssignment::search(['booking_id', '=', $booking['id']])
                ->update([
                    'qty'               => 20 ,
                    'age_range_id'      => 2 //Secondaire (12-26)
                ]);

            BookingLineGroupAgeRangeAssignment::create([
                    'booking_id'                => $booking['id'],
                    'booking_line_group_id'     => $booking_line_group['id'],
                    'qty'                       => 2 ,
                    'age_range_id'              => 1 //Adults
                ]);

            return Booking::id($booking['id'])
                ->read(['price',
                    'booking_lines_groups_ids' => ['id', 'price']])
                ->first(true);
        },

        'assert' =>  function ($booking) {
            $bookingLineGroups = BookingLineGroup::search(['booking_id', '=', $booking['id']])->read(['id', 'price'])->get(true);

            $total_price_blg = round(array_reduce($bookingLineGroups,
            fn($sum, $group) => $sum + $group['price'], 0),
             2);

            return ($booking['price'] == $total_price_blg);
        },

        'rollback' =>  function () {
         Booking::search(['description', 'like', '%'. 'Booking test for 20 children aged 12 and above for 3 days'.'%' ])->delete(true);
        }

    ],
    '0004' => [
        'description'       => 'Create a booking at Your Establisment for the midseason.',
        'help'              => "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices. \n
            The product 'Nuitée Arbrefontaine - Petite Maison' comes with advantages.
            The total cost, including VAT, is expected to be 711.1 EUR.\n
            Sejourn Type: Gîte de Groupe
            Dates from: 16/02/2023
            Dates to: 20/02/2023
            Nights: 4 nights",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-02-16'),
                    'date_to'               => strtotime('2023-02-20'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Create a booking at Your Establisment'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $product = Product::search(['sku','like','%'. 'AP-ArbPM-A'. '%' ])
                    ->read(['id','label'])
                    ->first(true);

            $sojourn_type = SojournType::search(['name','like','%'.'GG'. '%'])
                    ->read(['id','name'])
                    ->first(true);
            BookingLineGroup::create([
                'booking_id'     => $booking['id'],
                'name'           => "Séjour Arbrefontaine Petite",
                'order'          => 1,
                'group_type'     => 'sojourn',
                'is_sojourn'     => true,
                'has_pack'       => true,
                'pack_id'        => $product['id'],
                'rate_class_id'  => 4, //general
                'sojourn_type_id'=> $sojourn_type['id'],
                'date_from'      => $booking['date_from'],
                'date_to'        => $booking['date_to'],
                'nb_pers'       => 1
            ]);

            return Booking::id($booking['id'])
                    ->read(['id', 'price',
                        'booking_lines_groups_ids' => ['id', 'price']
                    ])
                    ->first(true);
        },
        'assert'            =>  function ($booking) {

            $total_price_blg = array_reduce($booking['booking_lines_groups_ids'], function($sum, $group) {
                return $sum + $group['price'];
            }, 0);

            return ($booking['price']== $total_price_blg && $booking['price'] == 768.7);
        },
        'rollback'          =>  function () {

            Booking::search(['description', 'like', '%'. 'Create a booking at Your Establisment'.'%' ])->delete(true);

        }

    ],

    '0005' => [
        'description'       => 'Create a booking at Center, the price adapters.',
        'help'              => "
            Creates a booking with configuration below and test the consistency between booking price, sum of groups prices. \n
            Numbers pers: 10
            Cetegory sojourn: Ecoles primaires et secondaires
            Dates from: 16/02/2023
            Dates to: 18/02/2023
            Nights: 2 nights",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-02-16'),
                    'date_to'               => strtotime('2023-02-18'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Create a booking at Your Establisment,the price adapters'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $product = Product::search(['sku', 'like', '%GA-SejScoSec-A%'])
                ->read(['id', 'label'])
                ->first(true);

            $sojourn_type = SojournType::search(['name', 'like', '%GA%'])
                ->read(['id', 'name'])
                ->first(true);

            BookingLineGroup::create([
                'booking_id'        => $booking['id'],
                'name'              => "Séjour Arbrefontaine Petite",
                'order'             => 1,
                'is_sojourn'        => true,
                'group_type'        => 'sojourn',
                'has_pack'          => true,
                'pack_id'           => $product['id'],
                'sojourn_type_id'   => $sojourn_type['id'],
                'rate_class_id'     => 5,                   // Ecoles primaires et secondaires
                'date_from'         => $booking['date_from'],
                'date_to'           => $booking['date_to'],
                'nb_pers'           => 10
            ]);

            BookingLineGroupAgeRangeAssignment::search([
                    ['booking_id', '=', $booking['id']],
                ])->update(['age_range_id' => 2]);

            $booking = Booking::id($booking['id'])
                ->read(['id',
                    'center_id' => ['price_list_category_id'],
                    'price',
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
                    'booking_lines_groups_ids' => [
                        'id',
                        'date_from',
                        'nb_pers',
                        'qty',
                        'unit_price',
                        'total',
                        'price'
                    ]
                ])->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {

            $total_price_blg = array_reduce($booking['booking_lines_groups_ids'], function($sum, $group) {
                return $sum + $group['price'];
            }, 0);

            return ($booking['price'] == $total_price_blg && $booking['price'] == 914);
        },
        'rollback'          =>  function () {

            Booking::search(['description', 'like', '%'. 'Create a booking at Your Establisment,the price adapters'.'%' ])->delete(true);

        }

    ]
];
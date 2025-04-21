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
use sale\price\PriceList;


$services = eQual::inject(['orm']);

/** @var \equal\orm\ObjectManager    $orm */
$orm = $services['orm'];

$tests = [

    '2501' => [
        'description'       => 'Validate that the booking contains products with a published price list',
        'help'              =>  "
            Dates from: 10-02-2023
            Dates to: 11-02-2023 (2 nights)
            Numbers pers: 1",
        'arrange'           =>  function () {

            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) use ($orm) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-02-20'),
                    'date_to'               => strtotime('2023-02-21'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the booking contains products with a published price list'
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
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 1
                ])
                ->read(['id'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id'])->first(true);

            try {
                eQual::run('do', 'sale_booking_update-sojourn-product',
                        ['id' => $booking_line_group['id'],'product_id' => $product['id'] ]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read(['id',  'price', 'is_price_tbc',
                    'booking_lines_groups_ids' => [ 'id', 'nb_pers', 'price',
                        'price_id' => [
                            'id',
                            'price_list_id' => ['id', 'status']
                        ]
                    ],
                    'booking_lines_ids' => [ 'id', 'price',
                        'price_id' => [
                            'id',
                            'price_list_id' => ['id', 'status']
                        ]
                    ]
                ])
                ->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {

            $is_tbc = false;

            foreach ($booking['booking_lines_ids'] as $line) {
                if ($line['price_id']['price_list_id']['status'] !== 'published') {
                    $is_tbc = true;
                    break;
                }
            }

            return !$is_tbc && !$booking['is_price_tbc'];
        },
        'rollback'          =>  function () {
            Booking::search(['description', 'like', '%'. 'Validate that the booking contains products with a published price list'.'%'])->delete(true);
        }

    ],

    '2502' => [
        'description'       => 'Validate that the booking contains products with a pending price list',
        'help'              =>  "
            Dates from: 20-02-2023
            Dates to: 21-02-2023 (2 nights)
            Numbers pers: 1",
        'arrange'           =>  function () {

            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) use ($orm) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-02-20'),
                    'date_to'               => strtotime('2023-02-21'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the booking contains products with a pending price list'
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
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 1
                ])
                ->read(['id'])
                ->first(true);

            PriceList::search(['name', '=', 'Price 2023'])->update(['status' => 'pending']);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id'])->first(true);

            try {
                eQual::run('do', 'sale_booking_update-sojourn-product',
                        ['id' => $booking_line_group['id'],'product_id' => $product['id'] ]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read(['id',  'price', 'is_price_tbc',
                    'booking_lines_groups_ids' => [ 'id', 'nb_pers', 'price',
                        'price_id' => [
                            'id',
                            'price_list_id' => ['id', 'status']
                        ]
                    ],
                    'booking_lines_ids' => [ 'id', 'price',
                        'price_id' => [
                            'id',
                            'price_list_id' => ['id', 'status']
                        ]
                    ]
                ])
                ->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {

            $is_tbc = false;

            foreach ($booking['booking_lines_ids'] as $line) {
                if ($line['price_id']['price_list_id']['status'] !== 'published') {
                    $is_tbc = true;
                    break;
                }
            }

            return $is_tbc && $booking['is_price_tbc'];
        },
        'rollback'          =>  function () {
            PriceList::search(['name', '=', 'Price 2023'])->update(['status' => 'published']);
            Booking::search(['description', 'like', '%'. 'Validate that the booking contains products with a pending price list'.'%'])->delete(true);
        }

    ]
];
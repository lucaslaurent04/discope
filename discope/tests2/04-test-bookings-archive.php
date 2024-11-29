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
use sale\catalog\Product;
use sale\booking\BookingType;
use sale\customer\CustomerNature;

$tests = [
    '0401' => [
        'description'       => 'Validate that the reservation can be archived from a reservation in quote status and with a price of 0.',
        'help'              => "
            action: sale_booking_do-archive
            Status: Quote
            Price 0",
        'arrange'           =>  function () {
            $center = Center::search(['name', 'like', '%Your Establisment%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [ $center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'] ];
        },
        'act'               =>  function ($data) {
            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-12'),
                    'date_to'               => strtotime('2023-03-13'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation can be archived from a reservation in quote status.'
                ])
                ->read(['id','created', 'state' ,'price', 'status', 'paid_amount'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_do-archive', [ 'id' => $booking['id'] ]);
            } catch (Exception $e) {
                $e->getMessage();
            }

            return $booking['id'];
        },
        'assert'            =>  function ($booking_id) {
            $booking = Booking::id($booking_id)
                ->update(['state' => 'archive'])
                ->read(['id','state'])
                ->first(true);
            return ($booking['state'] == 'archive');
        },
        'rollback'          =>  function () {
           Booking::search(['description', 'like', '%'. 'Validate that the reservation can be archived from a reservation in quote status'.'%' ])
                   ->delete(true);
        }
    ],
    '0402' => [
        'description'       => 'Validate that the reservation cannot be archived if the client has made a payment.',
        'arrange'           =>  function () {
            $center = Center::search(['name', 'like', '%Your Establisment%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [ $center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];
        },
        'act'               =>  function ($data) {
            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id , $quote_delay) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-12'),
                    'date_to'               => strtotime('2023-03-13'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation cannot be archived if the client has made a payment.'
                ])
                ->update([
                    'created'    => strtotime("+10 days")
                ])
                ->read(['id', 'date_from' , 'date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 1 personne pendant 1 nuitée',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1
                ])
                ->update([
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->update([
                    'nb_pers'        => 1,
                ])
                ->read(['id'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id','label'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id']
                ])
                ->update([
                    'product_id'            => $product['id'],
                ])
                ->read(['id', 'name', 'price'])
                ->first(true);

            $booking = Booking::id($booking['id'])
                ->update(['paid_amount' => 1])
                ->read(['id','status','price', 'paid_amount'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_do-archive', [ 'id' => $booking['id'] ]);
            } catch (Exception $e) {
                $message = $e->getMessage();
            }

            return $message;
        },
        'assert'            =>  function ($message) {
            return ($message == "invalid_quote_booking");
        },
        'rollback'          =>  function () {
            Booking::search(['description', 'like', '%'. 'Validate that the reservation cannot be archived if the client has made a payment'.'%' ])
                            ->update(['paid_amount' => '0'])
                            ->read(['id'])
                            ->delete(true);
        }
    ],
    '0403' => [
        'description'       => 'Validate that the reservation cannot be archived if the reservation is not in quote or checkout status.',
        'arrange'           =>  function () {
            $center = Center::search(['name', 'like', '%Your Establisment%'])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [ $center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];
        },
        'act'               =>  function ($data) {
            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id , $quote_delay) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-12'),
                    'date_to'               => strtotime('2023-03-13'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation cannot be archived if the reservation is not in quote or checkout status.'
                ])
                ->update([
                    'status'    => 'option'
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_do-archive', [ 'id' => $booking['id'] ]);
            } catch (Exception $e) {
                $message = $e->getMessage();
            }

            return $message;
        },
        'assert'            =>  function ($message) {
            return ($message == "invalid_status_booking");
        },
        'rollback'          =>  function () {
            Booking::search(['description', 'ilike', '%'. 'Validate that the reservation cannot be archived if the reservation is not in quote or checkout status'.'%' ])
                            ->update(['status' => 'quote'])
                            ->delete(true);
        }
    ]
];

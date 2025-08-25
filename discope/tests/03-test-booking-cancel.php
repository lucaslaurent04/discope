<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use identity\Identity;
use sale\booking\Booking;
use sale\booking\BookingType;
use sale\booking\SojournType;
use sale\customer\CustomerNature;
use sale\customer\RateClass;


$tests = [

    '0301' => [
        'description' => 'Validate that the reservation can be canceled from a reservation in quote status.',
        'help' => "
            action: sale_booking_do-cancel
            Dates from: 12/03/2023
            Dates to: 13/03/2023
            Nights: 1 nights",

        'arrange' =>  function () {

            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];
        },

        'act' =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-12'),
                    'date_to'               => strtotime('2023-03-13'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation can be canceled from a reservation in quote status.'
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_do-cancel', ['id' => $booking['id'], 'reason' => 'other']);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            return $booking['id'];
        },

        'assert' =>  function ($booking_id) {
            $booking = Booking::id($booking_id)
                ->read(['id','status','is_cancelled'])
                ->first(true);

            return ($booking['status'] == 'cancelled' && $booking['is_cancelled'] == true);
        },

        'rollback' =>  function () {
            Booking::search(['description', 'like', '%'. 'Validate that the reservation can be canceled from a reservation in quote status'.'%' ])
                ->update(['status' => 'quote'])
                ->delete(true);
        }
    ],

    '0302' => [
        'description' => 'Validate that the reservation cannot be canceled if the reason is missing',

        'arrange' =>  function () {

            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];
        },

        'act' =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-14'),
                    'date_to'               => strtotime('2023-03-15'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation cannot be canceled if the reason is missing.'
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_do-cancel', ['id' => $booking['id']]);

            }
            catch (Exception $e) {
                $message = $e->getMessage();
            }

            return $message;
        },

        'assert' =>  function ($message) {
            return ($message == 'reason');
        },

        'rollback' =>  function () {
            Booking::search(['description', 'like', '%'. 'Validate that the reservation cannot be canceled if the reason is missing'.'%' ])
                ->update(['status' => 'quote'])
                ->delete(true);
        }

    ],

    '0303' => [

        'description' => 'Validate that the reservation cannot be canceled if the reservation has been canceled before',

        'arrange' =>  function () {
            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },

        'act' =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-16'),
                    'date_to'               => strtotime('2023-03-17'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation cannot be canceled if the reservation has been canceled before.'
                ])
                ->update(['is_cancelled' => true])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_do-cancel', ['id' => $booking['id'],  'reason' => 'other']);

            } catch (Exception $e) {
                $message = $e->getMessage();

            }

            return $message;
        },

        'assert' =>  function ($message) {
            return ($message == "incompatible_status");
        },

        'rollback' =>  function () {
            Booking::search(['description', 'ilike', '%'. 'Validate that the reservation cannot be canceled if the reservation has been canceled before'.'%' ])
                ->update(['status' => 'quote'])
                ->delete(true);
        }

    ],

    '0304' => [

        'description' => 'Validate that the reservation can be canceled from a reservation in option status.',

        'help' => "
            Dates from: 18/03/2023
            Dates to: 19/03/2023
            Nights: 1 nights",

        'arrange' => function () {

            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },

        'act' => function ($data) {
            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-18'),
                    'date_to'               => strtotime('2023-03-19'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation can be canceled from a reservation in option status'
                ])
                ->update(['status' => 'option'])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_do-cancel', ['id' => $booking['id'], 'reason' => 'other']);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            return $booking['id'];
        },

        'assert' => function ($booking_id) {
            $booking = Booking::id($booking_id)
                ->read(['id', 'status', 'is_cancelled', 'cancellation_reason'])
                ->first(true);

            return (
                $booking['status'] == 'cancelled' &&
                $booking['is_cancelled'] == true &&
                $booking['cancellation_reason'] == 'other'
            );

        },

        'rollback' => function () {
            Booking::search(['description', 'ilike', '%'. 'Validate that the reservation can be canceled from a reservation in option status'.'%' ])
                ->update(['status' => 'quote'])
                ->delete(true);
        }

    ],

    '0305' => [

        'description' => "Validate that the reservation can be canceled from a reservation in balanced status.",

        'arrange' =>  function () {

            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];
        },

        'act' =>  function ($data) {
            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-19'),
                    'date_to'               => strtotime('2023-03-20'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation can be canceled from a reservation in balanced status'
                ])
                ->update(['status' => 'balanced'])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_do-cancel', ['id' => $booking['id'], 'reason' => 'other']);
            }
            catch (Exception $e) {
                $message = $e->getMessage();
            }

            return $message;
        },

        'assert' =>  function ($message) {
            return ($message == "incompatible_status");
        },

        'rollback' =>  function () {
            $booking = Booking::search(['description', 'ilike', '%'. 'Validate that the reservation can be canceled from a reservation in balanced status'.'%' ])
                ->update(['status' => 'quote'])
                ->read(['id'])
                ->first(true);

            $services = eQual::inject(['orm']);
            $services['orm']->delete(Booking::getType(), $booking['id'], true);
        }
    ]
];

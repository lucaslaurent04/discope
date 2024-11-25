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
use sale\customer\CustomerNature;


$tests = [
    '0801' => [
        'description'       => 'Validate that the reservation cannot be deleted if the user is not connected.',
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
                    'date_from'             => strtotime('2023-03-14'),
                    'date_to'               => strtotime('2023-03-15'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that the reservation cannot be deleted if the user is not connected.'
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_do-delete', ['id' => $booking['id']]);

            }
            catch (Exception $e) {
                $code = $e->getCode();
            }

            return $code;
        },
        'assert'            =>  function ($code) {

            return ($code == -4);
        },
        'rollback'=>  function () {
            Booking::search(['description', 'like', '%'. 'Validate that the reservation cannot be deleted if the user is not connected'.'%' ])
                    ->delete(true);
        }

    ]
];

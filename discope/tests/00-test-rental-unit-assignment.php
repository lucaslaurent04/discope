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
use realestate\RentalUnit;
use sale\booking\BookingType;
use sale\booking\SojournProductModel;
use sale\booking\SojournProductModelRentalUnitAssignement;
use sale\catalog\Product;
use sale\catalog\ProductModel;
use sale\customer\CustomerNature;
use sale\customer\RateClass;


$tests = [

    '0009' => [

        'description' => 'Validate Rental Unit Assignments for a Booking.',

        'help' => "
            Creates a booking with configuration below and test the consistency between of the number of participants matches the number of individuals assigned to the SPM Rental Unit. \n
            The price for the group booking is determined based on the advantages associated with the 'Ecoles primaires et secondaires' category.'
            RateClass: Ecoles primaires et secondaires
            Dates from: 20/01/2023
            Dates to: 24/01/2023
            Nights: 4 nights",

        'arrange' =>  function () {
            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            $rate_class = RateClass::search(['name', '=', 'T5'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $rate_class['id']];
        },
        'act' =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $rate_class_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-20'),
                    'date_to'               => strtotime('2023-01-24'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate the assignments of rental units in a booking.'
                ])
                ->read(['id','date_from','date_to','price'])
                ->first(true);

            $pack = Product::search(['sku', 'like', '%GA-SejScoSec-A%'])
                ->read(['id','label'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'name'           => $pack['label'],
                    'order'          => 1,
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => true,
                    'pack_id'        => $pack['id'],
                    'rate_class_id'  => $rate_class_id,
                    'sojourn_type_id'=> 1,
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'       => 2
                ])
                ->read(['id', 'price', 'booking_lines_ids'])
                ->first(true);

            $product_model = ProductModel::search([
                    ['is_accomodation', '=', true],
                    ['name', 'like', '%'. 'Nuitée Séjour scolaire'. '%']
                ])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id' , "=" , $booking_line_group['id']],
                    ['product_model_id' , "=" , $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_unit = RentalUnit::search([
                    ['center_id', '=' , $center_id],
                    ['is_accomodation', '=' , true],
                ])
                ->read(['id','sojourn_type_id','capacity','room_types_ids'])
                ->first(true);

            $spm_rental_unit_assignement = SojournProductModelRentalUnitAssignement::create([
                    'booking_id'                => $booking['id'],
                    'booking_line_group_id'     => $booking_line_group['id'],
                    'sojourn_product_model_id'  => $sojourn_product_model['id'],
                    'rental_unit_id'            => $rental_unit['id'],
                    'qty'                       => $rental_unit['capacity'],
                    'is_accomodation'           => true
                ])
                ->read(['id','qty', 'booking_id', 'rental_unit_id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_do-option', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            return $spm_rental_unit_assignement;
        },

        'assert' =>  function ($spm_rental_unit_assignement) {

            $booking = Booking::search(['id','=', $spm_rental_unit_assignement['booking_id']])
                ->read(['id','nb_pers','rental_unit_assignments_ids'])
                ->first(true);
            print_r($booking);

            $rental_unit_assignments_ids = $booking['rental_unit_assignments_ids'];

            foreach($rental_unit_assignments_ids as $rental_unit_assignments_id) {
                $b_spm_rental_unit_assignement = SojournProductModelRentalUnitAssignement::id($rental_unit_assignments_id)
                    ->read(['id'])
                    ->first(true);
            }

            print_r($b_spm_rental_unit_assignement);
            return (
                    $booking['id'] == $spm_rental_unit_assignement['booking_id'] &&
                    $b_spm_rental_unit_assignement['id'] == $spm_rental_unit_assignement['id']
                );
        },

        'rollback' => function () {
            $booking = Booking::search(['description', 'like', '%'. 'Validate the assignments of rental units in a booking'.'%' ])
                ->read('id')
                ->first(true);

            Booking::id($booking['id'])->update(['state' => 'archive']);
        }
    ]
];

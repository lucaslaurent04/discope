<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\ModelFactory;
use realestate\RentalUnit;
use identity\Center;
use identity\Identity;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\BookingType;
use sale\booking\SojournProductModel;
use sale\booking\SojournProductModelRentalUnitAssignement;
use sale\catalog\Product;
use sale\customer\CustomerNature;


$tests = [

    '2000' => [
        'description'       =>  'Validate that the rental unit is marked as None if no action is required',
        'help'              =>  "",
        'arrange'           =>  function () {

            $rental_unit_data = ModelFactory::create(RentalUnit::class, [
                "values" => [
                    "name"                => "Rental Unit Test",
                    "type"                => "room",
                    "action_required"     => "cleanup_daily",
                    "capacity"            => 1
                ]
            ]);

            $rental_unit = RentalUnit::create(
                    $rental_unit_data
                )
                ->read(['id', 'name', 'type', 'action_required'])
                ->first(true);


            return $rental_unit;

        },
        'act'               =>  function ($rental_unit) {

            try {
                eQual::run('do', 'realestate_do-cleaned', ['id' => $rental_unit['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $rental_unit = RentalUnit::id($rental_unit['id'])
                ->read(['id', 'name', 'type', 'action_required'])
                ->first(true);

            return $rental_unit;
        },
        'assert'            =>  function ($rental_unit) {

            return ($rental_unit['action_required'] == 'none');
        },
        'rollback'          =>  function () {

            RentalUnit::search(['name', '=', 'Rental Unit Test'])->delete(true);
        }
    ],

    '2001' => [
        'description'       =>  'Verify that the rental unit is assigned to a booking.',
        'help'              =>  "",
        'arrange'           =>  function () {

            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-07-01'),
                    'date_to'               => strtotime('2023-07-02'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Verify that the rental unit is assigned to a booking'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 1 personne pendant 1 nuitée',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1,
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 1
                ])
                ->read(['id','nb_pers'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id','product_model_id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id'],
                    'product_id'            => $product['id']
                ]);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id' , "=" , $booking_line_group['id']],
                    ['product_model_id' , "=" , $product['product_model_id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=' , $center_id],
                    ['is_accomodation', '=' , true],
                ])
                ->read(['id','name','capacity']);

            $num_rua = 0;
            foreach ($rental_units as $rental_unit) {

                if ($num_rua >= $booking_line_group['nb_pers']) {
                    break;
                }

                $spm_rental_unit_assignement = SojournProductModelRentalUnitAssignement::create([
                        'booking_id'                => $booking['id'],
                        'booking_line_group_id'     => $booking_line_group['id'],
                        'sojourn_product_model_id'  => $sojourn_product_model['id'],
                        'rental_unit_id'            => $rental_unit['id'],
                        'qty'                       => $rental_unit['capacity'],
                        'is_accomodation'           => true
                    ])
                    ->read(['id','qty'])
                    ->first(true);

                $num_rua += $spm_rental_unit_assignement['qty'];

            };


            try {
                eQual::run('do', 'sale_booking_do-option', ['id' => $booking['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_do-confirm', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id', 'consumptions_ids' => ['date', 'rental_unit_id', 'is_rental_unit' , 'status'] ])
                ->first(true);

            $rental_unit = RentalUnit::search([
                    ['center_id', '=' , $center_id],
                    ['is_accomodation', '=' , true],
                ])
                ->read(['id', 'status'])
                ->first(true);

            try {
                $data = eQual::run('do', 'realestate_check-unit-available', ['id' => $rental_unit['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            return $data;
        },
        'assert'            =>  function ($data) {
            return ($data);
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Verify that the rental unit is assigned to a booking'.'%'])
                ->update(['status' => 'quote'])
                ->read(['id'])
                ->first(true);

            Booking::id($booking['id'])->delete(true);

        }
    ]
];

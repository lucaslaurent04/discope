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
use sale\booking\Contract;
use sale\booking\ContractLine;
use sale\booking\ContractLineGroup;
use realestate\RentalUnit;
use sale\booking\BookingType;
use sale\booking\SojournProductModel;
use sale\booking\SojournProductModelRentalUnitAssignement;
use sale\catalog\Product;
use sale\catalog\ProductModel;
use sale\customer\CustomerNature;


$tests = [
    '1701' => [
        'description'       =>  'Validate the contract creation process based on the booking.',
        'help'              =>  "
            Validate that the customer in the contract is the same as in the booking.
            Validate that the total and price of the contract are equal to those in the booking.
            Validate that the total and price of the contract are equal to the sum of all contract lines.
            Validate that the total and price of the contract are equal to the sum of all contract line groups.",
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
                    'date_from'             => strtotime('2023-04-26'),
                    'date_to'               => strtotime('2023-04-27'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate the contract creation process based on the booking'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 2 personne pendant 1 nuitée',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1,
                    'nb_pers'        => 2,
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->read(['id','nb_pers'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id','product_model_id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id'],
                    'product_id'            => $product['id']
                ])
                ->read(['id','name','price'])
                ->first(true);

            $product_model = ProductModel::id($product['product_model_id'])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id' , "=" , $booking_line_group['id']],
                    ['product_model_id' , "=" , $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=' , $center_id],
                    ['is_accomodation', '=' , true],
                ])
                ->read(['id','capacity']);

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

            try {
                $contract = Contract::search([
                            ['booking_id', '=',  $booking['id']],
                            ['status', '=',  'pending'],
                    ])
                    ->read(['id'])
                    ->first(true);

                eQual::run('do', 'sale_contract_signed', ['id' => $contract['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read([
                    'id', 'price', 'total', 'customer_id',
                    'contracts_ids' => [
                        'id','status', 'customer_id','total', 'price',
                        'contract_lines_ids' => [ 'id', 'product_id','total', 'price'] ,
                        'contract_line_groups_ids' => [ 'id', 'total', 'price']
                    ]
                ])
                ->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {
            $contract = Contract::search(['booking_id', '=',  $booking['id']])
                ->read(['id','status', 'customer_id' ,'total', 'price'])
                ->first(true);

            $contract_lines = ContractLine::search(['contract_id','=', $contract['id']])->read(['id','total', 'price']);

            $total_lines = 0;
            $price_lines = 0;
            foreach($contract_lines as $line) {
                $total_lines += $line['total'];
                $price_lines += $line['price'];
            }

            $contract_groups = ContractLineGroup::search(['contract_id','=', $contract['id']])->read(['id','total', 'price']);

            $total_groups = 0;
            $price_groups = 0;
            foreach($contract_groups as $line) {
                $total_groups += $line['total'];
                $price_groups += $line['price'];
            }

            return (
                $contract['customer_id'] == $booking['customer_id'] &&
                $contract['total'] == $booking['total'] &&
                $contract['total'] == $total_groups &&
                $contract['total'] == $total_lines &&
                $contract['price'] == $booking['price'] &&
                $contract['price'] == $price_groups &&
                $contract['price'] == $price_lines
            );
        },
        'rollback'          =>  function () {

            Booking::search(['description', 'like', '%'. 'Validate the contract creation process based on the booking'.'%'])
                ->update(['status' => 'quote'])
                ->delete(true);

        }
    ],
    '1702' => [
        'description'       =>  'Validate the contract workflow: - From pending to sent - From sent to signed',
        'help'              =>  "",
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
                    'date_from'             => strtotime('2023-04-28'),
                    'date_to'               => strtotime('2023-04-29'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate the contract workflow'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 2 personne pendant 1 nuitée',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1,
                    'nb_pers'        => 2,
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                ])
                ->read(['id','nb_pers'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id','product_model_id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id'],
                    'product_id'            => $product['id']
                ]);

            $product_model = ProductModel::id($product['product_model_id'])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id' , "=" , $booking_line_group['id']],
                    ['product_model_id' , "=" , $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=' , $center_id],
                    ['is_accomodation', '=' , true],
                ])
                ->read(['id','name','sojourn_type_id','capacity','room_types_ids']);

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

            $contract = Contract::search(['booking_id', '=',  $booking['id']])
                ->read(['id','status', 'booking_id'])
                ->first(true);

            return $contract;
        },
        'assert'            =>  function ($contract) {

            try {
                eQual::run('do', 'sale_contract_sent', ['id' => $contract['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $contract_sent = Contract::id($contract['id'])
                ->read(['id','status'])
                ->first(true);

            try {
                eQual::run('do', 'sale_contract_signed', ['id' => $contract['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $contract_signed= Contract::id($contract['id'])
                ->read(['id','status'])
                ->first(true);

            return (
                $contract['status'] == 'pending' &&
                $contract_sent['status'] == 'sent' &&
                $contract_signed['status'] == 'signed'
            );
        },
        'rollback'          =>  function () {

            Booking::search(['description', 'like', '%'. 'Validate the contract workflow'.'%'])
                ->update(['status' => 'quote'])
                ->delete(true);

        }
    ],
    '1703' => [
        'description'       =>  'Validate that when the contract is locked, it is not possible to modify the reservation.',
        'help'              =>  "",
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
                    'date_from'             => strtotime('2023-05-10'),
                    'date_to'               => strtotime('2023-05-11'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Ensure the reservation cannot be modified when the contract is locked.'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                'booking_id'     => $booking['id'],
                'is_sojourn'     => true,
                'group_type'     => 'sojourn',
                'has_pack'       => false,
                'name'           => 'Séjour pour 2 personne pendant 1 nuitée',
                'order'          => 1,
                'rate_class_id'  => 4,
                'sojourn_type_id'=> 1,
                'nb_pers'        => 2,
                'date_from'      => $booking['date_from'],
                'date_to'        => $booking['date_to'],
            ])
            ->read(['id','nb_pers'])
            ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id','product_model_id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id'],
                    'product_id'            => $product['id']
                ]);

            $product_model = ProductModel::id($product['product_model_id'])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id' , "=" , $booking_line_group['id']],
                    ['product_model_id' , "=" , $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=' , $center_id],
                    ['is_accomodation', '=' , true],
                ])
                ->read(['id','name','sojourn_type_id','capacity','room_types_ids']);

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

            Booking::id($booking['id'])->update(['status' => 'confirmed']);

            $contract = Contract::search(['booking_id', '=',  $booking['id']])
                ->read(['id','status'])
                ->first(true);

            try {
                eQual::run('do', 'sale_contract_lock', ['id' => $contract['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id( $booking['id'])
                ->update(['is_locked' => null])
                ->read(['id','status' , 'is_locked','contracts_ids' => ['id', 'is_locked']])
                ->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {

            try {
                eQual::run('do', 'sale_booking_do-quote', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $message = $e->getMessage();
            }

            return ($message == 'locked_contract');

        },
        'rollback'          =>  function () {

            Booking::search(['description', 'like', '%'. 'Ensure the reservation cannot be modified when the contract is locked.'.'%'])
                ->update(['status' => 'quote'])
                ->delete(true);

        }
    ]

];

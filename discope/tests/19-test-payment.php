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
use sale\booking\Funding;
use realestate\RentalUnit;
use sale\booking\BookingType;
use sale\booking\SojournProductModel;
use sale\booking\SojournProductModelRentalUnitAssignement;
use sale\catalog\Product;
use sale\customer\CustomerNature;
use sale\booking\Payment;


$tests = [

    '1900' => [
        'description'       =>  'Validate the transfer of payment from one booking to another for the same customer',
        'help'              =>  "
        Validations:
            The customer ID of the original booking matches the customer ID of the target booking.
            The paid amount from the original funding matches the amount of the original payment received.
            The negative of the paid amount from the original funding equals the paid amount from the original reimbursement.
            The original reimbursement funding is marked as paid.
            There is no original reimbursement payment present.
            The paid amount of the target funding matches the paid amount of the original funding.
            The target funding is marked as paid.
            There is no payment present for the target funding.
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

            $booking_original = Booking::create([
                    'date_from'             => strtotime('2023-05-25'),
                    'date_to'               => strtotime('2023-05-26'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => '1900 The original booking from which to transfer the payment'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group_original = BookingLineGroup::create([
                    'booking_id'     => $booking_original['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 2 personne pendant 1 nuitée',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1,
                    'date_from'      => $booking_original['date_from'],
                    'date_to'        => $booking_original['date_to'],
                    'nb_pers'        => 2,
                ])
                ->read(['id','nb_pers'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id','product_model_id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking_original['id'],
                    'booking_line_group_id' => $booking_line_group_original['id'],
                    'product_id'            => $product['id']
                ]);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id' , "=" , $booking_line_group_original['id']],
                    ['product_model_id' , "=" , $product['product_model_id']]
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

                if ($num_rua >= $booking_line_group_original['nb_pers']) {
                    break;
                }

                $spm_rental_unit_assignement = SojournProductModelRentalUnitAssignement::create([
                        'booking_id'                => $booking_original['id'],
                        'booking_line_group_id'     => $booking_line_group_original['id'],
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
                eQual::run('do', 'sale_booking_do-option', ['id' => $booking_original['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_do-confirm', ['id' => $booking_original['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking_original = Booking::id($booking_original['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id', 'price', 'center_office_id'])
                ->first(true);

            Funding::search(['booking_id'  ,'=',  $booking_original['id']])->update(['state' => 'archive']);

            $funding_original = Funding::create([
                    'booking_id'       =>  $booking_original['id'],
                    'due_amount'        =>  $booking_original['price'],
                    'center_office_id'  =>  $booking_original['center_office_id']
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_funding_do-pay-append', ['id' => $funding_original['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking_target = Booking::create([
                    'date_from'             => strtotime('2023-05-27'),
                    'date_to'               => strtotime('2023-05-28'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'The target booking to receive the payment'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group_target = BookingLineGroup::create([
                    'booking_id'     => $booking_target['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 2 personne pendant 1 nuitée',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1,
                    'date_from'      => $booking_target['date_from'],
                    'date_to'        => $booking_target['date_to'],
                    'nb_pers'        => 2,
                ])
                ->read(['id','nb_pers'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id','product_model_id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking_target['id'],
                    'booking_line_group_id' => $booking_line_group_target['id'],
                    'product_id'            => $product['id']
                ]);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id' , "=" , $booking_line_group_target['id']],
                    ['product_model_id' , "=" , $product['product_model_id']]
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

                if ($num_rua >= $booking_line_group_target['nb_pers']) {
                    break;
                }

                $spm_rental_unit_assignement = SojournProductModelRentalUnitAssignement::create([
                        'booking_id'                => $booking_target['id'],
                        'booking_line_group_id'     => $booking_line_group_target['id'],
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
                eQual::run('do', 'sale_booking_do-option', ['id' => $booking_target['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_do-confirm', ['id' => $booking_target['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking_target = Booking::id($booking_target['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id', 'price', 'center_office_id'])
                ->first(true);

            Funding::search(['booking_id'  ,'=',  $booking_target['id']])->update(['state' => 'archive']);

            $funding_target = Funding::create([
                    'booking_id'      =>  $booking_target['id'],
                    'due_amount'                         =>  $booking_target['price'],
                    'center_office_id'                   =>  $booking_target['center_office_id']
                ])
                ->read(['id'])
                ->first(true);

            $payment_original = Payment::search(['funding_id', '=',  $funding_original['id']])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_payment_do-transfer', ['id' => $payment_original['id'] , 'funding_id' => $funding_target['id'] ]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking_target = Booking::id($booking_target['id'])
                ->read([
                    'id', 'status', 'price', 'total',
                    'customer_id' => [
                        'id', 'name'
                    ],
                    'fundings_ids' => [
                        'id','due_amount',
                        'paid_amount','is_paid' ,
                        'payments_ids' => ['id' , 'funding_id', 'booking_id', 'customer_id', 'amount']
                    ]
                ])
                ->first(true);

            $booking_original = Booking::id($booking_original['id'])
                ->read([
                    'id',  'status', 'price', 'total',
                    'customer_id' => [
                        'id', 'name'
                    ],
                    'fundings_ids' => [
                        'id','due_amount',
                        'paid_amount','is_paid' ,
                        'payments_ids' => ['id' , 'funding_id', 'booking_id', 'customer_id', 'amount']
                    ]
                ])
                ->first(true);

            return [$booking_original, $booking_target ];
        },
        'assert'            =>  function ($data) {

            list($booking_original, $booking_target) = $data;

            $funding_original_paid = Funding::search([
                    ['booking_id' , '=', $booking_original['id'] ] ,
                    ['due_amount', '=', $booking_original['price']]
                ])
                ->read(['id','due_amount', 'paid_amount','is_paid'])
                ->first(true);

            $payment_original_paid = Payment::search(['funding_id', '=', $funding_original_paid['id']])
                ->read(['id','amount'])
                ->first(true);

            $funding_original_reimbursement  = Funding::search([
                    ['booking_id' , '=', $booking_original['id'] ] ,
                    ['due_amount', '=', -$booking_original['price']]
                ])
                ->read(['id','due_amount', 'paid_amount','is_paid'])
                ->first(true);

            $payment_original_reimbursement = Payment::search(['funding_id' , '=', $funding_original_reimbursement['id'] ])
                ->read(['id'])
                ->first(true);

            $funding_target = Funding::search([
                    ['booking_id' , '=', $booking_target['id'] ] ,
                    ['paid_amount', '=',  $booking_original['price']],
                    ['is_paid', '=', true]
                ])
                ->read(['id','due_amount', 'paid_amount','is_paid'])
                ->first(true);

            $payment_target = Payment::search(['funding_id' , '=', $funding_target['id'] ])
                ->read(['id'])
                ->first(true);

            return (
                $booking_original['customer_id']['id'] == $booking_target['customer_id']['id'] &&
                $funding_original_paid['paid_amount'] == $payment_original_paid['amount'] &&
                -$funding_original_paid['paid_amount'] == $funding_original_reimbursement['paid_amount'] &&
                $funding_original_reimbursement['is_paid'] == true &&
                !isset($payment_original_reimbursement) &&
                $funding_target['paid_amount']  ==  $funding_original_paid['paid_amount'] &&
                $funding_target['is_paid'] == true &&
                !isset($payment_target)
            );
        },
        'rollback'          =>  function () {

            $booking_target = Booking::search(['description', 'like', '%'. 'The target booking to receive the payment'.'%'])
                ->read(['id'])
                ->first(true);

            $funding_target = Funding::search([['booking_id' , '=', $booking_target['id'] ], ['is_paid' ,'=', true]])
                ->read(['id'])
                ->first(true);

            $payment_target =  Payment::search(['funding_id', '=', $funding_target['id']])
                ->read(['id'])
                ->first(true);

            Payment::id($payment_target['id'])->update(['is_manual' => true]);

            try {
                eQual::run('do', 'sale_booking_funding_do-pay-remove', ['id' => $funding_target['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            Booking::id($booking_target['id'])->update(['status' => 'quote'])->delete(true);

            $booking_original = Booking::search(['description', 'like', '%'. '1900 The original booking from which to transfer the payment'.'%'])
                ->read(['id'])
                ->first(true);

            Payment::search(['booking_id', '=', $booking_original['id']])
                ->update(['is_manual' => true])
                ->delete(true);

            Funding::search(['booking_id', '=', $booking_original['id']])
                ->update(['paid_amount' => null])
                ->update(['is_paid' => null])
                ->delete(true);

            Booking::id($booking_original['id'])->update(['status' => 'quote'])->delete(true);

        }
    ]
];

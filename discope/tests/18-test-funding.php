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
use sale\booking\SojournType;
use sale\catalog\Product;
use sale\catalog\ProductModel;
use sale\customer\CustomerNature;
use sale\booking\Payment;

$tests = [

    '1800' => [
        'description'       =>  'Validate marking the funding as paid without a payment.',
        'help'              =>  "
        Validations:
            The booking price matches the due amount
            The due amount matches the paid amount
            The funding is marked as paid
            There are no payments registered for the funding
        ",
        'arrange'           =>  function () {

            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $date_from = strtotime('2023-05-01');
            $date_to = strtotime('2023-05-02');

            $booking = Booking::create([
                    'date_from'             => $date_from,
                    'date_to'               => $date_to,
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate marking the funding as paid without a payment'
                ])
                ->read(['id'])
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
                    'date_from'      => $date_from,
                    'date_to'        => $date_to,
                    'nb_pers'        => 2
                ])
                ->read(['id', 'nb_pers'])
                ->first(true);

            $product = Product::search(['sku', '=', 'GA-NuitCh1-A'])->read(['id', 'product_model_id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id'],
                    'product_id'            => $product['id']
                ]);

            $product_model = ProductModel::id($product['product_model_id'])->read(['id', 'name'])->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id', '=', $booking_line_group['id']],
                    ['product_model_id', '=', $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=', $center_id],
                    ['is_accomodation', '=', true]
                ])
                ->read(['id', 'capacity']);

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
                    ->read(['id', 'qty'])
                    ->first(true);

                $num_rua += $spm_rental_unit_assignement['qty'];
            }

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
                ->read(['id', 'price', 'center_office_id'])
                ->first(true);

            Funding::search(['booking_id'  ,'=',  $booking['id']])->update(['state' => 'archive']);

            $funding = Funding::create(['booking_id'       =>  $booking['id'],
                    'due_amount'        =>  $booking['price'],
                    'center_office_id'  =>  $booking['center_office_id']
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_funding_do-paid', ['id' => $funding['id'] , 'confirm'  => true]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read([
                    'id', 'status', 'price', 'total',
                    'fundings_ids' => [
                        'id','due_amount',
                        'paid_amount','is_paid' ,
                        'payments_ids'
                    ]
                ])
                ->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {
            $funding = Funding::search(['booking_id', '=',  $booking['id']])
                    ->read(['id','due_amount',
                        'paid_amount','is_paid' ,
                        'payments_ids'
                    ])
                    ->first(true);

            return (
                $booking['price'] == $funding['due_amount'] &&
                $funding['due_amount'] == $funding['paid_amount'] &&
                $funding['is_paid'] == true &&
                empty($funding['payments_ids'])
            );
        },
        'rollback'          =>  function () {

            Booking::search(['description', 'like', '%'. 'Validate marking the funding as paid without a payment'.'%'])
                ->update(['status' => 'quote'])
                ->delete(true);

        }
    ],
    '1801' => [
        'description'       =>  'Validate the creation of a manual payment to complete the funding payments and mark it as paid',
        'help'              =>  "
        Validations:
            The due amount matches the paid amount.
            The funding is marked as paid.
            The payment's funding ID matches the funding ID.
            The payment amount matches the paid amount.
            The payment is marked as manual.
            The payment's booking ID matches the booking ID.
            The payment origin is 'cashdesk'.
            The payment method is 'bank_card
        ",
        'arrange'           =>  function () {

            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            
            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-05-02'),
                    'date_to'               => strtotime('2023-05-03'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate manual payment to complete funding and mark as paid'
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
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 2
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

            $booking = Booking::id($booking['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id', 'price', 'center_office_id'])
                ->first(true);

            Funding::search(['booking_id'  ,'=',  $booking['id']])->update(['state' => 'archive']);

            $funding = Funding::create([
                        'booking_id'       =>  $booking['id'],
                        'due_amount'        =>  $booking['price'],
                        'center_office_id'  =>  $booking['center_office_id']
                    ])
                    ->read(['id'])
                    ->first(true);
            try {
                eQual::run('do', 'sale_booking_funding_do-pay-append', ['id' => $funding['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read([
                    'id',  'status', 'price', 'total',
                    'fundings_ids' => [
                        'id','booking_id', 'due_amount',
                        'paid_amount','is_paid',
                        'payments_ids' => [
                            'id' , 'booking_id', 'funding_id',  'is_manual', 'payment_origin', 'payment_method', 'amount'
                        ]
                    ]
                ])
                ->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {
            $funding = $booking['fundings_ids'][0];
            $payment = $funding['payments_ids'][0];

            return (
                $booking['price'] == $funding['due_amount'] &&
                $funding['due_amount'] == $funding['paid_amount'] &&
                $funding['is_paid'] == true &&
                $payment['funding_id'] ==  $funding['id'] &&
                $payment['amount'] == $funding['paid_amount'] &&
                $payment['is_manual'] == true &&
                $payment['booking_id'] == $booking['id'] &&
                $payment['payment_origin'] == 'cashdesk' &&
                $payment['payment_method'] == 'bank_card'
            );
        },
        'rollback'          =>  function () {

            Booking::search(['description', 'like', '%'. 'Validate manual payment to complete funding and mark as paid'.'%'])
                ->update(['status' => 'quote'])
                ->delete(true);

        }
    ],
    '1802' => [
        'description'       =>  'Validate the removal of any manual payment linked to the funding and unmark it as paid',
        'help'              =>  "
        Validations:
            The paid amount in the funding is zero.
            The is_paid field in the funding is set to false.
            There are no payments associated with the funding.
        ",
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
                    'date_from'             => strtotime('2023-05-04'),
                    'date_to'               => strtotime('2023-05-05'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate the removal of any manual payment linked to the funding and unmark it as paid'
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
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 2
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

            $booking = Booking::id($booking['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id', 'price', 'center_office_id'])
                ->first(true);

            Funding::search(['booking_id'  ,'=',  $booking['id']])->update(['state' => 'archive']);

            $funding = Funding::create([
                    'booking_id'       =>  $booking['id'],
                    'due_amount'        =>  $booking['price'],
                    'center_office_id'  =>  $booking['center_office_id']
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_funding_do-pay-append', ['id' => $funding['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_funding_do-pay-remove', ['id' => $funding['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }


            $booking = Booking::id($booking['id'])
                ->read([
                    'id', 'status', 'price', 'total',
                    'fundings_ids' => [
                        'id','due_amount',
                        'paid_amount','is_paid' ,
                        'payments_ids'
                    ]
                ])
                ->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {

            $funding = $booking['fundings_ids'][0];

            return (
                $funding['paid_amount'] == 0 &&
                $funding['is_paid'] == false &&
                empty($funding['payments_ids'])
            );
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Validate the removal of any manual payment linked to the funding and unmark it as paid'.'%'])
                ->update(['status' => 'quote'])
                ->read(['id'])
                ->first(true);

            Booking::id($booking['id'])->delete(true);

        }
    ],
    '1803' => [
        'description'       =>  'Validate marking the funding as unpaid without a payment.',
        'help'              =>  "
        Validations:
            The paid amount in the funding is zero.
            The is_paid field in the funding is set to false.
            There are no payments associated with the funding.
        ",
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
                    'date_from'             => strtotime('2023-05-06'),
                    'date_to'               => strtotime('2023-05-07'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate marking the funding as unpaid'
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
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 2
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
                ->read(['id'])
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

            $booking = Booking::id($booking['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id', 'price', 'center_office_id'])
                ->first(true);

            Funding::search(['booking_id'  ,'=',  $booking['id']])->update(['state' => 'archive']);

            $funding = Funding::create([
                    'booking_id'       =>  $booking['id'],
                    'due_amount'        =>  $booking['price'],
                    'center_office_id'  =>  $booking['center_office_id']
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_funding_do-paid', ['id' => $funding['id'] , 'confirm'  => true]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_funding_do-unpaid', ['id' => $funding['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read([
                    'id', 'status', 'price', 'total',
                    'fundings_ids' => [
                        'id','due_amount',
                        'paid_amount','is_paid' ,
                        'payments_ids'
                    ]
                ])
                ->first(true);

            return $booking;
        },
        'assert'            =>  function ($booking) {
            $funding = $booking['fundings_ids'][0];

            return (
                $funding['paid_amount'] == 0 &&
                $funding['is_paid'] == false &&
                empty($funding['payments_ids'])
            );
        },
        'rollback'          =>  function () {
            Booking::search(['description', 'like', '%'. 'Validate marking the funding as unpaid'.'%'])
                ->update(['status' => 'quote'])
                ->delete(true);

        }
    ],
    '1804' => [
        'description'       =>  'Validation that the funding cannot be removed when there is a payment received.',
        'help'              =>  "",
        'arrange'           =>  function () {
            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-05-08'),
                    'date_to'               => strtotime('2023-05-09'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that funding cannot be removed if a payment is received'
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
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 2
                ])
                ->read(['id','nb_pers'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id','product_model_id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id']
                ])
                ->update([
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

            $booking = Booking::id($booking['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id', 'price', 'center_office_id'])
                ->first(true);

            Funding::search(['booking_id'  ,'=',  $booking['id']])->update(['state' => 'archive']);

            $funding = Funding::create([
                    'booking_id'        =>  $booking['id'],
                    'due_amount'        =>  $booking['price'],
                    'center_office_id'  =>  $booking['center_office_id']
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_funding_do-paid', ['id' => $funding['id'] , 'confirm'  => true]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                $funding = Funding::search(['booking_id', '=',  $booking['id']])
                    ->read(['id'])
                    ->first(true);
                eQual::run('do', 'sale_booking_funding_do-delete', ['id' => $funding['id']]);
            }
            catch(Exception $e) {
                $message = $e->getMessage();
            }

            return $message;
        },
        'assert'            =>  function ($message) {
            return ( $message == 'funding_already_paid' );
        },
        'rollback'          =>  function () {
            $booking = Booking::search(['description', 'like', '%'. 'Validate that funding cannot be removed if a payment is received'.'%'])
                ->read(['id'])
                ->first(true);

            $funding = Funding::search(['booking_id' , '=', $booking['id']])
                ->read(['id'])
                ->first(true);

            $payment =  Payment::search(['funding_id', '=', $funding['id']])
                ->read(['id'])
                ->first(true);

            Payment::id($payment['id'])->update(['is_manual' => true]);

            try {
                eQual::run('do', 'sale_booking_funding_do-pay-remove', ['id' => $funding['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            Booking::id($booking['id'])->update(['status' => 'quote'])->delete(true);

        }
    ],
    '1805' => [
        'description'       =>  'Validate the transfer of funding from one booking to another for the same customer',
        'help'              =>  "
        Validations:
            Ensures the original and target bookings belong to the same customer.
            Confirms that the price of the original booking matches the due amount of the target funding.
            Confirms there is no funding for the original booking.
            Validates the target funding's due amount equals the paid amount.
            Checks that the target funding is marked as paid.
        ",
        'arrange'           =>  function () {

            $center = Center::id(1)->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            
            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking_original = Booking::create([
                    'date_from'             => strtotime('2023-05-10'),
                    'date_to'               => strtotime('2023-05-11'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => '1805 The original booking from which to transfer the payment'
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

            $product_model = ProductModel::id($product['product_model_id'])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id' , "=" , $booking_line_group_original['id']],
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
                'date_from'             => strtotime('2023-05-11'),
                'date_to'               => strtotime('2023-05-12'),
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

            $product_model = ProductModel::id($product['product_model_id'])->read(['id', 'name'])->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id' , "=" , $booking_line_group_target['id']],
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

            try {
                eQual::run('do', 'sale_booking_funding_do-transfer', ['id' => $funding_original['id'] , 'booking_id' => $booking_target['id'] ]);
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
                        'payments_ids' => ['id' , 'amount']
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
                        'payments_ids' => ['id' , 'amount']
                    ]
                ])
                ->first(true);

            return [$booking_original, $booking_target ];
        },
        'assert'            =>  function ($data) {

            list($booking_original, $booking_target) = $data;

            $funding_original = $booking_original['fundings_ids'][0];
            $funding_target = $booking_target['fundings_ids'][0];

            return (
                $booking_original['customer_id']['id'] == $booking_target['customer_id']['id'] &&
                empty($funding_original) &&
                $booking_original['price'] == $funding_target['due_amount'] &&
                $funding_target['due_amount'] ==  $funding_target['paid_amount'] &&
                $funding_target['is_paid'] == true
            );
        },
        'rollback'          =>  function () {

            Booking::search(['description', 'like', '%'. '1805 The original booking from which to transfer the payment'.'%'])
                ->update(['status' => 'quote'])
                ->delete(true);

            $booking = Booking::search(['description', 'like', '%'. 'The target booking to receive the payment'.'%'])
                ->read(['id'])
                ->first(true);

            $funding = Funding::search([['booking_id' , '=', $booking['id']], ['is_paid', '=' , true]])
                ->read(['id'])
                ->first(true);

            $payment =  Payment::search(['funding_id', '=', $funding['id']])
                ->read(['id'])
                ->first(true);

            Payment::id($payment['id'])->update(['is_manual' => true]);

            try {
                eQual::run('do', 'sale_booking_funding_do-pay-remove', ['id' => $funding['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            Booking::id($booking['id'])->update(['status' => 'quote'])->delete(true);

        }
    ]
];

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
use sale\pos\Order;
use sale\pos\OrderLine;
use sale\pos\OrderPayment;
use sale\pos\OrderPaymentPart;
use sale\pos\CashdeskSession;
use sale\pos\Cashdesk;
use sale\booking\Contract;
use sale\booking\Payment;

$tests = [

    '2200' => [
        'description'       =>  'Ensure the order is created successfully and that the reservation funding reflects accurate payment method and origin as cashdesk',
        'help'              =>  "
        Validations:
            The order status is equal to paid.
            The order price matches the amount in funding_id's payments_ids.
            The order price matches the total due in order_payments_ids.
            The order price matches the unit price in order_lines_ids within order_payments_ids.
            The order price matches the amount in order_payment_parts_ids within order_payments_ids.
            The funding is marked as paid.
            The payment method in funding_id's payments_ids is bank_card.
            The payment origin in funding_id's payments_ids is cashdesk.
            The payment method in order_payment_parts_ids within order_payments_ids is bank_card.
            The payment origin in order_payment_parts_ids within order_payments_ids is cashdesk.
        ",
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
                    'date_from'             => strtotime('2023-08-01'),
                    'date_to'               => strtotime('2023-08-02'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate the order to mark it as paid and update the funding'
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
                    'nb_pers'        => 2,
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
                ->read(['id', 'price', 'center_office_id'])
                ->first(true);

            Funding::search(['booking_id'  ,'=',  $booking['id']])->update(['state' => 'archive']);

            $funding = Funding::create([
                            'booking_id'        =>  $booking['id'],
                            'due_amount'        =>  $booking['price'],
                            'center_office_id'  =>  $booking['center_office_id']
                        ])
                        ->read(['id', 'name', 'due_amount'])
                        ->first(true);

            $session = CashdeskSession::search(['name' ,'like', '%'. 'Test - Caisse Organisation' . '%'])
                ->read(['id','user_id', 'name'])
                ->first(true);


            $cashdesk = Cashdesk::search(['center_id' ,'=' , $center_id])
                ->read(['id', 'name'])
                ->first(true);

            $order = Order::create([
                    'customer_id'   => $customer_identity_id,
                    'session_id'    => $session['id'],
                    'user_id'       => $session['user_id'],
                    'cashdesk_id'   => $cashdesk['id']
                ])
                ->read(['id', 'name'])
                ->first(true);


            $order_line = OrderLine::create([
                    'order_id'          => $order['id'],
                    'has_funding'       => true,
                    'funding_id'        => $funding['id'],
                    'booking_id'        => $booking['id'],
                    'name'              => $funding['name'],
                    'qty'               => 1 ,
                    'unit_price'        => $funding['due_amount']
                ])
                ->read(['id'])
                ->first(true);


            Order::id($order['id'])
                ->update([
                    'status' => 'payment'
                ]);

            $order_payment =  OrderPayment::create([
                    'order_id' =>  $order['id']
                ])
                ->read(['id'])
                ->first(true);


            $order_payment_part =  OrderPaymentPart::create([
                    'order_id'          => $order['id'] ,
                    'order_payment_id'  => $order_payment['id'],
                    'amount'            => $funding['due_amount'],
                ])
                ->update([
                    'booking_id'        => $booking['id'],
                    'has_funding'       => true,
                    'funding_id'        => $funding['id']
                ])
                ->read(['id'])
                ->first(true);

            $order_line = OrderLine::id($order_line['id'])
                ->update([
                    'order_payment_id' =>  $order_payment['id']
                ])
                ->read(['id'])
                ->first(true);

            OrderPaymentPart::id($order_payment_part['id'])
                ->update([
                    'payment_method'    => 'bank_card',
                    'status'            => 'paid',
                    'booking_id'    => $booking['id'],
                    'has_funding'       => true,
                    'funding_id'        => $funding['id']
                ]);

            try {
                eQual::run('do', 'sale_pos_payment_validate', [
                        'id'         => $order_payment['id']
                    ]
                );
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_pos_order_do-pay', [
                        'id'         => $order['id']
                    ]
                );
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $order = Order::id($order['id'])
                ->update(['status' => 'paid'])
                ->read([
                    'id', 'price', 'status',
                    'has_funding',
                    'funding_id' => [
                        'id',
                        'paid_amount',
                        'is_paid',
                        'payments_ids' => ['id','amount', 'payment_origin', 'payment_method']
                    ],
                    'order_payments_ids' => [
                        'total_due',
                        'order_lines_ids' => [ 'unit_price' ],
                        'order_payment_parts_ids' => [
                            'amount',
                            'payment_origin',
                            'payment_method'
                        ]
                    ]
                ])
                ->first(true);


            return $order;
        },
        'assert'            =>  function ($order) {

            return (
                $order['status'] == 'paid' &&
                $order['price'] == $order['funding_id']['paid_amount'] &&
                $order['price'] == $order['funding_id']['payments_ids'][0]['amount'] &&
                $order['price'] == $order['order_payments_ids'][0]['total_due'] &&
                $order['price'] == $order['order_payments_ids'][0]['order_lines_ids'][0]['unit_price']  &&
                $order['price'] == $order['order_payments_ids'][0]['order_payment_parts_ids'][0]['amount'] &&
                $order['funding_id']['is_paid'] == true &&
                $order['funding_id']['payments_ids'][0]['payment_method'] == "bank_card" &&
                $order['funding_id']['payments_ids'][0]['payment_origin'] == "cashdesk"  &&
                $order['order_payments_ids'][0]['order_payment_parts_ids'][0]['payment_method'] == "bank_card" &&
                $order['order_payments_ids'][0]['order_payment_parts_ids'][0]['payment_origin'] == "cashdesk"
            );
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Validate the order to mark it as paid and update the funding'.'%'])
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
    '2201' => [
        'description'       =>  'Validate product sales and associate them with the reservation using the cashdesk',
        'help'              =>  "
        Validations:
            The order status is equal to paid.
            The order price matches the price in the booking line.
            The order price matches the price in the booking line group associated with the booking line.
            The is extra field in the booking line group associated with the booking line is set to true.
            The order price matches the total due in the order payments.
            The order price matches the unit price in the order lines within the order payments.
            The order price matches the amount in the order payment parts within the order payments.
            The payment method in the order payment parts within the order payments is booking.
            The payment origin in the order payment parts within the order payments is cashdesk.
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
                    'date_from'             => strtotime('2023-08-01'),
                    'date_to'               => strtotime('2023-08-02'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate product sales and associate them with the reservation'
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
                    'nb_pers'        => 2,
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

                try {
                    eQual::run('do', 'realestate_do-cleaned', ['id' => $rental_unit['id']]);
                }
                catch(Exception $e) {
                    $e->getMessage();
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

            Funding::create([
                    'booking_id'        =>  $booking['id'],
                    'due_amount'        =>  $booking['price'],
                    'center_office_id'  =>  $booking['center_office_id']
                ]);

            try {
                $contract = Contract::search([
                            ['booking_id', '=',  $booking['id']],
                            ['status', '=',  'pending'],
                    ])
                    ->read(['id', 'status'])
                    ->first(true);

                eQual::run('do', 'sale_contract_signed', ['id' => $contract['id']]);

            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_do-checkin', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $session = CashdeskSession::search(['name' ,'like', '%'. 'Test - Caisse Organisation' . '%'])
                ->read(['id','user_id', 'name'])
                ->first(true);


            $cashdesk = Cashdesk::search(['center_id' ,'=' , $center_id])
                ->read(['id', 'name'])
                ->first(true);

            $order = Order::create([
                    'customer_id'   => $customer_identity_id,
                    'session_id'    => $session['id'],
                    'user_id'       => $session['user_id'],
                    'cashdesk_id'   => $cashdesk['id'],
                ])
                ->read(['id', 'name'])
                ->first(true);


            $product = Product::search(['sku','=', 'GA-Boisson-A' ])->read(['id', 'name'])->first(true);

            $order_line = OrderLine::create([
                    'order_id'          => $order['id'],
                    'name'              => $product['name'],
                    'product_id'        => $product['id'],
                    'qty'               => 1,
                    'unit_price'        => 10
                ])
                ->read(['id', 'qty', 'unit_price'])
                ->first(true);


            Order::id($order['id'])
                ->update([
                    'booking_id'        => $booking['id'],
                    'status'            => 'payment'
                ]);

            $order_payment =  OrderPayment::create([
                    'order_id'          =>  $order['id']
                ])
                ->read(['id'])
                ->first(true);


            $order_payment_part =  OrderPaymentPart::create([
                    'order_id'          => $order['id'] ,
                    'order_payment_id'  => $order_payment['id'],
                    'amount'            => $order_line['qty'] * $order_line['unit_price'],
                ])
                ->read(['id'])
                ->first(true);

            $order_line = OrderLine::id($order_line['id'])
                ->update([
                    'order_payment_id' =>  $order_payment['id']
                ])
                ->read(['id'])
                ->first(true);

            $order_payment_part =  OrderPaymentPart::id($order_payment_part['id'])
                -> update([
                    'payment_method'    => 'booking',
                    'status'            => 'paid',
                    'booking_id'        => $booking['id']
                ]);

            try {
                eQual::run('do', 'sale_pos_payment_validate', [
                        'id'         => $order_payment['id']
                    ]
                );
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_pos_order_do-pay', [
                        'id'         => $order['id']
                    ]
                );
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            OrderLine::id($order_line['id'])->update(['order_payment_id' =>  $order_payment['id']]);

            $order = Order::id($order['id'])
                ->update(['status' => 'paid'])
                ->read([
                    'id', 'price', 'status',
                    'booking_id' => ['id', 'price',
                                'booking_lines_ids' => [
                                    'product_id' ,
                                    'price',
                                    'booking_line_group_id' => [  'id', 'is_extra', 'price']
                            ]
                    ],
                    'order_payments_ids' => [
                        'total_due',
                        'order_lines_ids' => ['id', 'unit_price'],
                        'order_payment_parts_ids' => [
                            'amount',
                            'payment_origin',
                            'payment_method'
                        ]
                    ]
                ])
                ->first(true);


            return $order;
        },
        'assert'            =>  function ($order) {

            $product = Product::search(['sku','=', 'GA-Boisson-A' ])->read(['id'])->first(true);

            $booking_line = current(array_filter(
                $order['booking_id']['booking_lines_ids'],
                fn($line) => $line['product_id'] === $product['id']
            ));

            return (
                $order['status'] == 'paid' &&
                isset($booking_line) &&
                $order['price'] == $booking_line['price'] &&
                $order['price'] == $booking_line['booking_line_group_id']['price'] &&
                $booking_line['booking_line_group_id']['is_extra'] == true &&
                $order['price'] == $order['order_payments_ids'][0]['total_due'] &&
                $order['price'] == $order['order_payments_ids'][0]['order_lines_ids'][0]['unit_price']  &&
                $order['price'] == $order['order_payments_ids'][0]['order_payment_parts_ids'][0]['amount'] &&
                $order['order_payments_ids'][0]['order_payment_parts_ids'][0]['payment_method'] == "booking" &&
                $order['order_payments_ids'][0]['order_payment_parts_ids'][0]['payment_origin'] == "cashdesk"
            );
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Validate product sales and associate them with the reservation'.'%'])
                ->update(['status' => 'quote'])
                ->read(['id'])
                ->first(true);

            Booking::id($booking['id'])->delete(true);


        }
    ],
    '2202' => [
        'description'       =>  'Validate product sales and associate them for the guest the using the cashdesk',
        'help'              =>  "
         Validations:
            The order status is equal to paid.
            The order does not have a Booking.
            The order contains a valid product line.
            The order price matches the total paid in the order payments.
            The order price matches the unit price in the order lines within the order payments.
            The order price matches the amount in the order payment parts within the order payments.
            The payment method in the order payment parts within the order payments is cash.
            The payment origin in the order payment parts within the order payments is cashdesk.
        ",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'ORGANISATION'], ['lastname', '=', 'CLIENT PASSAGE']])->read(['id'])->first(true);
            return [$center['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $customer_identity_id) = $data;

            $session = CashdeskSession::search(['name' ,'like', '%'. 'Test - Caisse Organisation' . '%'])
                ->read(['id','user_id', 'name'])
                ->first(true);


            $cashdesk = Cashdesk::search(['center_id' ,'=' , $center_id])
                ->read(['id', 'name'])
                ->first(true);

            $order = Order::create([
                    'customer_id'   => $customer_identity_id,
                    'session_id'    => $session['id'],
                    'user_id'       => $session['user_id'],
                    'cashdesk_id'   => $cashdesk['id'],
                ])
                ->read(['id', 'name'])
                ->first(true);


            $product = Product::search(['sku','=', 'GA-Boisson-A' ])->read(['id', 'name'])->first(true);

            $order_line = OrderLine::create([
                    'order_id'          => $order['id'],
                    'name'              => $product['name'],
                    'product_id'        => $product['id'],
                    'qty'               => 1,
                    'unit_price'        => 10
                ])
                ->read(['id', 'qty', 'unit_price'])
                ->first(true);


            Order::id($order['id'])
                ->update([
                    'status'            => 'payment'
                ]);

            $order_payment =  OrderPayment::create([
                    'order_id'          =>  $order['id']
                ])
                ->read(['id'])
                ->first(true);


            $order_payment_part =  OrderPaymentPart::create([
                    'order_id'          => $order['id'] ,
                    'order_payment_id'  => $order_payment['id'],
                    'amount'            => $order_line['qty'] * $order_line['unit_price'],
                ])
                ->read(['id'])
                ->first(true);

            $order_line = OrderLine::id($order_line['id'])
                ->update([
                    'order_payment_id' =>  $order_payment['id']
                ])
                ->read(['id'])
                ->first(true);

            OrderPaymentPart::id($order_payment_part['id'])
                -> update([
                    'payment_method'    => 'cash',
                    'status'            => 'paid',
                ]);

            try {
                eQual::run('do', 'sale_pos_payment_validate', [
                        'id'         => $order_payment['id']
                    ]
                );
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_pos_order_do-pay', [
                        'id'         => $order['id']
                    ]
                );
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            OrderLine::id($order_line['id'])
                ->update([
                    'order_payment_id' =>  $order_payment['id'],
                ]);

            $order = Order::id($order['id'])
                ->update(['status' => 'paid'])
                ->read([
                    'id', 'booking_id','price', 'status',
                    'order_payments_ids' => [
                        'total_paid',
                        'order_lines_ids' => [
                            'product_id',
                            'unit_price'
                        ],
                        'order_payment_parts_ids' => [
                            'amount',
                            'payment_origin',
                            'payment_method'
                        ]
                    ]
                ])
                ->first(true);


            return $order;
        },
        'assert'            =>  function ($order) {

            $product = Product::search(['sku', '=', 'GA-Boisson-A'])->read(['id'])->first(true);

            $product_line = current(array_filter(
                $order['order_payments_ids'][0]['order_lines_ids'],
                fn($line) => $line['product_id'] === $product['id']
            ));

            return (
                $order['status'] == 'paid' &&
                !isset($order['booking_id']) &&
                isset($product_line) &&
                $order['price'] == $order['order_payments_ids'][0]['total_paid'] &&
                $order['price'] == $order['order_payments_ids'][0]['order_lines_ids'][0]['unit_price'] &&
                $order['price'] == $order['order_payments_ids'][0]['order_payment_parts_ids'][0]['amount'] &&
                $order['order_payments_ids'][0]['order_payment_parts_ids'][0]['payment_method'] == "cash" &&
                $order['order_payments_ids'][0]['order_payment_parts_ids'][0]['payment_origin'] == "cashdesk"
            );
        },
        'rollback'          =>  function () {

        }
    ]
];

<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/


use identity\Center;
use identity\Identity;
use sale\booking\BankStatementLine;
use sale\booking\Funding;
use realestate\RentalUnit;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\Payment;
use sale\booking\SojournProductModel;
use sale\booking\SojournProductModelRentalUnitAssignement;
use sale\booking\SojournType;
use sale\catalog\Product;
use sale\catalog\ProductModel;
use sale\booking\BookingType;
use sale\customer\CustomerNature;


$tests = [

    '1601' => [
        'description'       =>  'Verification the Automatic bank statement line reconciliation',
        'help'              =>  "
            1- Bank statement line
                The status must be reconciled.
                The structured communication of the line must match the funding reference.
                The reconciled value must match the amount paid in the payment.
            2.- Payment
                The payment origin must be bank.
                The payment method Bank transfer.
                The reservation must match the reference.
                The statement line must match the reconciled line
                The amount must be equal than the amount of the  bank statement line.
            3.- Funding
                The paid amount must be equal than due amount.
                The funding must be marked as paid.
        ",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $sojourn_type['id']];

        },
        'act'               =>  function ($data) {

            $PAYMENT_REFERENCE = '150041111782';

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-02-17'),
                    'date_to'               => strtotime('2023-02-19'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Automatic bank statement line reconciliation'
                ])
                ->update([
                    'name'                  => '411117'
                ])
                ->read(['id', 'date_from', 'date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 4 personne pendant 2 nuitées',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1,
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 4
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
                    ['center_id', '=', $center_id],
                    ['sojourn_type_id', '=', $sojourn_type_id],
                    ['is_accomodation', '=', true],
                ])->read(['id', 'capacity']);

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

            Booking::id($booking['id'])->update(['payment_reference' => $PAYMENT_REFERENCE]);

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

            Funding::create(['booking_id'       =>  $booking['id'],
                                    'due_amount'        =>  $booking['price'],
                                    'center_office_id'  =>  $booking['center_office_id']
                        ])
                        ->update(['payment_reference' => $PAYMENT_REFERENCE])
                        ->read(['id'])
                        ->first(true);

            $bank_statement_line = BankStatementLine::search(['structured_message', '=', $PAYMENT_REFERENCE])->read(['id'])->first(true);

            try {
                eQual::run('do', 'sale_pay_bankstatementline_do-reconcile', ['id' => $bank_statement_line['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read(['id','status' , 'payment_reference', 'price', 'total',
                            'fundings_ids' => [
                                'id', 'payment_reference', 'is_paid', 'paid_amount', 'due_amount',
                                'payments_ids' => [
                                    'id' , 'statement_line_id', 'payment_origin', 'payment_method'
                                ]
                            ]
                ])
                ->first(true);


            return $booking;
        },
        'assert'            =>  function ($booking) {

            $bankStatementLine = BankStatementLine::search(['structured_message', '=', $booking['payment_reference']])
                ->read(['id', 'status'])
                ->first(true);

            return $bankStatementLine['status'] === 'reconciled' &&
                $booking['fundings_ids'][0]['is_paid'] &&
                $booking['fundings_ids'][0]['paid_amount'] === $booking['fundings_ids'][0]['due_amount'] &&
                $booking['fundings_ids'][0]['payments_ids'][0]['payment_method'] === 'wire_transfer' &&
                $booking['fundings_ids'][0]['payments_ids'][0]['payment_origin'] === 'bank';

        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Automatic bank statement line reconciliation'.'%'])
                ->update(['status' => 'quote'])
                ->read(['id'])
                ->first(true);

            Payment::search(['booking_id' ,'=', $booking['id']])
                ->update(['state' => 'archive']);

            Booking::id($booking['id'])->delete(true);

        }
    ],

    '1602' => [
        'description'       =>  'Verification the Manual bank statement line reconciliation',
        'help'              =>  "
            1- Bank statement line
                The reconciled value must match the amount paid in the payment.
            2.- Payment
                The payment origin must be bank.
                The reservation must match the reference.
                The statement line must match the reconciled line
                The amount must be equal than the amount of the  bank statement line.
            3.- Funding
                The paid amount must be equal than the amount of the payment.
        ",
        'arrange'           =>  function () {
            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            $sojourn_type = SojournType::search(['name', '=', 'GA'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $sojourn_type['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $sojourn_type_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-02-20'),
                    'date_to'               => strtotime('2023-02-21'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Manual bank statement line reconciliation'
                ])
                ->update(['name' => '411118'])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 1 personne pendant 1 nuitées',
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
                    ['sojourn_type_id', '=' , $sojourn_type_id],
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

            $bank_statement_line = BankStatementLine::search(['message', '=', 'CONTRAT 411.118'])->read(['id', 'amount'])->first(true);

            $booking = Booking::id($booking['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id', 'price', 'center_office_id'])
                ->first(true);

            Funding::search(['booking_id'  ,'=',  $booking['id']])->update(['state' => 'archive']);

            $funding = Funding::create(['booking_id'       =>  $booking['id'],
                                    'due_amount'        =>  $booking['price'],
                                    'center_office_id'  =>  $booking['center_office_id']])
                        ->read(['id'])
                        ->first(true);

            try {
                Payment::create([
                        'statement_line_id' => $bank_statement_line['id'],
                        'funding_id'        => $funding['id']
                    ]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read(['id','status' , 'payment_reference', 'price', 'total',
                            'fundings_ids' => [
                                'id', 'payment_reference', 'is_paid', 'paid_amount', 'due_amount',
                                'payments_ids' => [
                                    'id' , 'funding_id', 'statement_line_id', 'payment_origin', 'amount'
                                ]
                            ]
                ])
                ->first(true);


            return $booking;
        },
        'assert'            =>  function ($booking) {

            $bank_statement_line = BankStatementLine::search(['message', '=', 'CONTRAT 411.118'])
                ->read(['id', 'amount'])
                ->first(true);

            return ($bank_statement_line['amount']  == $booking['fundings_ids'][0]['payments_ids'][0]['amount'] &&
                    $booking['fundings_ids'][0]['payments_ids'][0]['payment_origin']  == 'bank' &&
                    $booking['fundings_ids'][0]['paid_amount']  == $booking['fundings_ids'][0]['payments_ids'][0]['amount']
                );
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'.'Manual bank statement line reconciliation'.'%'])
                ->update(['status' => 'quote'])
                ->read(['id'])
                ->first(true);

            Payment::search(['booking_id' ,'=', $booking['id']])
                ->update(['state' => 'archive']);

            Booking::id($booking['id'])->delete(true);
        }
    ]
];

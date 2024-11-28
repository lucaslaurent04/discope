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
use realestate\RentalUnit;
use sale\booking\BookingType;
use sale\booking\SojournProductModel;
use sale\booking\SojournProductModelRentalUnitAssignement;
use sale\catalog\Product;
use sale\customer\CustomerNature;
use sale\booking\Funding;
use sale\booking\BankStatementLine;
use sale\booking\Payment;

$tests = [

    '2600' => [
        'description'       =>  'Verification of the bank statement import for payment processing.',
        'help'              =>  "
            1.- Import Bank Statement
                Create Bank Statement
                Create Bank Statement Line
            2- Bank statement line
                The status must be reconciled.
                The structured communication of the line must match the funding reference.
                The reconciled value must match the amount paid in the payment.
            3.- Payment
                The payment origin must be bank.
                The payment method Bank transfer.
                The reservation must match the reference.
                The statement line must match the reconciled line
                The amount must be equal than the amount of the  bank statement line.
            4.- Funding
                The paid amount must be equal than due amount.
                The funding must be marked as paid.
        ",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            $PAYMENT_REFERENCE = '151022051160';

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

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
                    'name'                  => '220511'
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

            try {
                eQual::run('do', 'finance_payments_import',
                ['data'      => 'data:application/octet-stream;base64,MDAwMDAyNzAzMjIxOTAwNSAgICAgICAgMDAwMjg5NjQgIFBST0dOTyBBTEVYQU5EUkUgICAgICAgICAgQ1JFR0JFQkIgICAwMDQwMTIxNDQ2NyAwMDAwMCAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDINCjEwMDM5MTkxMTU4Mjc5MjA5IEVVUjBCRSAgICAgICAgICAgICAgICAgIDAwMDAwMDAwMTE1ODMyNDAxOTAyMjBLQUxFTyAtIENFTlRSRSBCRUxHRSBUT1VSSUNvbXB0ZSBkJ2VudHJlcHJpc2UgQ0JDICAgICAgICAgICAgMDM5DQoyMTAwMDEwMDAwWkRaVTAxMTE1QVNDVEJCRU9OVFZBMDAwMDAwMDAwMDE1ODgwMDIwMDIyMDAwMTUwMDAwMTEwMTE1MTAyMjA1MTE2MCAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgMjAwMTIwMDM5MDEgMA0KMjIwMDAxMDAwMCAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBHS0NDQkVCQiAgICAgICAgICAgICAgICAgICAxIDANCjIzMDAwMTAwMDBCRTUwMDAxNTg4MDY2NDE4ICAgICAgICAgICAgICAgICAgICAgREVCT1JTVSBKRUFOICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgMCAxDQozMTAwMDEwMDAxWkRaVTAxMTE1QVNDVEJCRU9OVFZBMDAxNTAwMDAxMDAxREVCT1JTVSBKRUFOICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDEgMA0KMzIwMDAxMDAwMUpFQU4gREVCT1JTVSAxNy8yMDEgICAgICAgICA1MDAwICBOYW11ciAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAwIDANCjgwMzkxOTExNTgyNzkyMDkgRVVSMEJFICAgICAgICAgICAgICAgICAgMDAwMDAwMDAxMTc1NDI0MDIwMDIyMCAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAwDQo5ICAgICAgICAgICAgICAgMDAwMDA3MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMTU4ODAwICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgMg=='
                ]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $bank_statement_line = BankStatementLine::search(['structured_message' , '=', $PAYMENT_REFERENCE])
                ->read( ['id', 'amount'])
                ->first(true);

            $booking = Booking::id($booking['id'])
                ->update(['status' => 'confirmed'])
                ->read(['id', 'price', 'center_office_id'])
                ->first(true);

            Funding::search(['booking_id'  ,'=',  $booking['id']])->update(['state' => 'archive']);

            Funding::create([
                    'booking_id'        =>  $booking['id'],
                    'due_amount'        =>  $bank_statement_line['amount'],
                    'center_office_id'  =>  $booking['center_office_id']
                ])
                ->update(['payment_reference' => $PAYMENT_REFERENCE])
                ->read(['id', 'due_amount'])
                ->first(true);

            try {
                eQual::run('do', 'sale_pay_bankstatementline_do-reconcile', ['id' => $bank_statement_line['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read(['id','status' , 'payment_reference', 'price', 'total',
                            'fundings_ids' => [
                                'id','booking_id', 'center_office_id', 'payment_reference', 'is_paid', 'paid_amount', 'due_amount',
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

    '2601' => [
        'description'       =>  'Verification of the composition import with the file in the booking.',
        'help'              =>  "
            Create a reservation for 4 persons client for two nights.
            Change the reservation status from 'devis' to 'confirm'.
            Verify that the reservation could cannot be deleted.",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-04-17'),
                    'date_to'               => strtotime('2023-04-19'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Verification of the composition import with the file in the booking'
                ])
                ->read(['id','date_from','date_to'])
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
                    'nb_pers'        => 4,
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
                    ['booking_line_group_id', "=", $booking_line_group['id']],
                    ['product_model_id', "=", $product['product_model_id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=' , $center_id],
                    ['is_accomodation', '=' , true]
                ])
                ->read(['id','name','sojourn_type_id','capacity','room_types_ids']);

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
                    'booking_id' => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id'],
                    'sojourn_product_model_id' => $sojourn_product_model['id'],
                    'rental_unit_id' => $rental_unit['id'],
                    'qty' => $rental_unit['capacity'],
                    'is_accomodation' => true
                ])
                ->read(['id','qty'])
                ->first(true);

                $num_rua+= $spm_rental_unit_assignement['qty'];

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
                eQual::run('do', 'sale_booking_composition_import',
                ['booking_id' => $booking['id'],
                        'data' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,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'
                ]);
            }
            catch(Exception $e) {
                $message = $e->getMessage();
            }

            return $message;
        },
        'assert'            =>  function ($message) {
            return ($$message);
        },
        'rollback'          =>  function () {

        }
    ]
];

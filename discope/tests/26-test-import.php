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


            return $bank_statement_line;
        },
        'assert'            =>  function ($bank_statement_line) {

            return (isset($bank_statement_line));

        },
        'rollback'          =>  function () {

        }
    ]
];

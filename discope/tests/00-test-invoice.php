<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use identity\Identity;
use realestate\RentalUnit;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\BookingLineGroupAgeRangeAssignment;
use sale\booking\BookingType;
use sale\booking\Contract;
use sale\booking\Invoice;
use sale\booking\SojournProductModel;
use sale\booking\SojournProductModelRentalUnitAssignement;
use sale\catalog\Product;
use sale\catalog\ProductModel;
use sale\customer\CustomerNature;
use sale\customer\RateClass;


$tests = [
    '0010' => [
        'description'       => 'Validate the invoice price including the TVA.',
        'help'              => "
            Creates a booking with configuration below and test the consistency between  invoice price, booking price, group booking price, and sum of lines prices. \n
            The price for the group booking is determined based on the advantages associated with the 'Ecoles primaires et secondaires' category.'\n
            WorkFlow reservation:
                quote -> option: sale_booking_do-option
                option -> confirmed : sale_booking_do-confirm
                Contract Singed: sale_contract_signed
                confirmed -> checkedin : sale_booking_do-checkin
                checkedin -> checkedout : sale_booking_do-checkout
                checkedout -> invoiced: sale_booking_do-invoice
            RateClass: Ecoles primaires et secondaires
            Dates from: 10/03/2023
            Dates to: 14/03/2023
            Nights: 4 nights
            Numbers pers: 10 children (Primaire).",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'SEJ'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            $rate_class = RateClass::search(['name', '=', 'T5'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'],  $rate_class['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $rate_class_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-10'),
                    'date_to'               => strtotime('2023-03-14'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate the invoice price including the TVA.'
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
                    'nb_pers'        =>  10
                ])
                ->read(['id', 'nb_pers', 'price', 'fare_benefit','nb_pers','booking_lines_ids'])
                ->first(true);

            BookingLineGroupAgeRangeAssignment::search(['booking_id', '=', $booking['id']])->update(['age_range_id' => 3]);

            $product_model = ProductModel::search([
                    ['is_accomodation', '=', true],
                    ['name', 'like', '%'. 'Nuitée Séjour scolaire'. '%']
                ])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id', "=", $booking_line_group['id']],
                    ['product_model_id', "=", $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=', $center_id],
                    ['is_accomodation', '=', true],
                ])
                ->read(['id', 'name', 'capacity']);

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

            try {
                eQual::run('do', 'sale_booking_do-checkout', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_do-invoice', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read(['id', 'date_from', 'date_to', 'price', 'total'])
                ->first(true);

            $invoice = Invoice::search(['booking_id' ,'=', $booking['id']])
                ->read(['id', 'status', 'price', 'total'])
                ->first(true);

            $bookingLineGroup = BookingLineGroup::id($booking_line_group['id'])
                ->read(['id', 'price', 'total'])
                ->first(true);

            $bookingLines = BookingLine::search(['booking_id','=', $booking['id']])
                ->read(['id','price', 'total'])
                ->get(true);

            return [$booking, $invoice, $bookingLineGroup];

        },
        'assert'            =>  function ($data) {

            list($booking, $invoice, $bookingLineGroup, ) = $data;

            return (
                $booking['price'] == $invoice['price'] &&
                $booking['price'] == $bookingLineGroup['price']
            );

        },
        'rollback'          =>  function () {
            $booking = Booking::search(['description', 'like', '%'. 'Validate the invoice price including the TVA'.'%' ])->read('id')->first(true);

            Booking::id($booking['id'])->update([
                'state'     =>      'archive',
            ]);
        }
    ],/*
    '0011' => [
        'description'       => 'Validate that it is not possible to delete an invoice with the status Invoiced',
        'help'              => "
            The price for the group booking is determined based on the advantages associated with the 'Ecoles primaires et secondaires' category.'\n
            WorkFlow reservation:
                quote -> option: sale_booking_do-option
                option -> confirmed : sale_booking_do-confirm
                Contract Singed: sale_contract_signed
                confirmed -> checkedin : sale_booking_do-checkin
                checkedin -> checkedout : sale_booking_do-checkout
                checkedout -> invoiced: sale_booking_do-invoice
                proforma to invoiced : sale_booking_invoice_do-emit
            RateClass: Ecoles primaires et secondaires
            Dates from: 14/03/2023
            Dates to: 15/03/2023
            Nights: 1 nights
            Numbers pers: 5 children (Primaire).",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'SEJ'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            $rate_class = RateClass::search(['name', '=', 'T5'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $rate_class['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id,$rate_class_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-14'),
                    'date_to'               => strtotime('2023-03-15'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that it is not possible to delete an invoice.'
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
                ])
                ->read(['id', 'nb_pers', 'price', 'fare_benefit','nb_pers','booking_lines_ids'])
                ->first(true);

            BookingLineGroupAgeRangeAssignment::search(['booking_id', '=', $booking['id']])
                ->update(['age_range_id' => 3]);

            $product_model = ProductModel::search([
                    ['is_accomodation', '=', true],
                    ['name', 'like', '%'. 'Nuitée Séjour scolaire'. '%']
                ])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id', "=", $booking_line_group['id']],
                    ['product_model_id', "=", $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=', $center_id],
                    ['is_accomodation', '=', true],
                ])
                ->read(['id', 'name', 'capacity']);

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

            try {
                eQual::run('do', 'sale_booking_do-checkout', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_do-invoice', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read(['id', 'price', 'total'])
                ->first(true);

            $invoice = Invoice::search(['booking_id' ,'=', $booking['id']])
                ->read(['id', 'status', 'price', 'total'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_invoice_do-emit', ['id' => $invoice['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $invoice = Invoice::id($invoice['id'])
                ->read(['id', 'status', 'price', 'total'])
                ->first(true);

            return $invoice;

        },
        'assert'            =>  function ($invoice) {

            try {
                Invoice::id($invoice['id'])->delete(true);
            } catch (Exception $e) {
                $message= $e->getMessage();
            }
            $data = unserialize($message);
            return ($data['status']['non_removable'] == "Invoice can only be deleted while its status is proforma.");

        },
        'rollback'          =>  function () {

           $booking = Booking::search(['description', 'like', '%'. 'Validate that it is not possible to delete an invoice'.'%' ])->read('id')->first(true);

            Booking::id($booking['id'])->update([
                'state'     =>      'archive',
            ]);
        }
    ],
    '0012' => [
        'description'       => 'Validate that it is possible to delete a proforma',
        'help'              => "
            The price for the group booking is determined based on the advantages associated with the 'Ecoles primaires et secondaires' category.'\n
            WorkFlow reservation:
                quote -> option: sale_booking_do-option
                option -> confirmed : sale_booking_do-confirm
                Contract Singed: sale_contract_signed
                confirmed -> checkedin : sale_booking_do-checkin
                checkedin -> checkedout : sale_booking_do-checkout
                checkedout -> invoiced: sale_booking_do-invoice
                Delete proforma : sale_booking_invoice_do-delete
            RateClass: Ecoles primaires et secondaires
            Dates from: 16/03/2023
            Dates to: 17/03/2023
            Nights: 1 nights
            Numbers pers: 5 children (Primaire).",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'SEJ'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            $rate_class = RateClass::search(['name', '=', 'T5'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $rate_class['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $rate_class_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-16'),
                    'date_to'               => strtotime('2023-03-17'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate that it is possible to delete a proforma.'
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
                    'date_to'        => $booking['date_to']
                ])
                ->read(['id', 'nb_pers', 'price', 'fare_benefit','nb_pers','booking_lines_ids'])
                ->first(true);

            BookingLineGroupAgeRangeAssignment::search(['booking_id', '=', $booking['id']])
                ->update(['age_range_id' => 3]);


            try {
                eQual::run('do', 'sale_booking_update-sojourn-nbpers',
                            ['id'  => $booking_line_group['id'], 'nb_pers'   =>  10 ]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $product_model = ProductModel::search([
                    ['is_accomodation', '=', true],
                    ['name', 'like', '%'. 'Nuitée Séjour scolaire'. '%']
                ])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id', "=", $booking_line_group['id']],
                    ['product_model_id', "=", $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=', $center_id],
                    ['is_accomodation', '=', true],
                ])
                ->read(['id', 'name', 'capacity',]);

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

            try {
                eQual::run('do', 'sale_booking_do-checkout', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_do-invoice', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $invoice = Invoice::search(['booking_id' ,'=', $booking['id']])
                ->read(['id', 'status', 'price', 'total'])
                ->first(true);

            return $invoice;

        },
        'assert'            =>  function ($invoice) {

            try {
                eQual::run('do', 'sale_booking_invoice_do-delete', ['id' => $invoice['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $invoice = Invoice::id($invoice['id'])->read(['id'])->first(true);

            return !isset($invoice);

        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'like', '%'. 'Validate that it is possible to delete a proforma'.'%' ])->read('id')->first(true);

            Booking::id($booking['id'])->update([
                'state'     =>      'archive',
            ]);
        }
    ],
    '0013' => [
        'description'       => 'Validate the generation of a credit note during the invoice cancellation process.',
        'help'              => "
            Validations:
                The invoice status is verified to be cancelled
                The current invoice price matches the reversed invoice price
                The invoice price equals the reversed invoice's display price in negative
                The invoice total is the same as the reversed invoice total.
                The credit note type is credit_note
                The credit note status is proforma
                The booking status status is checkout
            WorkFlow reservation:
                quote -> option: sale_booking_do-option
                option -> confirmed : sale_booking_do-confirm
                Contract Singed: sale_contract_signed
                confirmed -> checkedin : sale_booking_do-checkin
                checkedin -> checkedout : sale_booking_do-checkout
                checkedout -> invoiced: sale_booking_do-invoice
                proforma to invoiced : sale_booking_invoice_do-emit
                invoiced to cancelled : sale_booking_invoice_do-reverse
            Center: Villers-Sainte-Gertrude
            Sejourn Type: Gîte Auberge
            RateClass: Ecoles primaires et secondaires
            Dates from: 17/03/2023
            Dates to: 18/03/2023
            Nights: 1 nights
            Numbers pers: 5 children (Primaire).",
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'SEJ'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search([['firstname', '=', 'John'], ['lastname', '=', 'Doe']])->read(['id'])->first(true);
            $rate_class = RateClass::search(['name', '=', 'T5'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id'], $rate_class['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id, $rate_class_id ) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-17'),
                    'date_to'               => strtotime('2023-03-18'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate Credit Note Creation for Invoice Cancellation.'
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
                ])
                ->read(['id', 'nb_pers', 'price', 'fare_benefit','nb_pers','booking_lines_ids'])
                ->first(true);

            BookingLineGroupAgeRangeAssignment::search(['booking_id', '=', $booking['id']])
                ->update(['age_range_id' => 3]);

            try {
                eQual::run('do', 'sale_booking_update-sojourn-nbpers',
                            ['id'  => $booking_line_group['id'], 'nb_pers'   =>  10 ]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $product_model = ProductModel::search([
                    ['is_accomodation', '=', true],
                    ['name', 'like', '%'. 'Nuitée Séjour scolaire'. '%']
                ])
                ->read(['id', 'name'])
                ->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id', "=", $booking_line_group['id']],
                    ['product_model_id', "=", $product_model['id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_units = RentalUnit::search([
                    ['center_id', '=', $center_id],
                    ['is_accomodation', '=', true],
                ])
                ->read(['id', 'name', 'capacity']);

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

            try {
                eQual::run('do', 'sale_booking_do-checkout', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_do-invoice', ['id' => $booking['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $booking = Booking::id($booking['id'])
                ->read(['id', 'price', 'total'])
                ->first(true);

            $invoice = Invoice::search(['booking_id' ,'=', $booking['id']])
                ->read(['id', 'status', 'price', 'total'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_invoice_do-emit', ['id' => $invoice['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            try {
                eQual::run('do', 'sale_booking_invoice_do-reverse', ['id' => $invoice['id']]);
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $invoice = Invoice::id($invoice['id'])
                ->read(['id', 'status', 'price', 'total','type',
                                'reversed_invoice_id' => ['id', 'status','display_price',  'price', 'total','type'],
                                'booking_id' =>['id', 'status']])
                ->first(true);

            return $invoice;

        },
        'assert'            =>  function ($invoice) {

            return(
                $invoice['status'] === 'cancelled' &&
                $invoice['price'] === $invoice['reversed_invoice_id']['price'] &&
                $invoice['price'] === -$invoice['reversed_invoice_id']['display_price'] &&
                $invoice['total'] === $invoice['reversed_invoice_id']['total'] &&
                $invoice['reversed_invoice_id']['type'] === 'credit_note' &&
                $invoice['reversed_invoice_id']['status'] === 'proforma' &&
                $invoice['booking_id']['status'] === 'checkedout'
            );
        },
        'rollback'          =>  function () {
            $booking = Booking::search(['description', 'like', '%'. 'Validate Credit Note Creation for Invoice Cancellation'.'%' ])->read('id')->first(true);

            Booking::id($booking['id'])->update([
                'state'     =>      'archive',
            ]);
        }
    ]*/
];

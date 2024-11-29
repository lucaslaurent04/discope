<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use communication\TemplatePart;
use core\Mail;
use identity\Center;
use identity\Identity;
use realestate\RentalUnit;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;
use sale\booking\BookingType;
use sale\booking\Composition;
use sale\booking\Contact;
use sale\booking\GuestList;
use sale\booking\GuestListItem;
use sale\booking\SojournProductModel;
use sale\booking\SojournProductModelRentalUnitAssignement;
use sale\catalog\Product;
use sale\customer\CustomerNature;

$tests = [

    '1001' => [
        'description' => "Test that status is changed to sent when a guest list is submitted.",

        'arrange' => function () {
            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);;
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-10'),
                    'date_to'               => strtotime('2023-03-14'),
                    'center_id'             => $center['id'],
                    'type_id'               => $booking_type['id'],
                    'customer_nature_id'    => $customer_nature['id'],
                    'customer_identity_id'  => $customer_identity['id'],
                    'description'           => 'Guest List test 1001: status is changed to sent when submitted.'
                ])
                ->read(['id'])
                ->first();

            $guest_list = GuestList::create([
                    'booking_id' => $booking['id']
                ])
                ->read(['id'])
                ->first();

            return $guest_list['id'];
        },

        'act' => function ($guest_list_id) {
            try {
                eQual::run('do', 'sale_booking_guests_list_submit', ['id' => $guest_list_id]);
            }
            catch(Exception $e) {
                return false;
            }

            return $guest_list_id;
        },

        'assert' => function ($guest_list_id) {
            $guest_list = GuestList::id($guest_list_id)
                ->read(['status'])
                ->first();

            return $guest_list['status'] === 'sent';
        },

        'rollback' => function () {
            $booking = Booking::search(['description', '=', 'Guest List test 1001: status is changed to sent when submitted.'])
                ->read(['id'])
                ->first();

            GuestList::search(['booking_id', '=', $booking['id']])->delete(true);

            Booking::id($booking['id'])->delete(true);
        }
    ],

    '1002' => [
        'description' => "Test that compositions are created when a guest list is submitted.",

        'arrange' => function () {
            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);;
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-10'),
                    'date_to'               => strtotime('2023-03-14'),
                    'center_id'             => $center['id'],
                    'type_id'               => $booking_type['id'],
                    'customer_nature_id'    => $customer_nature['id'],
                    'customer_identity_id'  => $customer_identity['id'],
                    'description'           => 'Guest List test 1002: composition are created when submitted.'
                ])
                ->read(['id', 'date_from', 'date_to'])
                ->first();

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'name'           => 'Booking Line Group 1 for test 1002',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1,
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 2
                ])
                ->read(['id'])
                ->first(true);

            $product = Product::search(['sku','=', 'GA-NuitCh1-A' ])->read(['id', 'product_model_id'])->first(true);

            BookingLine::create([
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id'],
                    'product_id'            => $product['id']
                ])
                ->read(['id','name','price'])
                ->first(true);

            $sojourn_product_model = SojournProductModel::search([
                    ['booking_line_group_id' , "=" , $booking_line_group['id']],
                    ['product_model_id' , "=" , $product['product_model_id']]
                ])
                ->read(['id'])
                ->first(true);

            $rental_unit = RentalUnit::search([
                    ['center_id', '=' , $center['id']],
                    ['is_accomodation', '=' , true]
                ])
                ->read(['id'])
                ->first(true);

            SojournProductModelRentalUnitAssignement::create([
                    'booking_id'                => $booking['id'],
                    'booking_line_group_id'     => $booking_line_group['id'],
                    'sojourn_product_model_id'  => $sojourn_product_model['id'],
                    'rental_unit_id'            => $rental_unit['id'],
                    'qty'                       => 2,
                    'is_accomodation'           => true
                ])
                ->read(['id','qty'])
                ->first(true);

            $guest_list = GuestList::create([
                    'booking_id' => $booking['id']
                ])
                ->read(['id'])
                ->first();

            $guests = [
                [
                    'guest_list_id'         => $guest_list['id'],
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id'],
                    'firstname'             => 'Jean',
                    'lastname'              => 'Dupont',
                    'gender'                => 'M',
                    'date_of_birth'         => strtotime('1994-01-06'),
                    'address_street'        => 'Avenue Louise 1',
                    'address_zip'           => '1000',
                    'address_city'          => 'Bruxelles',
                    'address_country'       => 'BE'
                ],
                [
                    'guest_list_id'         => $guest_list['id'],
                    'booking_id'            => $booking['id'],
                    'booking_line_group_id' => $booking_line_group['id'],
                    'firstname'             => 'Fanny',
                    'lastname'              => 'Doe',
                    'gender'                => 'F',
                    'date_of_birth'         => strtotime('1993-02-15'),
                    'address_street'        => 'Avenue Louise 1',
                    'address_zip'           => '1000',
                    'address_city'          => 'Bruxelles',
                    'address_country'       => 'BE'
                ]
            ];

            foreach($guests as $guest) {
                GuestListItem::create($guest);
            }

            return $guest_list['id'];
        },

        'act' => function ($guest_list_id) {
            try {
                eQual::run('do', 'sale_booking_guests_list_submit', ['id' => $guest_list_id]);
            }
            catch(Exception $e) {
                return false;
            }

            return $guest_list_id;
        },

        'assert' => function ($guest_list_id) {
            $guest_list = GuestList::id($guest_list_id)
                ->read(['booking_id'])
                ->first();

            $compositions = Composition::search(['booking_id', '=', $guest_list['booking_id']])
                ->read(['composition_items_ids' => ['firstname', 'lastname', 'gender', 'date_of_birth', 'address', 'country']])
                ->get(true);

            if(count($compositions) !== 1 || count($compositions[0]['composition_items_ids']) !== 2) {
                return false;
            }

            $expected_items = [
                [
                    'firstname'             => 'Jean',
                    'lastname'              => 'Dupont',
                    'gender'                => 'M',
                    'date_of_birth'         => strtotime('1994-01-06'),
                    'address'               => 'Avenue Louise 1 1000 Bruxelles',
                    'country'               => 'BE'
                ],
                [
                    'firstname'             => 'Fanny',
                    'lastname'              => 'Doe',
                    'gender'                => 'F',
                    'date_of_birth'         => strtotime('1993-02-15'),
                    'address'               => 'Avenue Louise 1 1000 Bruxelles',
                    'country'               => 'BE'
                ]
            ];

            $composition_items = $compositions[0]['composition_items_ids'];
            foreach($expected_items as $index => $expected_item) {
                foreach($expected_item as $field => $value) {
                    if($value !== $composition_items[$index][$field]) {
                        return false;
                    }
                }
            }

            return true;
        },

        'rollback' => function () {
            $booking = Booking::search(['description', '=', 'Guest List test 1002: composition are created when submitted.'])
                ->read(['id'])
                ->first();

            GuestList::search(['booking_id', '=', $booking['id']])->delete(true);
            Composition::search(['booking_id', '=', $booking['id']])->delete(true);

            Booking::id($booking['id'])->delete(true);
        }
    ],

    '1003' => [
        'description' => "Test that email is added to queue when a customer is invited to complete a guest list.",

        'arrange' => function () {
            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['code', '=', 'TP'])->read(['id'])->first(true);;
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id', 'lang_id' => ['code']])->first(true);

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-03-10'),
                    'date_to'               => strtotime('2023-03-14'),
                    'center_id'             => $center['id'],
                    'type_id'               => $booking_type['id'],
                    'customer_nature_id'    => $customer_nature['id'],
                    'customer_identity_id'  => $customer_identity['id'],
                    'description'           => 'Guest List test 1003: email is sent when invite.'
                ])
                ->read(['id'])
                ->first(true);

            $template = Template::search([
                    ['category_id', '=', 6],
                    ['type', '=', 'contract'],
                    ['code', '=', 'guestslist_invite']
                ])
                ->first(true);

            if(!$template) {
                $template = Template::create([
                        // common category [KA - 6]
                        'category_id'   => 6,
                        'type'          => 'contract',
                        'code'          => 'guestslist_invite'
                    ])
                    ->read(['id'])
                    ->first(true);

                TemplatePart::create([
                            'template_id'   => $template['id'],
                            'name'          => 'subject',
                            'value'         => "Complete your booking's guest list"
                        ],
                        $customer_identity['lang_id']['code']
                    );

                TemplatePart::create([
                            'template_id'   => $template['id'],
                            'name'          => 'body',
                            'value'         => "<p>This is an invitation to complete your booking guest list.</p><p>Click on the following link:</p>"
                        ],
                        $customer_identity['lang_id']['code']
                    );
            }

            return $booking['id'];
        },

        'act' => function ($booking_id) {
            try {
                eQual::run('do', 'sale_booking_guests_list_invite', ['id' => $booking_id]);
            }
            catch(Exception $e) {
                return false;
            }

            return $booking_id;
        },

        'assert' => function ($booking_id) {
            $mail = Mail::search([
                    ['object_class', '=', 'sale\booking\Booking'],
                    ['object_id', '=', $booking_id]
                ])
                ->read(['to', 'subject', 'body'])
                ->first(true);

            return !is_null($mail) && $mail['to'] === 'johndoe@example.com';
        },

        'rollback' => function () {
            $booking = Booking::search(['description', '=', 'Guest List test 1003: email is sent when invite.'])
                ->read(['id'])
                ->first();

            Contact::search(['booking_id', '=', $booking['id']])->delete(true);
            GuestList::search(['booking_id', '=', $booking['id']])->delete(true);

            Template::search([
                    ['type', '=', 'contract'],
                    ['code', '=', 'guestslist_invite']
                ])
                ->delete(true);

            Mail::search([
                    ['object_class', '=', 'sale\booking\Booking'],
                    ['object_id', '=', $booking['id']]
                ])
                ->delete(true);

            Booking::id($booking['id'])->delete(true);
        }
    ]
];

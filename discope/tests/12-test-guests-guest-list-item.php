<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use identity\Identity;
use sale\booking\Booking;
use sale\booking\BookingLineGroup;
use sale\booking\BookingType;
use sale\booking\GuestList;
use sale\booking\GuestListItem;
use sale\customer\CustomerNature;


$tests = [
    '1201' => [
        'description'       =>  'Validate create an empty guest list item based on the guest list ID.',
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['name', 'like', '%Tout public%'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-03'),
                    'date_to'               => strtotime('2023-01-04'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate create an empty guest list item.'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Test 1201: sale_booking_guests_listitem_create',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1
                ])
                ->read(['id'])
                ->first(true);

            $guest_list = GuestList::create(['booking_id' => $booking['id']])
                ->read('id')
                ->first(true);

            eQual::run('do', 'sale_booking_guests_listitem_create', [
                'guest_list_id'         => $guest_list['id'],
                'booking_line_group_id' => $booking_line_group['id']
            ]);

            return $guest_list['id'];
        },
        'assert'            =>  function ($guest_list_id) {

            $guest_list_item = GuestListItem::search(['guest_list_id' , '=' , $guest_list_id])
                                    ->read(['id', 'firstname', 'lastname','date_of_birth', 'citizen_identification',
                                            'address_street', 'address_zip', 'address_city','guest_list_id'])
                                    ->first(true);

            return ($guest_list_item['guest_list_id'] == $guest_list_id &&
                    empty($guest_list_item['firstname']) && empty($guest_list_item['lastname']) &&
                    empty($guest_list_item['date_of_birth']) && empty($guest_list_item['address_street']) &&
                    empty($guest_list_item['address_zip']) && empty($guest_list_item['address_city'])
            );
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'ilike', '%'. 'Validate create an empty guest list item'.'%'])
                    ->read(['id', 'guest_list_id' , 'guest_list_items_ids'])
                    ->first(true);

            GuestList::id($booking['guest_list_id'])->delete(true);
            GuestListItem::ids($booking['guest_list_items_ids'])->delete(true);
            Booking::id($booking['id'])->delete(true);

        }

    ],
    '1202' => [
        'description'       =>  'Validate delete hard guest list item.',
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['name', 'like', '%Tout public%'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                    'date_from'             => strtotime('2023-01-03'),
                    'date_to'               => strtotime('2023-01-04'),
                    'center_id'             => $center_id,
                    'type_id'               => $booking_type_id,
                    'customer_nature_id'    => $customer_nature_id,
                    'customer_identity_id'  => $customer_identity_id,
                    'description'           => 'Validate delete hard guest list item.'
                ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Test 1201: sale_booking_guests_listitem_create',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1
                ])
                ->read(['id'])
                ->first(true);

            $guest_list = GuestList::create(['booking_id' => $booking['id']])
                ->read('id')
                ->first(true);

            eQual::run('do', 'sale_booking_guests_listitem_create', [
                'guest_list_id'         => $guest_list['id'],
                'booking_line_group_id' => $booking_line_group['id']
            ]);

            $guest_list_item = GuestListItem::search(['guest_list_id' , '=', $guest_list['id']])
                ->read(['id', 'guest_list_id'])
                ->first(true);

            eQual::run('do', 'sale_booking_guests_listitem_delete', [
                'id' => $guest_list_item['id']
            ]);

            return $guest_list_item;
        },
        'assert'            =>  function ($guest_list_item) {

            $guest_list = GuestList::id($guest_list_item['guest_list_id'])
                ->read(['id', 'guest_list_items_ids'])
                ->first(true);


            $booking = Booking::search(['guest_list_id', '=', $guest_list_item['guest_list_id']])
                ->read(['id', 'guest_list_items_ids'])
                ->first(true);

            $guest_list_item_searched = GuestListItem::id($guest_list_item['id'])->read('id')->first(true);

            return (
                !isset($guest_list_item_searched) &&
                !in_array($guest_list_item['id'], $guest_list['guest_list_items_ids']) &&
                !in_array($guest_list_item['id'], $booking['guest_list_items_ids'])
            );
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'ilike', '%'. 'Validate delete hard guest list item'.'%'])
                ->read(['id', 'guest_list_id' , 'guest_list_items_ids'])
                ->first(true);

            GuestList::id($booking['guest_list_id'])->delete(true);
            Booking::id($booking['id'])->delete(true);
        }

    ],
    '1203' => [
        'description'       =>  'This test validates the update of the guest list item from an empty guest list item.',
        'arrange'           =>  function () {

            $center = Center::search(['name', 'like', '%Your Establisment%' ])->read(['id'])->first(true);
            $booking_type = BookingType::search(['name', 'like', '%Tout public%'])->read(['id'])->first(true);
            $customer_nature = CustomerNature::search(['code', '=', 'IN'])->read(['id'])->first(true);
            $customer_identity = Identity::search(['display_name', '=', 'John DOE'])->read(['id'])->first(true);

            return [$center['id'], $booking_type['id'], $customer_nature['id'], $customer_identity['id']];

        },
        'act'               =>  function ($data) {

            list($center_id, $booking_type_id, $customer_nature_id, $customer_identity_id) = $data;

            $booking = Booking::create([
                'date_from'             => strtotime('2023-01-03'),
                'date_to'               => strtotime('2023-01-04'),
                'center_id'             => $center_id,
                'type_id'               => $booking_type_id,
                'customer_nature_id'    => $customer_nature_id,
                'customer_identity_id'  => $customer_identity_id,
                'description'           => 'Validate update guest list item.'
            ])
                ->read(['id','date_from','date_to'])
                ->first(true);

            $booking_line_group = BookingLineGroup::create([
                    'booking_id'     => $booking['id'],
                    'is_sojourn'     => true,
                    'group_type'     => 'sojourn',
                    'has_pack'       => false,
                    'name'           => 'Séjour pour 1 personne pendant 2 nuitées',
                    'order'          => 1,
                    'rate_class_id'  => 4,
                    'sojourn_type_id'=> 1,
                    'date_from'      => $booking['date_from'],
                    'date_to'        => $booking['date_to'],
                    'nb_pers'        => 1,
                ])
                ->read(['id'])
                ->first(true);

            $guest_list = GuestList::create(['booking_id' => $booking['id']])
                ->read('id')
                ->first(true);

            eQual::run('do', 'sale_booking_guests_listitem_create', [
                'guest_list_id'         => $guest_list['id'],
                'booking_line_group_id' => $booking_line_group['id']
            ]);

            $guest_list_item = GuestListItem::search(['guest_list_id' , '=' , $guest_list['id']])
                ->read(['id'])
                ->first(true);

            $params =  [
                'id'        => $guest_list_item['id'],
                'fields'    => [
                    'firstname'                 => 'Jean',
                    'lastname'                  => 'Dupont',
                    'gender'                    => 'M',
                    'booking_id'                => $booking['id'],
                    'guest_list_id'             => $guest_list['id'],
                    'booking_line_group_id'     => $booking_line_group['id'],
                    'date_of_birth'             => strtotime('1994-01-06'),
                    'citizen_identification'    => '85.03.15.12345',
                    'address_street'            => 'Avenue Louise 1',
                    'address_zip'               => '1000',
                    'address_city'              => 'Bruxelles',
                    'address_country'           => 'BE'
                ]
            ];

            eQual::run('do', 'sale_booking_guests_listitem_update', $params);

            return $guest_list_item['id'];
        },
        'assert'            =>  function ($guest_list_item_id) {

            $guest_list_item = GuestListItem::id($guest_list_item_id)
                ->read(['firstname', 'lastname','citizen_identification',
                        'address_street', 'address_zip'])
                ->first(true);

            return ($guest_list_item['firstname'] == 'Jean' &&
                $guest_list_item['lastname'] == 'Dupont' &&
                $guest_list_item['citizen_identification'] == '85.03.15.12345' &&
                $guest_list_item['address_street'] == 'Avenue Louise 1' &&
                $guest_list_item['address_zip'] == '1000'
            );
        },
        'rollback'          =>  function () {

            $booking = Booking::search(['description', 'ilike', '%'. 'Validate update guest list item'.'%'])
                ->read(['id', 'guest_list_id' , 'guest_list_items_ids'])
                ->first(true);

            GuestList::id($booking['guest_list_id'])->delete(true);
            GuestListItem::ids($booking['guest_list_items_ids'])->delete(true);
            BookingLineGroup::search(['booking_id','=', $booking['id']])->delete(true);
            Booking::id($booking['id'])->delete(true);

        }

    ]
];

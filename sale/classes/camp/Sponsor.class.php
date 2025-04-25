<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

use equal\orm\Model;

class Sponsor extends Model {

    public static function getDescription(): string {
        return "Entity that offer a financial help to children for summer camps.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Short name of the sponsor for display.",
                'required'          => true,
                'unique'            => true
            ],

            'complete_name' => [
                'type'              => 'string',
                'description'       => "Complete name of the sponsor, used for address.",
                'required'          => true
            ],

            'address_street' => [
                'type'              => 'string',
                'description'       => "Street and number of the sponsor.",
                'required'          => true
            ],

            'address_dispatch' => [
                'type'              => 'string',
                'description'       => "Optional info for mail dispatch (apartment, box, floor, ...)."
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => "Zip code of the sponsor.",
                'required'          => true
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => "City of the sponsor.",
                'required'          => true
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Sponsored amount that is given for an enrollment to a camp.",
                'default'           => 0
            ],

            'sponsor_type' => [
                'type'              => 'string',
                'selection'         => [
                    'other',
                    'commune',
                    'community-of-communes',
                    'department-caf',
                    'department-msa',
                    'ce'
                ],
                'description'       => "Type of the sponsor.",
                'default'           => 'other'
            ],

            'code_ce' => [
                'type'              => 'string',
                'description'       => "Code of the \"commité d'entreprise\".",
                'visible'           => ['sponsor_type', '=', 'ce']
            ],

            'enrollments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\camp\Enrollment',
                'foreign_field'     => 'sponsor_id',
                'description'       => "Sponsored enrollments."
            ]

        ];
    }
}

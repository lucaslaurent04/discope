<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace lathus\sale\camp;

class Institution extends \sale\camp\Institution {

    public static function getDescription(): string {
        return "Override of camp Institution to add data fetched from CPA Lathus API.";
    }

    public static function getColumns(): array {
        return [

            'phone' => [
                'type'              => 'string',
                'description'       => "Fix phone number of the child's guardian."
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => "City of the child's guardian.",
                'dependents'        => ['is_ccvg']
            ]

        ];
    }
}

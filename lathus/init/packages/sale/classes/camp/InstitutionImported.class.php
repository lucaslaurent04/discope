<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp;

class InstitutionImported extends Institution {

    public static function getModelScope(): ?string {
        return Institution::getType();
    }

    public static function getDescription(): string {
        return "Override of camp Institution to add data fetched from CPA Lathus API.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the institution."
            ],

            'email' => [
                'type'              => 'string',
                'description'       => "Email address of the institution."
            ],

            'phone' => [
                'type'              => 'string',
                'description'       => "Fix phone number of the child's institution."
            ],

            'address_street' => [
                'type'              => 'string',
                'description'       => "Street and number of the child's institution.",
            ],

            'address_zip' => [
                'type'              => 'string',
                'description'       => "Zip code of the child's institution.",
            ],

            'address_city' => [
                'type'              => 'string',
                'description'       => "City of the child's institution."
            ]

        ];
    }
}

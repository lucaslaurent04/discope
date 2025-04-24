<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\camp\price;

use equal\orm\Model;
use sale\camp\Enrollment;

class PriceAdapter extends Model {

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "The name of the price adapter.",
                'required'          => true
            ],

            'enrollment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Enrollment',
                'description'       => "Enrollment the line is part of.",
                'required'          => true
            ],

            'price_adapter_type' => [
                'type'              => 'string',
                'selection'         => [
                    'other',
                    'help-commune',
                    'help-community-of-communes',
                    'help-department-caf',
                    'help-department-msa',
                    'holiday-voucher',
                    'loyalty-discount'
                ],
                'description'       => "Type of the price adapter.",
                'default'           => 'other'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Additional information about the price adapter."
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Amount to remove to the enrollment price.",
                'onupdate'          => 'onupdateAmount'
            ]

        ];
    }

    public static function getActions(): array {
        return [

            'reset-enrollments-prices' => [
                'description'   => "Reset the enrollments prices fields values so they can be re-calculated.",
                'policies'      => [],
                'function'      => 'doResetEnrollmentsPrices'
            ]

        ];
    }

    public static function doResetEnrollmentsPrices($self) {
        $self->read(['enrollment_id']);

        $map_enrollment_ids = [];
        foreach($self as $enrollment_line) {
            $map_enrollment_ids[$enrollment_line['enrollment_id']] = true;
        }

        Enrollment::ids(array_keys($map_enrollment_ids))
            ->update([
                'total' => null,
                'price' => null
            ]);
    }

    public static function onupdateAmount($self) {
        $self->do('reset-enrollments-prices');
    }
}

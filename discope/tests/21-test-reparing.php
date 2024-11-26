<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use realestate\RentalUnit;
use sale\booking\Consumption;
use sale\booking\Repairing;


$tests = [

    '2100' => [
        'description'       =>  'Verify that a repair request can be created by providing a date range, customer, and rental unit',
        'help'              =>  "
        Validations:
            The rental unit ID is included in the list of units associated with the repair.
            The repair’s start and end dates match the specified date range.
            The center ID of the repair matches that of the rental unit.
        ",
        'arrange'           =>  function () {

            $rental_unit = RentalUnit::search(['name', '=', 'OR- 001'])
                ->update(['status' => 'ready'])
                ->read(['id', 'status','center_id'])
                ->first(true);

            return $rental_unit;

        },
        'act'               =>  function ($rental_unit) {

            $date_from = strtotime('today');
            $date_to = strtotime('tomorrow');

            try {
                eQual::run('do', 'sale_booking_plan-repair', [
                        'date_from'         => $date_from,
                        'date_to'           => $date_to,
                        'rental_unit_id'    => $rental_unit['id'],
                        'description'       => 'Verify Repair Request Creation'
                    ]
                );
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $repairing = Repairing::search([
                    ['center_id', '=' , $rental_unit['center_id']],
                    ['date_from', '>=', $date_from],
                    ['date_to', '<=', $date_to]
                ])
                ->read([
                    'id', 'description','center_id', 'date_from', 'date_to',
                    'rental_units_ids' => ['id', 'name', 'status']
                ])
                ->read(['id'])
                ->first(true);

            return $repairing;
        },
        'assert'            =>  function ($repairing) {

            $date_from = strtotime('today');
            $date_to = strtotime('tomorrow');

            $rental_unit = RentalUnit::search(['name', '=', 'OR- 001'])
                ->update(['status' => 'ready'])
                ->read(['id', 'name', 'status','center_id'])
                ->first(true);

            return (in_array($rental_unit['id'], array_column($repairing['rental_units_ids'], 'id')) &&
                    $repairing['date_from'] == $date_from &&
                    $repairing['date_to'] == $date_to &&
                    $repairing['center_id'] == $rental_unit['center_id']
            );
        },
        'rollback'          =>  function () {

            $repairing = Repairing::search(['description', 'like', '%'. 'Verify Repair Request Creation'.'%'])
                ->read(['id'])
                ->first(true);


            $consumptions_ids = Consumption::search(['repairing_id' , '=', $repairing['id']])->ids();

            foreach($consumptions_ids  as $consumptions_id){
                Consumption::id($consumptions_id)->delete(true);
            }

            Repairing::id($repairing['id'])->delete(true);

            RentalUnit::search(['name', '=', 'OR- 001'])->update(['status' => 'ready']);

        }
    ],
    '2101' => [
        'description'       =>  'Verify the remove the repairing episode and The rental unit will be released and made available',
        'help'              =>  "",
        'arrange'           =>  function () {

            $rental_unit = RentalUnit::search([
                    ['name', '=', 'OR- 002']
                ])
                ->update(['status' => 'ready'])
                ->read(['id','center_id'])
                ->first(true);

            return $rental_unit;

        },
        'act'               =>  function ($rental_unit) {

            $date_from = strtotime('tomorrow +1 days');
            $date_to = strtotime('tomorrow +2 days');

            try {
                eQual::run('do', 'sale_booking_plan-repair', [
                        'date_from'         => $date_from,
                        'date_to'           => $date_to,
                        'rental_unit_id'    => $rental_unit['id'],
                        'description'       => 'Verify Repair Request Creation'
                    ]
                );
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $repairing = Repairing::search([
                    ['center_id', '=' , $rental_unit['center_id']],
                    ['date_from', '>=', $date_from],
                    ['date_to', '<=', $date_to]
                ])
                ->read(['id'])
                ->first(true);

            try {
                eQual::run('do', 'sale_booking_repairing_do-remove', [
                        'id'         => $repairing['id']
                    ]
                );
            }
            catch(Exception $e) {
                $e->getMessage();
            }

            $repairing = Repairing::id($repairing['id'])
                ->read(['id'])
                ->first(true);

            return $repairing;
        },
        'assert'            =>  function ($repairing) {
            return (!isset($repairing));
        },
        'rollback'          =>  function () {
            RentalUnit::search(['name', '=', 'OR- 002'])->update(['status' => 'ready']);

        }
    ]
];

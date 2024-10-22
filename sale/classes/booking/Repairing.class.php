<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use equal\orm\Model;
use equal\html\HTMLToText;
use lodging\sale\booking\Consumption;

class Repairing extends Model {

    public static function getDescription() {
        return "Repairings are episodes of repairs and maintenance impacting one or more rental units of a given Center.";
    }

    public static function getLink() {
        return "/booking/#/repairings/repairing/object.id";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Excerpt of the description to serve as reference.",
                'function'          => 'calcName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => "Reason of the repairing, for internal use.",
                'default'           => '',
                'onupdate'          => 'onupdateDescription'
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => 'The center the repairing relates to.',
                'required'          => true,
                'ondelete'          => 'cascade'         // delete repairing when parent center is deleted
            ],

            'repairs_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Repair',
                'foreign_field'     => 'repairing_id',
                'description'       => 'Consumptions related to the booking.',
                'ondetach'          => 'delete'
            ],

            'rental_units_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'realestate\RentalUnit',
                'foreign_field'     => 'repairings_ids',
                'rel_table'         => 'sale_rel_repairing_rentalunit',
                'rel_foreign_key'   => 'rental_unit_id',
                'rel_local_key'     => 'repairing_id',
                'description'       => 'List of rental units assigned to the scheduled repairing.',
                'onupdate'          => 'onupdateRentalUnitsIds'
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "",
                'default'           => time(),
                'onupdate'          => 'onupdateDateFrom'
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "",
                'default'           => time(),
                'onupdate'          => 'onupdateDateTo'
            ],

            // time fields are based on dates from repairs (consumptions)
            'time_from' => [
                'type'              => 'computed',
                'result_type'       => 'time',
                'function'          => 'calcTimeFrom',
                'store'             => true
            ],

            'time_to' => [
                'type'              => 'computed',
                'result_type'       => 'time',
                'function'          => 'calcTimeTo',
                'store'             => true
            ]

        ];
    }

    public static function calcName($om, $oids, $lang) {
        $result = [];
        $repairings = $om->read(self::getType(), $oids, ['description'], $lang);
        if($repairings > 0) {
            foreach($repairings as $oid => $odata) {
                $result[$oid] = mb_substr(strip_tags($odata['description']), 0, 25);
            }
        }
        return $result;
    }

    public static function calcTimeFrom($om, $oids, $lang) {
        $result = [];
        $repairings = $om->read(self::getType(), $oids, ['repairs_ids']);
        if($repairings > 0) {
            foreach($repairings as $oid => $repairing) {
                $min_date = PHP_INT_MAX;
                $time_from = 0;
                $repairs = $om->read(Repair::getType(), $repairing['repairs_ids'], ['date', 'schedule_from']);
                if($repairs > 0 && count($repairs)) {
                    foreach($repairs as $rid => $repair) {
                        if($repair['date'] < $min_date) {
                            $min_date = $repair['date'];
                            $time_from = $repair['schedule_from'];
                        }
                    }
                    $result[$oid] = $time_from;
                }
            }
        }
        return $result;
    }

    public static function calcTimeTo($om, $oids, $lang) {
        $result = [];
        $repairings = $om->read(self::getType(), $oids, ['repairs_ids']);
        if($repairings > 0) {
            foreach($repairings as $oid => $repairing) {
                $max_date = 0;
                $time_to = 0;
                $repairs = $om->read(Repair::getType(), $repairing['repairs_ids'], ['date', 'schedule_to']);
                if($repairs > 0 && count($repairs)) {
                    foreach($repairs as $rid => $repair) {
                        if($repair['date'] > $max_date) {
                            $max_date = $repair['date'];
                            $time_to = $repair['schedule_to'];
                        }
                    }
                    $result[$oid] = $time_to;
                }
            }
        }
        return $result;
    }

    public static function onupdateRentalUnitsIds($om, $ids, $values, $lang) {
        $om->callonce(self::getType(), '_updateRepairs', $ids, [], $lang);
    }

    public static function onupdateDescription($om, $oids, $values, $lang) {
        $om->update(self::getType(), $oids, ['name' => null]);
        $om->read(self::getType(), $oids, ['name']);
    }

    public static function onupdateDateFrom($om, $ids, $values, $lang) {
        $om->callonce(self::getType(), '_updateRepairs', $ids, [], $lang);
    }

    public static function onupdateDateTo($om, $ids, $values, $lang) {
        $om->callonce(self::getType(), '_updateRepairs', $ids, [], $lang);
    }

    public static function _updateRepairs($om, $ids, $values, $lang) {
        // generate consumptions
        $repairings = $om->read(self::getType(), $ids, ['repairs_ids', 'center_id', 'date_from', 'date_to', 'rental_units_ids'], $lang);
        // reset time_from and time_to
        $om->update(self::getType(), $ids, ['time_from' => null, 'time_to' => null], $lang);
        if($repairings > 0) {
            foreach($repairings as $id => $repairing) {
                // remove existing repairs
                $repairs_ids = array_map(function($a) { return "-$a";}, $repairing['repairs_ids']);
                $om->update(self::getType(), $id, ['repairs_ids' => $repairs_ids]);
                $nb_days = floor( ($repairing['date_to'] - $repairing['date_from']) / (60*60*24) ) + 1;
                list($day, $month, $year) = [ date('j', $repairing['date_from']), date('n', $repairing['date_from']), date('Y', $repairing['date_from']) ];
                for($i = 0; $i < $nb_days; ++$i) {
                    $c_date = mktime(0, 0, 0, $month, $day+$i, $year);
                    foreach($repairing['rental_units_ids'] as $rental_unit_id) {
                        $fields = [
                            'repairing_id'          => $id,
                            'center_id'             => $repairing['center_id'],
                            'date'                  => $c_date,
                            'rental_unit_id'        => $rental_unit_id
                        ];
                        $om->create('sale\booking\Repair', $fields, $lang);
                    }
                }
                // #todo - check-contingencies (! at this stage, we don't know the previously assigned rental units)
            }
        }
    }

    public static function canupdate($om, $ids, $values, $lang) {
        if(isset($values['center_id']) || isset($values['date_from'])  ||  isset($values['date_to']) || isset($values['rental_units_ids']) ) {
            $repairings = $om->read(self::getType(), $ids, ['id','center_id', 'date','date_from', 'date_to', 'rental_units_ids'], $lang);

            if($repairings > 0) {
                foreach($repairings as $id => $repairing) {
                    $center_id = (isset($values['center_id']))?$values['center_id']:$repairing['center_id'];
                    $date_from = (isset($values['date_from']))?$values['date_from']:$repairing['date_from'];
                    $date_to = (isset($values['date_to']))?$values['date_to']:$repairing['date_to'];
                    $rental_units_ids = (isset($values['rental_units_ids']))?$values['rental_units_ids']:$repairing['rental_units_ids'];

                    foreach($rental_units_ids as $rental_unit_id) {
                        $result = Consumption::search([
                            [
                                ['date', '>=', $date_from],
                                ['date', '<=', $date_to] ,
                                ['rental_unit_id' , '=' , $rental_unit_id],
                                ['center_id', '=', $center_id],
                                ['repairing_id', '<>', $repairing['id'] ]

                            ],
                            [
                                ['date', '>=', $date_from],
                                ['date', '<=', $date_to] ,
                                ['rental_unit_id' , '=' , $rental_unit_id],
                                ['center_id', '=', $center_id],
                                ['booking_id', '>', 0 ]
                            ]
                        ])
                            ->get(true);

                        if(count($result)) {
                            return ['id' => ['non_editable' => 'The change is not allowed because there is another consumption.']];
                        }
                    }
                }
            }
        }

        return parent::canupdate($om, $ids, $values, $lang);
    }
}

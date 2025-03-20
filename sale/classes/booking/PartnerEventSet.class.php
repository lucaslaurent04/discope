<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use equal\orm\Model;

class PartnerEventSet extends Model {

    public static function getDescription(): string {
        return "Custom planning activity to add notes on partners activities or partners availabilities.";
    }

    public static function getColumns(): array {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the activity.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Complete description of the event."
            ],

            'partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'description'       => "Employee assigned to the supervision of the activity.",
                'required'          => true
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Starting date of the set.",
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "Ending date of the set.",
                'required'          => true
            ],

            'partner_events_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\PartnerEvent',
                'foreign_field'     => 'partner_event_set_id',
                'description'       => 'Detailed consumptions of the booking.'
            ]

        ];
    }
}

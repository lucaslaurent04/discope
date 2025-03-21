<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use equal\orm\Model;

class PartnerEvent extends Model {

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
                'usage'             => 'text/plain',
                'description'       => "Complete description of the event."
            ],

            'partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'description'       => "Partner the event is related to.",
                'required'          => true,
                'readonly'          => true
            ],

            'partner_event_set_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\PartnerEventSet',
                'description'       => "Set this event is part of.",
                'readonly'          => true
            ],

            'event_date' => [
                'type'              => 'date',
                'description'       => "Specific date on which the service is delivered.",
                'required'          => true
            ],

            'time_slot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\TimeSlot',
                'description'       => "Specific day time slot on which the service is delivered.",
                'required'          => true,
                'domain'            => ['code', 'in', ['AM', 'PM', 'EV']]
            ]

        ];
    }
}

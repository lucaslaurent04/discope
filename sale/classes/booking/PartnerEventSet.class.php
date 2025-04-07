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
                'usage'             => 'text/plain',
                'description'       => "Complete description of the event."
            ],

            'partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'description'       => "Employee assigned to the supervision of the activity.",
                'required'          => true,
                'readonly'          => true
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Starting date of the set.",
                'required'          => true,
                'onupdate'          => 'onupdateDateFrom'
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "Ending date of the set.",
                'required'          => true,
                'onupdate'          => 'onupdateDateTo'
            ],

            'partner_events_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\PartnerEvent',
                'foreign_field'     => 'partner_event_set_id',
                'description'       => "Events generated from this set."
            ]

        ];
    }

    public static function getActions(): array {
        return [
            'regenerate-partner-events' => [
                'description'   => "Update linked PartnerEvents.",
                'policies'      => [],
                'function'      => 'doRegeneratePartnerEvent'
            ]
        ];
    }

    public static function doRegeneratePartnerEvent($self) {
        $self->read(['name', 'description', 'date_from', 'date_to', 'partner_id', 'partner_events_ids' => ['event_date', 'time_slot_id']]);
        foreach($self as $id => $partner_event_set) {
            // Remove linked partner events
            $partner_events_ids = [];
            foreach($partner_event_set['partner_events_ids'] as $partner_event) {
                $partner_events_ids[] = $partner_event['id'];
            }
            $old_partner_events = [];
            if(!empty($partner_events_ids)) {
                $old_partner_events = PartnerEvent::ids($partner_events_ids)
                    ->read(['name', 'description', 'event_date', 'time_slot_id'])
                    ->get();

                PartnerEvent::ids($partner_events_ids)->delete(true);
            }

            // Generate new partner events
            $time_slots_ids = TimeSlot::search(['code', 'in', ['AM', 'PM', 'EV']])->ids();
            sort($time_slots_ids);

            $date = date('Ymd', $partner_event_set['date_from']);
            $date_to = date('Ymd', $partner_event_set['date_to']);
            while($date <= $date_to) {
                foreach($time_slots_ids as $time_slots_id) {
                    $old_partner_event = null;
                    foreach($old_partner_events as $event) {
                        if(date('Ymd', $event['event_date']) === $date && $event['time_slot_id'] === $time_slots_id) {
                            $old_partner_event = $event;
                            break;
                        }
                    }

                    PartnerEvent::create([
                        'name'                  => $old_partner_event['name'] ?? $partner_event_set['name'],
                        'description'           => $old_partner_event['description'] ?? $partner_event_set['description'],
                        'partner_id'            => $partner_event_set['partner_id'],
                        'partner_event_set_id'  => $id,
                        'event_date'            => strtotime($date),
                        'time_slot_id'          => $time_slots_id,
                    ]);
                }

                $date = date('Ymd', strtotime($date.' +1day'));
            }
        }
    }

    public static function onupdateDateFrom($self) {
        $self->do('regenerate-partner-events');
    }

    public static function onupdateDateTo($self) {
        $self->do('regenerate-partner-events');
    }
}

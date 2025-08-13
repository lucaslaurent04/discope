<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use equal\orm\Field;
use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\TimeSlot;
use sale\catalog\Product;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for : returns a collection of Reports according to extra parameters.',
    'params'        => [
        /**
         * Filters
         */
        'date_from' => [
            'type'              => 'date',
            'description'       => "Start of the time interval of the desired plannings.",
            'default'           => fn() => time()
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "End of the time interval of the desired plannings.",
            'default'           => fn() => strtotime('+1 month')
        ],

        /**
         * Virtual model columns
         */
        'id' => [
            'type'              => 'integer',
            'description'       => "Identifier of the booking line."
        ],
        'product' => [
            'type'              => 'string',
            'description'       => "The transport product concerned."
        ],
        'date' => [
            'type'              => 'date',
            'description'       => "The date of the transport has to be taken care of.",
        ],
        'time_slot' => [
            'type'              => 'string',
            'description'       => "The date of the transport has to be taken care of.",
        ],
        'booking' => [
            'type'              => 'string',
            'description'       => "The booking the transport product is needed for."
        ],
        'group' => [
            'type'              => 'string',
            'description'       => "The booking group the transport product is needed for."
        ],
        'nb_pers' => [
            'type'              => 'integer',
            'description'       => "The quantity of people in the group."
        ],
        'nb_children' => [
            'type'              => 'integer',
            'description'       => "The quantity of children in the group."
        ],
        'customer' => [
            'type'              => 'string',
            'description'       => "The booking customer the transport product is needed for."
        ],
        'booking_activity' => [
            'type'              => 'string',
            'description'       => "The activity the transport product is needed for."
        ],
        'description' => [
            'type'              => 'string',
            'description'       => "The description of the booking line."
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'adapt']
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\data\adapt\DataAdapterProvider $dap
 */
['context' => $context, 'adapt' => $dap] = $providers;

// SKU for the holiday round-trip transportation product (covers transport from home to holiday destination and back)
$setting_holiday_transfer_sku = Setting::get_value('sale', 'organization', 'sku.transport', 'not_found');

$time_slots = TimeSlot::search(['code', 'in', ['AM', 'PM']])
    ->read(['name', 'code', 'order'])
    ->get();

$map_codes_timeslots = [];
foreach($time_slots as $time_slot) {
    $map_codes_timeslots[$time_slot['code']] = $time_slot;
}

$booking_ids = Booking::search([
    ['has_transport', '=', true],
    ['date_from', '>=', $params['date_from']],
    ['date_from', '<=', $params['date_to']]
])
    ->ids();

$json_adapter = $dap->get('json');

$transport_products_ids = Product::search(['is_transport', '=', true])->ids();

$lines = BookingLine::search([
    ['booking_id', 'in', $booking_ids],
    ['product_id', 'in', $transport_products_ids]
])
    ->read([
        'description',
        'service_date',
        'time_slot_id'          => ['name', 'order'],
        'booking_activity_id'   => ['name', 'activity_date', 'time_slot_id' => ['name', 'order']],
        'product_id'            => ['name', 'sku'],
        'booking_id'            => ['name', 'customer_id' => ['name'], 'booking_lines_groups_ids' => ['name', 'date_from', 'date_to', 'group_type']],
        'booking_line_group_id' => ['name', 'nb_pers', 'nb_children']
    ])
    ->get();

$result = [];
foreach($lines as $id => $line) {
    if(isset($line['product_id']['id']) && $line['product_id']['sku'] === $setting_holiday_transfer_sku && is_null($line['booking_activity_id'])) {
        foreach($line['booking_id']['booking_lines_groups_ids'] as $group) {
            if($group['group_type'] !== 'sojourn') {
                continue;
            }

            $result[] = [
                'id'                => $id,
                'product'           => $line['product_id']['name'],
                'date'              => $json_adapter->adaptOut($group['date_from'], Field::MAP_TYPE_USAGE['date']),
                'time_slot'         => $map_codes_timeslots['AM']['name'] ?? '',
                'time_slot_order'   => $map_codes_timeslots['AM']['order'] ?? -1,
                'booking'           => $line['booking_id']['name'],
                'group'             => $group['name'],
                'nb_pers'           => $line['booking_line_group_id']['nb_pers'],
                'nb_children'       => $line['booking_line_group_id']['nb_children'],
                'customer'          => $line['booking_id']['customer_id']['name'],
                'booking_activity'  => '',
                'description'       => $line['description']
            ];

            $result[] = [
                'id'                => $id,
                'product'           => $line['product_id']['name'],
                'date'              => $json_adapter->adaptOut($group['date_to'], Field::MAP_TYPE_USAGE['date']),
                'time_slot'         => $map_codes_timeslots['PM']['name'] ?? '',
                'time_slot_order'   => $map_codes_timeslots['PM']['order'] ?? -1,
                'booking'           => $line['booking_id']['name'],
                'group'             => $group['name'],
                'nb_pers'           => $line['booking_line_group_id']['nb_pers'],
                'nb_children'       => $line['booking_line_group_id']['nb_children'],
                'customer'          => $line['booking_id']['customer_id']['name'],
                'booking_activity'  => '',
                'description'       => $line['description']
            ];
        }
    }
    else {
        $date = $line['booking_activity_id']['activity_date'] ?? $line['service_date'];
        if(!is_null($date)) {
            $date = $json_adapter->adaptOut($date, Field::MAP_TYPE_USAGE['date']);
        }

        $result[] = [
            'id'                => $id,
            'product'           => $line['product_id']['name'],
            'date'              => $date,
            'time_slot'         => $line['booking_activity_id']['time_slot_id']['name'] ?? ($line['time_slot_id']['name'] ?? ''),
            'time_slot_order'   => $line['booking_activity_id']['time_slot_id']['order'] ?? ($line['time_slot_id']['order'] ?? -1),
            'booking'           => $line['booking_id']['name'],
            'group'             => $line['booking_line_group_id']['name'],
            'nb_pers'           => $line['booking_line_group_id']['nb_pers'],
            'nb_children'       => $line['booking_line_group_id']['nb_children'],
            'customer'          => $line['booking_id']['customer_id']['name'],
            'booking_activity'  => $line['booking_activity_id']['name'],
            'description'       => $line['description']
        ];
    }
}

usort($result, function ($a, $b) {
    // first sort on date
    if($a['date'] !== $b['date']) {
        return strcmp($a['date'], $b['date']);
    }

    // second sort on time slot
    if($a['time_slot_order'] !== $b['time_slot_order']) {
        return $a['time_slot_order'] <=> $b['time_slot_order'];
    }

    // last sort on booking activity name, if any
    return strcmp($a['booking_activity_id']['name'] ?? '', $b['booking_activity_id']['name'] ?? '');
});

$context->httpResponse()
        ->header('X-Total-Count', count($result))
        ->body($result)
        ->send();

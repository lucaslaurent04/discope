<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\PartnerPlanningSummary;

[$params, $provider] = eQual::announce([
    'description'   => "Returns the planning summary activities table data in CSV format.",
    'params'        => [

        'id' => [
            'type'          => 'integer',
            'description'   => "Identifier of targeted planning summary.",
            'required'      => true
        ]

    ],
    'access'        => [
        'visibility'            => 'protected'
    ],
    'response'      => [
        'content-type'          => 'text/csv',
        'content-disposition'   => 'inline; filename="camp-export.csv"',
        'charset'               => 'utf-8',
        'accept-origin'         => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $provider;

$planning_summary = PartnerPlanningSummary::id($params['id'])
    ->read(['activities_table_data'])
    ->first();

if(is_null($planning_summary)) {
    throw new Exception("unknown_partner_planning_summary", EQ_ERROR_UNKNOWN_OBJECT);
}

$activities_table_data = json_decode($planning_summary['activities_table_data'], true);

if(is_null($activities_table_data)) {
    throw new Exception("empty_planning_table_data", EQ_ERROR_INVALID_CONFIG);
}

$data = [
    [
        'Date',
        'Client',
        'Informations groupe',
        'Status réservation',
        'Moment',
        'Activité',
        'Prix'
    ]
];
foreach($activities_table_data as $row) {
    $data[] = [
        $row['activity_date'],
        $row['customer_name'],
        'G'.$row['group_num'].' : '.implode(', ', $row['age_range_assignments']),
        $row['booking_status'],
        $row['time_slot_name'].' ('.$row['activity_from'].' - '.$row['activity_to'].')',
        $row['activity_name'],
        $row['price']
    ];
}

$tmp_file = tempnam(sys_get_temp_dir(), 'csv');

$fp = fopen($tmp_file, 'w');
foreach($data as $row) {
    fputcsv($fp, $row, ';');
}
fclose($fp);

$output = file_get_contents($tmp_file);

$context->httpResponse()
        ->body($output)
        ->send();

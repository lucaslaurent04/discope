<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Field;
use sale\camp\Camp;

[$params, $providers] = eQual::announce([
    'description'   => "Manage children that are attending the current camps.",
    'params'        => [
        'only_weekend' => [
            'type'              => 'boolean',
            'description'       => "Show only the children present during the weekend.",
            'default'           => false
        ],
        'only_birthday' => [
            'type'              => 'boolean',
            'description'       => "Show only the children that have a birthday during the camp.",
            'default'           => false
        ],
        'date_from' => [
            'type'              => 'date',
            'description'       => "Date interval lower limit (defaults to first day of the current week).",
            'default'           => fn() => strtotime('Monday this week')
        ],
        'date_to' => [
            'type'              => 'date',
            'description'       => "Date interval upper limit (defaults to last day of the current week).",
            'default'           => fn() => strtotime('Sunday this week')
        ],

        /* parameters used as properties of virtual entity */
        'camp' => [
            'type'              => 'string',
            'description'       => "The camp that the child is attending."
        ],
        'child_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\camp\Child',
            'description'       => "The child attending the camp."
        ],
        'gender' => [
            'type'              => 'string',
            'selection'         => [
                'M',
                'F'
            ],
            'description'       => "Gender of the gender."
        ],
        'birthday' => [
            'type'              => 'date',
            'description'       => "Birthday of the child if it happens during the camp."
        ],
        'is_ase' => [
            'type'              => 'boolean',
            'description'       => "Is the child a foster child."
        ],
        'weekend_extra' => [
            'type'              => 'string',
            'description'       => "Does the child stay during the weekend."
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
 * @var \equal\php\Context                  $context
 * @var \equal\data\adapt\AdapterProvider   $adapter_provider
 */
['context' => $context, 'adapt' => $adapter_provider] = $providers;

$json_adapter = $adapter_provider->get('json');

$camps = Camp::search(
    [
        ['date_from', '>=', $params['date_from']],
        ['date_from', '<=', $params['date_to']]
    ]
)
    ->read([
        'short_name',
        'date_from',
        'date_to',
        'enrollments_ids' => [
            'status',
            'weekend_extra',
            'child_id' => [
                'name',
                'gender',
                'birthdate',
                'is_ase'
            ],
            'enrollment_lines_ids' => [
                'product_id'
            ]
        ]
    ])
    ->get();

$result = [];

foreach($camps as $camp) {
    foreach($camp['enrollments_ids'] as $enrollment) {
        if(!in_array($enrollment['status'], ['confirmed', 'validated'])) {
            continue;
        }

        $birthday = null;
        $month_day = date('m-d', $enrollment['child_id']['birthdate']);
        $year_birthday = DateTime::createFromFormat('Y-m-d', date('Y').'-'.$month_day);
        if($year_birthday->getTimestamp() >= $camp['date_from'] && $year_birthday->getTimestamp() <= $camp['date_to']) {
            $birthday = $year_birthday->getTimestamp();
        }

        $result[] = [
            'camp'          => $camp['short_name'],
            'child_id'      => ['id' => $enrollment['child_id']['id'], 'name' => $enrollment['child_id']['name']],
            'gender'        => $enrollment['child_id']['gender'],
            'birthday'      => !is_null($birthday) ? $json_adapter->adaptOut($birthday, Field::MAP_TYPE_USAGE['date']) : null,
            'is_ase'     => $enrollment['child_id']['is_ase'],
            'weekend_extra' => $enrollment['weekend_extra']
        ];
    }
}

if($params['only_weekend']) {
    $result = array_filter(
        $result,
        fn($item) => in_array($item['weekend_extra'], ['full', 'saturday-morning'])
    );
}

if($params['only_birthday']) {
    $result = array_filter(
        $result,
        fn($item) => !empty($item['birthday'])
    );
}

$context->httpResponse()
        ->body(array_values($result))
        ->send();

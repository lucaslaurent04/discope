<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use identity\Partner;
use sale\booking\BookingActivity;

[$params, $providers] = eQual::announce([
    'description'   => "Generate the planning mail content of the given partner, between the given dates.",
    'params'        => [

        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Partner',
            'description'       => "The partner concerned by the planning summary.",
            'required'          => true,
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => "Date (included) at which the partner planning starts.",
            'required'          => true
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "Date (included) at which the partner planning ends.",
            'required'          => true
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$template = Template::search([
    ['code', '=', 'partner_reminder'],
    ['type', '=', 'planning']
])
    ->read(['parts_ids' => ['name', 'value']])
    ->first(true);

if(is_null($template)) {
    throw new Exception("missing_template", EQ_ERROR_UNKNOWN_OBJECT);
}

$activities = BookingActivity::search(
    [
        ['providers_ids', 'in', $params['id']],
        ['activity_date', '>=', $params['date_from']],
        ['activity_date', '<=', $params['date_to']]
    ],
    ['sort'  => ['activity_date' => 'asc', 'group_num' => 'asc']]
)
    ->read([
        'name',
        'activity_date',
        'group_num',
        'employee_id',
        'providers_ids',
        'time_slot_id' => ['name'],
        'booking_id' => [
            'name',
            'status',
            'customer_id' => ['name']
        ],
        'booking_line_group_id' => [
            'date_from',
            'date_to',
            'age_range_assignments_ids' => ['qty', 'age_from', 'age_to']
        ]
    ])
    ->get();

$partner = Partner::id($params['id'])
    ->read(['email', 'relationship'])
    ->get();

$map_status = [
    'quote'             => 'Devis',
    'option'            => 'Option',
    'confirmed'         => 'Confirmée',
    'validated'         => 'Validée',
    'checkedin'         => 'En cours',
    'checkedout'        => 'Terminée',
    'invoiced'          => 'Facturée',
    'debit_balance'     => 'Solde débiteur',
    'credit_balance'    => 'Solde créditeur',
    'balanced'          => 'Soldée'
];

$date_from = $date_to = null;
$planned_activities = [];
foreach($activities as $activity) {
    if(is_null($date_from) || $date_from > $activity['booking_line_group_id']['date_from']) {
        $date_from = $activity['booking_line_group_id']['date_from'];
    }
    if(is_null($date_to) || $date_to < $activity['booking_line_group_id']['date_to']) {
        $date_to = $activity['booking_line_group_id']['date_to'];
    }

    $age_range_assignments = [];
    foreach($activity['booking_line_group_id']['age_range_assignments_ids'] as $age_range_assignment) {
        $age_range_assignments[] = $age_range_assignment['qty'].' ('.$age_range_assignment['age_from'].' à '.$age_range_assignment['age_to'].' ans)';
    }

    $planned_activities[] = [
        'activity_date'         => date('d/m/y', $activity['activity_date']),
        'customer_name'         => $activity['booking_id']['customer_id']['name'],
        'group_num'             => $activity['group_num'],
        'age_range_assignments' => $age_range_assignments,
        'booking_status'        => $map_status[$activity['booking_id']['status']],
        'time_slot_name'        => $activity['time_slot_id']['name'],
        'activity_name'         => explode(' (', $activity['name'])[0]
    ];
}

$activities_table =
    '<table style="width: 100%; border-collapse: collapse; margin-top: 20px; border: 1px solid #ddd;">
            <tr>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Date</th>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Client</th>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Informations groupe</th>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Status réservation</th>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Moment</th>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Activité</th>
            </tr>'
    .implode(
        array_map(
            function($planned_activity) {
                return
                    '<tr>'.
                    '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">'.$planned_activity['activity_date'].'</td>'.
                    '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">'.$planned_activity['customer_name'].'</td>'.
                    '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">G'.$planned_activity['group_num'].'<br />'.implode('<br />', $planned_activity['age_range_assignments']).'</td>'.
                    '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">'.$planned_activity['booking_status'].'</td>'.
                    '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">'.$planned_activity['time_slot_name'].'</td>'.
                    '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">'.$planned_activity['activity_name'].'</td>'.
                    '</tr>';
            },
            $planned_activities
        )
    )
    .'</table>';

$mail_content = '';
foreach($template['parts_ids'] as $part) {
    if($part['name'] === 'body') {
        $mail_content = $part['value'];
        $data = [
            'activities_table' => $activities_table
        ];
        foreach($data as $key => $val) {
            if($key === 'activities_table') {
                // try to remove <p></p> tags if activities table, cause not needed
                $mail_content = str_replace('<p>{'.$key.'}</p>', $val, $mail_content);
            }
            $mail_content = str_replace('{'.$key.'}', $val, $mail_content);
        }
        break;
    }
}

$context->httpResponse()
        ->body($mail_content)
        ->status(200)
        ->send();

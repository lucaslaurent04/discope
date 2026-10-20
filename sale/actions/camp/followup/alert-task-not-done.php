<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2025
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use core\alert\MessageModel;
use sale\camp\Enrollment;
use sale\camp\followup\Task;

[$params, $providers] = eQual::announce([
    'description'   => "Alerts followup tasks that should have be done by today.",
    'params'        => [

        'message_model' => [
            'type'              => 'string',
            'description'       => "The name of the message model to use for the alert.",
            'default'           => 'sale.enrollment.followup.task.reminder'
        ],

        'severity' => [
            'type'              => 'string',
            'description'       => "Severity of the created alerts.",
            'selection'         => [
                'notice',
                'warning',
                'important',
                'urgent'
            ],
            'default'           => 'important'
        ]

    ],
    'access'        => [
        'visibility'        => 'protected'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'dispatch']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
['context' => $context, 'dispatch' => $dispatch] = $providers;

$domain = [
    ['is_done', '=', false],
    ['deadline_date', '<=', time()],
    ['object_class', '=', Enrollment::getType()]
];

$tasks = Task::search($domain)
    ->read(['enrollment_id' => ['center_office_id']])
    ->get();

if(!empty($tasks)) {
    $message_model = MessageModel::search([
        ['name', '=', $params['message_model']]
    ])
        ->read(['name'])
        ->first();

    if(is_null($message_model)) {
        $message_model = MessageModel::create([
            'name'          => $params['message_model'],
            'label'         => "Enrollment task deadline has expired",
            'description'   => "An enrollment task was not handled within the required timeframe."
        ])
            ->read(['name'])
            ->first();
    }

    foreach($tasks as $id => $task) {
        $dispatch_params = [
            'id'            => $id,
            'message_model' => $message_model['name'],
            'severity'      => $params['severity']
        ];

        $dispatch->dispatch(
            $message_model['name'],
            Task::getType(),
            $id,
            $params['severity'],
            'sale_camp_followup_Task_check-done',
            $dispatch_params,
            [],
            null,
            $task['enrollment_id']['center_office_id']
        );
    }
}

$context->httpResponse()
        ->status(200)
        ->send();

<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use documents\export\ExportingTask;
use documents\export\ExportingTaskLine;

[$params, $providers] = eQual::announce([
    'description'   => 'Handle next task in ExportingTask queue.',
    'params'        => [
        'id' =>  [
            'description'       => "Optional identifier of a specific exporting task to run.",
            'type'              => 'many2one',
            'foreign_object'    => 'documents\export\ExportingTask'
        ]
    ],
    // #todo - mark as private (only from scheduler)
    'access'        => [
        'visibility'    => 'protected'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8'
    ],
    'providers'     => ['context', 'orm', 'dispatch']
]);

/**
 * @var \equal\php\Context          $context
 * @var \equal\orm\ObjectManager    $orm
 * @var \equal\dispatch\Dispatcher  $dispatch
 */
['context' => $context, 'orm' => $orm, 'dispatch' => $dispatch] = $providers;

$now = time();

// check if a task is already active, if so, do nothing and wait for the next cycle
$runningExportingTask = ExportingTask::search(['status', '=', 'running'])->first();

if($runningExportingTask) {
    // exit wit no error code
    throw new Exception('task_in_progress', 0);
}

// #todo - handle optional param `id`


// take the first one by creation date
$exportingTask = ExportingTask::search([
            ['status', '=', 'idle'],
        ],
        ['limit' => 1, 'sort' => ['created' => 'asc']]
    )
    ->update(['status' => 'running'])
    ->read(['is_temp', 'exporting_task_lines_ids' => ['controller', 'params']])
    ->first();

// no task in queue
if(!$exportingTask) {
    // exit wit no error code
    throw new Exception('no_task_awaiting', 0);
}


$has_failing_line = false;

foreach($exportingTask['exporting_task_lines_ids'] as $exporting_task_line_id => $exportingTaskLine) {

    [$status, $log] = ['ready', ''];

    ExportingTaskLine::id($exporting_task_line_id)
        ->update([
            'pid'       => getmypid(),
            'status'    => 'running'
        ]);

    try {
        $body = json_decode($exportingTaskLine['params'], true);
        $body['is_temp'] = $exportingTask['is_temp'];
        // run the task - expected to generate a PDF document
        $data = \eQual::run('do', $exportingTaskLine['controller'], $body);
        $status = 'success';
        $log = (string) json_encode($data, JSON_PRETTY_PRINT);
        if(!$data['document_id'] ?? null) {
            throw new Exception('missing_document_in_response', EQ_ERROR_UNKNOWN);
        }
        // assign generated document to Export
        // #memo - documents linked to ExportingTaskLine are not visible in the EDMS, and are meant to be removed when at ExportingTask expiry
        ExportingTaskLine::id($exporting_task_line_id)
            ->update([
                'document_id'       => !$exportingTask['is_temp'] ? $data['document_id'] : null,
                'document_temp_id'  => $exportingTask['is_temp'] ? $data['document_id'] : null,
                'status'            => 'ready'
            ]);
    }
    catch(\Exception $e) {
        // error occurred during execution
        trigger_error("PHP::Error while running scheduled job [{$exportingTaskLine['id']}]: ".$e->getMessage(), EQ_REPORT_ERROR);
        $has_failing_line = true;
        if($e->getCode() !== 0) {
            $status = 'error';
            ExportingTaskLine::id($exporting_task_line_id)
                ->update([
                    'status'    => 'failing'
                ]);
        }
        $msg = $e->getMessage();
        $data = @unserialize($msg);
        if(is_array($data)) {
            $data = json_encode($data, JSON_PRETTY_PRINT);
        }
        $log = ($data) ? $data : $msg;
    }

    // create a new TaskLog holding result
    $orm->create('documents\export\ExportingTaskLog', [
            'task_id'       => $exportingTask['id'],
            'task_line_id'  => $exporting_task_line_id,
            'status'        => $status,
            'log'           => "<pre>{$log}</pre>"
        ]);
}


// at least one line in failing
if($has_failing_line) {
    ExportingTask::id($exportingTask['id'])
        ->update(['status' => 'failing']);

    // #todo - handle alert failing
    // $dispatch->dispatch('documents.export.export_failing', 'documents\export\ExportingTask', $exportingTask['id'], 'important', null, [], [], null, $exportingTask['condo_id']);
}
else {
    ExportingTask::id($exportingTask['id'])
        ->update(['status' => 'ready']);

    // #todo - handle alert success
    // $dispatch->dispatch('documents.export.export_ready', 'documents\export\ExportingTask', $exportingTask['id'], 'notice', null, [], [], null, $exportingTask['condo_id']);
}

$context
    ->httpResponse()
    ->status(204)
    ->send();

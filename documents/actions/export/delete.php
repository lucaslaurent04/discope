<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use discope\setting\Setting;
use documents\Export;

[$params, $providers] = eQual::announce([
    'description'   => "Deletes the export and set linked objects as non exported.",
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Export',
            'description'       => "Identifier of the targeted export.",
            'required'          => true
        ],
        'confirm' =>  [
            'type'          => 'boolean',
            'description'   => "Confirm the deletion of the export.",
            'required'      => true
        ]
    ],
    'access'        => [
        'visibility'    => 'public'
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

if(!isset($params['confirm']) || !$params['confirm']) {
    throw new Exception("missing_confirmation", EQ_ERROR_INVALID_PARAM);
}

$export = Export::id($params['id'])
    ->read(['center_office_id', 'export_type'])
    ->first(true);

if(!$export) {
    throw new Exception("export_unknown", EQ_ERROR_UNKNOWN_OBJECT);
}

$enforce_sequential_deletion = Setting::get_value('documents', 'export', 'enforce_sequential_deletion', true);
if($enforce_sequential_deletion) {
    $latest_export = Export::search(
        [
            ['center_office_id', '=', $export['center_office_id']],
            ['export_type', '=', $export['export_type']]
        ],
        ['sort' => ['created' => 'desc']]
    )
        ->read(['id'])
        ->first();

    if($latest_export['id'] !== $export['id']) {
        throw new Exception("deletion_not_allowed", EQ_ERROR_NOT_ALLOWED);
    }
}

Export::id($params['id'])
    ->do('reverse')
    ->delete();

$context
    ->httpResponse()
    ->send();

<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use documents\Export;

[$params, $providers] = eQual::announce([
    'description'   => "Return raw data (with original MIME) of an export document identified by its ID.",
    'params'        => [
        'id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'documents\Export',
            'description'       => "Management Group to which the center belongs.",
            'required'          => true
        ],
    ],
    'access'        => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'accept-origin'     => '*',
        'content-type'      => 'application/zip'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$export = Export::id($params['id'])
    ->read(['name', 'data', 'type'])
    ->first(true);

if(!$export) {
    throw new Exception("document_unknown", QN_ERROR_UNKNOWN_OBJECT);
}

Export::id($params['id'])->update(['is_exported' => true]);

$context->httpResponse()
        ->header('Content-Type', $export['type'])
        ->header('Content-Disposition', 'attachment; filename="'.$export['name'].'.zip"')
        ->body($export['data'], true)
        ->send();

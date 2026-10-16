<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use documents\Document;
use documents\DocumentTemp;

[$params, $providers] = eQual::announce([
    'description'   => "Return raw data (with original MIME) of a document identified by given hash.",
    'params'        => [
        'hash' =>  [
            'type'          => 'string',
            'description'   => "Unique identifier of the resource.",
            'required'      => true
        ],
        'disposition' => [
            'type'          => 'string',
            'description'   => "Inline document or attachment document.",
            'selection'     => [
                'inline',
                'attachment'
            ],
            'default'       => 'inline'
        ],
        'is_temp' => [
            'description'   => "Is the document temporary?",
            'type'          => 'boolean',
            'default'       => false
        ]
    ],
    'access'        => [
        'visibility'    => 'public'
    ],
    'response'      => [
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'auth']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'auth' => $auth] = $providers;

$user_id = $auth->userId();

// documents can be public : switch to root user to bypass any permission check
$auth->su();

/** @var Document $document_class */
$document_class = Document::getType();
if($params['is_temp']) {
    $document_class = DocumentTemp::getType();
}

// search for documents matching given hash code (should be only one match)
$collection = $document_class::search(['hash', '=', $params['hash']]);
$document = $collection->read(['public'])->first();

if(!$document) {
    throw new Exception("document_unknown", EQ_ERROR_UNKNOWN_OBJECT);
}

// if document is not public, switch back to original user: regular permission checks will apply
if(!$document['public']) {
    $auth->su($user_id);
}

$document = $collection->read(['name', 'data', 'type'])->first();

$context
    ->httpResponse()
    ->header('Content-Disposition', $params['disposition'].'; filename="'.$document['name'].'"')
    ->header('Content-Type', $document['type'])
    ->body($document['data'], true)
    ->send();

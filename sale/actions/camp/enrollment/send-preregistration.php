<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\TemplateAttachment;
use core\Mail;
use documents\Document;
use equal\email\Email;
use equal\email\EmailAttachment;
use identity\Center;
use sale\camp\Child;
use sale\camp\Enrollment;
use sale\camp\EnrollmentMail;

[$params, $providers] = eQual::announce([
    'description'   => "Send an instant enrollment pre-registration email with the given details and 'invoice'.",
    'params'        => [

        'children_ids' => [
            'type'          => 'array',
            'description'   => "Identifier of the children we want to send the email for.",
            'help'          => "They should have the same main guardian.",
            'required'      => true
        ],

        'center_id' => [
            'type'          => 'integer',
            'description'   => "The center for which we want send the children's pre-registration confirmation.",
            'required'      => true
        ],

        'title' =>  [
            'description'   => 'Title of the message.',
            'type'          => 'string',
            'required'      => true
        ],

        'message' => [
            'description'   => 'Body of the message.',
            'type'          => 'string',
            'usage'         => 'text/html',
            'required'      => true
        ],

        'sender_email' => [
            'description'   => 'Email address FROM.',
            'type'          => 'string',
            'usage'         => 'email',
            'required'      => true
        ],

        'recipient_email' => [
            'description'   => 'TO email address.',
            'type'          => 'string',
            'usage'         => 'email',
            'required'      => true
        ],

        'recipients_emails' => [
            'description'   => 'CC email addresses.',
            'type'          => 'array',
            // #todo - wait for support for "array of" usage
            // 'usage'         => 'email'
        ],

        'attachments_ids' => [
            'description'   => 'List of identifiers of attachments to join.',
            'type'          => 'array',
            'default'       => []
        ],

        'documents_ids' => [
            'description'   => 'List of identifiers of documents to join.',
            'type'          => 'array',
            'default'       => []
        ],

        'lang' =>  [
            'description'   => 'Language to use for multilang contents.',
            'type'          => 'string',
            'usage'         => 'language/iso-639',
            'default'       => constant('DEFAULT_LANG')
        ]

    ],
    'constants'             => ['DEFAULT_LANG'],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['camp.default.user'],
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$center = Center::id($params['center_id'])
    ->read(['center_office_id' => ['email_bcc']])
    ->first();

if(is_null($center)) {
    throw new Exception("unknown_center", EQ_ERROR_UNKNOWN_OBJECT);
}

if(empty($params['children_ids'])) {
    throw new Exception("empty_children_ids", EQ_ERROR_INVALID_PARAM);
}

$children = Child::ids($params['children_ids'])
    ->read(['main_guardian_id', 'enrollments_ids' => ['camp_id' => ['center_id', 'date_from']]])
    ->get();

if(count($params['children_ids']) !== count($children)) {
    throw new Exception("unknown_children", EQ_ERROR_UNKNOWN_OBJECT);
}

$enrollments_ids = [];
foreach($children as $child) {
    foreach($child['enrollments_ids'] as $enrollment) {
        if(
            $enrollment['camp_id']['center_id'] === $center['id']
            && date('Y', $enrollment['camp_id']['date_from']) === date('Y')
        ) {
            $enrollments_ids[] = $enrollment['id'];
        }
    }
}

if(empty($enrollments_ids)) {
    throw new Exception("no_enrollments", EQ_ERROR_INVALID_PARAM);
}

$main_guardian_id = null;
foreach($children as $child) {
    if(is_null($main_guardian_id)) {
        $main_guardian_id = $child['main_guardian_id'];
    }

    if($child['main_guardian_id'] !== $main_guardian_id) {
        throw new Exception("not_same_main_guardian", EQ_ERROR_INVALID_PARAM);
    }
}

$attachment = eQual::run('get', 'sale_camp_enrollment_preregistration_print-invoice', [
    'ids'       => $params['children_ids'],
    'center_id' => $center['id'],
    'lang'      => $params['lang']
]);

// generate signature
$signature = '';
try {
    $data = eQual::run('get', 'identity_center-signature', [
        'center_id'     => $center['id'],
        'lang'          => $params['lang']
    ]);
    $signature = (isset($data['signature']))?$data['signature']:'';
}
catch(Exception $e) {
    // ignore errors
}

$params['message'] .= $signature;

/** @var EmailAttachment[] */
$attachments = [];

$attachments[] = new EmailAttachment('confirmation_pré-inscription.pdf', (string) $attachment, 'application/pdf');

// add attachments whose ids have been received as param ($params['attachments_ids'])
if(count($params['attachments_ids'])) {
    $params['attachments_ids'] = array_unique($params['attachments_ids']);
    $template_attachments = TemplateAttachment::ids($params['attachments_ids'])->read(['name', 'document_id'])->get();
    foreach($template_attachments as $tid => $tdata) {
        $document = Document::id($tdata['document_id'])->read(['name', 'data', 'type'])->first(true);
        if($document) {
            $attachments[] = new EmailAttachment($document['name'], $document['data'], $document['type']);
        }
    }
}

if(count($params['documents_ids'])) {
    foreach($params['documents_ids'] as $oid) {
        $document = Document::id($oid)->read(['name', 'data', 'type'])->first(true);
        if($document) {
            $attachments[] = new EmailAttachment($document['name'], $document['data'], $document['type']);
        }
    }
}

// create message
$message = new Email();
$message->setTo($params['recipient_email'])
    ->setReplyTo($params['sender_email'])
    ->setSubject($params['title'])
    ->setContentType("text/html")
    ->setBody($params['message']);

$bcc = $camp['center_id']['center_office_id']['email_bcc'] ?? '';

if(strlen($bcc)) {
    $message->addBcc($bcc);
}

if(isset($params['recipients_emails'])) {
    $recipients_emails = array_diff($params['recipients_emails'], (array) $params['recipient_email']);
    foreach($recipients_emails as $address) {
        $message->addCc($address);
    }
}

// append attachments to message
foreach($attachments as $attachment) {
    $message->addAttachment($attachment);
}

// queue message
$mail_id = Mail::queue($message, 'sale\camp\Enrollment', $enrollments_ids[0]);

// create enrollment mail to link the email sent to multiple enrollments
EnrollmentMail::create([
    'mail_id'           => $mail_id,
    'mail_type'         => 'pre-registration',
    'enrollments_ids'   => $enrollments_ids
]);

Enrollment::ids($enrollments_ids)->update(['preregistration_sent' => true]);

$context->httpResponse()
        ->status(204)
        ->send();

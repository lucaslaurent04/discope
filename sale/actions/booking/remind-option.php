<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use core\Mail;
use discope\setting\Setting;
use equal\email\Email;
use sale\booking\Booking;

[$params, $providers] = eQual::announce([
    'description'   => "Send a reminder before an option reaches its expiry date.",
    'params'        => [
        'id' => [
            'type'          => 'integer',
            'description'   => 'Identifier of the targeted booking.',
            'required'      => true
        ],
        'lang' => [
            'type'          => 'string',
            'usage'         => 'language/iso-639',
            'description'   => 'Language to use for the email.',
            'default'       => constant('DEFAULT_LANG')
        ],
        'recipient_email' => [
            'type'          => 'string',
            'usage'         => 'email',
            'description'   => 'Recipient email address.',
            'required'      => true
        ],
        'expected_date_expiry' => [
            'type'          => 'integer',
            'description'   => 'Option expiry date known when the reminder was scheduled.',
            'required'      => true
        ]
    ],
    'constants'     => ['DEFAULT_LANG'],
    'access'        => [
        'visibility' => 'private'
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
['context' => $context] = $providers;

$booking = Booking::id($params['id'])
    ->read([
        'id',
        'name',
        'status',
        'is_noexpiry',
        'date_from',
        'date_to',
        'date_expiry',
        'center_id'        => ['id', 'name', 'template_category_id'],
        'center_office_id' => ['email', 'email_bcc']
    ])
    ->first(true);

$date_expiry = $booking ? strtotime(date('Y-m-d', $booking['date_expiry'])) : 0;
$remind_auto = Setting::get_value('sale', 'features', 'option.remind_auto', false);

if(
    !$booking
    || $booking['status'] !== 'option'
    || $booking['is_noexpiry']
    || !$remind_auto
    || $date_expiry !== $params['expected_date_expiry']
    || $date_expiry <= time()
) {
    $context->httpResponse()->status(204)->send();
    return;
}

$template = Template::search([
        ['category_id', '=', $booking['center_id']['template_category_id']],
        ['code', '=', 'reminder'],
        ['type', '=', 'option']
    ])
    ->read(['parts_ids' => ['name', 'value']], $params['lang'])
    ->first(true);

if(!$template) {
    trigger_error("APP::missing option reminder template for booking {$booking['id']}", QN_REPORT_WARNING);
    $context->httpResponse()->status(204)->send();
    return;
}

$values = [
    'booking'       => $booking['name'],
    'center'        => $booking['center_id']['name'],
    'date_from'     => date('d/m/Y', $booking['date_from']),
    'date_to'       => date('d/m/Y', $booking['date_to']),
    'date_expiry'   => date('d/m/Y', $date_expiry)
];

$subject = '';
$body = '';
foreach($template['parts_ids'] as $part) {
    if($part['name'] === 'subject') {
        $subject = strip_tags($part['value']);
    }
    elseif($part['name'] === 'body') {
        $body = $part['value'];
    }
}

foreach($values as $key => $value) {
    $subject = str_replace('{'.$key.'}', $value, $subject);
    $body = str_replace('{'.$key.'}', $value, $body);
}

try {
    $data = eQual::run('get', 'identity_center-signature', [
        'center_id' => $booking['center_id']['id'],
        'lang'      => $params['lang']
    ]);
    $body .= $data['signature'] ?? '';
}
catch(Exception $e) {
    // a missing signature must not prevent the reminder from being sent
}

$message = new Email();
$message->setTo($params['recipient_email'])
    ->setReplyTo($booking['center_office_id']['email'])
    ->setSubject($subject)
    ->setContentType('text/html')
    ->setBody($body);

$bcc = $booking['center_office_id']['email_bcc'] ?? '';
if(strlen($bcc)) {
    $message->addBcc($bcc);
}

Mail::queue($message, 'sale\booking\Booking', $booking['id']);

$context->httpResponse()->status(204)->send();

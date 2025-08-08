<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\Mail;
use equal\email\Email;
use sale\booking\PartnerPlanningSummary;

[$params, $providers] = eQual::announce([
    'description'   => "Send partner planning summary as mail.",
    'params'        => [

        'id' => [
            'description'       => 'Identifier of the targeted partner planning summary.',
            'type'              => 'many2one',
            'foreign_object'    => 'sale\booking\PartnerPlanningSummary',
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

$planning_summary = PartnerPlanningSummary::id($params['id'])
    ->read(['mail_subject', 'mail_content', 'sent_qty', 'partner_id' => ['email']])
    ->first();

if(is_null($planning_summary)) {
    throw new Exception("unknown_partnerplanningsummary", EQ_ERROR_UNKNOWN_OBJECT);
}

if(!isset($planning_summary['partner_id']['email'])) {
    throw new Exception("missing_partner_email", EQ_ERROR_INVALID_CONFIG);
}

$message = new Email();
$message->setTo($planning_summary['partner_id']['email'])
    ->setSubject($planning_summary['mail_subject'])
    ->setContentType('text/html')
    ->setBody($planning_summary['mail_content']);

$mail_id = Mail::queue($message, 'sale\booking\PartnerPlanningSummary', $params['id']);

PartnerPlanningSummary::id($planning_summary['id'])
    ->update(['sent_qty' => ++$planning_summary['sent_qty']]);

$context->httpResponse()
        ->status(204)
        ->send();

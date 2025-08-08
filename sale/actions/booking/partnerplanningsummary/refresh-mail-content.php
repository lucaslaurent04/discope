<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\PartnerPlanningSummary;

[$params, $providers] = eQual::announce([
    'description'   => "Refresh the mail content of given partner's planning summary.",
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
    ->read(['partner_id', 'date_from', 'date_to'])
    ->first();

if(is_null($planning_summary)) {
    throw new Exception("unknown_partnerplanningsummary", EQ_ERROR_UNKNOWN_OBJECT);
}

$mail_content = \eQual::run('get', 'sale_booking_partnerplanningsummary_generate-mail-content', [
    'id'        => $planning_summary['partner_id'],
    'date_from' => $planning_summary['date_from'],
    'date_to'   => $planning_summary['date_to']
]);

PartnerPlanningSummary::id($planning_summary['id'])
    ->update(['mail_content' => $mail_content]);

$context->httpResponse()
        ->status(204)
        ->send();

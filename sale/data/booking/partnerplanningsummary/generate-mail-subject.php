<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use core\setting\Setting;
use identity\Partner;
use sale\booking\BookingActivity;

[$params, $providers] = eQual::announce([
    'description'   => "Generate the planning mail subject.",
    'params'        => [

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

$date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

$date_from = date($date_format, $params['date_from']);
$date_to = date($date_format, $params['date_to']);

$subject = 'Planning summary from '.$date_from.' to '.$date_to;
foreach($template['parts_ids'] as $part) {
    if($part['name'] === 'subject') {
        $subject = strip_tags($part['value']);
        $data = compact('date_from', 'date_to');
        foreach($data as $key => $val) {
            $subject = str_replace('{'.$key.'}', $val, $subject);
        }

        break;
    }
}

$context->httpResponse()
        ->body($subject)
        ->status(200)
        ->send();

<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => "Render a booking quote as a PDF document, given its id.",
    'params'        => [
        'id' => [
            'description'   => 'Identifier of the booking to print.',
            'type'          => 'integer',
            'required'      => true
        ],
        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.room-plans'
        ],
        'lang' =>  [
            'description'   => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'          => 'string',
            'default'       => constant('DEFAULT_LANG')
        ],
        'output' =>  [
            'description'   => 'Output format of the document.',
            'type'          => 'string',
            'selection'     => ['pdf', 'html'],
            'default'       => 'pdf'
        ]
    ],
    'constants'             => ['DEFAULT_LANG', 'L10N_LOCALE'],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/pdf',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

['context' => $context] = $providers;

// steer towards custom controller, if any
$has_custom_package = Setting::get_value('discope', 'features', 'has_custom_package', false);
if($has_custom_package) {
    $custom_package = Setting::get_value('discope', 'features', 'custom_package');
    if(!$custom_package) {
        trigger_error('APP::Missing customization package setting (despite `discope.features.has_custom_package`)', EQ_REPORT_WARNING);
    }
    elseif($custom_package !== 'sale') {
        if(file_exists(EQ_BASEDIR."/packages/{$custom_package}/data/sale/booking/print-room-plans.php")) {
            $output = eQual::run('get', "{$custom_package}_sale_booking_print-room-plans", $params, true);

            $context->httpResponse()
                    // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
                    ->header('Content-Disposition', 'inline; filename="document.pdf"')
                    ->body($output)
                    ->send();

            exit(0);
        }
    }
}

// #todo - handle room plans for most customers (for now only lathus specific is handled)

$output = '';

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();



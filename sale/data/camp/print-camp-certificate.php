<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use identity\Center;
use core\setting\Setting;

[$params, $providers] = eQual::announce([
    'description'   => "Render an end of camp certificate given the child ID as a PDF document.",
    'params'        => [

        'child_id' => [
            'type'          => 'integer',
            'description'   => "Identifier of child concerned by the camp certificate.",
            'required'      => true
        ],

        'year' => [
            'type'          => 'integer',
            'description'   => "Year the certificate is needed for.",
            'default'       => fn() => intval(date('Y'))
        ],

        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => 'The center to which the child enrollments relates to.',
            'default'           => function() {
                return ($centers = Center::search())->count() === 1 ? current($centers->ids()) : null;
            }
        ],

        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.camp-certificate'
        ],

        'output' =>  [
            'description'   => 'Output format of the document.',
            'type'          => 'string',
            'selection'     => ['pdf', 'html'],
            'default'       => 'pdf'
        ]

    ],
    'constants'     => [],
    'access'        => [
        'visibility'        => 'protected',
        'groups'            => ['camp.default.user'],
    ],
    'response'      => [
        'content-type'      => 'application/pdf',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

$output = '';

// steer towards custom controller, if any
$has_custom_package = Setting::get_value('discope', 'features', 'has_custom_package', false);
if($has_custom_package) {
    $custom_package = Setting::get_value('discope', 'features', 'custom_package');
    if(!$custom_package) {
        trigger_error('APP::Missing customization package setting (despite `discope.features.has_custom_package`)', EQ_REPORT_WARNING);
    }
    elseif($custom_package !== 'sale') {
        if(file_exists(EQ_BASEDIR."/packages/{$custom_package}/data/sale/camp/print-camp-certificate.php")) {
            $output = eQual::run('get', "{$custom_package}_sale_camp_print-camp-certificate", $params, true);
        }
    }
}

if(empty($output)) {
    // #todo - handle camp certificate for most customers (for now only lathus specific is handled)
}

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();
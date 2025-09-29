<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\camp\Enrollment;

[$params, $providers] = eQual::announce([
    'description'   => "Create a manual payment to complete the payments of all fundings not related to a deposit invoice.",
    'help'          => "This action is intended for payment with bank card only. Manual payments can be undone while the enrollment is not fully balanced (and invoiced).",
    'params'        => [

        'id' =>  [
            'type'          => 'integer',
            'min'           => 1,
            'description'   => "Identifier of the targeted enrollment.",
            'required'      => true
        ]

    ],
    'access'        => [
        'groups'        => ['booking.default.user', 'sale.default.administrator']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm']
]);

/**
 * @var \equal\php\Context  $context
 */
['context' => $context] = $providers;

// read enrollment object
$enrollment = Enrollment::id($params['id'])
    ->read(['status', 'fundings_ids' => ['type', 'is_paid', 'invoice_id' => ['is_deposit']]])
    ->first(true);

if(is_null($enrollment)) {
    throw new Exception("unknown_enrollment", EQ_ERROR_UNKNOWN_OBJECT);
}

if($enrollment['status'] !== 'confirmed') {
    throw new Exception("incompatible_status", EQ_ERROR_INVALID_PARAM);
}

foreach($enrollment['fundings_ids'] as $funding) {
    if(
        $funding['type'] === 'installment' || (
            isset($funding['invoice_id']['is_deposit'])
            && $funding['invoice_id']['is_deposit'] === false
        )
    ) {
        try {
            eQual::run('do', 'sale_camp_enrollment_funding_do-pay-append', ['id' => $funding['id']]);
        }
        catch(Exception $e) {
            // ignore errors raised while appending payments
        }
    }
}

$context->httpResponse()
        ->status(204)
        ->send();

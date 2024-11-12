<?php
use sale\booking\Consumption;
use realestate\RentalUnit;
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/


// announce script and fetch parameters values
list($params, $providers) = eQual::announce([
    'description'	=>	"Mark a selection of Rental Units as cleaned.",
    'params' 		=>	[
        'ids' => [
            'description'       => 'List of rental unit consumption identifiers to check for emptiness.',
            'type'              => 'array'
        ]
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response' => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers' => ['context']
]);

/**
 * @var \equal\php\Context                  $context
 */
list($context) = [ $providers['context']];

$consumptions = Consumption::ids($params['ids'])->read(['id', 'rental_unit_id'])->get(true);
foreach($consumptions as $consumption) {
    RentalUnit::id($consumption['rental_unit_id'])->update(['action_required' => 'none']);
}

$context->httpResponse()
        ->status(204)
        ->send();

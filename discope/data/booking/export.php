<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\booking\Booking;
use sale\booking\BookingLine;
use sale\booking\BookingLineGroup;

[$params, $providers] = eQual::announce([
    'description'   => 'Export a booking and all its booking line groups and booking lines as JSON.',
    'params'        => [
        'id' => [
            'description'   => 'Identifier of the booking to export.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access'        => [
        'visibility'    => 'protected',
        'groups'        => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'UTF-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']
]);

/**
 * @var \equal\php\Context                $context
 * @var \equal\orm\ObjectManager          $orm
 * @var \equal\auth\AuthenticationManager $auth
 */
['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;

// Avoid repeating access checks for every nested object after controller access was granted.
$auth->su();

$get_model_fields = function(string $entity) use($orm): array {
    $model = $orm->getModel($entity);
    if(!$model) {
        throw new Exception('unknown_entity', EQ_ERROR_INVALID_PARAM);
    }

    return array_keys($model->getSchema());
};

$booking_fields = array_values(array_diff(
    $get_model_fields(Booking::getType()),
    ['booking_lines_groups_ids']
));

$booking_line_group_fields = array_values(array_diff(
    $get_model_fields(BookingLineGroup::getType()),
    ['booking_lines_ids']
));

$booking_line_fields = $get_model_fields(BookingLine::getType());

$fields = array_merge(
    $booking_fields,
    [
        'booking_lines_groups_ids' => array_merge(
            $booking_line_group_fields,
            [
                'booking_lines_ids' => $booking_line_fields
            ]
        )
    ]
);

$booking = Booking::id($params['id'])
    ->read($fields)
    ->adapt('json')
    ->first(true);

if(!$booking) {
    throw new Exception('unknown_booking', EQ_ERROR_UNKNOWN_OBJECT);
}

$context
    ->httpResponse()
    ->body($booking)
    ->send();

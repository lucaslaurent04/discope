<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\Mail;
use equal\email\Email;
use sale\booking\Booking;
use sale\booking\BookingInspection;

[$params, $providers] = eQual::announce([
    'description'	=>	"Submit Booking Inspection, sends consumption meters readings to given emails.",
    'params' 		=>	[
        'id' => [
            'type'              => 'many2one',
            'description'       => "Identifier of the targeted Booking Inspection.",
            'foreign_object'    => 'sale\booking\BookingLine',
            'required'          => true
        ],
        'emails' => [
            'type'              => 'array',
            'description'       => "List of email addresses the readings must be sent to."
        ]
    ],
    'access'        => [
        'visibility'    => 'protected',
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

$formatIndexValue = function($number) {
    $padded_number = str_pad((string) $number, 8, '0', STR_PAD_LEFT);

    $part1 = substr($padded_number, 0, 5);
    $part2 = substr($padded_number, -3);

    return "{$part1},{$part2}";
};

$createMessage = function($booking_inspection, $emails, $blind_copy_email) use ($formatIndexValue) {
    $message = new Email();
    $message->setTo($emails[0])
        ->setSubject('Réservation ' . $booking_inspection['booking_id']['name'] . ' relevés des compteurs.')
        ->addBcc($blind_copy_email);

    if(count($emails) > 1) {
        foreach($emails as $index => $email) {
            if($index === 0) {
                continue;
            }
            $message->addCc($email);
        }
    }

    $note_by_lang = [
        'Vous trouverez ci-dessous les relevés des compteurs pour le check-%s de votre réservation.',
        'Hieronder vindt u de meterstanden voor check-%s van uw reservering.',
        'Below you will find the meter readings for check-%s of your booking.'
    ];

    $body = '';
    foreach($note_by_lang as $note) {
            $body .= sprintf(
                '<p>' . $note . '</p>',
                $booking_inspection['booking_id']['status'] === 'checkedin' ? 'out' : 'in'
            );
    }

    $body .= '<ul>';
    foreach($booking_inspection['consumptions_meters_readings_ids'] as $reading) {
        $body .= sprintf(
                '<li>%s : %s %s %s</li>',
                str_pad($reading['consumption_meter_id']['type_meter'], 40),
                $formatIndexValue($reading['index_value']),
                $reading['consumption_meter_id']['meter_unit'],
                isset($reading['unit_price']) && $reading['unit_price'] > 0 ? ' (€' . $reading['unit_price'] . ')' : ''
            );
    }
    $body .= '</ul>';

    return $message->setBody($body);
};

$booking_inspection = BookingInspection::id($params['id'])
    ->read([
        'status',
        'booking_id' => ['status'],
        'consumptions_meters_readings_ids' => [
            'index_value',
            'unit_price',
            'consumption_meter_id' => [
                'type_meter',
                'meter_number',
                'meter_unit'
            ]
        ]
    ])
    ->first(true);

if(is_null($booking_inspection)) {
    throw new Exception('unknown_booking_inspection', EQ_ERROR_UNKNOWN_OBJECT);
}

if($booking_inspection['status'] !== 'pending') {
    throw new Exception('not_a_pending_booking_inspection', EQ_ERROR_INVALID_PARAM);
}

if(!in_array($booking_inspection['booking_id']['status'], ['confirmed', 'validated', 'checkedin'])) {
    throw new Exception('wrong_booking_status', EQ_ERROR_INVALID_PARAM);
}

if(empty($booking_inspection['consumptions_meters_readings_ids'])) {
    throw new Exception('no_readings', EQ_ERROR_INVALID_PARAM);
}

if(empty($params['emails'])) {
    throw new Exception('at_least_one_email_required', EQ_ERROR_INVALID_PARAM);
}

$message = $createMessage($booking_inspection, $params['emails'], 'facturation@kaleo-asbl.be');

Mail::queue($message);

$next_booking_status = $booking_inspection['booking_id']['status'] === 'checkedin' ? 'checkedout' : 'checkedin';

Booking::id($booking_inspection['booking_id']['id'])
    ->update(['status' => $next_booking_status]);

BookingInspection::id($booking_inspection['id'])
    ->update(['status' => 'submitted']);

if ($next_booking_status === 'checkedout'){
    $data = eQual::run('do', 'sale_booking_update-readings-services', ['id' => $booking_inspection['booking_id']['id']]);
    if(is_array($data) && count($data)) {
        throw new Exception('failed_to_create_consumption_reading_service ', EQ_ERROR_INVALID_PARAM);
    }
}

$context->httpResponse()
        ->status(204)
        ->send();

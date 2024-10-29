<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use core\Mail;
use equal\email\Email;
use communication\Template;
use sale\booking\Booking;

list($params, $providers) = eQual::announce([
    'description'	=>	"Send an email with a one-time access link to the provided email address, if allowed for given booking.",
    'params' 		=>	[
        'booking_id' =>  [
            'description'   => "Identifier of the Booking for which an access is requested.",
            'type'          => 'integer',
            'required'      => true
        ],
        'email' =>  [
            'description'   => "Email address to send the link to (must be in booking Contacts).",
            'type'          => 'string',
            'usage'         => 'email',
            'required'      => true
        ],
        'lang' =>  [
            'description'   => 'Language of the reminder which is defined by the responsible.',
            'type'          => 'string',
            'default'       => constant('DEFAULT_LANG')
        ],
    ],
    'access' => [
        'visibility'        => 'public'
    ],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'utf-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'auth', 'orm'],
    'constants'     => ['BACKEND_URL']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\auth\AuthenticationManager   $auth
 */
['context' => $context, 'orm' => $orm, 'auth' => $auth] = $providers;

$booking = Booking::id($params['booking_id'])
    ->read([
        'id', 'status', 'name',
        'date_from', 'date_to',
        'contacts_ids' => ['partner_identity_id' => ['email']],
        'center_id' => ['name', 'center_office_id' => ['email_bcc'] ]
    ])
    ->first(true);

if(!$booking) {
    throw new Exception('unknown_booking', EQ_ERROR_NOT_ALLOWED);
}

if(!in_array($booking['status'], ['confirmed', 'validated', 'checkedin'])) {
    throw new Exception('out_of_range_booking', EQ_ERROR_NOT_ALLOWED);
}

if($booking['date_to'] < time()) {
    throw new Exception('expired_booking', EQ_ERROR_NOT_ALLOWED);
}

$found = false;
foreach($booking['contacts_ids'] as $id => $contact) {
    if($contact['partner_identity_id']['email'] == $params['email']) {
        $found = true;
        break;
    }
}

if(!$found) {
    throw new Exception('unrelated_contact', EQ_ERROR_NOT_ALLOWED);
}

$template = Template::search([
        // common category [KA - 6]
        ['category_id', '=', 6],
        ['type', '=', 'contract'],
        ['code', '=', 'guestslist_request']
    ])
    ->read(['parts_ids' => ['name', 'value']], $params['lang'])
    ->first(true);

if(!$template){
    throw new Exception('missing_mandatory_template', EQ_ERROR_INVALID_CONFIG);
}

// create an email based on template
$body = $subject = '';
foreach($template['parts_ids'] as $part) {
    if($part['name'] == 'subject') {
        $subject = strip_tags($part['value']);
        $data = [
            'booking'   => $booking['name'],
            'center'    => $booking['center_id']['name'],
            'date_from' => date('d/m/Y', $booking['date_from']),
            'date_to'   => date('d/m/Y', $booking['date_to'])
        ];
        foreach($data as $key => $val) {
            $subject = str_replace('{'.$key.'}', $val, $subject);
        }
    }
    elseif($part['name'] == 'body') {
        // generate a "nonce" access token, valid for 30 minutes
        $nonce_token  = $auth->encode([
                'booking_id' => $params['booking_id'],
                'email'      => $params['email'],
                'exp'        => time() + (30 * 60)
            ]);
        $url = constant('BACKEND_URL').'/guests/#/'.$nonce_token;
        $url_txt = substr($url, 0, 50).'...';
        $body = str_replace('{link}', "<a href=\"$url\">$url_txt</a>", $part['value']);
    }
}

$trailer_notices = [
        'fr'    => "<p>Ceci est un message automatique envoyé de la part de <strong>Kaleo ASBL</strong> suite à une demande utilisant votre adresse email.</p><p>Si vous n'êtes pas à l'origine de ce message, vous pouvez simplement l'ignorer. En cas d'envois non sollicités répétés, vous pouvez nous contacter via <a href=\"mailto:info@kaleo-asbl.be\">info@kaleo-asbl.be</a>.</p>",
        'en'    => "<p>This is an automatic message sent on behalf of <strong>Kaleo ASBL</strong> following a request using your email address.</p><p>If you did not initiate this message, you can simply ignore it. In case of repeated unsolicited messages, you can contact us at <a href=\"mailto:info@kaleo-asbl.be\">info@kaleo-asbl.be</a>.</p>",
        'nl'    => "<p>Dit is een automatisch bericht verzonden namens <strong>Kaleo VZW</strong> naar aanleiding van een verzoek met uw e-mailadres.</p><p>Als u niet de afzender van dit bericht bent, kunt u het gewoon negeren. Bij herhaaldelijke ongewenste berichten kunt u contact met ons opnemen via <a href=\"mailto:info@kaleo-asbl.be\">info@kaleo-asbl.be</a>.</p>"
    ];

if(isset($trailer_notices[$params['lang']])) {
    $body .= '<p></p><small>'.$trailer_notices[$params['lang']].'</small>';
}

// build Email envelope
$message = new Email();
$message->setTo($params['email'])
        ->setSubject($subject)
        ->setContentType("text/html")
        ->setBody($body);

// send instant email
Mail::send($message, 'sale\booking\Booking', $params['booking_id']);

$context->httpResponse()
        ->send();

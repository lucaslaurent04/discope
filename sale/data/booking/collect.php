<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use equal\orm\Domain;
use identity\Center;
use identity\Identity;
use sale\booking\Booking;
use sale\booking\Contact;
use sale\booking\Funding;
use sale\booking\BankStatementLine;
use sale\booking\Payment;
use sale\booking\SojournProductModelRentalUnitAssignement;

[$params, $providers] = eQual::announce([
    'description'   => 'Advanced search for Bookings: returns a collection of Booking according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [
        'entity' =>  [
            'description'   => 'Full name (including namespace) of the class to look into (e.g. \'core\\User\').',
            'type'          => 'string',
            'default'       => 'sale\booking\Booking'
        ],

        'date_from' => [
            'type'          => 'date',
            'description'   => "First date of the time interval.",
            'default'       => function () {
                $active = Setting::get_value('sale', 'features', 'ui.booking.filter_on_fiscal_year', false);
                $default = Setting::get_value('finance', 'accounting', 'fiscal_year.date_from');
                return ($active && $default) ? strtotime($default) : null;
            }
        ],

        'date_to' => [
            'type'          => 'date',
            'description'   => "Last date of the time interval.",
            'default'       => function () {
                $active = Setting::get_value('sale', 'features', 'ui.booking.filter_on_fiscal_year', false);
                $default = Setting::get_value('finance', 'accounting', 'fiscal_year.date_to');
                return ($active && $default) ? strtotime($default) : null;
            }
        ],

        'bank_account_iban' => [
            'type'          => 'string',
            'usage'         => 'uri/urn:iban',
            'description'   => "Number of the bank account of the Identity, if any."
        ],

        'structured_message' => [
            'type'          => 'string',
            'description'   => "Structured message from bank statement."
        ],

        'display_name' => [
            'type'          => 'string',
            'description'   => "Complete name of the booking."
        ],

        'customer_accounting_account' => [
            'type'          => 'string',
            'description'   => "Accounting account of the Identity of the customer."
        ],

        'identity_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Identity',
            'domain'            => ["id", ">", 4],
            'description'       => 'Customer identity.'
        ],

        'center_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'identity\Center',
            'description'       => 'The center to which the booking relates to.',
            'default'           => function() {
                return ($centers = Center::search())->count() === 1 ? current($centers->ids()) : null;
            }
        ],

        'rental_unit_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'realestate\RentalUnit',
            'description'       => 'Rental unit on which to perform the search.'
        ],

        'has_tour_operator' => [
            'type'              => 'boolean',
            'description'       => 'Mark the booking as completed by a Tour Operator.'
        ],

        'tour_operator_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\customer\TourOperator',
            'domain'            => ['is_tour_operator', '=', true],
            'description'       => 'Mark the booking as completed by a Tour Operator.',
            'visible'           => ['has_tour_operator', '=', true]
        ],

        'tour_operator_ref' => [
            'type'              => 'string',
            'description'       => 'Specific reference, voucher code, or booking ID from the TO.',
            'visible'           => ['has_tour_operator', '=', true]
        ],

        'extref_reservation_id' => [
            'type'              => 'string',
            'description'       => 'Identifier of the reservation at Channel Manager side.'
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);

/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
['context' => $context, 'orm' => $orm] = $providers;


/*
    Add conditions to the domain to consider advanced parameters
*/

$domain = $params['domain'];
$bookings_ids = [];

if(isset($params['date_from'])) {
    $domain = Domain::conditionAdd($domain, ['date_from', '>=', $params['date_from']]);
}
if(isset($params['date_to'])) {
    $domain = Domain::conditionAdd($domain, ['date_from', '<=', $params['date_to']]);
}

if(!empty($params['display_name'])) {
    $domain = Domain::conditionAdd($domain, ['display_name', 'ilike', '%'.$params['display_name'].'%']);
}

if(isset($params['has_tour_operator']) && $params['has_tour_operator'] === true ) {
    $domain = Domain::conditionAdd($domain, ['has_tour_operator', '=', true]);
}

if(isset($params['tour_operator_id']) && ($params['tour_operator_id'] > 0 )) {
    $domain = Domain::conditionAdd($domain, ['tour_operator_id', '=', $params['tour_operator_id']]);
}

if(isset($params['tour_operator_ref']) && strlen($params['tour_operator_ref']) > 0) {
    $domain = Domain::conditionAdd($domain, ['tour_operator_ref', 'like', $params['tour_operator_ref']]);
}

if(isset($params['extref_reservation_id']) && ($params['extref_reservation_id'] > 0 )) {
    $domain = Domain::conditionAdd($domain, ['extref_reservation_id', '=', $params['extref_reservation_id']]);
}

/*
    center : trivial booking::center_id
*/
if(isset($params['center_id']) && $params['center_id'] > 0) {
    // add constraint on center_id
    $domain = Domain::conditionAdd($domain, ['center_id', '=', $params['center_id']]);
}

/*
    bank_account_iban : search in statement lines and identities
*/
if(isset($params['bank_account_iban']) && strlen($params['bank_account_iban'])) {
    $found = false;
    // lookup in bank statement lines
    $lines_ids = BankStatementLine::search(['account_iban', '=', $params['bank_account_iban']])->ids();
    if(count($lines_ids)) {
        $payments = Payment::search(['statement_line_id', 'in', $lines_ids])->read(['id', 'booking_id'])->get();
        if(count($payments)) {
            $bookings_ids = array_map(function ($a) { return $a['booking_id']; }, $payments);
            $found = true;
        }
    }

    // lookup in identities
    $identities_ids = Identity::search(['bank_account_iban', '=', $params['bank_account_iban']])->ids();
    if(count($identities_ids)) {
        $domain = Domain::conditionAdd($domain, ['customer_identity_id', 'in', $identities_ids]);
        $found = true;
    }

    if(!$found) {
        // add a constraint to void the result set
        $bookings_ids = [0];
    }
}

/*
    customer_accounting_code : search identities
*/
if(!empty($params['customer_accounting_account'])) {
    $found = false;

    $identities_ids = Identity::search(['accounting_account', 'ilike', "%{$params['customer_accounting_account']}%"])->ids();
    if(!empty($identities_ids)) {
        $domain = Domain::conditionAdd($domain, ['customer_identity_id', 'in', $identities_ids]);
        $found = true;
    }

    if(!$found) {
        // add a constraint to void the result set
        $bookings_ids = [0];
    }
}


/*
    structured_message : search in funding
*/
if(isset($params['structured_message']) && strlen($params['structured_message'])) {
    $fundings = Funding::search(['payment_reference', '=', $params['structured_message']])->read(['booking_id'])->get();
    if(count($fundings)) {
        $matches_ids = array_map(function ($a) { return $a['booking_id']; }, $fundings);
        if(count($bookings_ids)) {
            $bookings_ids = array_intersect(
                $bookings_ids,
                $matches_ids
            );
        }
        else {
            $bookings_ids = $matches_ids;
        }
    }
    else {
        // add a constraint to void the result set
        $bookings_ids = [0];
    }
}

/*
    identity_id : search in contacts (customer should be in it as well)
*/
if(isset($params['identity_id']) && $params['identity_id'] > 0) {

    $matches_ids = array_merge(
        Booking::search(['customer_identity_id', '=', $params['identity_id']])->read(['id'])->ids(),
        array_map(function ($a) { return $a['booking_id']; }, Contact::search(['partner_identity_id', '=', $params['identity_id']])->read(['booking_id'])->get() )
    );

    if(count($matches_ids)) {
        if(count($bookings_ids)) {
            $bookings_ids = array_intersect(
                $bookings_ids,
                $matches_ids
            );
        }
        else {
            $bookings_ids = $matches_ids;
        }
        if(empty($bookings_ids)) {
            // add a constraint to void the result set
            $bookings_ids = [0];
        }
    }
    else {
        // add a constraint to void the result set
        $bookings_ids = [0];
    }
}

/*
    rental_unit : search amongst rental_unit_assignment
*/
if(isset($params['rental_unit_id']) && $params['rental_unit_id'] > 0) {
    $assignments = SojournProductModelRentalUnitAssignement::search(['rental_unit_id', '=', $params['rental_unit_id']])->read(['booking_id'])->get();
    if(count($assignments)) {
        if(count($bookings_ids)) {
            $bookings_ids = array_intersect(
                    $bookings_ids,
                    array_map(function ($a) { return $a['booking_id']; }, $assignments )
                );
        }
        else {
            $bookings_ids = array_map(function ($a) { return $a['booking_id']; }, $assignments );
        }
        if(empty($bookings_ids)) {
            // add a constraint to void the result set
            $bookings_ids = [0];
        }
    }
    else {
        // add a constraint to void the result set
        $bookings_ids = [0];
    }
}


if(count($bookings_ids)) {
    $domain = Domain::conditionAdd($domain, ['id', 'in', $bookings_ids]);
}

$params['domain'] = $domain;

$result = eQual::run('get', 'model_collect', $params, true);


$context->httpResponse()
        ->body($result)
        ->send();

<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use core\Mail;

class PartnerPlanningMail extends Mail
{
    public static function getColumns() {
        return [

            'booking_activities_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\booking\BookingActivity',
                'foreign_field'     => 'partner_planning_mails_ids',
                'rel_table'         => 'sale_booking_bookingactivity_rel_partnerplanningmail',
                'rel_foreign_key'   => 'booking_activity_id',
                'rel_local_key'     => 'partner_planning_mail_id',
                'description'       => "Booking activities who were reminded in mail."
            ]

        ];
    }
}

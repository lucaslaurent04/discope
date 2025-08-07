<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use equal\orm\Model;

class PartnerPlanningSummary extends Model {

    public static function getDescription(): string {
        return "Summary of the partner's planning between two dates.";
    }

    public static function getColumns(): array {
        return [

            'partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'description'       => "The partner concerned by the planning summary.",
                'required'          => true,
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Date (included) at which the partner planning starts.",
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "Date (included) at which the partner planning ends.",
                'required'          => true
            ],

            'sent_qty' => [
                'type'              => 'integer',
                'description'       => "Specifies how many times this planning summary has already been sent.",
                'default'           => 0
            ],

            'mail_content' => [
                'type'          => 'string',
                'usage'         => 'text/html',
                'description'   => "Body of the last mail sent.",
                'help'          => "If the planning summary hasn't been sent yet the content is the auto generated one at creation.",
                'required'      => true
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'domain'            => ['object_class', '=', 'sale\booking\PartnerPlanningSummary']
            ]

        ];
    }
}

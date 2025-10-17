<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;

class InvoiceLine extends \finance\accounting\InvoiceLine {

    public static function getColumns() {
        return [

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Invoice',
                'description'       => 'Invoice the line is related to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'invoice_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\InvoiceLineGroup',
                'description'       => 'Group the line relates to (in turn, groups relate to their invoice).',
                'ondelete'          => 'cascade'
            ],

            'downpayment_invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Invoice',
                'description'       => 'Downpayment invoice (set when the line refers to an invoiced downpayment.)'
            ],

            'is_supply' => [
                'type'              => 'boolean',
                'description'       => "Is the contract line related to a booking line who is a supply.",
                'default'           => false
            ],

            'booking_activity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingActivity',
                'description'       => "Main Booking Activity this line relates to, if any.",
                'help'              => "If the line refers to a transport/supply, it means that the transport/supply is needed for a specific activity."
            ]

        ];
    }

}

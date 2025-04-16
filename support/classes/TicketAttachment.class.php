<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace support;

class TicketAttachment extends \documents\Document {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function '         => 'calcName',
                'store'             => true
            ],

            'category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'documents\DocumentCategory',
                'description'       => 'Category of the document (default to \'support\')',
                'default'           =>  2
            ],

            'ticket_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'support\Ticket',
                'description'       => 'Ticket of the attachment.',
                'ondelete'          => 'cascade',
                'dependents'        => ['name']
            ],

            'ticket_entry_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'support\TicketEntry',
                'description'       => 'Ticket of the attachment.',
                'ondelete'          => 'cascade'
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['ticket_id']);
        foreach($result as $id => $ticketAttachment) {
            if(isset($ticketAttachment['ticket_id'])) {
                $result[$id] = sprintf("attachment [ticket %05d]", $ticketAttachment['ticket_id']);
            }
        }
        return $result;
    }
}
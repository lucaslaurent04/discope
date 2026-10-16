<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2026
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace discope\core\alert;

class Message extends \core\alert\Message {

    public static function getColumns() {
        return [

            'group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => 'Office the message relates to (for targeting the users).'
            ],

            'alert' => [
                'type'              => 'computed',
                'usage'             => 'icon',
                'result_type'       => 'string',
                'function'          => 'calcAlert'
            ]

        ];
    }

    public static function calcAlert($self) {
        $result = [];
        $self->read(['severity']);
        foreach($self as $id => $message) {
            switch($message['severity']) {
                case 'notice':
                    $alert = 'info';
                    break;
                case 'warning':
                    $alert = 'warn';
                    break;
                case 'important':
                    $alert = 'major';
                    break;
                case 'error':
                default:
                    $alert = 'error';
            }

            $result[$id] = $alert;
        }

        return $result;
    }
}

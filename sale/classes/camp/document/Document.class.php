<?php

namespace sale\camp\document;

class Document extends \documents\Document {

    public static function getColumns(): array {
        return [

            'enrollment_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Enrollment',
                'description'       => "The enrollment the document is needed for."
            ],

            'enrollment_type' => [
                'type'              => 'string',
                'selection'         => [
                    'other',
                    'help-ccvg',
                    'health-sheet'
                ],
                'description'       => "The enrollment document type.",
                'default'           => 'other'
            ]

        ];
    }
}

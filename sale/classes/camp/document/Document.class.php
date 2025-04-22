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
                    'doc-type-1', // TODO: Add real documents codes    "aide de la CCVG" | ""
                    'doc-type-2',
                    'doc-type-3',
                    'doc-type-4',
                    'other'
                ],
                'description'       => "The enrollment document type.",
                'default'           => 'other'
            ]

        ];
    }
}

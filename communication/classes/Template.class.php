<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace communication;

use equal\orm\Model;

class Template extends Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Code of the template (allows duplicates).",
                'function'          => 'calcName',
                'store'             => true,
                'readonly'          => true
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "Code of the template (allows duplicates).",
                'required'          => true,
                'onupdate'          => 'onupdateCode'
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Role and intended usage of the template.",
                'multilang'         => true
            ],

            'category_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'communication\TemplateCategory',
                'description'       => "The category the template belongs to.",
                'onupdate'          => 'onupdateCategoryId',
                'required'          => true
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => [ 'quote', 'option', 'contract', 'funding', 'invoice', 'guest', 'planning', 'camp' ],
                'description'       => 'The context in which the template is meant to be used.',
                'onupdate'          => 'onupdateType'
            ],

            'parts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'communication\TemplatePart',
                'foreign_field'     => 'template_id',
                'description'       => 'List of templates parts related to the template.'
            ],

            'attachments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'communication\TemplateAttachment',
                'foreign_field'     => 'template_id',
                'description'       => 'List of attachments related to the template, if any.'
            ]

        ];
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['code', 'type', 'category_id' => ['name']]);
        foreach($self as $id => $template) {
            $result[$id] = sprintf('%s.%s.%s',
                $template['category_id']['name'],
                $template['type'],
                $template['code']
            );
        }

        return $result;
    }

    public static function onupdateCode($self) {
        $self->update(['name' => null]);
        $self->read(['name']);
    }

    public static function onupdateType($self) {
        $self->update(['name' => null]);
        $self->read(['name']);
    }

    public static function onupdateCategoryId($self) {
        $self->update(['name' => null]);
        $self->read(['name']);
    }
}

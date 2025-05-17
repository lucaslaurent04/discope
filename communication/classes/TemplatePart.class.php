<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace communication;
use equal\orm\Model;

class TemplatePart extends Model {

    public static function getColumns() {

        return [
            'order' => [
                'type'              => 'integer',
                'description'       => "Arbitrary order sequence of the part.",
                'default'           => 1
            ],

            'name' => [
                'type'              => 'string',
                'description'       => "Code of the template part.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the template part."
            ],

            'value' => [
                'type'              => 'string',
                'usage'             => 'text/html',
                'description'       => "Template body (html).",
                'multilang'         => true,
                'dependents'        => ['excerpt']
            ],

            'template_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'communication\Template',
                'description'       => "The template the part belongs to.",
                'required'          => true
            ],

            'excerpt' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcExcerpt',
                'description'       => "Excerpt from the value part.",
                'multilang'         => true,
                'store'             => true
            ],

        ];
    }

    public static function calcExcerpt($self) {
        $result = [];
        $self->read(['value']);

        foreach ($self as $id => $part) {
            $html = $part['value'];
            $text = strip_tags($html);
            $text = preg_replace('/\s+/u', ' ', $text);
            $excerpt = mb_substr(trim($text), 0, 255);
            $suffix = (mb_strlen($text) > 255) ? ' […]' : '';
            $result[$id] = $excerpt . $suffix;
        }

        return $result;
    }

}

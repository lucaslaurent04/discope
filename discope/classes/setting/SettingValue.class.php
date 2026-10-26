<?php
/*
    Developed by Yesbabylon - https://yesbabylon.com
    (c) 2025-2026 Yesbabylon SA
    Licensed under the GNU AGPL v3 License - https://www.gnu.org/licenses/agpl-3.0.html
*/

namespace discope\setting;

class SettingValue extends \core\setting\SettingValue {

    public static function getModelScope(): ?string {
        return null;
    }

    public static function getColumns() {
        return [

            'setting_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'discope\setting\Setting',
                'description'       => 'Setting the value relates to.',
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => 'User the setting is specific to (optional).',
                'ondelete'          => 'cascade'
            ],

            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => 'Organisation the setting is specific to (optional).',
                'ondelete'          => 'cascade'
            ],

            'center_office_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\CenterOffice',
                'description'       => 'Center office the setting is specific to (optional).',
                'ondelete'          => 'cascade'
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Center',
                'description'       => "Center the setting is specific to (optional).",
                'ondelete'          => 'cascade'
            ]

        ];
    }

    public function getUniques(): array {
        return [
            ['setting_id', 'user_id', 'organisation_id', 'center_office_id', 'center_id']
        ];
    }
}

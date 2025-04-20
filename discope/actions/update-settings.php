<?php
use core\setting\Setting;

$sections = [
        1 => 'locale',
        3 => 'security',
        4 => 'default',
        5 => 'accounting',
        12 => 'features',
        11 => 'analytics',
        13 => 'storage',
        14 => 'integration',
        15 => 'system',
        16 => 'workflow',
        17 => 'schedule',
        18 => 'organization'
    ];

$map_sections = array_flip($sections);

$json_file = 'settings_adaptations.json';

if (!file_exists($json_file)) {
    die("Missing JSON file : $json_file\n");
}

$adaptations = json_decode(file_get_contents($json_file), true);

if(!$adaptations) {
    die("Invalid JSON data (unable to parse): $json_file\n");
}

foreach($adaptations as $entry) {
    $before = $entry['before'];
    $after = $entry['after'];

    $setting = Setting::search([
            ['package', '=', $before['package']],
            ['section', '=', $before['section']],
            ['code', '=', $before['code']],
        ])
        ->first();

    if(!$setting) {
        echo "Setting not found - skipping : {$before['package']}.{$before['section']}.{$before['code']}\n";
        continue;
    }

    $package = $after['package'];
    $code    = $after['code'];
    $section = $after['section'];

    if(!isset($map_sections[$section])) {
        echo "Section not found - skipping : {$section}\n";
        continue;
    }

    $section_id = $map_sections[$section];

    echo "updating : {$before['package']}.{$before['section']}.{$before['code']} => {$package}.{$section}.{$code}\n";

    try {
        Setting::id($setting['id'])->update([
                'package'       => $package,
                'section'       => $section,
                'section_id'    => $section_id,
                'code'          => $code
            ]);
    }
    catch(Exception $e) {
        echo "error while updating : {$before['package']}.{$before['section']}.{$before['code']} => {$package}.{$section}.{$code}\n";
        echo $e->getMessage()."\n";
    }
}

echo "\nupdate complete.\n";

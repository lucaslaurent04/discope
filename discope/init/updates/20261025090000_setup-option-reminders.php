<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2026
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use communication\Template;
use communication\TemplatePart;
use core\Task;
use discope\setting\Setting;
use identity\Center;

$settings = [
    'option.expired_notify_auto' => false,
    'option.remind_auto'         => false,
    'option.remind_delay'        => 7
];

foreach($settings as $code => $default) {
    if(is_null(Setting::get_value('sale', 'features', $code))) {
        Setting::assert_value('sale', 'features', $code, $default);
    }
}

// Legacy quote reminders must never send the former quote/expired template.
Task::search(['name', 'like', 'booking.quote.reminder.%'])->delete(true);

$templates = [
    'reminder' => [
        'description' => 'Reminder sent before an option expires.',
        'subject' => [
            'fr' => 'Rappel : votre option {booking} expire bientôt',
            'en' => 'Reminder: your option {booking} expires soon',
            'nl' => 'Herinnering: uw optie {booking} vervalt binnenkort'
        ],
        'body' => [
            'fr' => '<p>Bonjour,</p><p>Votre option {booking} auprès de {center} pour le séjour du {date_from} au {date_to} expire le {date_expiry}.</p><p>Merci de nous contacter avant cette date pour confirmer votre réservation.</p>',
            'en' => '<p>Hello,</p><p>Your option {booking} with {center} for the stay from {date_from} to {date_to} expires on {date_expiry}.</p><p>Please contact us before that date to confirm your booking.</p>',
            'nl' => '<p>Hallo,</p><p>Uw optie {booking} bij {center} voor het verblijf van {date_from} tot {date_to} vervalt op {date_expiry}.</p><p>Gelieve vóór deze datum contact met ons op te nemen om uw reservatie te bevestigen.</p>'
        ]
    ],
    'expired' => [
        'description' => 'Notification sent when an option has effectively expired.',
        'subject' => [
            'fr' => 'Votre option {booking} a expiré',
            'en' => 'Your option {booking} has expired',
            'nl' => 'Uw optie {booking} is vervallen'
        ],
        'body' => [
            'fr' => '<p>Bonjour,</p><p>Votre option {booking} auprès de {center} pour le séjour du {date_from} au {date_to} a expiré le {date_expiry}.</p><p>Cette option n’est désormais plus valable. N’hésitez pas à nous contacter afin d’étudier une nouvelle réservation.</p>',
            'en' => '<p>Hello,</p><p>Your option {booking} with {center} for the stay from {date_from} to {date_to} expired on {date_expiry}.</p><p>This option is no longer valid. Please contact us if you would like us to review a new booking.</p>',
            'nl' => '<p>Hallo,</p><p>Uw optie {booking} bij {center} voor het verblijf van {date_from} tot {date_to} is vervallen op {date_expiry}.</p><p>Deze optie is niet langer geldig. Neem gerust contact met ons op om een nieuwe reservatie te bespreken.</p>'
        ]
    ]
];

$centers = Center::search()->read(['template_category_id'])->get(true);
$category_ids = [];
foreach($centers as $center) {
    if($center['template_category_id']) {
        $category_ids[$center['template_category_id']] = true;
    }
}

foreach(array_keys($category_ids) as $category_id) {
    foreach($templates as $code => $definition) {
        $template = Template::search([
                ['category_id', '=', $category_id],
                ['type', '=', 'option'],
                ['code', '=', $code]
            ])
            ->read(['id'])
            ->first(true);

        if(!$template) {
            $template = Template::create([
                    'category_id'   => $category_id,
                    'type'          => 'option',
                    'code'          => $code,
                    'description'   => $definition['description']
                ])
                ->read(['id'])
                ->first(true);
        }

        foreach(['subject' => 1, 'body' => 2] as $part_name => $order) {
            $part = TemplatePart::search([
                    ['template_id', '=', $template['id']],
                    ['name', '=', $part_name]
                ])
                ->read(['id'])
                ->first(true);

            if(!$part) {
                $part = TemplatePart::create([
                        'template_id'   => $template['id'],
                        'name'          => $part_name,
                        'order'         => $order
                    ])
                    ->read(['id'])
                    ->first(true);

                foreach($definition[$part_name] as $lang => $value) {
                    TemplatePart::id($part['id'])->update(['value' => $value], $lang);
                }
                continue;
            }

            foreach($definition[$part_name] as $lang => $value) {
                $current = TemplatePart::id($part['id'])->read(['value'], $lang)->first(true);
                if(!isset($current['value']) || trim($current['value']) === '') {
                    TemplatePart::id($part['id'])->update(['value' => $value], $lang);
                }
            }
        }
    }
}

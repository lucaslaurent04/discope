<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2025
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\booking;

use communication\Template;
use core\Mail;
use core\setting\Setting;
use equal\email\Email;
use equal\orm\Model;
use sale\provider\Provider;

class PartnerPlanningSummary extends Model {

    public static function getDescription(): string {
        return "Summary of the partner's planning between two dates.";
    }

    public static function getColumns(): array {
        return [

            'partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'description'       => "The partner concerned by the planning summary.",
                'required'          => true,
            ],

            'date_from' => [
                'type'              => 'date',
                'description'       => "Date (included) at which the partner planning starts.",
                'required'          => true
            ],

            'date_to' => [
                'type'              => 'date',
                'description'       => "Date (included) at which the partner planning ends.",
                'required'          => true
            ],

            'sent_qty' => [
                'type'              => 'integer',
                'description'       => "Specifies how many times this planning summary has already been sent.",
                'default'           => 0
            ],

            'mail_subject' => [
                'type'          => 'computed',
                'result_type'   => 'string',
                'description'   => "Subject of the mail that will be sent.",
                'store'         => true,
                'function'      => 'calcSubject'
            ],

            'mail_content' => [
                'type'          => 'computed',
                'result_type'   => 'string',
                'usage'         => 'text/html',
                'description'   => "Body of the mail that will be sent.",
                'help'          => "If the planning summary hasn't been sent yet the content is the auto generated one at creation.",
                'store'         => true,
                'function'      => 'calcMailContent'
            ],

            'mail_remarks' => [
                'type'          => 'string',
                'usage'         => 'text/plain',
                'description'   => "Additional remarks added to the mail when the planning summary is sent.",
                'dependencies'  => ['mail_content']
            ],

            'mails_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'core\Mail',
                'foreign_field'     => 'object_id',
                'domain'            => ['object_class', '=', 'sale\booking\PartnerPlanningSummary']
            ]

        ];
    }

    public static function calcSubject($self): array {
        $template = Template::search([
            ['code', '=', 'partner_reminder'],
            ['type', '=', 'planning']
        ])
            ->read(['parts_ids' => ['name', 'value']])
            ->first(true);

        if(is_null($template)) {
            return [];
        }

        $date_format = Setting::get_value('core', 'locale', 'date_format', 'm/d/Y');

        $result = [];
        $self->read(['date_from', 'date_to']);
        foreach($self as $id => $planning) {
            $date_from = date($date_format, $planning['date_from']);
            $date_to = date($date_format, $planning['date_to']);

            $subject = 'Planning summary from '.$date_from.' to '.$date_to;
            foreach($template['parts_ids'] as $part) {
                if($part['name'] === 'subject') {
                    $subject = strip_tags($part['value']);
                    $data = compact('date_from', 'date_to');
                    foreach($data as $key => $val) {
                        $subject = str_replace('{'.$key.'}', $val, $subject);
                    }

                    break;
                }
            }

            $result[$id] = $subject;
        }

        return $result;
    }

    public static function calcMailContent($self): array {
        $template = Template::search([
            ['code', '=', 'partner_reminder'],
            ['type', '=', 'planning']
        ])
            ->read(['parts_ids' => ['name', 'value']])
            ->first(true);

        if(is_null($template)) {
            return [];
        }

        $result = [];

        $self->read(['mail_remarks']);
        foreach($self as $id => $planning) {
            $activities = BookingActivity::ids(self::getActivitiesIds($id))
                ->read([
                    'name',
                    'activity_date',
                    'group_num',
                    'employee_id',
                    'providers_ids',
                    'time_slot_id' => ['name'],
                    'booking_id' => [
                        'name',
                        'status',
                        'customer_id' => ['name']
                    ],
                    'booking_line_group_id' => [
                        'age_range_assignments_ids' => ['qty', 'age_from', 'age_to']
                    ]
                ])
                ->get();

            $map_status = [
                'quote'             => 'Devis',
                'option'            => 'Option',
                'confirmed'         => 'Confirmée',
                'validated'         => 'Validée',
                'checkedin'         => 'En cours',
                'checkedout'        => 'Terminée',
                'invoiced'          => 'Facturée',
                'debit_balance'     => 'Solde débiteur',
                'credit_balance'    => 'Solde créditeur',
                'balanced'          => 'Soldée'
            ];

            $planned_activities = [];
            foreach($activities as $activity) {
                $age_range_assignments = [];
                foreach($activity['booking_line_group_id']['age_range_assignments_ids'] as $age_range_assignment) {
                    $age_range_assignments[] = $age_range_assignment['qty'].' ('.$age_range_assignment['age_from'].' à '.$age_range_assignment['age_to'].' ans)';
                }

                $matches = [];
                preg_match('/(.*)\s+\(([^()]*)\)$/', $activity['name'], $matches);

                $activity_name = $activity['name'];
                $activity_sku = '';
                if(count($matches) === 3) {
                    $activity_name = $matches[1];
                    $activity_sku = $matches[2];
                }

                $planned_activities[] = [
                    'activity_date'         => date('d/m/y', $activity['activity_date']),
                    'customer_name'         => $activity['booking_id']['customer_id']['name'],
                    'group_num'             => $activity['group_num'],
                    'age_range_assignments' => $age_range_assignments,
                    'booking_status'        => $map_status[$activity['booking_id']['status']],
                    'time_slot_name'        => $activity['time_slot_id']['name'],
                    'activity_name'         => $activity_name,
                    'activity_sku'          => $activity_sku
                ];
            }

            $activities_table =
                '<table style="width: 100%; border-collapse: collapse; margin-top: 20px; border: 1px solid #ddd;">
            <tr>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Date</th>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Client</th>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Informations groupe</th>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Status réservation</th>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Moment</th>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">Activité</th>
                <th style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #f4f4f4; color: #333;">SKU</th>
            </tr>'
                .implode(
                    array_map(
                        function($planned_activity) {
                            return
                                '<tr>'.
                                '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">'.$planned_activity['activity_date'].'</td>'.
                                '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">'.$planned_activity['customer_name'].'</td>'.
                                '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">G'.$planned_activity['group_num'].'<br />'.implode('<br />', $planned_activity['age_range_assignments']).'</td>'.
                                '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">'.$planned_activity['booking_status'].'</td>'.
                                '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">'.$planned_activity['time_slot_name'].'</td>'.
                                '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa;">'.$planned_activity['activity_name'].'</td>'.
                                '<td style="border: 1px solid #ddd; padding: 12px; text-align: left; background-color: #fafafa; font-size: 10px;">'.$planned_activity['activity_sku'].'</td>'.
                                '</tr>';
                        },
                        $planned_activities
                    )
                )
                .'</table>';

            $mail_content = '';
            foreach($template['parts_ids'] as $part) {
                if($part['name'] === 'body') {
                    $mail_content = $part['value'];
                    $data = [
                        'activities_table'  => $activities_table,
                        'mail_remarks'      => $planning['mail_remarks']
                    ];
                    foreach($data as $key => $val) {
                        if($key === 'activities_table') {
                            // try to remove <p></p> tags if activities table, cause not needed
                            $mail_content = str_replace('<p>{'.$key.'}</p>', $val, $mail_content);
                        }
                        $mail_content = str_replace('{'.$key.'}', $val, $mail_content);
                    }
                    break;
                }
            }

            $result[$id] = $mail_content;
        }

        return $result;
    }

    public static function getActions(): array {
        return [

            'refresh-mail-content' => [
                'description'   => "Refresh the mail content with a html template displaying the booking activities.",
                'policies'      => [],
                'function'      => 'doRefreshMailContent'
            ],

            'send-mail' => [
                'description'   => "Send the planning summary to the partner as a mail.",
                'policies'      => [],
                'function'      => 'doSendMail'
            ]

        ];
    }

    protected static function doRefreshMailContent($self) {
        $self->update(['mail_content' => null])
            ->read(['mail_content']);
    }

    protected static function doSendMail($self) {
        $self->read(['mail_subject', 'mail_content', 'sent_qty', 'partner_id' => ['email']]);
        foreach($self as $planning) {
            if (!isset($planning['partner_id']['email'])) {
                throw new \Exception("missing_partner_email", EQ_ERROR_INVALID_CONFIG);
            }
        }

        foreach($self as $id => $planning) {
            $message = new Email();
            $message->setTo($planning['partner_id']['email'])
                ->setSubject($planning['mail_subject'])
                ->setContentType('text/html')
                ->setBody($planning['mail_content']);

            Mail::queue($message, 'sale\booking\PartnerPlanningSummary', $id);

            PartnerPlanningSummary::id($id)
                ->update(['sent_qty' => ++$planning['sent_qty']]);
        }
    }

    public static function getActivitiesIds($id): array {
        $planning_summary = PartnerPlanningSummary::id($id)
            ->read(['date_from', 'date_to', 'partner_id' => ['relationship']])
            ->first();

        if(is_null($planning_summary)) {
            throw new \Exception("unknown_partner-planning-summary");
        }

        $domain = [
            ['activity_date', '>=', $planning_summary['date_from']],
            ['activity_date', '<=', $planning_summary['date_to']]
        ];

        if($planning_summary['partner_id']['relationship'] === 'employee') {
            $domain[] = ['employee_id', '=', $planning_summary['partner_id']['id']];
        }
        elseif($planning_summary['partner_id']['relationship'] === 'provider') {
            $provider = Provider::search([
                ['id', '=', $planning_summary['partner_id']['id']],
                ['relationship', '=', 'provider']
            ])
                ->read(['booking_activities_ids'])
                ->first();

            if(empty($provider['booking_activities_ids'])) {
                throw new \Exception("provider_must_have_activities", EQ_ERROR_INVALID_PARAM);
            }

            $domain[] = ['id', 'in', $provider['booking_activities_ids']];
        }
        else {
            throw new \Exception("partner_must_be_employee_or_provider", EQ_ERROR_INVALID_PARAM);
        }

        return BookingActivity::search($domain)->ids();
    }
}

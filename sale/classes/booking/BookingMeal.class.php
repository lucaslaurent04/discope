<?php
/*
    This file is part of the Discope property management software <https://github.com/discope-pms/discope>
    Some Rights Reserved, Discope PMS, 2020-2024
    Original author(s): Yesbabylon SRL
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;

use equal\orm\Model;

class BookingMeal extends Model {

    public static function getName() {
        return "Booking meal";
    }

    public static function getColumns() {
        return [

            /**
             * Booking
             */

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => "Booking the meal relates to."
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLineGroup',
                'description'       => "Booking line group the meal relates to."
            ],

            'booking_lines_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'sale\booking\BookingLine',
                'foreign_field'     => 'booking_meals_ids',
                'rel_table'         => 'sale_booking_line_rel_booking_meal',
                'rel_foreign_key'   => 'booking_line_id',
                'rel_local_key'     => 'booking_meal_id',
                'description'       => "All booking lines that are linked the meal (moment).",
            ],

            'is_self_provided' => [
                'type'              => 'boolean',
                'description'       => "Is the meal provided by the customer, not related to a booking line?",
                'default'           => false
            ],

            /**
             * Camp
             */

            'camp_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\camp\Camp',
                'description'       => "The camp the meal relates to."
            ],

            /**
             * Common
             */

            'date' => [
                'type'              => 'date',
                'description'       => "Date of the meal."
            ],

            'time_slot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\TimeSlot',
                'description'       => "Specific day time slot on which the service is delivered.",
                'dependents'        => ['time_slot_order']
            ],

            'time_slot_order' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => "Order of the time slot, used to sort meals.",
                'store'             => true,
                'instant'           => true,
                'relation'          => ['time_slot_id' => 'order']
            ],

            'meal_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\MealType',
                'description'       => "Type of the meal being served.",
                'default'           => 1
            ],

            'meal_place_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\MealPlace',
                'description'       => "Place where the meal is served.",
                'default'           => 1
            ]

        ];
    }

    public static function canupdate($self, $values): array {
        $self->read(['booking_id', 'camp_id', 'time_slot_id', 'date']);
        if(isset($values['booking_id']) || isset($values['booking_line_group_id']) || isset($values['booking_lines_ids'])) {
            foreach($self as $booking_meal) {
                if(isset($booking_meal['camp_id'])) {
                    return ['booking_id' => ['camp_meal' => "The meal is already related to a camp."]];
                }
            }
        }
        if(isset($values['camp_id'])) {
            foreach($self as $booking_meal) {
                if(isset($booking_meal['booking_id'])) {
                    return ['camp_id' => ['booking_meal' => "The meal is already related to a booking."]];
                }
            }
        }

        foreach($self as $id => $booking_meal) {
            $time_slot_id = $values['time_slot_id'] ??  $booking_meal['time_slot_id'];
            $date = $values['date'] ??  $booking_meal['date'];
            $booking_id = array_key_exists('booking_id', $values) ? $values['booking_id'] : $booking_meal['booking_id'];
            $camp_id = array_key_exists('camp_id', $values) ? $values['camp_id'] : $booking_meal['camp_id'];
            if(!is_null($booking_id)) {
                $meals_ids = self::search([
                    ['time_slot_id', '=', $time_slot_id],
                    ['date', '=', $date],
                    ['booking_id', '=', $booking_id],
                    ['id', '<>', $id]
                ])
                    ->ids();

                if(!empty($meals_ids)) {
                    return ['booking_id' => ['already_booked' => "A meal has already been booked for this time."]];
                }
            }
            elseif(!is_null($camp_id)) {
                $meals_ids = self::search([
                    ['time_slot_id', '=', $time_slot_id],
                    ['date', '=', $date],
                    ['camp_id', '=', $camp_id],
                    ['id', '<>', $id]
                ])
                    ->ids();

                if(!empty($meals_ids)) {
                    return ['camp_id' => ['already_booked' => "A meal has already been booked for this time."]];
                }
            }
        }

        return parent::canupdate($self, $values);
    }
}


/*
    * Les repas sont globaux à une réservation, mais doivent nécessairement se mettre sur un groupe de service (il peut donc y avoir plusieurs BookingMeal en parallèle [même date, même tranche horaire] pour une même réservation)
    * Les bookingMeal ne sont visibles que sur les groupes de type "sojourn" (pas "simple", "événement" ou "activité")
    * les bookingMeal peuvent être modifiés en UI, mais pas créés
    * les meals sont dans une section distincte "Repas" d'un bookingLineGroup

    refreshMeals()
        à chaque modification de bookingLine (product_id)
        à chaque modification de la durée d'un groupe de service
        à chaque modification du type d'un groupe de service

    Pour toutes les bookingLine de type repas (is_meal), on vérifie si un bookingMeal existe pour ce groupe (pour cette réservation), et pour le time_slot correspondant, pour chacune des dates du séjour
    si pas encore : on crée un bookingMeal
    ensuite on assigne automatiquement la ligne au bookingMeal
    (il peut y avoir plusieurs produits repas pour un même moment, comme variantes d'un même modèle [variation sur la tranche d'âge ou autre])


*/
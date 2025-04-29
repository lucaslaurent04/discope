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

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => "Booking the activity relates to."
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLineGroup',
                'description'       => "Booking line group the activity relates to."
            ],

            'booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingLine',
                'foreign_field'     => 'booking_meal_id',
                'description'       => "All booking lines that are linked the meal (moment).",
            ],

            'date' => [
                'type'              => 'date',
                'description'       => 'Date of the meal.'
            ],

            'time_slot_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\TimeSlot',
                'description'       => "Specific day time slot on which the service is delivered.",
                'onupdate'          => 'onupdateTimeSlotId'
            ],

            'meal_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\MealType',
                'description'       => 'Type of the meal being served.',
                'default'           => 1
            ],

            'meal_place' => [
                'type'              => 'string',
                'selection'         => ['indoor', 'outdoor', 'bbq_place'],
                'description'       => 'Place where the meal is served.',
                'default'           => 'indoor'
            ]

        ];
    }
}


/*
Les repas sont globaux à une réservation, mais doivent nécessairement se mettre sur un groupe de service (il peut donc y en avoir plusieurs en parallèle pour une même réservation)
Les bookingMeal ne sont visibles que sur les groupes de type "sojourn" (pas "simple", "événement ou "activité")

refreshMeals

* lorsqu'on créée une bookingline marquée is_meal dans un groupe "sojourn", on vérifie si un bookingMeal existe pour cetee réservation, ce groupe, le time_slot correspondant, pour chacune des date du séjour
si pas encore : on crée un bookingMeal
ensuite on assigne automatiquement la ligne au bookingMeal
(il peut y avoir plusieurs fois un produit repas pour un même moment, avec une variation sur la tranche d'âge)

* les bookingMeal peuvent être modifiés en UI, mais pas créés

* les meals sont dans une section distincte "Repas" d'un bookingLineGroup

*/
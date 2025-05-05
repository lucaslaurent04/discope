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
                'type'              => 'many2many',
                'foreign_object'    => 'sale\booking\BookingLine',
                'foreign_field'     => 'booking_meals_ids',
                'rel_table'         => 'sale_booking_line_rel_booking_meal',
                'rel_foreign_key'   => 'booking_line_id',
                'rel_local_key'     => 'booking_meal_id',
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

            'is_self_provided' => [
                'type'              => 'boolean',
                'description'       => "Is the meal provided by the customer, not related to a booking line.",
                'default'           => false
            ],

            'meal_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\MealType',
                'description'       => 'Type of the meal being served.',
                'default'           => 1
            ],

            'meal_place' => [   
                'type'              => 'string',
                'selection'         => [
                    'indoor',
                    'outdoor',
                    'bbq_place'
                ],
                'description'       => 'Place where the meal is served.',
                'default'           => 'indoor'
            ]

        ];
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
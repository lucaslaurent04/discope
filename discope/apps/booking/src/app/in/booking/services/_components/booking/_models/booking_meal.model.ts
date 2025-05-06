export class BookingMeal {
    // index signature
    [key: string]: any;
    // model entity
    public get entity():string { return 'sale\\booking\\BookingMeal' };
    // constructor with public properties
    constructor(
        public id: number = 0,
        public name: string = '',
        public booking_id: number = 0,
        public booking_line_group_id: number = 0,
        public booking_lines_ids: number[] = [],
        public date: Date = new Date(),
        public time_slot_id: number = 0,
        public is_self_provided: boolean = false,
        public meal_type_id: number = 0,
        public meal_place: 'indoor' | 'outdoor' | 'bbq_place' = 'indoor'
    ) {}
}

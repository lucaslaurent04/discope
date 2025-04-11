export class Activity {
    constructor(
        public id: number = 0,
        public name: string = '',
        public booking_line_group_id: number = 0,
        public activity_date: Date = new Date(),
        public activity_booking_line_id: number = 0,
        public time_slot_id: number = 0,
        public group_num: number = 0,
        public is_virtual: boolean = false,
        public has_staff_required: boolean = false,
        public employee_id: number = 0,
        public has_provider: boolean = false
    ) {}
}

export class BookingLineGroup {
    constructor(
        public id: number = 0,
        public name: string = '',
        public date_from: Date = new Date(),
        public date_to: Date = new Date(),
        public activity_group_num: number = 0,
        public nb_pers: number = 0,
        public is_locked: boolean = false,
        public has_person_with_disability: boolean = false,
        public person_disability_description: string = '',
        public age_range_assignments_ids: number[] = []
    ) {}
}

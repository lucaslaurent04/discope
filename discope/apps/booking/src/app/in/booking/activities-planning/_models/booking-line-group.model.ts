export class BookingLineGroup {
    constructor(
        public id: number = 0,
        public name: string = '',
        public activity_group_num: number = 0,
        public nb_pers: number = 0,
        public is_locked: boolean = false,
        public age_range_assignments_ids: number[] = []
    ) {}
}

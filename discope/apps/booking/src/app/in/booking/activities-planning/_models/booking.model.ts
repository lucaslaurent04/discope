export class Booking {
    constructor(
        public id: number = 0,
        public name: string = '',
        public display_name: string = '',
        public created: Date = new Date(),
        public date_from: Date = new Date(),
        public date_to: Date = new Date(),
        public center_id: number = 0,
        public status: string = '',
        public activity_weeks_descriptions: string|null = null
    ) {}
}

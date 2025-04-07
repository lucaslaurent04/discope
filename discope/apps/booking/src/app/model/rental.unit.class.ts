
export class RentalUnitClass {

    constructor(
        public id: number = 0,
        public name: string = '',
        public description: string = '',
        public capacity: number = 0,
        public order: number = 0,
        public is_accomodation: boolean = false,
        public parent_id: number = 0
    ) {}

}

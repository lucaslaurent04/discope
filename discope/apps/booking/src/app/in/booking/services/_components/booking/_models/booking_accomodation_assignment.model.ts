export class BookingAccomodationAssignment {
    // index signature
    [key: string]: any;
    // model entity
    public get entity():string { return 'sale\\booking\\SojournProductModelRentalUnitAssignement'};
    // constructor with public properties
    constructor(
        public id: number = 0,
        public rental_unit_id: any = {},
        public qty: number = 0,
        public use_extra: boolean = false,
        public extra_qty: number = 0,
        public booking_line_group_id: number = 0,
    ) {}
}

export class BookingLine {
    constructor(
        public id: number = 0,
        public name: string = '',
        public product_id: number = 0,
        public qty: number = 0,
        public qty_accounting_method: 'accomodation'|'person'|'unit' = 'accomodation'
    ) {}
}

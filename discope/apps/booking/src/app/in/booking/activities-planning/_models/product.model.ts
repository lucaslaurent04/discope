export class Product {
    constructor(
        public id: number = 0,
        public name: string = '',
        public sku: string = '',
        public can_sell: boolean = true,
        public product_model_id: number = 0
    ) {}
}

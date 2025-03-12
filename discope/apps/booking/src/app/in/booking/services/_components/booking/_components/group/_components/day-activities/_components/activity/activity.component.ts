import { Component, EventEmitter, Input, Output } from '@angular/core';
import { ApiService } from 'sb-shared-lib';
import { BookingLineGroup } from '../../../../../../_models/booking_line_group.model';
import { FormControl, Validators } from '@angular/forms';
import { Observable, ReplaySubject } from 'rxjs';
import { debounceTime, map, mergeMap } from 'rxjs/operators';
import { Booking } from '../../../../../../_models/booking.model';
import { BookingActivity } from '../../../../../../_models/booking_activity.model';
import { MatDialog } from '@angular/material/dialog';
import { BookingServicesBookingGroupLinePriceDialogComponent } from '../../../line/_components/price.dialog/price.component';

interface vmModel {
    product: {
        name: string,
        formControl: FormControl,
        inputClue: ReplaySubject <any> ,
        filteredList: Observable <any> ,
        inputChange: (event: any) => void,
        focus: () => void,
        restore: () => void,
        reset: () => void,
        display: (type: any) => string
    },
    qty: {
        formControl: FormControl,
        change: () => void
    },
    providers: {
        formControls: FormControl[],
        change: () => void
    },
    rentalUnit: {
        formControl: FormControl,
        change: () => void
    }
}

@Component({
    selector: 'booking-services-booking-group-day-activities-activity',
    templateUrl: 'activity.component.html',
    styleUrls: ['activity.component.scss']
})
export class BookingServicesBookingGroupDayActivitiesActivityComponent {

    @Input() activity: BookingActivity | null;
    @Input() date: Date;
    @Input() timeSlot: any;
    @Input() group: BookingLineGroup;
    @Input() booking: Booking;
    @Input() opened: boolean = false;
    @Input() allowFulldaySelection: boolean = true;

    @Output() loadStart = new EventEmitter();
    @Output() loadEnd   = new EventEmitter();
    @Output() updated = new EventEmitter();
    @Output() deleteLine = new EventEmitter();
    @Output() open = new EventEmitter();
    @Output() close = new EventEmitter();

    public ready: boolean = false;

    public vm: vmModel;

    public mapTimeSlotCodeName: any = {
        'AM': 'Matin',
        'PM': 'Après-Midi',
        'EV': 'Soir',
    };

    public providersQty: number = 1;

    constructor(
        private api: ApiService,
        public dialog: MatDialog
    ) {
        this.vm = {
            product: {
                name: '',
                formControl: new FormControl(''),
                inputClue: new ReplaySubject(1),
                filteredList: new Observable(),
                inputChange: (event:any) => this.productInputChange(event),
                focus: () => this.productFocus(),
                restore: () => this.productRestore(),
                reset: () => this.productReset(),
                display: (type:any) => this.productDisplay(type)
            },
            qty: {
                formControl: new FormControl('', Validators.required),
                change: () => this.qtyChange()
            },
            providers: {
                formControls: [],
                change: () => this.providerChange()
            },
            rentalUnit: {
                formControl: new FormControl(null),
                change: () => this.rentalUnitChange()
            }
        };
    }

    public ngOnInit() {
        this.ready = true;

        if(!this.activity) {
            // listen to the changes on FormControl objects
            this.vm.product.filteredList = this.vm.product.inputClue.pipe(
                debounceTime(300),
                map((value:any) => (typeof value === 'string' ? value : ((value == null) ? '' : value.name))),
                mergeMap(async (name:string) => this.filterProducts(name))
            );

            this.vm.product.name = '';
            this.vm.qty.formControl.setValue(0);

            return;
        }

        this.vm.product.name = this.activity.activity_booking_line_id.product_id.name;
        this.vm.qty.formControl.setValue(this.activity.activity_booking_line_id.qty);

        this.providersQty = this.activity.activity_booking_line_id.qty_accounting_method === 'unit' ? this.activity.activity_booking_line_id.qty : 1;
        for(let i = 0; i < this.providersQty; i++) {
            let providerId: number | null = null;
            if(this.activity.providers_ids[i]) {
                providerId = parseInt(this.activity?.providers_ids[i]);
            }
            this.vm.providers.formControls.push(new FormControl(providerId));
        }

        if(this.activity.rental_unit_id) {
            this.vm.rentalUnit.formControl.setValue(this.activity.rental_unit_id);
        }
    }

    public toggleOpen() {
        this.opened ? this.close.emit() : this.open.emit();
    }

    private async filterProducts(name: string): Promise<any> {
        let filtered: any[] = [];
        try {
            let domain = [
                ['is_activity', '=', true]
            ];

            if(!this.allowFulldaySelection) {
                domain.push(['is_fullday', '=', false]);
            }

            if(name && name.length) {
                domain.push(['name', 'ilike', `%${name}%`]);
            }

            const productCollectParams: any = {
                    center_id: this.booking.center_id.id,
                    domain: JSON.stringify(domain),
                    date_from: this.booking.date_from.toISOString(),
                    date_to: this.booking.date_to.toISOString()
                };

            filtered = await this.api.fetch('?get=sale_catalog_product_collect', productCollectParams);
        }
        catch(response) {
            console.log(response);
        }
        return filtered;
    }

    private productInputChange(event:any) {
        this.vm.product.inputClue.next(event.target.value);
    }

    private productFocus() {
        this.vm.product.inputClue.next('');
    }

    private productDisplay(product:any): string {
        return (product && product.hasOwnProperty('name')) ? product.name : '';
    }

    private productReset() {
        setTimeout(() => {
            this.vm.product.name = '';
        }, 100);
    }

    private productRestore() {
        this.vm.product.formControl.setErrors(null);
        this.vm.product.name = this.activity?.activity_booking_line_id?.product_id ? this.activity.activity_booking_line_id.product_id.name : '';
    }

    public async onchangeProduct(event: any) {
        console.log('BookingServicesBookingGroupDayActivitiesActivityComponent::productChange', event)

        // from mat-autocomplete
        if(event && event.option && event.option.value) {
            let product = event.option.value;
            if(
                product.hasOwnProperty('name')
                && (typeof product.name === 'string' || product.name instanceof String)
                && product.name !== '[object Object]'
            ) {
                this.vm.product.name = product.name;
            }

            this.loadStart.emit();

            let newLine: any = null;

            // notify back-end about the change
            try {
                newLine = await this.api.create('sale\\booking\\BookingLine', {
                    order: this.group.booking_lines_ids.length + 1,
                    booking_id: this.booking.id,
                    booking_line_group_id: this.group.id,
                    service_date: this.date.getTime() / 1000,
                    time_slot_id: this.timeSlot.id
                });
                await this.api.call('?do=sale_booking_update-bookingline-product', {
                    id: newLine.id,
                    product_id: product.id
                });
                this.vm.product.formControl.setErrors(null);

                this.loadEnd.emit();

                // relay change to parent component
                this.updated.emit();
            }
            catch(response: any) {
                if(newLine) {
                    this.deleteLine.emit(newLine.id);
                }

                this.loadEnd.emit();

                this.api.errorFeedback(response);
            }
        }
    }

    public async qtyChange() {
        if(!this.activity?.activity_booking_line_id || this.activity.activity_booking_line_id.qty == this.vm.qty.formControl.value) {
            return;
        }

        // notify back-end about the change
        try {
            await this.api.update('sale\\booking\\BookingLine', [this.activity.activity_booking_line_id.id], {qty: this.vm.qty.formControl.value});
            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async providerChange() {
        let providersIds: number[] = [];
        for(let providerId of this.activity.providers_ids) {
            providersIds.push(-providerId)
        }
        for(let formControl of this.vm.providers.formControls) {
            if(formControl.value !== null) {
                providersIds.push(formControl.value);
            }
        }

        // notify back-end about the change
        try {
            await this.api.update('sale\\booking\\BookingActivity', [this.activity.id], {providers_ids: providersIds});
            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async rentalUnitChange() {
        // notify back-end about the change
        try {
            await this.api.update('sale\\booking\\BookingActivity', [this.activity.id], {rental_unit_id: this.vm.rentalUnit.formControl.value});
            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async oncreateTransport() {
        try {
            await this.api.create('sale\\booking\\BookingLine', {
                order: this.group.booking_lines_ids.length + 1,
                booking_id: this.booking.id,
                booking_line_group_id: this.group.id,
                service_date: this.date.getTime() / 1000,
                time_slot_id: this.timeSlot.id,
                booking_activity_id: this.activity.activity_booking_line_id.booking_activity_id,
                is_transport: true
            });

            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public async oncreateSupply() {
        try {
            await this.api.create('sale\\booking\\BookingLine', {
                order: this.group.booking_lines_ids.length + 1,
                booking_id: this.booking.id,
                booking_line_group_id: this.group.id,
                service_date: this.date.getTime() / 1000,
                time_slot_id: this.timeSlot.id,
                booking_activity_id: this.activity.activity_booking_line_id.booking_activity_id,
                is_supply: true
            });

            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public ondeleteActivityLine(lineId: number) {
        this.deleteLine.emit(lineId);
    }

    public openPriceEdition() {
        if(this.group.is_locked) {
            return;
        }

        const line = this.activity?.activity_booking_line_id;
        if(!line) {
            return;
        }

        const dialogRef = this.dialog.open(BookingServicesBookingGroupLinePriceDialogComponent, {
            width: '500px',
            height: '500px',
            data: { line }
        });

        dialogRef.afterClosed().subscribe(async (result) => {
            if(result) {
                if(line.unit_price != result.unit_price || line.vat != result.vat_rate) {
                    try {
                        await this.api.update('sale\\booking\\BookingLine', [line.id], {unit_price: result.unit_price, vat_rate: result.vat_rate});
                        // relay change to parent component
                        this.updated.emit();
                    }
                    catch(response) {
                        this.api.errorFeedback(response);
                    }
                }
            }
        });
    }
}

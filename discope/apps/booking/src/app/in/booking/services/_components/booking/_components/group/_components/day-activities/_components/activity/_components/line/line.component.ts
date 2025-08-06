import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { FormControl, Validators } from '@angular/forms';
import { Observable, ReplaySubject } from 'rxjs';
import { BookingLineGroup } from '../../../../../../../../_models/booking_line_group.model';
import { Booking } from '../../../../../../../../_models/booking.model';
import { ApiService } from 'sb-shared-lib';
import { BookingLine } from '../../../../../../../../_models/booking_line.model';
import { debounceTime, map, mergeMap } from 'rxjs/operators';
import { MatDialog } from '@angular/material/dialog';
import { BookingServicesBookingGroupLinePriceDialogComponent } from '../../../../../line/_components/price.dialog/price.component';
import { BookingActivity } from '../../../../../../../../_models/booking_activity.model';

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
    }
}

@Component({
    selector: 'booking-services-booking-group-day-activities-activity-line',
    templateUrl: 'line.component.html',
    styleUrls: ['line.component.scss']
})
export class BookingServicesBookingGroupDayActivitiesActivityLineComponent implements OnInit {

    @Input() line: BookingLine | null;
    @Input() group: BookingLineGroup;
    @Input() booking: Booking;
    @Input() activity: BookingActivity | null;

    @Output() loadStart = new EventEmitter();
    @Output() loadEnd = new EventEmitter();
    @Output() updated = new EventEmitter();

    public ready = false;

    public vm: vmModel;

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
            }
        };
    }

    public ngOnInit() {
        this.ready = true;

        // listen to the changes on FormControl objects
        this.vm.product.filteredList = this.vm.product.inputClue.pipe(
            debounceTime(300),
            map((value:any) => (typeof value === 'string' ? value : ((value == null) ? '' : value.name))),
            mergeMap(async (name:string) => this.filterProducts(name))
        );

        this.vm.product.name = this.line?.product_id ? this.line.product_id.name : '';
        this.vm.qty.formControl.setValue(this.line?.qty ? this.line.qty : 0);
    }

    private async filterProducts(name: string): Promise<any> {
        let filtered: any[] = [];
        try {
            let domain = [
                ['is_pack', '=', false]
            ];

            if(this.line.is_transport) {
                domain.push(['is_transport', '=', true]);
            }
            else {
                domain.push(['is_supply', '=', true]);
            }

            if(name && name.length) {
                domain.push(['name', 'ilike', `%${name}%`]);
            }

            const params: {[key: string]: any} = {
                    center_id: this.booking.center_id.id,
                    domain: JSON.stringify(domain),
                    date_from: this.booking.date_from.toISOString(),
                    date_to: this.booking.date_to.toISOString()
                };

            filtered = await this.api.fetch('?get=sale_catalog_product_collect', params);
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
        this.vm.product.name = this.line?.product_id ? this.line.product_id.name : '';
    }

    public async onchangeProduct(event: any) {
        console.log('BookingServicesBookingGroupDayActivitiesActivityLineComponent::productChange', event)

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

            // notify back-end about the change
            try {
                this.loadStart.emit();

                await this.api.call('?do=sale_booking_update-bookingline-activity-product', {
                    id: this.line.id,
                    booking_activity_id: this.activity.id,
                    product_id: product.id
                });
                this.vm.product.formControl.setErrors(null);

                this.loadEnd.emit();

                // relay change to parent component
                this.updated.emit();
            }
            catch(response) {
                this.vm.product.formControl.setErrors({ 'missing_price': 'Pas de liste de prix pour ce produit.' });
                this.loadEnd.emit();
                this.api.errorFeedback(response);
            }
        }
    }

    public async qtyChange() {
        if(!this.line || this.line.qty == this.vm.qty.formControl.value) {
            return;
        }

        // notify back-end about the change
        try {
            await this.api.update('sale\\booking\\BookingLine', [this.line.id], {qty: this.vm.qty.formControl.value});
            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    public openPriceEdition() {
        if(this.group.is_locked) {
            return;
        }

        if(!this.line) {
            return;
        }

        const dialogRef = this.dialog.open(BookingServicesBookingGroupLinePriceDialogComponent, {
            width: '500px',
            height: '500px',
            data: { line: this.line }
        });

        dialogRef.afterClosed().subscribe(async (result) => {
            if(result) {
                if(this.line.unit_price != result.unit_price || this.line.vat != result.vat_rate) {
                    try {
                        await this.api.update('sale\\booking\\BookingLine', [this.line.id], {unit_price: result.unit_price, vat_rate: result.vat_rate});
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

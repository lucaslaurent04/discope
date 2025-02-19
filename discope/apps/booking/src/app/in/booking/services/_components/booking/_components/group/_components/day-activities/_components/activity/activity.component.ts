import {Component, EventEmitter, Input, Output} from '@angular/core';
import { ApiService } from 'sb-shared-lib';
import { BookingLineGroup } from '../../../../../../_models/booking_line_group.model';
import { FormControl } from '@angular/forms';
import { Observable, ReplaySubject } from 'rxjs';
import { debounceTime, map, mergeMap } from 'rxjs/operators';
import { Booking } from '../../../../../../_models/booking.model';
import { BookingLine } from '../../../../../../_models/booking_line.model';

interface vmModel {
    product: {
        name: string,
        formControl: FormControl,
        inputClue: ReplaySubject < any > ,
        filteredList: Observable < any > ,
        inputChange: (event: any) => void,
        focus: () => void,
        restore: () => void,
        reset: () => void,
        display: (type: any) => string
    }
}

@Component({
    selector: 'booking-services-booking-group-day-activities-activity',
    templateUrl: 'activity.component.html',
    styleUrls: ['activity.component.scss']
})
export class BookingServicesBookingGroupDayActivitiesActivityComponent {

    @Input() activityBookingLine: BookingLine | null;
    @Input() date: Date;
    @Input() timeSlotId: number;
    @Input() group: BookingLineGroup;
    @Input() booking: Booking;

    @Output() updated = new EventEmitter();

    public ready: boolean = false;

    public vm: vmModel;

    constructor(
        private api: ApiService
    ) {
        this.vm = {
            product: {
                name:           '',
                formControl:    new FormControl(''),
                inputClue:      new ReplaySubject(1),
                filteredList:   new Observable(),
                inputChange:    (event:any) => this.productInputChange(event),
                focus:          () => this.productFocus(),
                restore:        () => this.productRestore(),
                reset:          () => this.productReset(),
                display:        (type:any) => this.productDisplay(type)
            }
        };
    }

    public ngOnInit() {
        this.ready = true;

        // listen to the changes on FormControl objects
        this.vm.product.filteredList = this.vm.product.inputClue.pipe(
            debounceTime(300),
            map( (value:any) => (typeof value === 'string' ? value : (value == null)?'':value.name) ),
            mergeMap( async (name:string) => this.filterProducts(name) )
        );

        this.vm.product.name = this.activityBookingLine?.product_id ? this.activityBookingLine.product_id.name : '';
    }

    private async filterProducts(name: string): Promise<any> {
        let filtered: any[] = [];
        try {
            let domain = [
                ["is_pack", "=", false]
            ];

            if(name && name.length) {
                domain.push(["name", "ilike", '%'+name+'%']);
            }

            filtered = await this.api.fetch('?get=sale_catalog_product_collect', {
                center_id: this.booking.center_id.id,
                domain: JSON.stringify(domain),
                date_from: this.booking.date_from.toISOString(),
                date_to: this.booking.date_to.toISOString()
            });
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
        this.vm.product.name = this.activityBookingLine?.product_id ? this.activityBookingLine.product_id.name : '';
    }

    public async onchangeProduct(event:any) {
        console.log('BookingEditCustomerComponent::productChange', event)

        // from mat-autocomplete
        if(event && event.option && event.option.value) {
            let product = event.option.value;
            if(product.hasOwnProperty('name') && (typeof product.name === 'string' || product.name instanceof String) && product.name !== '[object Object]') {
                this.vm.product.name = product.name;
            }
            // notify back-end about the change
            try {
                const new_line: any = await this.api.create("sale\\booking\\BookingLine", {
                    order: this.group.booking_lines_ids.length + 1,
                    booking_id: this.booking.id,
                    booking_line_group_id: this.group.id,
                    service_date: this.date.getTime() / 1000,
                    time_slot_id: this.timeSlotId
                });
                await this.api.call('?do=sale_booking_update-bookingline-product', {
                    id: new_line.id,
                    product_id: product.id
                });
                this.vm.product.formControl.setErrors(null);

                // relay change to parent component
                this.updated.emit();
            }
            catch(response) {
                this.vm.product.formControl.setErrors({ 'missing_price': 'Pas de liste de prix pour ce produit.' });
                this.api.errorFeedback(response);
            }
        }
    }
}

import { Component, ElementRef, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges, ViewChild } from '@angular/core';
import { Activity } from '../../_models/activity.model';
import { FormControl } from '@angular/forms';
import { Observable, ReplaySubject } from 'rxjs';
import { BookingLineGroup } from '../../_models/booking-line-group.model';
import { Product } from '../../_models/product.model';
import { ApiService } from 'sb-shared-lib';
import { debounceTime, map, mergeMap } from 'rxjs/operators';
import { Booking } from '../../_models/booking.model';
import { Partner } from '../../_models/partner.model';

interface vmModel {
    product: {
        name: string,
        formControl: FormControl,
        inputClue: ReplaySubject <any> ,
        filteredList: Observable <any> ,
        inputChange: (event: any) => void,
        focus: () => void,
        restore: () => void,
        display: (type: any) => string
    },
    employee: {
        formControl: FormControl,
        change: () => void
    },
    providers: {
        formControls: FormControl[],
        change: () => void
    }
}

@Component({
    selector: 'booking-activities-planning-activity-details',
    templateUrl: 'activity-details.component.html',
    styleUrls: ['activity-details.component.scss']
})
export class BookingActivitiesPlanningActivityDetailsComponent implements OnInit, OnChanges {

    @Input() booking: Booking;
    @Input() activity: Activity|null;
    @Input() timeSlot: 'AM'|'PM'|'EV';
    @Input() group: BookingLineGroup|null;
    @Input() employees: Partner[];
    @Input() providers: Partner[];

    @Output() productSelected = new EventEmitter<Product>();
    @Output() activityDeleted = new EventEmitter();
    @Output() employeeChanged = new EventEmitter<{employeeId: number, onFail: () => void}>();
    @Output() providersChanged = new EventEmitter<{providersIds: number[], onFail: () => void}>();

    @ViewChild('inputField') inputField!: ElementRef;

    public vm: vmModel;

    constructor(
        public api: ApiService
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
                display: (type:any) => this.productDisplay(type)
            },
            employee: {
                formControl: new FormControl(null),
                change: () => this.employeeChange()
            },
            providers: {
                formControls: [],
                change: () => this.providerChange()
            }
        };
    }

    public ngOnInit() {
        console.log('init BookingActivitiesPlanningActivityDetailsComponent');

        this.vm.product.filteredList = this.vm.product.inputClue.pipe(
            debounceTime(300),
            map((value:any) => (typeof value === 'string' ? value : ((value == null) ? '' : value.name))),
            mergeMap(async (name:string) => this.filterProducts(name))
        );
    }

    public ngOnChanges(changes: SimpleChanges) {
        if(changes.activity) {
            if(!this.activity) {
                this.vm.product.name = '';
            }
            else {
                this.vm.product.name = this.activity.name;

                if(this.activity.has_staff_required) {
                    this.vm.employee.formControl.setValue(this.activity.employee_id);
                }

                const providersFormControls = [];
                for(let i = 0; i < this.activity.qty; i++) {
                    let providerId: number | null = null;
                    if(this.activity.providers_ids?.[i]) {
                        providerId = +this.activity.providers_ids[i];
                    }
                    providersFormControls.push(new FormControl(providerId));
                }
                this.vm.providers.formControls = providersFormControls;
            }
        }
    }

    private async filterProducts(name: string): Promise<any> {
        let filtered: any[] = [];
        try {
            let domain = [
                ['is_activity', '=', true],
                ['is_fullday', '=', false]
            ];

            if(name && name.length) {
                domain.push(['name', 'ilike', `%${name}%`]);
            }

            const productCollectParams: any = {
                center_id: this.booking.center_id,
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

    private productRestore() {
        this.vm.product.formControl.setErrors(null);
        this.vm.product.name = this.activity ? this.activity.name : '';
    }

    private productDisplay(product: any): string {
        return (product && product.hasOwnProperty('name')) ? product.name : '';
    }

    public async onchangeProduct(event: any) {
        console.log('BookingActivitiesPlanningActivityDetailsComponent::productChange');

        // from mat-autocomplete
        if(event && event.option && event.option.value) {
            this.vm.product.name = event.option.value.name;

            this.productSelected.emit(event.option.value);
        }
    }

    public async ondeleteActivity() {
        this.activityDeleted.emit();
    }

    public employeeChange() {
        this.employeeChanged.emit({
            employeeId: this.vm.employee.formControl.value,
            onFail: () => {
                this.vm.employee.formControl.setValue(this.activity.employee_id);
            }
        });
    }

    public providerChange() {
        const providersIds: number[] = this.vm.providers.formControls.map((formControl) => formControl.value);

        this.providersChanged.emit({
            providersIds,
            onFail: () => {
                let i = 0;
                for(let formControl of this.vm.providers.formControls) {
                    if(this.activity.providers_ids[i]) {
                        formControl.setValue(this.activity.providers_ids[i]);
                    }
                    else {
                        formControl.setValue(null);
                    }
                    i++;
                }
            }
        });
    }

    public focusInput(): void {
        this.inputField.nativeElement.focus();
    }
}

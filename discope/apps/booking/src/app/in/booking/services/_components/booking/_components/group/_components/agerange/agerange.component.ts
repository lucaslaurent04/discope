import { Component, OnInit, Output, Input, EventEmitter, AfterViewInit } from '@angular/core';
import { ApiService, TreeComponent } from 'sb-shared-lib';
import { FormControl, Validators } from '@angular/forms';
import { BookingLineGroup } from '../../../../_models/booking_line_group.model';
import { BookingAgeRangeAssignment } from '../../../../_models/booking_agerange_assignment.model';
import { Booking } from '../../../../_models/booking.model';
import { debounceTime } from 'rxjs/operators';

interface BookingGroupAgeRangeComponentsMap {
}

@Component({
  selector: 'booking-services-booking-group-agerangeassignment',
  templateUrl: 'agerange.component.html',
  styleUrls: ['agerange.component.scss']
})
export class BookingServicesBookingGroupAgeRangeComponent extends TreeComponent<BookingAgeRangeAssignment, BookingGroupAgeRangeComponentsMap> implements OnInit, AfterViewInit  {
    // server-model relayed by parent
    @Input() set model(values: any) { this.update(values) }
    @Input() agerange: BookingAgeRangeAssignment;
    @Input() group: BookingLineGroup;
    @Input() booking: Booking;
    @Output() updated = new EventEmitter();
    @Output() updating = new EventEmitter();
    @Output() deleted = new EventEmitter();

    public ready: boolean = false;

    public age_range_id: number;

    public ageFromFormControl: FormControl;
    public ageToFormControl: FormControl;
    public qtyFormControl: FormControl;

    constructor(
        private api: ApiService
    ) {
        super( new BookingAgeRangeAssignment() );

        this.ageFromFormControl = new FormControl('', [Validators.required, Validators.min(0), Validators.max(99)]);
        this.ageToFormControl = new FormControl('', [Validators.required, Validators.min(0), Validators.max(99)]);
        this.qtyFormControl = new FormControl('', [Validators.required, Validators.min(1)]);
    }

    public ngAfterViewInit() {
        this.age_range_id = this.instance.age_range_id;
        this.ageFromFormControl.setValue(this.instance.age_from);
        this.ageToFormControl.setValue(this.instance.age_to);
        this.qtyFormControl.setValue(this.instance.qty);
    }

    public ngOnInit() {
        this.ready = true;

        this.qtyFormControl.valueChanges.pipe(debounceTime(500)).subscribe( () => {
            if(this.qtyFormControl.invalid) {
                this.qtyFormControl.markAsTouched();
                return;
            }
        });
    }

    public async update(values: any) {
        super.update(values);

        // assign VM values
        this.ageFromFormControl.setValue(this.instance.age_from);
        this.ageToFormControl.setValue(this.instance.age_to);
        this.qtyFormControl.setValue(this.instance.qty);
    }

    public async onupdateAgeRange(age_range: any) {
        this.age_range_id = age_range.id;
        /*
        if(this.qtyFormControl.value <= 0) {
            return;
        }
        this.updating.emit(true);
        let prev_age_range_id = this.instance.age_range_id;
        this.instance.age_range_id = age_range.id;
        // notify back-end about the change
        try {
            await this.api.update(this.instance.entity, [this.instance.id], {
                age_range_id: age_range.id,
            });
            // relay change to parent component (update nb_pers)
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
            // rollback
            this.instance.age_range_id = prev_age_range_id;
        }
        this.updating.emit(false);
        */
    }

    public async onupdateQty() {
        if(this.qtyFormControl.invalid || this.age_range_id <= 0) {
            return;
        }

        this.updating.emit(true);

        // notify back-end about the change
        try {
            await this.api.fetch('?do=sale_booking_update-sojourn-agerange-set', {
                id: this.group.id,
                age_range_id: this.age_range_id,
                age_range_assignment_id: this.instance.id,
                qty: this.qtyFormControl.value
            });

            // relay change to parent component (update nb_pers)
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.updating.emit(false);
    }

    public async onupdateAgeFrom() {
        if(this.ageFromFormControl.invalid) {
            return;
        }

        this.updating.emit(true);

        // notify back-end about the change
        try {
            await this.api.update('sale\\booking\\BookingLineGroupAgeRangeAssignment', [this.instance.id], {age_from: this.ageFromFormControl.value});

            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.updating.emit(false);
    }

    public async onupdateAgeTo() {
        if(this.ageToFormControl.invalid) {
            return;
        }

        this.updating.emit(true);

        // notify back-end about the change
        try {
            await this.api.update('sale\\booking\\BookingLineGroupAgeRangeAssignment', [this.instance.id], {age_to: this.ageToFormControl.value});

            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.updating.emit(false);
    }
}

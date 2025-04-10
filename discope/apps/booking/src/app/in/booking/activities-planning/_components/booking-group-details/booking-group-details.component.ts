import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { BookingLineGroup } from '../../_models/booking-line-group.model';
import { FormControl, Validators } from '@angular/forms';

@Component({
    selector: 'booking-activities-planning-booking-group-details',
    templateUrl: 'booking-group-details.component.html',
    styleUrls: ['booking-group-details.component.scss']
})
export class BookingActivitiesPlanningBookingGroupDetailsComponent implements OnChanges {

    @Input() group: BookingLineGroup|null;

    @Output() nbPersChanged = new EventEmitter<number>();

    public nbPersFormControl: FormControl;

    constructor(
    ) {
        this.nbPersFormControl = new FormControl(0, [Validators.min(1)]);
    }

    public ngOnChanges(changes: SimpleChanges) {
        if(changes.group && this.group) {
            this.nbPersFormControl.setValue(this.group.nb_pers);
        }
    }

    public async onNbPersChanges() {
        if(!this.nbPersFormControl.valid) {
            return;
        }

        this.nbPersChanged.emit(this.nbPersFormControl.value);
    }
}

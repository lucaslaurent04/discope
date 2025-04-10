import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { BookingLineGroup } from '../../_models/booking-line-group.model';
import { AbstractControl, FormControl, ValidationErrors, ValidatorFn, Validators } from '@angular/forms';
import { AgeRangeAssignment } from '../../_models/age-range-assignment.model';

@Component({
    selector: 'booking-activities-planning-booking-group-details',
    templateUrl: 'booking-group-details.component.html',
    styleUrls: ['booking-group-details.component.scss']
})
export class BookingActivitiesPlanningBookingGroupDetailsComponent implements OnChanges {

    @Input() group: BookingLineGroup|null;
    @Input() mapGroupAgeRangeAssignment: {[groupId: number]: AgeRangeAssignment};

    @Output() nbPersChanged = new EventEmitter<number>();
    @Output() ageFromChanged = new EventEmitter<number>();
    @Output() ageToChanged = new EventEmitter<number>();

    public nbPersFormControl: FormControl;
    public ageFromFormControl: FormControl;
    public ageToFormControl: FormControl;

    constructor(
    ) {
        this.nbPersFormControl = new FormControl(0, [Validators.min(1)]);
        this.ageFromFormControl = new FormControl(0, [Validators.min(0), Validators.max(99), this.maxAgeTo()]);
        this.ageToFormControl = new FormControl(0, [Validators.min(0), Validators.max(99), this.minAgeFrom()]);
    }

    public maxAgeTo(): ValidatorFn {
        return (control: AbstractControl): ValidationErrors | null => {
            if(!this.group || !this.mapGroupAgeRangeAssignment[this.group.id]) {
                return null;
            }

            const ageFrom = control.value;

            return ageFrom > this.mapGroupAgeRangeAssignment[this.group.id].age_to ? { maxAgeTo: true } : null;
        };
    }

    public minAgeFrom(): ValidatorFn {
        return (control: AbstractControl): ValidationErrors | null => {
            if(!this.group || !this.mapGroupAgeRangeAssignment[this.group.id]) {
                return null;
            }

            const ageTo = control.value;

            return ageTo < this.mapGroupAgeRangeAssignment[this.group.id].age_from ? { minAgeFrom: true } : null;
        };
    }

    public ngOnChanges(changes: SimpleChanges) {
        if(changes.group && this.group) {
            this.nbPersFormControl.setValue(this.group.nb_pers);

            if(this.mapGroupAgeRangeAssignment[this.group.id]) {
                const ageRangeAssign = this.mapGroupAgeRangeAssignment[this.group.id];

                this.ageFromFormControl.setValue(ageRangeAssign.age_from);
                this.ageToFormControl.setValue(ageRangeAssign.age_to);
            }
        }
        if(changes.mapGroupAgeRangeAssignment && this.group && this.mapGroupAgeRangeAssignment[this.group.id]) {
            const ageRangeAssign = this.mapGroupAgeRangeAssignment[this.group.id];

            this.ageFromFormControl.setValue(ageRangeAssign.age_from);
            this.ageToFormControl.setValue(ageRangeAssign.age_to);
        }
    }

    public async onNbPersChanges() {
        if(!this.nbPersFormControl.valid) {
            return;
        }

        this.nbPersChanged.emit(this.nbPersFormControl.value);
    }

    public async onAgeFromChanges() {
        if(!this.ageFromFormControl.valid) {
            return;
        }

        this.ageFromChanged.emit(this.ageFromFormControl.value);
    }

    public async onAgeToChanges() {
        if(!this.ageToFormControl.valid) {
            return;
        }

        this.ageToChanged.emit(this.ageToFormControl.value);
    }
}

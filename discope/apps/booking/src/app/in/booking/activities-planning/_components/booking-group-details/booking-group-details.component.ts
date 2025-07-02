import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { BookingLineGroup } from '../../_models/booking-line-group.model';
import { AbstractControl, FormControl, ValidationErrors, ValidatorFn, Validators } from '@angular/forms';
import { AgeRangeAssignment } from '../../_models/age-range-assignment.model';
import { MatDialog } from '@angular/material/dialog';
import { BookingActivitiesPlanningBookingGroupDetailDialogParticipantsOptionsComponent } from './_components/dialog-participants-options/dialog-participants-options.component';

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
    @Output() hasPersonWithDisabilityChanged = new EventEmitter<boolean>();
    @Output() participantsOptionsChanged = new EventEmitter<{ person_disability_description: string }>();

    public nbPersFormControl: FormControl;
    public ageFromFormControl: FormControl;
    public ageToFormControl: FormControl;
    public hasPersonWithDisabilityFormControl: FormControl;

    constructor(
        private dialog: MatDialog,
    ) {
        this.nbPersFormControl = new FormControl(0, [Validators.min(1)]);
        this.ageFromFormControl = new FormControl(0, [Validators.min(0), Validators.max(99), this.maxAgeTo()]);
        this.ageToFormControl = new FormControl(0, [Validators.min(0), Validators.max(99), this.minAgeFrom()]);
        this.hasPersonWithDisabilityFormControl = new FormControl(false);
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
            this.hasPersonWithDisabilityFormControl.setValue(this.group.has_person_with_disability);

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

    public async onHasPersonWithDisabilityChanges() {
        if(!this.hasPersonWithDisabilityFormControl.valid) {
            return;
        }

        this.hasPersonWithDisabilityChanged.emit(this.hasPersonWithDisabilityFormControl.value);
    }

    public onclickParticipantsInfo() {
        const dialogRef = this.dialog.open(BookingActivitiesPlanningBookingGroupDetailDialogParticipantsOptionsComponent, {
            width: '33vw',
            data: {
                person_disability_description: this.group.person_disability_description
            }
        });

        dialogRef.afterClosed().subscribe(async (result) => {
            if(result) {
                if(this.group.person_disability_description != result.person_disability_description) {
                    this.participantsOptionsChanged.emit({
                        person_disability_description: result.person_disability_description
                    });
                }
            }
        });
    }
}

import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { FormControl } from '@angular/forms';

interface vmModel {
    person_disability_description: {
        formControl: FormControl
    }
}

@Component({
    selector: 'booking-activities-planning-booking-group-details-dialog-participants-options',
    templateUrl: './dialog-participants-options.component.html',
    styleUrls: ['./dialog-participants-options.component.scss']
})
export class BookingActivitiesPlanningBookingGroupDetailDialogParticipantsOptionsComponent implements OnInit  {

    public vm: vmModel;

    constructor(
        public dialogRef: MatDialogRef<BookingActivitiesPlanningBookingGroupDetailDialogParticipantsOptionsComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any
    ) {
        this.vm = {
            person_disability_description: {
                formControl: new FormControl(data.person_disability_description)
            }
        };
    }

    public ngOnInit() {
    }

    public onClose() {
        this.dialogRef.close();
    }

    public onSave() {
        if(this.vm.person_disability_description.formControl.invalid) {
            console.warn('invalid person disability description');
            return;
        }
        this.dialogRef.close({
            person_disability_description: this.vm.person_disability_description.formControl.value
        });
    }
}

import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { FormControl } from '@angular/forms';

interface vmModel {
    has_person_with_disability: {
        formControl: FormControl
    },
    person_disability_description: {
        formControl: FormControl
    }
}

@Component({
    selector: 'booking-services-booking-group-dialog-participants-options',
    templateUrl: './dialog-participants-options.component.html',
    styleUrls: ['./dialog-participants-options.component.scss']
})
export class BookingServicesBookingGroupDialogParticipantsOptionsComponent implements OnInit  {

    public vm: vmModel;

    constructor(
        public dialogRef: MatDialogRef<BookingServicesBookingGroupDialogParticipantsOptionsComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any
    ) {
        this.vm = {
            has_person_with_disability: {
                formControl: new FormControl(data.has_person_with_disability)
            },
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
        if(this.vm.has_person_with_disability.formControl.invalid) {
            console.warn('invalid has person with disability');
            return;
        }
        if(this.vm.person_disability_description.formControl.invalid) {
            console.warn('invalid person disability description');
            return;
        }
        this.dialogRef.close({
            has_person_with_disability: this.vm.has_person_with_disability.formControl.value,
            person_disability_description: this.vm.person_disability_description.formControl.value
        });
    }
}

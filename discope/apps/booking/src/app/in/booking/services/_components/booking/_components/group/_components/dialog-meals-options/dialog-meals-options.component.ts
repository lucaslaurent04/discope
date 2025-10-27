import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { FormControl } from '@angular/forms';

interface vmModel {
    meal_prefs_description: {
        formControl: FormControl
    }
}

@Component({
    selector: 'booking-services-booking-group-dialog-participants-options',
    templateUrl: './dialog-meals-options.component.html',
    styleUrls: ['./dialog-meals-options.component.scss']
})
export class BookingServicesBookingGroupDialogMealsOptionsComponent implements OnInit  {

    public vm: vmModel;

    constructor(
        public dialogRef: MatDialogRef<BookingServicesBookingGroupDialogMealsOptionsComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any
    ) {
        this.vm = {
            meal_prefs_description: {
                formControl: new FormControl(data.meal_prefs_description)
            }
        };
    }

    public ngOnInit() {
    }

    public onClose() {
        this.dialogRef.close();
    }

    public onSave() {
        if(this.vm.meal_prefs_description.formControl.invalid) {
            console.warn('invalid meal prefs description');
            return;
        }
        this.dialogRef.close({
            meal_prefs_description: this.vm.meal_prefs_description.formControl.value
        });
    }
}

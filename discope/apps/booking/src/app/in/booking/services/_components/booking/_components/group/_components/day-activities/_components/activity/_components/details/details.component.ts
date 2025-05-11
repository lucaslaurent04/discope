import { Component, HostListener, Inject } from '@angular/core';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { FormControl, Validators } from '@angular/forms';

interface vmModel {
    schedule_from: {
        formControl: FormControl
    },
    schedule_to: {
        formControl: FormControl
    },
    description: {
        formControl: FormControl
    }
}

@Component({
    selector: 'booking-services-booking-group-day-activities-activity-details',
    templateUrl: 'details.component.html',
    styleUrls: ['details.component.scss']
})
export class BookingServicesBookingGroupDayActivitiesActivityDetailsDialogComponent {

    public vm: vmModel;

    @HostListener('window:keyup.Enter', ['$event'])
    onDialogClick(event: KeyboardEvent): void {
        this.onSave();
    }

    public quillCustomModules = {
        toolbar: [
            ['bold', 'italic', 'underline'],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            [{ 'color': <any>[] }, { 'background': <any>[] }],
            ['link']
        ]
    };

    constructor(
        public dialogRef: MatDialogRef<BookingServicesBookingGroupDayActivitiesActivityDetailsDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any
    ) {
        this.vm = {
            schedule_from: {
                formControl: new FormControl(this.data.schedule_from, [
                    Validators.required,
                    Validators.pattern(/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/)
                ])
            },
            schedule_to: {
                formControl: new FormControl(this.data.schedule_to, [
                    Validators.required,
                    Validators.pattern(/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/)
                ])
            },
            description: {
                formControl: new FormControl(this.data.description)
            }
        };
    }

    // prevent closing dialog when enter key is pressed within editor
    public onEditorReady(quill: any) {
        quill.root.addEventListener('keydown', (e: KeyboardEvent) => {
            if (e.key === 'Enter') {
                e.stopPropagation();
            }
        });
    }

    public onClose(): void {
        this.dialogRef.close();
    }

    public onSave(): void {
        if(this.vm.schedule_from.formControl.invalid) {
            console.warn('invalid schedule from');
            return;
        }
        if(this.vm.schedule_to.formControl.invalid) {
            console.warn('invalid schedule to');
            return;
        }
        if(this.vm.description.formControl.invalid) {
            console.warn('invalid description');
            return;
        }

        this.dialogRef.close(this);
    }
}

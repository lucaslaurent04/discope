import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { Activity } from '../../_models/activity.model';
import { FormControl, Validators } from '@angular/forms';
import { ApiService } from 'sb-shared-lib';

interface vmModel {
    scheduleFrom: {
        formControl: FormControl,
        change: () => void
    },
    scheduleTo: {
        formControl: FormControl,
        change: () => void
    }
}

@Component({
    selector: 'booking-activities-planning-activity-schedule',
    templateUrl: 'activity-schedule.component.html',
    styleUrls: ['activity-schedule.component.scss']
})
export class BookingActivitiesPlanningActivityScheduleComponent implements OnChanges {

    @Input() activity: Activity|null;

    @Output() scheduleFromChanged = new EventEmitter<string>();
    @Output() scheduleToChanged = new EventEmitter<string>();

    public vm: vmModel;

    constructor(
        public api: ApiService
    ) {
        this.vm = {
            scheduleFrom: {
                formControl: new FormControl('', [Validators.pattern(/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/)]),
                change: () => this.scheduleFromChange()
            },
            scheduleTo: {
                formControl: new FormControl('', Validators.pattern(/^(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/)),
                change: () => this.scheduleToChange()
            }
        };
    }

    public ngOnChanges(changes: SimpleChanges) {
        if(changes.activity) {
            if(this.activity) {
                this.vm.scheduleFrom.formControl.setValue(this.activity.schedule_from);
                this.vm.scheduleTo.formControl.setValue(this.activity.schedule_to);
            }
        }
    }

    public async scheduleFromChange() {
        if(!this.vm.scheduleFrom.formControl.valid) {
            return;
        }

        this.scheduleFromChanged.emit(this.vm.scheduleFrom.formControl.value);
    }

    public async scheduleToChange() {
        if(!this.vm.scheduleTo.formControl.valid) {
            return;
        }

        this.scheduleToChanged.emit(this.vm.scheduleTo.formControl.value);
    }
}

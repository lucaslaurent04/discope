import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { Activity } from '../../_models/activity.model';
import { AbstractControl, FormControl, ValidationErrors, ValidatorFn, Validators } from '@angular/forms';
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
                formControl: new FormControl('', [Validators.pattern(/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/), this.maxScheduleTo()]),
                change: () => this.scheduleFromChange()
            },
            scheduleTo: {
                formControl: new FormControl('', [Validators.pattern(/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/), this.minScheduleFrom()]),
                change: () => this.scheduleToChange()
            }
        };
    }

    private timeToSeconds(timeStr: string) {
        const [hours, minutes] = timeStr.split(':').map(Number);
        return hours * 3600 + minutes * 60;
    }

    public maxScheduleTo(): ValidatorFn {
        return (control: AbstractControl): ValidationErrors | null => {
            if(!this.activity) {
                return null;
            }

            const scheduleFrom = control.value;

            return this.timeToSeconds(scheduleFrom) > this.timeToSeconds(this.activity.schedule_to) ? { maxScheduleTo: true } : null;
        };
    }

    public minScheduleFrom(): ValidatorFn {
        return (control: AbstractControl): ValidationErrors | null => {
            if(!this.activity) {
                return null;
            }

            const scheduleTo = control.value;

            return this.timeToSeconds(scheduleTo) < this.timeToSeconds(this.activity.schedule_from) ? { minScheduleFrom: true } : null;
        };
    }

    public ngOnChanges(changes: SimpleChanges) {
        if(changes.activity) {
            if(this.activity) {
                const scheduleFrom = this.activity.schedule_from.split(':');
                const scheduleTo = this.activity.schedule_to.split(':');

                this.vm.scheduleFrom.formControl.setValue(`${scheduleFrom[0]}:${scheduleFrom[1]}`);
                this.vm.scheduleTo.formControl.setValue(`${scheduleTo[0]}:${scheduleTo[1]}`);
            }
        }
    }

    public async scheduleFromChange() {
        if(!this.vm.scheduleFrom.formControl.valid || (this.vm.scheduleFrom.formControl.value + ':00') === this.activity.schedule_from) {
            return;
        }

        this.scheduleFromChanged.emit(this.vm.scheduleFrom.formControl.value + ':00');
    }

    public async scheduleToChange() {
        if(!this.vm.scheduleTo.formControl.valid || (this.vm.scheduleTo.formControl.value + ':00') === this.activity.schedule_to) {
            return;
        }

        this.scheduleToChanged.emit(this.vm.scheduleTo.formControl.value + ':00');
    }
}

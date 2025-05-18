import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { BookingMeal } from '../../../../../../_models/booking_meal.model';
import { BookingLineGroup } from '../../../../../../_models/booking_line_group.model';
import { Booking } from '../../../../../../_models/booking.model';
import { FormControl } from '@angular/forms';
import { MatDialog } from '@angular/material/dialog';
import { ApiService } from 'sb-shared-lib';

interface vmModel {
    is_self_provided: {
        formControl: FormControl,
        change: () => void
    },
    meal_type_id: {
        formControl: FormControl,
        change: () => void
    },
    meal_place: {
        formControl: FormControl,
        change: () => void
    }
}

@Component({
    selector: 'booking-services-booking-group-day-meals-meal',
    templateUrl: 'meal.component.html',
    styleUrls: ['meal.component.scss']
})
export class BookingServicesBookingGroupDayMealsMealComponent implements OnInit{

    @Input() meal: BookingMeal | null;
    @Input() date: Date;
    @Input() timeSlot: any;
    @Input() mealTypes: { id: number, name: string, code: string }[];
    @Input() mealPlaces: { id: number, name: string, code: string }[];
    @Input() group: BookingLineGroup;
    @Input() booking: Booking;
    @Input() opened: boolean = false;

    @Output() open = new EventEmitter();
    @Output() close = new EventEmitter();
    @Output() loadStart = new EventEmitter();
    @Output() loadEnd = new EventEmitter();
    @Output() updated = new EventEmitter();

    public ready: boolean = false;

    public vm: vmModel;

    public mapTimeSlotCodeName: any = {
        'B':  'Petit déjeuner',
        'AM': 'Collation matin',
        'L':  'Déjeuner',
        'PM': 'Collation aprèm',
        'D':  'Diner',
    };

    constructor(
        private api: ApiService,
        public dialog: MatDialog
    ) {
        this.vm = {
            is_self_provided: {
                formControl: new FormControl(false),
                change: () => this.isSelfProvidedChange()
            },
            meal_type_id: {
                formControl: new FormControl(1),
                change: () => this.mealTypeIdChange()
            },
            meal_place: {
                formControl: new FormControl('indoor'),
                change: () => this.mealPlaceChange()
            }
        };
    }

    public ngOnInit() {
        this.ready = true;

        if(this.meal) {
            if(this.meal.booking_lines_ids.length === 0 && this.meal.is_self_provided) {
                this.vm.is_self_provided.formControl.disable();
            }

            this.vm.is_self_provided.formControl.setValue(this.meal.is_self_provided);
            this.vm.meal_type_id.formControl.setValue(this.meal.meal_type_id);
            this.vm.meal_place.formControl.setValue(this.meal.meal_place);
        }
    }

    private async isSelfProvidedChange() {
        if(!this.meal || this.meal.is_self_provided == this.vm.is_self_provided.formControl.value) {
            return;
        }

        this.loadStart.emit();

        // notify back-end about the change
        try {
            await this.api.update('sale\\booking\\BookingMeal', [this.meal.id], {is_self_provided: this.vm.is_self_provided.formControl.value});
            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loadEnd.emit();
    }

    private async mealTypeIdChange() {
        if(!this.meal?.meal_type_id || this.meal.meal_type_id == this.vm.meal_type_id.formControl.value) {
            return;
        }

        this.loadStart.emit();

        // notify back-end about the change
        try {
            await this.api.update('sale\\booking\\BookingMeal', [this.meal.id], {meal_type_id: this.vm.meal_type_id.formControl.value});
            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loadEnd.emit();
    }

    private async mealPlaceChange() {
        if(!this.meal?.meal_place || this.meal.meal_place == this.vm.meal_place.formControl.value) {
            return;
        }

        this.loadStart.emit();

        // notify back-end about the change
        try {
            await this.api.update('sale\\booking\\BookingMeal', [this.meal.id], {meal_place: this.vm.meal_place.formControl.value});
            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loadEnd.emit();
    }

    public toggleOpen() {
        this.opened ? this.close.emit() : this.open.emit();
    }

    public async addMeal() {
        this.loadStart.emit();

        try {
            await this.api.create('sale\\booking\\BookingMeal', {
                booking_id: this.booking.id,
                booking_line_group_id: this.group.id,
                date: this.date,
                time_slot_id: this.timeSlot.id,
                is_self_provided: true
            });
            // relay change to parent component
            this.updated.emit();
        }
        catch(response) {
            this.api.errorFeedback(response);
        }

        this.loadEnd.emit();
    }
}

import { Injectable } from '@angular/core';
import { Subject } from 'rxjs';

const millisecondsPerDay:number = 24 * 60 * 60 * 1000;

@Injectable({
    providedIn: 'root'
})
export class PlanningEmployeesCalendarParamService {

    // current state of filters
    private observable: Subject<any>;
    // date from
    private _date_from: Date;
    // date to
    private _date_to: Date;
    // duration in days
    private _duration: number;
    // selected partners (employees/providers)
    private _partners_ids: number[];
    // if true display only product models with has_transport_required
    private _show_only_transport: boolean;
    // selected product category
    private _product_category_id: number;
    // selected product model
    private _product_model_id: number|null;
    // ids of the product models to display (all if empty)
    private _product_model_ids: number[];
    // timeout handler for debounce
    private timeout: any;
    // current state, for changes detection
    private state: string;

    constructor() {
        this.observable = new Subject();
    }

    /**
     * Current state according to instant values of the instance.
     */
    private getState(): string {
        return this._date_from.getTime() + this._date_to.getTime() + this._partners_ids.toString() + this._product_model_ids.toString();
    }

    private treatAsUTC(date:Date): Date {
        let result = new Date(date.getTime());
        result.setMinutes(result.getMinutes() - result.getTimezoneOffset());
        return result;
    }

    private updateRange() {
        if(this.timeout) {
            clearTimeout(this.timeout);
        }

        // add a debounce in case range is updated several times in a row
        this.timeout = setTimeout( () => {
            console.log('update', this._date_from, this._date_to);
            this.timeout = undefined;
            const new_state = this.getState();
            if(new_state != this.state) {
                this.state = new_state;
                this._duration = Math.abs(this.treatAsUTC(this._date_to).getTime() - this.treatAsUTC(this._date_from).getTime()) / millisecondsPerDay;
                this.observable.next(this.state);
            }
        }, 150);
    }

    /**
     * Allow init request from other components
     */
    public init() {
        this._duration = 7;
        this._date_from = this.getPreviousMonday();
        this._date_to = new Date(this._date_from.getTime());
        this._date_to.setDate(this._date_from.getDate() + this._duration);
        this._partners_ids = [];
        this._show_only_transport = false;
        this._product_category_id = 0;
        this._product_model_id = null;
        this._product_model_ids = [];
        this.state = this.getState();
    }

    private getPreviousMonday() {
        const today = new Date();
        const dayOfWeek = today.getDay();

        if(dayOfWeek === 1) {
            return today;
        }

        const daysToSubtract = (dayOfWeek === 0) ? 6 : dayOfWeek - 1;
        today.setDate(today.getDate() - daysToSubtract);
        return today;
    }

    public getObservable(): Subject<any> {
        return this.observable;
    }


    /***********
     * Setters *
     ***********/

    public set partners_ids(partners_ids: number[]) {
        this._partners_ids = [...partners_ids];
        this.updateRange();
    }

    public set product_model_ids(product_model_ids: number[]) {
        this._product_model_ids = [...product_model_ids];
        this.updateRange();
    }

    public set date_from(date: Date) {
        this._date_from = date;
        this.updateRange();
    }

    public set date_to(date: Date) {
        this._date_to = date;
        this.updateRange();
    }

    public set show_only_transport(show_only_transport: boolean) {
        this._show_only_transport = show_only_transport;
        this.updateRange();
    }

    public set product_category_id(product_category_id: number) {
        this._product_category_id = product_category_id;
        this.updateRange();
    }

    public set product_model_id(product_model_id: number) {
        this._product_model_id = product_model_id;
        this.updateRange();
    }


    /***********
     * Getters *
     ***********/

    public get partners_ids(): number[] {
        return this._partners_ids;
    }

    public get product_model_ids(): number[] {
        return this._product_model_ids;
    }

    public get date_from(): Date {
        return this._date_from;
    }

    public get date_to(): Date {
        return this._date_to;
    }

    public get duration(): number {
        return this._duration;
    }

    public get show_only_transport() {
        return this._show_only_transport;
    }

    public get product_category_id() {
        return this._product_category_id;
    }

    public get product_model_id() {
        return this._product_model_id;
    }
}

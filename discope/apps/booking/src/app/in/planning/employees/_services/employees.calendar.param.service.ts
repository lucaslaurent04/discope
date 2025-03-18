import { Injectable } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { TranslateService } from '@ngx-translate/core';
import { Subject } from 'rxjs';

const millisecondsPerDay:number = 24 * 60 * 60 * 1000;

@Injectable({
    providedIn: 'root'
})
export class PlanningEmployeesCalendarParamService {

    private observable: Subject<any>;

    private _date_from: Date;
    private _date_to: Date;
    // duration in days
    private _duration: number;
    private _partners_ids: number[];

    // timeout handler for debounce
    private timeout: any;
    // current state, for changes detection
    private state: string;

    constructor(private translate:TranslateService, private snack: MatSnackBar) {
        this.observable = new Subject();
    }

    /**
     * Current state according to instant values of the instance.
     */
    private getState(): string {
        return this._date_from.getTime() + this._date_to.getTime() + this._partners_ids.toString()
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
        // #todo - toujours commencer le lundi par défaut
        this._date_from = new Date('2025-06-01'); // TODO: fix
        this._date_to = new Date(this._date_from.getTime());
        this._date_to.setDate(this._date_from.getDate() + this._duration);
        this._partners_ids = [];
        this.state = this.getState();
    }

    public getObservable(): Subject<any> {
        return this.observable;
    }

    public set partners_ids(partners_ids: number[]) {
        this._partners_ids = [...partners_ids];
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

    public get partners_ids(): number[] {
        return this._partners_ids;
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
}

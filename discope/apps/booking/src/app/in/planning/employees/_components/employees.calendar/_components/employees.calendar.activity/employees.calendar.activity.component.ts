import { Component, Input, Output, ElementRef, EventEmitter, OnInit, OnChanges, SimpleChanges } from '@angular/core';

@Component({
  selector: 'planning-employees-calendar-activity',
  templateUrl: './employees.calendar.activity.component.html',
  styleUrls: ['./employees.calendar.activity.component.scss']
})
export class PlanningEmployeesCalendarActivityComponent implements OnInit, OnChanges  {
    @Input()  day: Date;
    @Input()  activity: any;
    @Input()  width: number;
    @Input()  height: number
    @Input()  tableRect: DOMRect;
    @Output() hover = new EventEmitter<any>();
    @Output() selected = new EventEmitter<any>();

    constructor(
        private elementRef: ElementRef
    ) {}

    ngOnInit() { }

    ngOnChanges(changes: SimpleChanges) {
        if (changes.activity || changes.width) {
            this.datasourceChanged();
        }
        if(changes.height) {
            this.elementRef.nativeElement.style.setProperty('--height', (this.height-1)+'px');
        }
    }

    private datasourceChanged() {

        // ignore invalid activities
        if(!this.activity || Object.keys(this.activity).length == 0) {
            return;
        }

        this.elementRef.nativeElement.style.setProperty('--width', '100%');
        this.elementRef.nativeElement.style.setProperty('--height', (this.height-1)+'px');

    }


    public onShowBooking(activity: any) {
       this.selected.emit(activity);
    }

    public onEnterActivity(activity:any) {
        this.hover.emit(activity);
    }

    public onLeaveActivity(activity:any) {
        this.hover.emit();
    }

    public getColor() {
        const colors: any = {
            yellow: '#ff9633',
            turquoise: '#0fc4a7',
            green: '#0fa200',
            blue: '#0288d1',
            violet: '#9575cd',
            red: '#c80651',
            grey: '#988a7d',
            dark_grey: '#655c58',
            light_grey: '#baa9a2',
            purple: '#7733aa',
            light_purple: '#8c58b8'
        };

        if(this.activity.is_partner_event) {
            const mapPartnerEventColors: any = {
                camp_activity: '#7A8F78',
                leave: '#BFA58A',
                time_off: '#8C6E5E',
                other: '#6C7A91',
                rest: '#6F5B4D',
                trainer: '#C27A5A',
                training: '#8F4E3A'
            };

            return mapPartnerEventColors[this.activity.event_type];
        }
        else if(this.activity.type == 'ooo') {
            return colors['red'];
        }
        else if(this.activity.booking_id?.status == 'quote') {
            // #memo - reverted to quote but without releasing the rental units
            return colors['grey'];
        }
        else if(this.activity.booking_id?.status == 'option') {
            return colors['blue'];
        }
        else if(this.activity.booking_id?.status == 'confirmed') {
            return colors['yellow'];
        }
        else if(this.activity.booking_id?.status == 'validated') {
            return colors['green'];
        }
        else if(this.activity.booking_id?.status == 'checkedin') {
            return colors['turquoise'];
        }
        else if(this.activity.booking_id?.status == 'checkedout') {
            return colors['grey'];
        }
        else if(this.activity.booking_id?.status == 'proforma') {
            return colors['light_purple'];
        }
        else if(this.activity.camp_id) {
            return colors['dark_grey'];
        }

        // invoiced and beyond
        return colors['purple'];
    }

    public getIcon() {
        if(this.activity.type == 'ooo') {
            return 'block';
        }
        else if(this.activity.booking_id?.status == 'quote') {
            // #memo - reverted to quote but without releasing the rental units
            return 'question_mark';
        }
        else if(this.activity.booking_id?.status == 'option') {
            return 'question_mark';
        }
        else if(this.activity.booking_id?.status == 'confirmed') {
            if(this.activity.booking_id?.payment_status == 'paid') {
                return 'money_off';
            }
            else {
                return 'attach_money';
            }
        }
        else if(this.activity.booking_id?.status == 'validated') {
            return 'check';
        }
        else if(this.activity.booking_id?.status == 'invoiced') {
            if(this.activity.booking_id?.payment_status == 'paid') {
                return 'money_off';
            }
            else {
                return 'attach_money';
            }
        }
        return '';
    }
}

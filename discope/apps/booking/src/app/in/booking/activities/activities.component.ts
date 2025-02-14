import { Component, OnInit, Renderer2 } from '@angular/core';
import { BookingApiService } from '../_services/booking.api.service';
import { ActivatedRoute } from '@angular/router';
import { ContextService, EqualUIService } from 'sb-shared-lib';

class Booking {
    constructor(
        public id: number = 0,
        public name: string = '',
        public display_name: string = '',
        public created: Date = new Date(),
        public status: string = ''
    ) {}
}

@Component({
    selector: 'booking-activities',
    templateUrl: 'activities.component.html',
    styleUrls: ['activities.component.scss']
})
export class BookingActivitiesComponent implements OnInit {

    public booking: any = new Booking();
    public booking_id: number = 0;

    public ready: boolean = false;

    public status: any = {
        'quote': 'Devis',
        'option': 'Option',
        'confirmed': 'Confirmée',
        'validated': 'Validée',
        'checkedin': 'En cours',
        'checkedout': 'Terminée',
        'invoiced': 'Facturée',
        'debit_balance': 'Solde débiteur',
        'credit_balance': 'Solde créditeur',
        'balanced': 'Soldée'
    }

    constructor(
        private api: BookingApiService,
        private route: ActivatedRoute,
        private context: ContextService,
        private eq: EqualUIService,
        private renderer: Renderer2
    ) {}

    public ngOnInit() {
        console.debug('BookingActivitiesEditComponent init');

        // when action is performed, we need to reload booking object
        // #memo - context change triggers sidemenu panes updates
        this.context.getObservable().subscribe( async (descriptor:any) => {
            if(this.ready) {
                // reload booking
                await this.load( Object.getOwnPropertyNames(new Booking()) );
                // this.refreshActionButton();
                // force reloading child component
                let booking_id = this.booking_id;
                this.booking_id = 0;
                setTimeout( () => {
                    this.booking_id = booking_id;
                }, 250);
            }
        });

        // fetch the booking ID from the route
        this.route.params.subscribe( async (params) => {
            console.debug('BookingActivitiesEditComponent : received routeParams change', params);
            if(params && params.hasOwnProperty('booking_id')) {
                this.booking_id = <number> params['booking_id'];

                try {
                    // load booking object
                    await this.load( Object.getOwnPropertyNames(new Booking()) );

                    // relay change to context (to display sidemenu panes according to current object)
                    this.context.change({
                        context_only: true,   // do not change the view
                        context: {
                            entity: 'sale\\booking\\Booking',
                            type: 'form',
                            purpose: 'view',
                            domain: ['id', '=', this.booking_id]
                        }
                    });

                    this.ready = true;
                }
                catch(response) {
                    console.warn(response);
                }
            }
        });
    }

    /**
     * Assign values based on selected booking and load sub-objects required by the view.
     *
     */
    private async load(fields:any) {
        try {
            const data:any = await this.api.read("sale\\booking\\Booking", [this.booking_id], fields);
            if(data && data.length) {
                // update local object
                for(let field of Object.keys(data[0])) {
                    this.booking[field] = data[0][field];
                }

                // assign booking to Booking API service (for conditioning calls)
                this.api.setBooking(this.booking);
            }
        }
        catch(response) {
            console.log('unexpected error');
        }
    }
}

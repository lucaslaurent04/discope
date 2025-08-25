import { Component, OnInit, AfterViewInit, ElementRef, ViewChild, Renderer2  } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { BookingApiService } from 'src/app/in/booking/_services/booking.api.service';
import { ContextService, EqualUIService, EnvService } from 'sb-shared-lib';

class Booking {
    constructor(
        public id: number = 0,
        public name: string = '',
        public display_name: string = '',
        public created: Date = new Date(),
        public status: string = ''
    ) {}
}

export interface BookedServicesDisplaySettings {
    store_folded_settings: boolean;
    identification_folded: boolean;
    products_folded: boolean;
    activities_folded: boolean;
    meals_folded: boolean;
    meals_show: boolean;
    accommodations_folded: boolean;
    meals_prefs_folded: boolean;
    meals_prefs_enabled: boolean;
    activities_enabled: boolean;
    activities_visible: boolean;
    meals_prefs_visible: boolean;
}

export interface RentalUnitsSettings {
    store_rental_units_settings: boolean;
    show: 'all'|'parents'|'children';
}

@Component({
    selector: 'booking-services',
    templateUrl: 'services.component.html',
    styleUrls: ['services.component.scss']
})
export class BookingServicesComponent implements OnInit, AfterViewInit  {

    public booking: any = new Booking();
    public booking_id: number = 0;

    public display_settings: BookedServicesDisplaySettings = {
        store_folded_settings: false,
        identification_folded: true,
        products_folded: true,
        activities_folded: true,
        meals_folded: true,
        meals_show: true,
        accommodations_folded: true,
        meals_prefs_folded: true,
        meals_prefs_enabled: true,
        activities_enabled: true,
        activities_visible: true,
        meals_prefs_visible: true
    };

    public rental_units_settings: RentalUnitsSettings = {
        store_rental_units_settings: false,
        show: 'all'
    };

    public ready: boolean = false;

    @ViewChild('actionButtonContainer') actionButtonContainer: ElementRef;

    public status:any = {
        'quote': 'Devis',
        'option': 'Option',
        'confirmed': 'Confirmée',
        'validated': 'Validée',
        'checkedin': 'En cours',
        'checkedout': 'Terminée',
        'proforma': 'Pro forma',
        'invoiced': 'Facturée',
        'debit_balance': 'Solde débiteur',
        'credit_balance': 'Solde créditeur',
        'balanced': 'Soldée',
        'cancelled': 'Annulée'
    }

    constructor(
        private api: BookingApiService,
        private route: ActivatedRoute,
        private context:ContextService,
        private eq:EqualUIService,
        private renderer: Renderer2,
        private env: EnvService
    ) {}

    /**
     * Set up callbacks when component DOM is ready.
     */
    public async ngAfterViewInit() {
        await this.refreshActionButton();
        this.ready = true;
    }

    public ngOnInit() {
        console.debug('BookingEditComponent init');

        // when action is performed, we need to reload booking object
        // #memo - context change triggers sidemenu panes updates
        this.context.getObservable().subscribe( async (descriptor:any) => {
            if(this.ready) {
                // reload booking
                await this.load( Object.getOwnPropertyNames(new Booking()) );
                this.refreshActionButton();
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
            console.debug('BookingEditComponent : received routeParams change', params);
            if(params && params.hasOwnProperty('booking_id')) {
                this.booking_id = <number> params['booking_id'];

                try {
                    // relay change to context (to display sidemenu panes according to current object)
                    this.context.change({
                        context_only: true,   // do not change the view
                        context: {
                            entity: 'sale\\booking\\Booking',
                            type: 'form',
                            name: 'services',   // specific view with actions only (required for the Actions button)
                            purpose: 'view',
                            domain: ['id', '=', this.booking_id]
                        }
                    });

                    // load booking object
                    await this.load( Object.getOwnPropertyNames(new Booking()) );
                }
                catch(response) {
                    console.warn(response);
                }
            }
        });

        this.loadDisplaySettings();
    }

    /**
     * #memo - in cas new actions and routes are added to Booking form, remind to update form.services accordingly.
     */
    private async refreshActionButton() {
        let $button = await this.eq.getActionButton('sale\\booking\\Booking', 'form.services', ['id', '=', this.booking_id]);
        // remove previous button, if any
        for (let child of this.actionButtonContainer.nativeElement.children) {
            this.renderer.removeChild(this.actionButtonContainer.nativeElement, child);
        }
        if($button.length) {
            this.renderer.appendChild(this.actionButtonContainer.nativeElement, $button[0]);
        }
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

    private async loadDisplaySettings() {
        try {
            const environment = await this.env.getEnv();

            const settings: {[key: string]: string} = {
                store_folded_settings: 'sale.features.ui.booking.store_folded_settings',
                identification_folded: 'sale.features.ui.booking.identification_folded',
                products_folded: 'sale.features.ui.booking.products_folded',
                accommodations_folded: 'sale.features.ui.booking.accommodations_folded',
                activities_folded: 'sale.features.ui.booking.activities_folded',
                activities_enabled: 'sale.features.booking.activity',
                activities_visible: 'sale.features.ui.booking.activities_visible',
                meals_folded: 'sale.features.ui.booking.meals_folded',
                meals_show: 'sale.features.booking.meal',
                meals_prefs_folded: 'sale.features.ui.booking.meal_preferences_folded',
                meals_prefs_enabled: 'sale.features.booking.meal_preferences',
                meals_prefs_visible: 'sale.features.ui.booking.meal_preferences_visible'
            };

            for(let setting of Object.keys(settings)) {
                const settingName = settings[setting];
                if(environment[settingName] !== undefined) {
                    this.display_settings[setting as keyof BookedServicesDisplaySettings] = environment[settingName];
                }
            }

            if(this.display_settings.store_folded_settings) {
                this.setDisplaySettingsFromLocalStorage();
            }

            const rentalUnitsSettings: {[key: string]: string} = {
                store_rental_units_settings: 'sale.features.ui.booking.store_rental_units_settings',
                show: 'sale.features.ui.booking.show_parent_children_rental_units'
            }

            for(let setting of Object.keys(rentalUnitsSettings)) {
                const settingName = rentalUnitsSettings[setting];
                if(environment[settingName] !== undefined) {
                    // @ts-ignore
                    this.rental_units_settings[setting as keyof RentalUnitsSettings] = environment[settingName];
                }
            }

            if(this.rental_units_settings.store_rental_units_settings) {
                this.setRentalUnitsSettingsFromLocalStorage();
            }
        }
        catch(response) {
            this.api.errorFeedback(response);
        }
    }

    private setDisplaySettingsFromLocalStorage() {
        const stored_map_bookings_booked_services_settings: string | null = localStorage.getItem('map_bookings_booked_services_settings');
        if(stored_map_bookings_booked_services_settings === null) {
            return;
        }

        const map_bookings_booked_services_settings: {[key: number]: BookedServicesDisplaySettings} = JSON.parse(stored_map_bookings_booked_services_settings);
        if(!map_bookings_booked_services_settings[this.booking_id]) {
            return;
        }

        const booked_services_settings = map_bookings_booked_services_settings[this.booking_id];
        for(let key of Object.keys(this.display_settings)) {
            this.display_settings[key as keyof BookedServicesDisplaySettings] = booked_services_settings[key as keyof BookedServicesDisplaySettings];
        }
    }

    private setRentalUnitsSettingsFromLocalStorage() {
        const stored_map_bookings_rental_units_settings: string | null = localStorage.getItem('map_bookings_rental_units_settings');
        if(stored_map_bookings_rental_units_settings === null) {
            return;
        }

        const map_bookings_rental_units_settings: {[key: number]: RentalUnitsSettings} = JSON.parse(stored_map_bookings_rental_units_settings);
        if(!map_bookings_rental_units_settings[this.booking_id]) {
            return;
        }

        const rental_units_settings = map_bookings_rental_units_settings[this.booking_id];
        for(let key of Object.keys(this.rental_units_settings)) {
            // @ts-ignore
            this.rental_units_settings[key as keyof RentalUnitsSettings] = rental_units_settings[key as keyof RentalUnitsSettings];
        }
    }
}

import { Component, Input, Output, EventEmitter, OnInit, ViewChild, OnChanges, SimpleChanges } from '@angular/core';

import { PlanningEmployeesCalendarParamService } from '../../../../_services/employees.calendar.param.service';

import { ChangeReservationArg } from 'src/app/model/changereservationarg';
import { AuthService, EnvService } from 'sb-shared-lib';
import { FormControl, FormGroup } from '@angular/forms';
import { MatSelect } from '@angular/material/select';
import { MatOption } from '@angular/material/core';
import { ProductModelCategory, ProductModel } from '../../employees.calendar.component';
import { debounceTime } from 'rxjs/operators';

type AggregatedProductModelType = {
    id: number,
    name: string,
    categories_ids: number[],
    product_models_ids: number[]
}

@Component({
  selector: 'planning-employees-calendar-navbar',
  templateUrl: './employees.calendar.navbar.component.html',
  styleUrls: ['./employees.calendar.navbar.component.scss']
})
export class PlanningEmployeesCalendarNavbarComponent implements OnInit, OnChanges {
    @Input() activity: any;
    @Input() partner: any;
    @Input() holidays: any;
    @Input() productModelCategories: ProductModelCategory[];
    @Input() productModels: ProductModel[];

    @Output() changedays = new EventEmitter<ChangeReservationArg>();
    @Output() refresh = new EventEmitter<Boolean>();
    @Output() openLegendDialog = new EventEmitter();
    @Output() openPrefDialog = new EventEmitter();
    @Output() fullScreen = new EventEmitter();

    @ViewChild('productModelSelector') productModelSelector: MatSelect;
    @ViewChild('partnerSelector') partnerSelector: MatSelect;

    private environment: any;

    private dateFrom: Date;
    private dateTo: Date;
    public duration: number;

    private allProductCategory: ProductModelCategory = { id: 0, name: 'TOUTES' };
    public selectedProductCategory: ProductModelCategory = this.allProductCategory;
    public filteredProductModels: AggregatedProductModelType[] = [];

    public displayedProductModelCategories: ProductModelCategory[] = [];
    public displayedProductModels: AggregatedProductModelType[] = [];

    public partners: any[] = [];
    public selected_partners_ids: any[] = [];

    vm: any = {
        duration:   '31',
        date_range: new FormGroup({
            date_from: new FormControl(),
            date_to: new FormControl()
        }),
        product_model_code: new FormControl(),
        show_only_transport: new FormControl(),
        filter_product_models: new FormControl('')
    };

    constructor(
        private auth: AuthService,
        private env: EnvService,
        private params: PlanningEmployeesCalendarParamService
    ) {}

    public async ngOnInit() {

        /*
            Setup events listeners
        */

        this.params.getObservable()
            .subscribe( async () => {
                console.log('received change from params');
                // update local vars according to service new values
                this.dateFrom = new Date(this.params.date_from.getTime())
                this.dateTo = new Date(this.params.date_to.getTime())
                this.duration = this.params.duration;

                this.vm.duration = this.duration.toString();
                this.vm.date_range.get('date_from').setValue(this.dateFrom);
                this.vm.date_range.get('date_to').setValue(this.dateTo);
                if(this.params.product_model_id === null) {
                    if(this.vm.product_model_code.value !== ('cat_' + this.params.product_category_id)) {
                        this.vm.product_model_code.setValue('cat_' + this.params.product_category_id);
                    }
                }
                else if(this.vm.product_model_code.value !== ('mod_' + this.params.product_model_id)) {
                    this.vm.product_model_code.setValue('mod_' + this.params.product_model_id);
                }
                this.vm.show_only_transport.setValue(this.params.show_only_transport);

                if(this.productModelCategories.length > 0) {
                    this.selectedProductCategory = this.productModelCategories.find((cat) => cat.id === this.params.product_category_id);
                }
            });

        // use user centers_ids to filter displayed employees
        this.auth.getObservable()
            .subscribe( async (user:any) => {
                if(!user.hasOwnProperty('centers_ids') || !user.centers_ids.length) {
                    return;
                }

                await this.params.loadPartners(user.centers_ids);

                const partners = this.params.partners;
                if(partners.length === 0) {
                    return;
                }

                this.partners = partners.sort((a: any, b: any) => {
                    if (a.relationship !== b.relationship) {
                        return a.relationship < b.relationship ? -1 : 1;
                    }
                    return a.name.localeCompare(b.name);
                });
            });

        this.vm.product_model_code.valueChanges.subscribe((value: string) => {
            if(value.startsWith('cat_')) {
                this.params.product_category_id = +value.split('_')[1];
                this.params.product_model_id = null;
            }
            else {
                this.params.product_model_id = +value.split('_')[1];
            }

            this.vm.filter_product_models.setValue('');

            this.filterProductModels();
            this.filterPartners();
        });

        this.vm.show_only_transport.valueChanges.subscribe((value: boolean) => {
            this.params.show_only_transport = value;

            this.filterProductModels();
        });

        this.vm.filter_product_models.valueChanges.pipe(debounceTime(300)).subscribe(() => {
            this.refreshDisplayedProductModels();
        });

        this.refreshDisplayedProductModels();

        this.environment = await this.env.getEnv();
    }

    public refreshDisplayedProductModels() {
        this.displayedProductModelCategories = this.productModelCategories.filter(cat => {
            return cat.name.toLowerCase().includes(this.vm.filter_product_models.value.toLowerCase());
        });

        this.displayedProductModels = this.filteredProductModels.filter(cat => {
            return cat.name.toLowerCase().includes(this.vm.filter_product_models.value.toLowerCase());
        });
    }

    public ngOnChanges(changes: SimpleChanges) {
        if(changes.productModels) {
            this.filteredProductModels = this.aggregatedProductModels(this.productModels);
        }
    }

    /**
     * Aggregate product models to not repeat the same name
     *
     * @param productModels
     * @private
     */
    private aggregatedProductModels(productModels: ProductModel[]): AggregatedProductModelType[] {
        const aggregateProductModels: AggregatedProductModelType[] = [];

        for(let productModel of productModels) {
            const index = aggregateProductModels.findIndex((pm) => pm.name === productModel.name);
            if(index >= 0) {
                aggregateProductModels[index].product_models_ids.push(productModel.id);
                for(let categoryId of productModel.categories_ids) {
                    if(!aggregateProductModels[index].categories_ids.includes(categoryId)) {
                        aggregateProductModels[index].categories_ids.push(categoryId);
                    }
                }
            }
            else {
                aggregateProductModels.push({
                    ...productModel,
                    product_models_ids: [productModel.id]
                });
            }
        }

        return aggregateProductModels;
    }

    public onOpenLegendDialog() {
        this.openLegendDialog.emit();
    }

    public onOpenPrefDialog() {
        this.openPrefDialog.emit();
    }

    public onFullScreen() {
        this.fullScreen.emit();
    }

    private filterProductModels() {
        let productModels = this.productModels.filter((productModel) => {
            return !this.params.show_only_transport || productModel.has_transport_required;
        });

        if(this.params.product_category_id > 0) {
            productModels = productModels.filter((productModel) => {
                return productModel.categories_ids.map(p => +p).includes(this.params.product_category_id);
            });
        }

        this.filteredProductModels = this.aggregatedProductModels(productModels);

        if(productModels.length > 0) {
            if(this.params.product_model_id) {
                const aggregatedProductModel = this.filteredProductModels.find((pm) => pm.id === this.params.product_model_id);
                this.params.product_model_ids = aggregatedProductModel.product_models_ids;
            }
            else {
                this.params.product_model_ids = productModels.map(p => p.id);
            }
        }
        else {
            // No product models so show no activities
            this.params.product_model_ids = [0];
            // And unselect selected product model
            this.params.product_model_id = null;
        }

        this.refreshDisplayedProductModels();
    }

    private filterPartners() {
        let employees = this.params.employees;
        if(this.environment.hasOwnProperty('sale.features.employee.activity_filter') && this.environment['sale.features.employee.activity_filter']) {
            if(this.params.product_model_ids.length === 0) {
                employees = [];
            }
            else {
                employees = this.params.employees.filter((e) => {
                    let hasProductModel = false;
                    for(let activityId of e.activity_product_models_ids) {
                        if(this.params.product_model_ids.includes(activityId)) {
                            hasProductModel = true;
                            break;
                        }
                    }

                    return hasProductModel;
                });
            }
        }

        this.params.partners_ids = [
            ...employees.map(e => e.id),
            ...this.params.providers.map(p => p.id)
        ];

        this.selected_partners_ids = this.params.partners_ids;

        this.partners = [...employees, ...this.params.providers]
            .sort((a: any, b: any) => {
                if (a.relationship !== b.relationship) {
                    return a.relationship < b.relationship ? -1 : 1;
                }
                return a.name.localeCompare(b.name);
            });
    }

    public async onchangeDateRange() {
        let start = this.vm.date_range.get('date_from').value;
        let end = this.vm.date_range.get('date_to').value;

        if(!start || !end) return;

        if(typeof start == 'string') {
            start = new Date(start);
        }

        if(typeof end == 'string') {
            end = new Date(end);
        }

        if(start <= end) {
            // relay change to parent component
            if((start.getTime() != this.dateFrom.getTime() || end.getTime() != this.dateTo.getTime())) {
                //  update local members and relay to params service
                this.dateFrom = this.vm.date_range.get('date_from').value;
                this.dateTo = this.vm.date_range.get('date_to').value;
                this.params.date_from = this.dateFrom;
                this.params.date_to = this.dateTo;
            }
        }
    }

    public onDurationChange(event: any) {
        console.log('onDurationChange');
        // update local values
        this.duration = parseInt(event.value, 10);
        this.dateTo = new Date(this.dateFrom.getTime());
        this.dateTo.setDate(this.dateTo.getDate() + this.duration);
        this.vm.date_range.get("date_to").setValue(this.dateTo);

        this.params.date_from = this.dateFrom;
        this.params.date_to = this.dateTo;
    }

    public onToday() {
        this.dateFrom = new Date();
        this.dateTo = new Date(this.dateFrom.getTime());
        this.dateTo.setDate(this.dateTo.getDate() + this.params.duration);
        this.vm.date_range.get("date_from").setValue(this.dateFrom);
        this.vm.date_range.get("date_to").setValue(this.dateTo);

        this.params.date_from = this.dateFrom;
        this.params.date_to = this.dateTo;
    }

    public onPrev(duration: number) {
        this.dateFrom.setDate(this.dateFrom.getDate() - duration);
        this.dateTo.setDate(this.dateTo.getDate() - duration);
        this.vm.date_range.get("date_from").setValue(this.dateFrom);
        this.vm.date_range.get("date_to").setValue(this.dateTo);

        this.params.date_from = this.dateFrom;
        this.params.date_to = this.dateTo;
    }

    public onNext(duration: number) {
        this.dateFrom.setDate(this.dateFrom.getDate() + duration);
        this.dateTo.setDate(this.dateTo.getDate() + duration);
        this.vm.date_range.get("date_from").setValue(this.dateFrom);
        this.vm.date_range.get("date_to").setValue(this.dateTo);

        this.params.date_from = this.dateFrom;
        this.params.date_to = this.dateTo;
    }

    public onRefresh() {
        this.refresh.emit(true);
    }

    public onchangeSelectedPartners() {
        console.log('::onchangeSelectedEmployees');
        this.params.partners_ids = this.selected_partners_ids;
    }

    public onclickUnselectAllPartners() {
        this.partnerSelector.options.forEach((item: MatOption) => item.deselect());
    }

    public onclickSelectAllPartners() {
        this.partnerSelector.options.forEach((item: MatOption) => item.select());
    }

    public onclickSelectInternal() {
        this.partnerSelector.options.forEach((item: MatOption) => {
            const partner = this.partners.find(p => p.id == item.value);
            if(partner.relationship === 'employee') {
                item.select();
            }
            else {
                item.deselect();
            }
        });
    }

    public onclickSelectExternal() {
        this.partnerSelector.options.forEach((item: MatOption) => {
            const partner = this.partners.find(p => p.id == item.value);
            if(partner.relationship === 'provider') {
                item.select();
            }
            else {
                item.deselect();
            }
        });
    }

    public calcHolidays() {
        return this.holidays.map( (a:any) => a.name ).join(', ');
    }
}

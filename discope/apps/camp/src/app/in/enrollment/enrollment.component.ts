import { Component, OnInit, AfterViewInit, OnDestroy } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { ContextService } from 'sb-shared-lib';

@Component({
    selector: 'enrollment',
    templateUrl: 'enrollment.component.html',
    styleUrls: ['enrollment.component.scss']
})
export class EnrollmentComponent implements OnInit, AfterViewInit, OnDestroy {

    // rx subject for unsubscribing subscriptions on destroy
    private ngUnsubscribe = new Subject<void>();

    public ready: boolean = false;

    private default_descriptor: any = {
        // route: '/enrollment/object.id',
        context: {
            entity: 'sale\\camp\\Enrollment',
            view:   'form.default'
        }
    };

    private enrollment_id: number = 0;

    constructor(
        private route: ActivatedRoute,
        private context: ContextService
    ) {}

    public ngOnDestroy() {
        console.debug('EnrollmentComponent::ngOnDestroy');
        this.ngUnsubscribe.next();
        this.ngUnsubscribe.complete();
    }

    public ngAfterViewInit() {
        console.debug('EnrollmentComponent::ngAfterViewInit');

        this.context.setTarget('#sb-container-enrollment');

        this.default_descriptor.context.domain = ["id", "=", this.enrollment_id];
        this.context.change(this.default_descriptor);
    }

    public ngOnInit() {
        console.debug('EnrollmentComponent::ngOnInit');

        this.context.ready.pipe(takeUntil(this.ngUnsubscribe)).subscribe( (ready:boolean) => {
            this.ready = ready;
        });

        this.route.params.pipe(takeUntil(this.ngUnsubscribe)).subscribe( async (params) => {
            this.enrollment_id = <number> parseInt(params['enrollment_id'], 10);
            if(this.ready) {
                this.default_descriptor.context.domain = ["id", "=", this.enrollment_id];
                this.default_descriptor.context.reset = true;
                this.context.change(this.default_descriptor);
            }
        });
    }
}

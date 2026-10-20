import { Component, OnInit, AfterViewInit, OnDestroy } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { ContextService } from 'sb-shared-lib';

@Component({
    selector: 'waiting',
    templateUrl: 'waiting.component.html',
    styleUrls: ['waiting.component.scss']
})
export class TasksWaitingComponent implements OnInit, AfterViewInit, OnDestroy {

    // rx subject for unsubscribing subscriptions on destroy
    private ngUnsubscribe = new Subject<void>();

    public ready: boolean = false;

    private default_descriptor: any = {
        // route: '/tasks/waiting',
        context: {
            entity: 'sale\\booking\\followup\\Task',
            view:   'list.waiting',
            domain: [
                [
                    ['is_done', '=', false],
                    ['object_class', '=', 'sale\\booking\\Booking'],
                    ['visible_date', '<=', new Date().toISOString().split('T')[0]]
                ],
                [
                    ['is_done', '=', false],
                    ['object_class', '=', 'sale\\booking\\Booking'],
                    ['visible_date', 'is', null]
                ]
            ]
        }
    };

    constructor(
        private route: ActivatedRoute,
        private context: ContextService
    ) {}

    public ngOnDestroy() {
        console.debug('TasksWaitingComponent::ngOnDestroy');
        this.ngUnsubscribe.next();
        this.ngUnsubscribe.complete();
    }

    public ngAfterViewInit() {
        console.debug('TasksWaitingComponent::ngAfterViewInit');

        this.context.setTarget('#sb-container-task');

        this.context.change(this.default_descriptor);
    }

    public ngOnInit() {
        console.debug('TasksWaitingComponent::ngOnInit');

        this.context.ready.pipe(takeUntil(this.ngUnsubscribe)).subscribe( (ready:boolean) => {
            this.ready = ready;
        });

        this.route.params.pipe(takeUntil(this.ngUnsubscribe)).subscribe( async (params) => {
            if(this.ready) {
                this.default_descriptor.context.reset = true;
                this.context.change(this.default_descriptor);
            }
        });
    }
}

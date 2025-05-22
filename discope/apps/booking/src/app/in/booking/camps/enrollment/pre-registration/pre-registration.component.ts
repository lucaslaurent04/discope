import { AfterContentInit, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { ApiService, EnvService, AuthService, ContextService } from 'sb-shared-lib'
import { UserClass } from 'sb-shared-lib/lib/classes/user.class';
import { FormControl, Validators } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import {debounceTime} from "rxjs/operators";

class Enrollment {
    constructor(
        public id: number = 0,
        public name: string = '',
        public child_id: number = 0,
        public camp_id: number = 0
    ) {
    }
}

class Child {
    constructor(
        public id: number = 0,
        public name: string = '',
        public main_guardian_id: number = 0,
        public guardians_ids: number[] = []
    ) {
    }
}

class Guardian {
    constructor(
        public id: number = 0,
        public name: string = '',
        public firstname: string = '',
        public lastname: string = '',
        public email: string = '',
        public relation_type: 'mother' | 'father' | 'legal-tutor' | 'family-member' | 'home-manager' | 'departmental-council' = 'mother'
    ) {
    }
}

class Camp {
    constructor(
        public id: number = 0,
        public name: string = '',
        public short_name: string = '',
        public center_id: number = 0
    ) {
    }
}

class Center {
    constructor(
        public id: number = 0,
        public name: string = '',
        public email: string = '',
        public organisation_id: number = 0,
        public center_office_id: number = 0,
        public template_category_id: number = 0
    ) {}
}

class CenterOffice {
    constructor(
        public id: number = 0,
        public name: string = '',
        public email: string = '',
        public email_alt: string = '',
        public code: number = 0
    ) {}
}

class Organisation {
    constructor(
        public id: number = 0,
        public name: string = '',
        public email: string = '',
        public signature: string = ''
    ) {}
}

class Language {
    constructor(
        public id: number = 0,
        public code: string = '',
        public name: string = ''
    ) {
    }
}

interface vModel {
    lang: {
        formControl: FormControl,
    },
    title: {
        formControl: FormControl
    },
    message: {
        formControl: FormControl,
    },
    sender: {
        addresses: string[],
        formControl: FormControl
    },
    recipient: {
        addresses: string[],
        formControl: FormControl
    },
    recipients: {
        addresses: string[],
        formControl: FormControl
    }
}

@Component({
    selector: 'booking-camps-enrollment-pre-registration',
    templateUrl: 'pre-registration.component.html',
    styleUrls: ['pre-registration.component.scss']
})
export class BookingCampsEnrollmentPreRegistrationComponent implements OnInit, AfterContentInit {

    public enrollment: Enrollment = new Enrollment();

    public child: Child = new Child();
    public mainGuardian: Guardian = new Guardian();
    public guardians: Guardian[] = [];

    public camp: Camp = new Camp();
    public center: Center = new Center();
    public office: CenterOffice = new CenterOffice();
    public organisation: Organisation = new Organisation();

    public user: UserClass = null;

    public mapLanguages: {[key: string]: Language} = {};
    public selectedLanguage: Language = null;

    public loading = true;

    public vm: vModel;

    constructor(
        private api: ApiService,
        private env: EnvService,
        private auth: AuthService,
        private context: ContextService,
        private route: ActivatedRoute,
        private cd: ChangeDetectorRef
    ) {
        this.vm = {
            lang: {
                formControl: new FormControl('fr'),
            },
            title: {
                formControl:    new FormControl('', Validators.required)
            },
            message: {
                formControl:    new FormControl('', Validators.required),
            },
            sender: {
                addresses: [],
                formControl: new FormControl('', [Validators.required, Validators.pattern("^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,8}$")])
            },
            recipient: {
                addresses: [],
                formControl: new FormControl('', [Validators.required, Validators.email])
            },
            recipients: {
                addresses: [],
                formControl: new FormControl()
            }
        };
    }

    public async ngOnInit() {
        await this.loadLanguages();

        this.auth.getObservable().subscribe(async (user: UserClass) => {
            this.user = user;
            this.refreshSenderAddresses();
        });

        this.route.params.subscribe(async (params) => {
            if(params) {
                try {
                    if(params.hasOwnProperty('enrollment_id')) {
                        const enrollmentId = parseInt(params['enrollment_id'], 10);
                        await this.loadEnrollment(enrollmentId);
                        this.refreshSenderAddresses();

                        this.context.change({
                            context_only: true,
                            context: {
                                entity: 'sale\\camp\\Enrollment',
                                type: 'form',
                                purpose: 'view',
                                domain: ['id', '=', this.enrollment.id]
                            }
                        });
                    }
                }
                catch(error) {
                    console.warn(error);
                }
            }
        });

        this.loading = false;
    }

    private async loadLanguages() {
        const environment: any = await this.env.getEnv();
        const languages = await this.api.collect("core\\Lang", [], ['id', 'code', 'name'], 'name', 'asc', 0, 100, environment.locale);
        for(let language of languages) {
            this.mapLanguages[language.code] = language;
            if(language.code == environment.lang) {
                this.selectedLanguage = language;
            }
        }
    }

    private async loadEnrollment(enrollmentId: number) {
        const enrollments: Enrollment[] = await this.api.read("sale\\camp\\Enrollment", [enrollmentId], Object.getOwnPropertyNames(new Enrollment()));
        if(enrollments.length > 0) {
            const enrollment  = enrollments[0];

            this.enrollment = enrollment;
            await this.loadChild(enrollment.child_id);
            await this.loadCamp(enrollment.camp_id);
        }
    }

    private async loadChild(childId: number) {
        const children: Child[] = await this.api.read("sale\\camp\\Child", [childId], Object.getOwnPropertyNames(new Child()));
        if(children.length > 0) {
            const child = children[0];

            this.child = child;
            await this.loadGuardians(child.guardians_ids, child.main_guardian_id);
        }
    }

    private async loadGuardians(guardiansIds: number[], mainGuardianId: number) {
        const guardians: Guardian[] = await this.api.read("sale\\camp\\Guardian", guardiansIds, Object.getOwnPropertyNames(new Guardian()));
        if(guardians.length > 0) {
            this.guardians = guardians;
            for(let guardian of guardians) {
                if(guardian.id === mainGuardianId) {
                    this.mainGuardian = guardian;
                    break;
                }
            }
            this.refreshRecipientAddresses();
        }
    }

    private async loadCamp(campId: number) {
        const camps: Camp[] = await this.api.read("sale\\camp\\Camp", [campId], Object.getOwnPropertyNames(new Camp()));
        if(camps.length > 0) {
            const camp = camps[0];

            this.camp = camp;
            await this.loadCenter(camp.center_id);
        }
    }

    private async loadCenter(centerId: number) {
        const centers: Center[] = await this.api.read("identity\\Center", [centerId], Object.getOwnPropertyNames(new Center()));
        if(centers.length > 0) {
            const center = centers[0];

            this.center = center;
            await this.loadCenterOffice(center.center_office_id);
            await this.loadCenterOrganisation(center.organisation_id);
        }
    }

    private async loadCenterOffice(officeId: number) {
        const offices: CenterOffice[] = await this.api.read("identity\\CenterOffice", [officeId], Object.getOwnPropertyNames(new CenterOffice()));
        if(offices.length > 0) {
            this.office = offices[0];
        }
    }

    private async loadCenterOrganisation(organisationId: number) {
        const organisations: Organisation[] = await this.api.read("identity\\Identity", [organisationId], Object.getOwnPropertyNames(new CenterOffice()));
        if(organisations.length > 0) {
            this.organisation = organisations[0];
        }
    }

    private refreshSenderAddresses() {
        this.vm.sender.addresses = [];
        this.vm.sender.formControl.reset();

        const emails = [
            this?.office?.email ?? '',
            this?.office?.email_alt ?? '',
            this?.organisation?.email ?? '',
            this?.center?.email ?? '',
            this?.user?.login ?? ''
        ];

        for(let email of emails) {
            if(email.length > 0 && !this.vm.sender.addresses.includes(email)) {
                this.vm.sender.addresses.push(email);
            }
        }

        if(this.vm.sender.addresses.length > 0) {
            this.vm.sender.formControl.setValue(this.vm.sender.addresses[0]);
        }
    }

    private refreshRecipientAddresses() {
        this.vm.recipient.addresses = [];
        this.vm.recipient.formControl.reset();

        const emails = [
            this?.mainGuardian?.email ?? '',
            ...this.guardians.map((guardian) => guardian?.email ?? '')
        ];

        for(let email of emails) {
            if(email.length > 0 && !this.vm.recipient.addresses.includes(email)) {
                this.vm.recipient.addresses.push(email);
                this.vm.recipients.addresses.push(email);
            }
        }

        if(this.vm.recipient.addresses.length > 0) {
            this.vm.recipient.formControl.setValue(this.vm.recipient.addresses[0]);
        }
    }

    public ngAfterContentInit() {
        // bind VM to model
        this.vm.lang.formControl.valueChanges.subscribe((languageCode: string) => {
            this.selectedLanguage = this.mapLanguages[languageCode];
            this.refreshTemplates();
        });
    }

    private refreshTemplates() {
        console.log('re-fetch templates');

        // TODO: set title
        // TODO: set message
    }

    public onclickEnrollment() {
        let descriptor:any = {
            context_silent: true,
            context: {
                entity: 'sale\\camp\\Enrollment',
                type: 'form',
                name: 'default',
                domain: ['id', '=', this.enrollment.id],
                mode: 'view',
                purpose: 'view',
                display_mode: 'popup',
                callback: (data: any) => {
                    // restart angular lifecycles
                    this.cd.reattach();
                }
            }
        };

        // prevent angular lifecycles while a context is open
        this.cd.detach();
        this.context.change(descriptor);
    }

    public onclickCamp() {
        let descriptor:any = {
            context_silent: true,
            context: {
                entity: 'sale\\camp\\Camp',
                type: 'form',
                name: 'default',
                domain: ['id', '=', this.camp.id],
                mode: 'view',
                purpose: 'view',
                display_mode: 'popup',
                callback: (data: any) => {
                    // restart angular lifecycles
                    this.cd.reattach();
                }
            }
        };

        // prevent angular lifecycles while a context is open
        this.cd.detach();
        this.context.change(descriptor);
    }

    public onclickMainGuardian() {
        let descriptor:any = {
            context_silent: true,
            context: {
                entity: 'sale\\camp\\Guardian',
                type: 'form',
                name: 'default',
                domain: ['id', '=', this.mainGuardian.id],
                mode: 'view',
                purpose: 'view',
                display_mode: 'popup',
                callback: (data: any) => {
                    // restart angular lifecycles
                    this.cd.reattach();
                }
            }
        };

        // prevent angular lifecycles while a context is open
        this.cd.detach();
        this.context.change(descriptor);
    }
}

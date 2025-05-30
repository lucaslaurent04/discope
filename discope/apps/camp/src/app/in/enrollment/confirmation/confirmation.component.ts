import { AfterContentInit, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { FormControl, Validators } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ApiService, EnvService, AuthService, ContextService } from 'sb-shared-lib';
import { UserClass } from 'sb-shared-lib/lib/classes/user.class';

class Enrollment {
    constructor(
        public id: number = 0,
        public name: string = '',
        public child_id: number = 0,
        public camp_id: number = 0,
        public total: number = 0,
        public price: number = 0
    ) {
    }
}

class Child {
    constructor(
        public id: number = 0,
        public name: string = '',
        public firstname: string = '',
        public lastname: string = '',
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
        public sojourn_number: number = 0,
        public center_id: number = 0,
        public date_from: Date = new Date(),
        public date_to: Date = new Date(),
        public accounting_code: string = ''
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

class Template {
    constructor(
        public id: number = 0,
        public name: string = '',
        public category_id: number = 0,
        public type: 'quote' | 'option' | 'contract' | 'funding' | 'invoice' | 'guest' | 'planning' | 'camp' = 'camp',
        public code: string = '',
        public parts_ids: number[] = [],
        public attachments_ids: number[] = []
    ) {
    }
}

class TemplatePart {
    constructor(
        public id: number = 0,
        public name: string = '',
        public value: string = ''
    ) {
    }
}

class Document {
    constructor(
        public id: number = 0,
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
    },
    attachments: {
        items: any[]
    },
    documents: {
        items: any[]
    }
}

@Component({
    selector: 'enrollment-confirmation',
    templateUrl: 'confirmation.component.html',
    styleUrls: ['confirmation.component.scss']
})
export class EnrollmentConfirmationComponent implements OnInit, AfterContentInit {

    public enrollment: Enrollment = new Enrollment();

    public camp: Camp = new Camp();
    public child: Child = new Child();
    public mainGuardian: Guardian = new Guardian();
    public guardians: Guardian[] = [];

    public center: Center = new Center();
    public office: CenterOffice = new CenterOffice();
    public organisation: Organisation = new Organisation();

    public user: UserClass = null;

    public mapLanguages: {[key: string]: Language} = {};
    public selectedLanguage: Language = null;

    public loading = true;
    public isSent = false;

    public printConfirmationUrl: string = null;

    public vm: vModel;

    constructor(
        private api: ApiService,
        private env: EnvService,
        private auth: AuthService,
        private context: ContextService,
        private route: ActivatedRoute,
        private cd: ChangeDetectorRef,
        private snack: MatSnackBar
    ) {
        this.vm = {
            lang: {
                formControl: new FormControl('fr'),
            },
            title: {
                formControl: new FormControl('', Validators.required)
            },
            message: {
                formControl: new FormControl('', Validators.required),
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
            },
            attachments: {
                items: []
            },
            documents: {
                items: []
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
            if(!params) {
                return;
            }

            try {
                if(!params.hasOwnProperty('enrollment_id')) {
                    return;
                }

                const enrollmentId = parseInt(params['enrollment_id'], 10);
                await this.loadEnrollment(enrollmentId);

                this.refreshSenderAddresses();
                this.refreshTemplates();
                this.refreshPrintConfirmationUrl();

                this.context.change({
                    context_only: true,
                    context: {
                        entity: 'sale\\camp\\Enrollment',
                        type: 'form',
                        purpose: 'view',
                        domain: ['id', '=', this.enrollment.id]
                    }
                });

                this.loading = false;
            }
            catch(error) {
                console.warn(error);
            }
        });
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
            const enrollment = enrollments[0];

            this.enrollment = enrollment;

            await this.loadCamp(enrollment.camp_id);
            await this.loadChild(enrollment.child_id);
        }
    }

    private async loadCamp(campId: number) {
        const camps: Camp[] = await this.api.read("sale\\camp\\Camp", [campId], Object.getOwnPropertyNames(new Camp()))
        if(camps.length > 0) {
            const camp = camps[0];

            this.camp = camp;

            await this.loadCenter(camp.center_id);
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

    private async loadCenter(centerId: number) {
        const centers: Center[] = await this.api.read("identity\\Center", [centerId], Object.getOwnPropertyNames(new Center()));
        if(centers.length > 0) {
            const center = centers[0];

            this.center = center;

            await this.loadCenterOffice(center.center_office_id);
            await this.loadOrganisation(center.organisation_id);
        }
    }

    private async loadCenterOffice(officeId: number) {
        const centerOffices = await this.api.read("identity\\CenterOffice", [officeId], Object.getOwnPropertyNames(new CenterOffice()));;
        if(centerOffices.length > 0) {
            this.office = centerOffices[0];
        }
    }

    private async loadOrganisation(organisationId: number) {
        const organisations = await this.api.read("identity\\Identity", [organisationId], Object.getOwnPropertyNames(new Organisation()));
        if(organisations.length > 0) {
            this.organisation = organisations[0];
        }
    }

    private refreshSenderAddresses() {
        this.vm.sender.addresses = [];
        this.vm.sender.formControl.reset();

        const emails: string[] = [
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
            this.refreshPrintConfirmationUrl();
        });
    }

    private async refreshTemplates() {
        console.log('re-fetch templates');

        try {
            const templates: Template[] = await this.api.collect(
                "communication\\Template",
                [
                    ['category_id', '=', this.center.template_category_id],
                    ['type', '=', 'camp'],
                    ['code', '=', 'confirmation']
                ],
                Object.getOwnPropertyNames(new Template()),
                'id', 'asc', 0, 1, this.selectedLanguage.code
            );

            if(templates.length > 0) {
                const template = templates[0];

                const parts = await this.api.collect(
                    "communication\\TemplatePart",
                    ['id', 'in', template.parts_ids],
                    Object.getOwnPropertyNames(new TemplatePart()),
                    'id', 'asc', 0, 10, this.selectedLanguage.code
                );

                let subjectPart: TemplatePart = null;
                let bodyPart: TemplatePart = null;
                for(let part of parts) {
                    if(part.name === 'subject') {
                        subjectPart = part;
                    }
                    else if(part.name == 'body') {
                        bodyPart = part;
                    }
                }

                const dateFrom = new Date(this.camp.date_from);
                const dateTo = new Date(this.camp.date_to);

                let strDateFrom = dateFrom.getDate().toString().padStart(2, '0') + '/' + (dateFrom.getMonth()+1).toString().padStart(2, '0') + '/' + dateFrom.getFullYear();
                let strDateTo = dateTo.getDate().toString().padStart(2, '0') + '/' + (dateTo.getMonth()+1).toString().padStart(2, '0') + '/' + dateTo.getFullYear();

                const mapKeyValue: {[key: string]: string} = {
                    total: this.enrollment.total.toString(),
                    price: this.enrollment.price.toString(),
                    camp: this.camp.short_name,
                    sojourn_number: this.camp.sojourn_number.toString(),
                    accounting_code: this.camp.accounting_code,
                    date_from: strDateFrom,
                    date_to: strDateTo,
                    child: this.child.name,
                    firstname: this.child.firstname,
                    lastname: this.child.lastname.toUpperCase()
                };

                if(subjectPart) {
                    let title = '';
                    if(subjectPart.value && subjectPart.value.length > 0) {
                        // strip html nodes
                        title = subjectPart.value.replace(/<[^>]*>?/gm, '');
                    }

                    this.vm.title.formControl.setValue(title);
                }

                if(bodyPart) {
                    let body = '';
                    for(let key in mapKeyValue) {
                        body = bodyPart.value.replace(`{${key}}`, mapKeyValue[key]);
                    }

                    for(let key in mapKeyValue) {
                        body = body.replace(`{${key}}`, mapKeyValue[key]);
                    }

                    this.vm.message.formControl.setValue(body);
                }

                // reset attachments list
                this.vm.attachments.items.splice(0, this.vm.attachments.items.length);
                const attachments = await this.api.collect(
                    "communication\\TemplateAttachment",
                    [
                        ['id', 'in', template['attachments_ids']],
                        ['lang_id', '=', this.selectedLanguage.id]
                    ],
                    ['name', 'document_id.name', 'document_id.hash'],
                    'id', 'asc', 0, 20, this.selectedLanguage.code
                );
                for(let attachment of attachments) {
                    this.vm.attachments.items.push(attachment)
                }
            }
        }
        catch(error) {
            console.log(error);
        }
    }

    public refreshPrintConfirmationUrl() {
        console.log('refreshPrintConfirmationUrl');
        if(this.selectedLanguage.id === 0 || this.enrollment.id === 0) {
            this.printConfirmationUrl = null;
            return;
        }

        this.printConfirmationUrl = '/?get=sale_camp_enrollment_confirmation_print-confirmation&lang=' + this.selectedLanguage.code + '&id='+ this.enrollment.id;
    }

    public async onSend() {
        /*
            Validate values (otherwise mark fields as invalid)
        */

        let hasError = false;
        if(this.vm.title.formControl.invalid) {
            this.vm.title.formControl.markAsTouched();
            hasError = true;
        }
        if(this.vm.message.formControl.invalid) {
            this.vm.message.formControl.markAsTouched();
            hasError = true;
        }
        if(this.vm.sender.formControl.invalid) {
            this.vm.sender.formControl.markAsTouched();
            hasError = true;
        }
        if(this.vm.recipient.formControl.invalid) {
            this.vm.recipient.formControl.markAsTouched();
            hasError = true;
        }

        if(hasError) {
            return;
        }

        try {
            this.loading = true;
            await this.api.call('?do=sale_camp_enrollment_send-confirmation', {
                enrollment_id: this.enrollment.id,
                sender_email: this.vm.sender.formControl.value,
                recipient_email: this.vm.recipient.formControl.value,
                recipients_emails: this.vm.recipients.formControl.value,
                title: this.vm.title.formControl.value,
                message: this.vm.message.formControl.value,
                lang: this.vm.lang.formControl.value,
                attachments_ids: this.vm.attachments.items.filter((a: any) => a?.id).map((a: any) => a.id),
                documents_ids: this.vm.documents.items.filter((d: any) => d?.id).map((d: any) => d.id)
            })

            this.isSent = true;
            this.snack.open("Confirmation de pré-inscription envoyée avec succès.");
            this.loading = false;
        }
        catch(response: any) {
            let message: string = 'Erreur inconnue';
            if(response.error && response.error.errors) {
                const codes = Object.keys(response.error.errors);
                if(codes.length) {
                    switch(codes[0]) {
                        case 'NOT_ALLOWED':
                            message = 'Opération non autorisée';
                            break;
                    }
                }
            }
            setTimeout( () => {
                this.loading = false;
                this.snack.open(message, "Erreur");
            }, 500);
        }
    }

    public async onclickChild() {
        let descriptor:any = {
            context_silent: true,
            context: {
                entity: 'sale\\camp\\Child',
                type: 'form',
                name: 'default',
                domain: ['id', '=', this.child.id],
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

    public onRemoveAttachment(index: number) {
        this.vm.attachments.items.splice(index, 1);
    }

    public onselectDocuments(item: any, index: number) {
        const document = item as Document;
        this.vm.documents.items.splice(index, 1, document);
    }

    public addDocument(){
        this.vm.documents.items.push(new Document());
    }

    public onRemoveDocument(index: number) {
        this.vm.documents.items.splice(index, 1);
    }
}

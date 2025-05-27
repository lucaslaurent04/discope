import { AfterContentInit, ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { ApiService, EnvService, AuthService, ContextService } from 'sb-shared-lib'
import { UserClass } from 'sb-shared-lib/lib/classes/user.class';
import { FormControl, Validators } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { MatSnackBar } from '@angular/material/snack-bar';

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
    center: {
        formControl: FormControl,
    },
    children: {
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
    selector: 'booking-camps-enrollment-pre-registration',
    templateUrl: 'pre-registration.component.html',
    styleUrls: ['pre-registration.component.scss']
})
export class BookingCampsChildPreRegistrationComponent implements OnInit, AfterContentInit {

    public child: Child = new Child();

    public enrollments: Enrollment[] = [];
    public mainGuardian: Guardian = new Guardian();
    public mainGuardianChildren: Child[] = [];
    public guardians: Guardian[] = [];

    public camps: Camp[] = [];
    public centers: Center[] = [];

    public selectedCenter: Center = new Center();
    public office: CenterOffice = new CenterOffice();
    public organisation: Organisation = new Organisation();

    public user: UserClass = null;

    public mapLanguages: {[key: string]: Language} = {};
    public selectedLanguage: Language = null;

    public loading = true;
    public isSent = false;

    public printInvoiceUrl: string = null;

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
            center: {
                formControl: new FormControl(1),
            },
            children: {
                formControl: new FormControl([], Validators.required),
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
            if(params) {
                try {
                    if(params.hasOwnProperty('child_id')) {
                        const childId = parseInt(params['child_id'], 10);
                        await this.loadChild(childId);

                        this.refreshSenderAddresses();
                        this.refreshTemplates();
                        this.refreshPrintInvoiceUrl();

                        this.context.change({
                            context_only: true,
                            context: {
                                entity: 'sale\\camp\\Child',
                                type: 'form',
                                purpose: 'view',
                                domain: ['id', '=', this.child.id]
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
    private async loadChild(childId: number) {
        const children: Child[] = await this.api.read("sale\\camp\\Child", [childId], Object.getOwnPropertyNames(new Child()));
        if(children.length > 0) {
            const child = children[0];

            this.child = child;
            this.vm.children.formControl.setValue([this.child.id]);

            await this.loadEnrollments(child.id);
            await this.loadGuardians(child.guardians_ids, child.main_guardian_id);
        }
    }

    private async loadEnrollments(childId: number) {
        const startYearDate = new Date(new Date().getFullYear(), 0, 1);
        const endYearDate = new Date(new Date().getFullYear(), 11, 31);

        const domain = [
            ['child_id', '=', childId],
            ['date_from', '>=', startYearDate.getTime() / 1000],
            ['date_from', '<=', endYearDate.getTime() / 1000]
        ];
        this.enrollments = await this.api.collect("sale\\camp\\Enrollment", domain, Object.getOwnPropertyNames(new Enrollment()));

        const campsIds = this.enrollments.map(e => e.camp_id);
        if(campsIds.length > 0) {
            await this.loadCamps(campsIds);
        }
    }

    private async loadGuardians(guardiansIds: number[], mainGuardianId: number) {
        const guardians: Guardian[] = await this.api.read("sale\\camp\\Guardian", guardiansIds, Object.getOwnPropertyNames(new Guardian()));
        if(guardians.length > 0) {
            this.guardians = guardians;
            for(let guardian of guardians) {
                if(guardian.id === mainGuardianId) {
                    this.mainGuardian = guardian;
                    await this.loadOtherChildrenOfMainGuardian(guardian.id);
                    break;
                }
            }
            this.refreshRecipientAddresses();
        }
    }

    private async loadOtherChildrenOfMainGuardian(guardianId: number) {
        const domain = ['main_guardian_id', '=', guardianId];
        const children: Child[] = await this.api.collect("sale\\camp\\Child", domain, Object.getOwnPropertyNames(new Child()));
        if(children.length > 1) {
            this.mainGuardianChildren = children;
        }
    }

    private async loadCamps(campsIds: number[]) {
        this.camps = await this.api.read("sale\\camp\\Camp", campsIds, Object.getOwnPropertyNames(new Camp()));

        const centersIds = this.camps.map(e => e.center_id);
        if(centersIds.length > 0) {
            await this.loadCenters(centersIds);
        }
    }

    private async loadCenters(centersIds: number[]) {
        const centers: Center[] = await this.api.read("identity\\Center", centersIds, Object.getOwnPropertyNames(new Center()));
        if(centers.length > 0) {
            const center = centers[0];

            this.centers = centers;
            this.selectedCenter = center;

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

        const emails: string[] = [
            this?.office?.email ?? '',
            this?.office?.email_alt ?? '',
            this?.organisation?.email ?? '',
            this?.selectedCenter?.email ?? '',
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
            this.refreshPrintInvoiceUrl();
        });

        this.vm.center.formControl.valueChanges.subscribe((centerId: number) => {
            for(let center of this.centers) {
                if(center.id === centerId) {
                    this.selectedCenter = center;
                    this.refreshTemplates();
                    this.refreshPrintInvoiceUrl();
                }
            }
        });

        this.vm.children.formControl.valueChanges.subscribe((childrenIds: number[]) => {
            this.refreshPrintInvoiceUrl();
        })
    }

    private async refreshTemplates() {
        console.log('re-fetch templates');

        try {
            const templates: Template[] = await this.api.collect(
                "communication\\Template",
                [
                    ['category_id', '=', this.selectedCenter.template_category_id],
                    ['type', '=', 'camp'],
                    ['code', '=', 'preregistration']
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

                const camp = this.camps[0];
                const enrollment = this.enrollments[0];

                const dateFrom = new Date(camp.date_from);
                const dateTo = new Date(camp.date_to);

                let strDateFrom = dateFrom.getDate().toString().padStart(2, '0') + '/' + (dateFrom.getMonth()+1).toString().padStart(2, '0') + '/' + dateFrom.getFullYear();
                let strDateTo = dateTo.getDate().toString().padStart(2, '0') + '/' + (dateTo.getMonth()+1).toString().padStart(2, '0') + '/' + dateTo.getFullYear();

                const dateDeadline = new Date(camp.date_from);
                dateDeadline.setMonth(dateDeadline.getMonth() - 1);
                if(dateDeadline.getDate() !== dateFrom.getDate()) {
                    dateDeadline.setDate(0);
                }
                let strDateDeadline = dateDeadline.getDate().toString().padStart(2, '0') + '/' + (dateDeadline.getMonth()+1).toString().padStart(2, '0') + '/' + dateDeadline.getFullYear();

                let accounting_code = camp.accounting_code;
                if(accounting_code && accounting_code.length >= 4) {
                    accounting_code = (parseInt(accounting_code.slice(-4), 10)).toString();
                }

                const mapKeyValue: {[key: string]: string} = {
                    total: enrollment.total.toString(),
                    price: enrollment.price.toString(),
                    camp: camp.short_name,
                    date_from: strDateFrom,
                    date_to: strDateTo,
                    date_deadline: strDateDeadline,
                    accounting_code: accounting_code,
                    child: this.child.name,
                    child_firstname: this.child.firstname,
                    child_lastname: this.child.lastname.toUpperCase()
                };

                if(subjectPart) {
                    let title = '';
                    if(subjectPart.value && subjectPart.value.length > 0) {
                        // strip html nodes
                        title = subjectPart.value.replace(/<[^>]*>?/gm, '');
                    }

                    for(let key in mapKeyValue) {
                        title = title.replace(`{${key}}`, mapKeyValue[key]);
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

    public refreshPrintInvoiceUrl() {
        console.log('refreshPrintInvoiceUrl');
        const childrenIds = this.vm.children.formControl.value;
        if(this.selectedCenter.id === 0 || this.selectedLanguage.id === 0 || childrenIds.length < 1) {
            this.printInvoiceUrl = null;
            return;
        }

        this.printInvoiceUrl = '/?get=sale_camp_enrollment_preregistration_print-invoice&center_id=' + this.selectedCenter.id + '&lang=' + this.selectedLanguage.code + '&ids=[' + childrenIds.join(',') + ']';
    }

    public async onSend() {
        /*
            Validate values (otherwise mark fields as invalid)
        */

        let hasError = false;
        if(this.vm.children.formControl.invalid) {
            this.vm.children.formControl.markAsTouched();
            hasError = true;
        }
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
            await this.api.call('?do=sale_camp_enrollment_send-preregistration', {
                children_ids: this.vm.children.formControl.value,
                center_id: this.vm.center.formControl.value,
                sender_email: this.vm.sender.formControl.value,
                recipient_email: this.vm.recipient.formControl.value,
                recipients_emails: this.vm.recipients.formControl.value,
                title: this.vm.title.formControl.value,
                message: this.vm.message.formControl.value,
                lang: this.vm.lang.formControl.value,
                attachments_ids: this.vm.attachments.items.filter((a: any) => a?.id).map((a: any) => a.id),
                documents_ids: this.vm.documents.items.filter((d: any) => d?.id).map((d: any) => d.id),
            });

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

    public onclickChild() {
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

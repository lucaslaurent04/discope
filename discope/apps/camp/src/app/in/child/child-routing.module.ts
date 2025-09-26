import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { ChildPreRegistrationComponent } from './pre-registration/pre-registration.component';
import { ChildPreRegistrationReminderComponent } from './pre-registration-reminder/pre-registration-reminder.component';


const routes: Routes = [
    {
        path: 'preregistration',
        component: ChildPreRegistrationComponent
    },
    {
        path: 'preregistration-reminder',
        component: ChildPreRegistrationReminderComponent
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class ChildRoutingModule {}

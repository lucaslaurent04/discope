import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { BookingComponent } from './booking.component';
import { BookingServicesComponent } from './services/services.component';
import { BookingCompositionComponent } from './composition/composition.component';
import { BookingCompositionInviteComponent } from './composition/invite/invite.component';
import { BookingQuoteComponent } from './quote/quote.component';
import { BookingInvoiceComponent } from './invoice/invoice.component';
import { BookingOptionComponent } from './option/option.component';
import { BookingActivitiesPlanningComponent } from './activities-planning/activities-planning.component';

const routes: Routes = [
    {
        path: 'services',
        component: BookingServicesComponent
    },
    {
        path: 'composition',
        component: BookingCompositionComponent
    },
    {
        path: 'composition/invite',
        component: BookingCompositionInviteComponent
    },
    {
        path: 'activities-planning',
        component: BookingActivitiesPlanningComponent
    },
    {
        path: 'quote',
        component: BookingQuoteComponent
    },
    {
        path: 'option',
        component: BookingOptionComponent
    },
    {
        path: 'invoice/:invoice_id',
        component: BookingInvoiceComponent
    },
    {
        path: 'funding/:funding_id',
        loadChildren: () => import(`./funding/funding.module`).then(m => m.AppInBookingFundingModule)
    },
    {
        path: 'contract/:contract_id',
        loadChildren: () => import(`./contract/contract.module`).then(m => m.AppInBookingContractModule)
    },
    // wildcard route (accept root and any sub route that does not match any of the routes above)
    {
        path: '**',
        component: BookingComponent
    }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class BookingRoutingModule {}

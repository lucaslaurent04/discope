import { NgModule } from '@angular/core';

import { PreloadAllModules, RouterModule, Routes } from '@angular/router';

import { AppComponent } from './in/app.component';

/*
    target components can be loaded simultaneously
    they will be destroyed when another routing module is loaded (either above or below in the routing tree)
*/
const routes: Routes = [
    /* routes specific to current app */
    {
        path: 'camps',
        loadChildren: () => import(`./in/camps/camps.module`).then(m => m.AppInCampsModule)
    },
    {
        path: 'child/:child_id',
        loadChildren: () => import(`./in/child/child.module`).then(m => m.AppInChildModule)
    },
    {
        path: 'enrollment/:enrollment_id',
        loadChildren: () => import(`./in/enrollment/enrollment.module`).then(m => m.AppInEnrollmentModule)
    },
    {
        /*
            default route, for bootstrapping the App
            1) load necessary info
            2) ask for permissions (and store choices)
            3) redirect to applicable page (/auth/sign or /in)
         */
        path: '',
        component: AppComponent
    }
];

@NgModule({
    imports: [
        RouterModule.forRoot(routes, { preloadingStrategy: PreloadAllModules, onSameUrlNavigation: 'reload', useHash: true })
    ],
    exports: [RouterModule]
})
export class AppRoutingModule { }

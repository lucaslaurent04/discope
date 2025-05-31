import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

import { CampsComponent } from './camps.component';

const routes: Routes = [
    {
        path: '',
        component: CampsComponent
    }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class CampsRoutingModule {}

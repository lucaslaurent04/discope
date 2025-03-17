import { Component, HostListener } from '@angular/core';
import { MatDialogRef } from '@angular/material/dialog';

@Component({
    selector: 'planning-employees-preferences-dialog',
    templateUrl: './preferences.component.html',
    styleUrls: ['./preferences.component.scss']
})
export class PlanningEmployeesPreferencesDialogComponent {

    public rows_height: number;

    @HostListener('window:keyup.Enter', ['$event'])
    onDialogClick(event: KeyboardEvent): void {
        this.onSave();
    }

    constructor(
        public dialogRef: MatDialogRef<PlanningEmployeesPreferencesDialogComponent>,
    ) {
        let rows_height = localStorage.getItem('planning_rows_height');
        if(rows_height) {
            this.rows_height = parseInt(rows_height, 10);
        }
        else {
            this.rows_height = 30;
        }
    }

    public onClose(): void {
        this.dialogRef.close();
    }

    public onSave(): void {
        this.dialogRef.close(this);
    }
}

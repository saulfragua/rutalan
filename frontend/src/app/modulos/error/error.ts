import { Component } from '@angular/core';

@Component({
  selector: 'app-error',
  standalone: false,
  templateUrl: './error.html',
  styleUrl: './error.css',
})
export class Error {

    contactarAdministrador() {
    alert('Por favor contacte al administrador del sistema.');
    // aquí luego puedes redirigir a soporte o abrir mail
  }
  
}

import { Component, OnInit, AfterViewInit, Inject, PLATFORM_ID } from '@angular/core';
import { Router } from '@angular/router';
import { isPlatformBrowser } from '@angular/common';

declare var $: any;

@Component({
  selector: 'app-navbar',
  standalone: false,
  templateUrl: './navbar.html',
  styleUrl: './navbar.css',
})
export class Navbar implements OnInit, AfterViewInit {

  private isBrowser: boolean;

  constructor(
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
  }

  ngOnInit() {
    // Inicializar el pushmenu de AdminLTE cuando el componente esté listo
  }

  ngAfterViewInit() {
    // Inicializar el pushmenu después de que la vista esté cargada
    if (this.isBrowser) {
      this.inicializarPushMenu();
    }
  }

  inicializarPushMenu() {
    if (!this.isBrowser) return;

    // Esperar a que jQuery y AdminLTE estén cargados
    if (typeof $ !== 'undefined' && $.fn.pushMenu) {
      $('[data-widget="pushmenu"]').PushMenu('toggle');
    } else {
      // Si no está cargado, intentar de nuevo después de un breve delay
      setTimeout(() => {
        if (typeof $ !== 'undefined' && $.fn.pushMenu) {
          $('[data-widget="pushmenu"]').PushMenu('toggle');
        }
      }, 100);
    }
  }

  toggleSidebar() {
    if (!this.isBrowser) return;

    const body = document.body;

    if (window.innerWidth < 992) {
      // Móvil
      body.classList.toggle('sidebar-open');
    } else {
      // Desktop
      body.classList.toggle('sidebar-collapse');
    }
  }

  cerrarSesion() {
    if (!this.isBrowser) return;

    // 🔐 Eliminar usuario del localStorage
    localStorage.removeItem('usuario');

    // 🚪 Redirigir al login
    this.router.navigate(['/login']);
  }

}

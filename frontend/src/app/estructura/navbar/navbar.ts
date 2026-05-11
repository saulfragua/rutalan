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
    
    // Toggle manual del sidebar
    const sidebar = document.querySelector('.main-sidebar');
    const body = document.body;
    
    if (sidebar && body) {
      // En móviles, usar clase personalizada
      if (window.innerWidth < 992) {
        if (body.classList.contains('sidebar-open')) {
          body.classList.remove('sidebar-open');
          sidebar.classList.remove('show');
        } else {
          body.classList.add('sidebar-open');
          sidebar.classList.add('show');
        }
      } else {
        // En desktop, usar la clase de AdminLTE
        if (body.classList.contains('sidebar-collapse')) {
          body.classList.remove('sidebar-collapse');
        } else {
          body.classList.add('sidebar-collapse');
        }
      }
    }

    // También usar AdminLTE si está disponible
    if (typeof $ !== 'undefined' && $.fn.pushMenu) {
      $('[data-widget="pushmenu"]').PushMenu('toggle');
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

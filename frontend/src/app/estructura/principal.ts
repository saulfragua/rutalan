import { Component, OnInit, AfterViewInit, Inject, PLATFORM_ID } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { isPlatformBrowser } from '@angular/common';
import { filter } from 'rxjs/operators';

declare var $: any;

@Component({
  selector: 'app-principal',
  standalone: false,
  templateUrl: './principal.html',
  styleUrl: './principal.css',
})
export class Principal implements OnInit, AfterViewInit {

  private isBrowser: boolean;

  rutaActual: string = 'Dashboard';

  constructor(
    @Inject(PLATFORM_ID) private platformId: Object,
    private router: Router
  ) {
    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe(() => {
      const ruta = this.router.url.split('/')[1];
      this.rutaActual = ruta.charAt(0).toUpperCase() + ruta.slice(1);
    });

    this.isBrowser = isPlatformBrowser(this.platformId);
  }

  ngOnInit() {
    // Validar sesión al cargar el componente
    if (this.isBrowser) {
      this.validarSesion();
    }
  }

  ngAfterViewInit() {
    // Asegurar que el sidebar esté inicializado
    if (this.isBrowser) {
      this.inicializarSidebar();
    }
  }

  inicializarSidebar() {
    if (!this.isBrowser) return;

    // Cerrar sidebar por defecto en móviles
    if (window.innerWidth < 992) {
      document.body.classList.add('sidebar-collapse');
    }

    // Manejar el resize
    window.addEventListener('resize', () => {
      if (window.innerWidth < 992) {
        document.body.classList.add('sidebar-collapse');
      }
    });
  }

  /**
   * Valida si hay una sesión activa
   */
  validarSesion() {
    if (!this.isBrowser || typeof localStorage === 'undefined') return;

    const usuarioStr = localStorage.getItem('usuario');
    if (!usuarioStr) {
      this.router.navigate(['/login']);
      return;
    }
  }

  irAlLanding() {
    if (this.isBrowser) {
      window.location.href = 'http://localhost/rutalan/landing/index.html';
    }
  }

}

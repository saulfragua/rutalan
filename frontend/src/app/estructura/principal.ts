import { Component, OnInit, AfterViewInit, Inject, PLATFORM_ID } from '@angular/core';
import { Router } from '@angular/router';
import { isPlatformBrowser } from '@angular/common';

declare var $: any;

@Component({
  selector: 'app-principal',
  standalone: false,
  templateUrl: './principal.html',
  styleUrl: './principal.css',
})
export class Principal implements OnInit, AfterViewInit {

  private isBrowser: boolean;

  constructor(
    @Inject(PLATFORM_ID) private platformId: Object,
    private router: Router
  ) {
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

}

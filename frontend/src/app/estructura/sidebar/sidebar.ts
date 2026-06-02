import { Component, OnInit, HostListener, Inject, PLATFORM_ID } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { isPlatformBrowser } from '@angular/common';

@Component({
  selector: 'app-sidebar',
  standalone: false,
  templateUrl: './sidebar.html',
  styleUrl: './sidebar.css',
})
export class Sidebar implements OnInit {

  usuario: any;
  private isBrowser: boolean;
  rolUsuario: string = '';

  constructor(
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    
    // Cerrar sidebar cuando se navega a una nueva ruta en móviles
    if (this.isBrowser) {
      this.router.events.subscribe((event) => {
        if (event instanceof NavigationEnd) {
          if (window.innerWidth < 992) {
            this.cerrarSidebar();
          }
        }
      });
    }
  }

  ngOnInit() {
    if (this.isBrowser) {
      const data = localStorage.getItem('usuario');
      this.usuario = data ? JSON.parse(data) : null;
      // Obtener el rol del usuario
      if (this.usuario) {
        this.rolUsuario = this.usuario.rol || '';
      }
    }
  }

  @HostListener('document:click', ['$event'])
  onClickOutside(event: Event) {
    if (!this.isBrowser) return;
    
    const target = event.target as HTMLElement;
    const sidebar = document.querySelector('.main-sidebar');
    const navbar = document.querySelector('.main-header');
    
    // Si estamos en móvil y el sidebar está abierto
    if (window.innerWidth < 992 && sidebar && document.body.classList.contains('sidebar-open')) {
      // Si el click fue fuera del sidebar y fuera del botón hamburguesa
      if (!sidebar.contains(target) && !navbar?.contains(target)) {
        this.cerrarSidebar();
      }
    }
  }

  cerrarSidebar() {
    if (!this.isBrowser) return;
    
    document.body.classList.remove('sidebar-open');
    const sidebar = document.querySelector('.main-sidebar');
    if (sidebar) {
      sidebar.classList.remove('show');
    }
  }

   irAlLanding() {
    if (this.isBrowser) {
      window.location.href = 'http://localhost/rutalan/landing/index.html';
    }
  }

}

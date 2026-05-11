import { Injectable, Inject, PLATFORM_ID } from '@angular/core';
import { CanActivate, Router, ActivatedRouteSnapshot } from '@angular/router';
import { isPlatformBrowser } from '@angular/common';

@Injectable({
  providedIn: 'root'
})
export class AdminGuard implements CanActivate {
  
  constructor(
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {}

  canActivate(route: ActivatedRouteSnapshot): boolean {
    if (!isPlatformBrowser(this.platformId) || typeof localStorage === 'undefined') {
      this.router.navigate(['/login']);
      return false;
    }

    const usuarioStr = localStorage.getItem('usuario');
    if (!usuarioStr) {
      this.router.navigate(['/login']);
      return false;
    }

    try {
      const usuario = JSON.parse(usuarioStr);
      const rol = usuario.rol || '';

      // Solo administradores pueden acceder
      if (rol === 'admin') {
        return true;
      } else {
        // Si es cobrador, redirigir a clientes (o la página principal permitida)
        this.router.navigate(['/clientes']);
        return false;
      }
    } catch (error) {
      console.error('Error al validar rol:', error);
      this.router.navigate(['/login']);
      return false;
    }
  }
}

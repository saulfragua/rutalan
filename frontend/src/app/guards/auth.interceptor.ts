import { HttpInterceptorFn } from '@angular/common/http';
import { inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { Router } from '@angular/router';
import { tap } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// Adjunta el token a las llamadas a la API PHP y, si el servidor responde 401, cierra la sesión local.
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const browser = isPlatformBrowser(inject(PLATFORM_ID));
  const router = inject(Router);
  const esApi = req.url.startsWith(environment.apiUrl) && !req.url.includes('loginControlador.php');
  const token = browser && esApi ? localStorage.getItem('token') : null;

  return next(token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req).pipe(
    tap({
      error: (e) => {
        if (esApi && browser && e.status === 401) {
          localStorage.removeItem('usuario');
          localStorage.removeItem('token');
          router.navigate(['/login']);
        }
      }
    })
  );
};

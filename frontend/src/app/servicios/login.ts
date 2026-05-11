import { HttpClient, HttpHeaders, HttpErrorResponse } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, throwError } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class LoginService {

  url = `${environment.apiUrl}/controllers/loginControlador.php`;

  private httpOptions = {
    headers: new HttpHeaders({
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    })
  };

  constructor(private http: HttpClient) {}

  login(usuario: string, clave: string): Observable<any> {
    const body = JSON.stringify({ usuario, clave });
    
    return this.http.post<any>(
      `${this.url}?control=login`,
      body,
      this.httpOptions
    ).pipe(
      catchError((error: HttpErrorResponse) => {
        let errorMessage = 'Error desconocido';
        
        if (error.status === 0) {
          errorMessage = 'No se pudo conectar con el servidor. Verifique que XAMPP esté corriendo y que la URL sea correcta.';
        } else if (error.error instanceof ErrorEvent) {
          // Error del lado del cliente
          errorMessage = `Error: ${error.error.message}`;
        } else {
          // Error del lado del servidor
          errorMessage = error.error?.mensaje || error.error?.message || `Error del servidor: ${error.status} ${error.statusText}`;
        }
        
        return throwError(() => new Error(errorMessage));
      })
    );
  }
}

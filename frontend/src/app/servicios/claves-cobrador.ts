import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class ClavesCobradorService {
  
  url = `${environment.apiUrl}/controllers/clavesCobradorControlador.php`;

  private httpOptions = {
    headers: new HttpHeaders({
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    })
  };

  constructor(private http: HttpClient) { }

  /**
   * Genera una nueva clave dinámica para un cobrador
   * @param idUsuario ID del usuario cobrador
   * @returns Observable con la clave generada
   */
  generarClave(idUsuario: number): Observable<any> {
    return this.http.get(`${this.url}?control=generarClave&id_usuario=${idUsuario}`);
  }

  /**
   * Obtiene la clave activa del día para un usuario
   * @param idUsuario ID del usuario
   * @returns Observable con la clave activa
   */
  obtenerClaveActiva(idUsuario: number): Observable<any> {
    return this.http.get(`${this.url}?control=obtenerClaveActiva&id_usuario=${idUsuario}`);
  }

  /**
   * Consulta todas las claves de un usuario
   * @param idUsuario ID del usuario
   * @returns Observable con la lista de claves
   */
  consultarPorUsuario(idUsuario: number): Observable<any> {
    return this.http.get(`${this.url}?control=consultarPorUsuario&id_usuario=${idUsuario}`);
  }

  /**
   * Valida una clave para un usuario
   * @param idUsuario ID del usuario
   * @param clave Clave a validar
   * @returns Observable con el resultado de la validación
   */
  validarClave(idUsuario: number, clave: string): Observable<any> {
    const datos = {
      id_usuario: idUsuario,
      clave: clave
    };
    return this.http.post(
      `${this.url}?control=validarClave`,
      JSON.stringify(datos),
      this.httpOptions
    );
  }

  /**
   * Desactiva todas las claves expiradas del sistema
   * @returns Observable con el resultado
   */
  desactivarClavesExpiradas(): Observable<any> {
    return this.http.get(`${this.url}?control=desactivarClavesExpiradas`);
  }
}

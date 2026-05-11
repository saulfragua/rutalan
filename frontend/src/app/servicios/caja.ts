import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class CajaService {
  
  url = `${environment.apiUrl}/controllers/cajasControlador.php`;

  private httpOptions = {
    headers: new HttpHeaders({
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    })
  };

  constructor(private http: HttpClient) { }

  /**
   * Verifica si un usuario tiene una caja abierta
   * @param idUsuario ID del usuario
   * @returns Observable con la caja abierta o null
   */
  obtenerCajaAbierta(idUsuario: number): Observable<any> {
    return this.http.get(`${this.url}?control=obtenerCajaAbierta&id_usuario=${idUsuario}`);
  }

  /**
   * Verifica si un usuario tiene una caja abierta (boolean)
   * @param idUsuario ID del usuario
   * @returns Observable con { tiene_caja_abierta: boolean }
   */
  tieneCajaAbierta(idUsuario: number): Observable<any> {
    return this.http.get(`${this.url}?control=tieneCajaAbierta&id_usuario=${idUsuario}`);
  }

  /**
   * Abre una nueva caja
   * @param datos Datos de la caja (id_usuario, id_ruta, saldo_inicial, nombre_caja)
   * @returns Observable con el resultado
   */
  abrirCaja(datos: any): Observable<any> {
    return this.http.post(
      `${this.url}?control=abrirCaja`,
      JSON.stringify(datos),
      this.httpOptions
    );
  }

  /**
   * Cierra una caja
   * @param idCaja ID de la caja
   * @param saldoFinal Saldo final (opcional)
   * @param observaciones Observaciones (opcional)
   * @returns Observable con el resultado
   */
  cerrarCaja(idCaja: number, saldoFinal?: number, observaciones?: string): Observable<any> {
    const datos = {
      saldo_final: saldoFinal,
      observaciones: observaciones
    };
    return this.http.post(
      `${this.url}?control=cerrarCaja&id_caja=${idCaja}`,
      JSON.stringify(datos),
      this.httpOptions
    );
  }

  /**
   * Consulta todas las cajas de un usuario
   * @param idUsuario ID del usuario
   * @returns Observable con la lista de cajas
   */
  consultarPorUsuario(idUsuario: number): Observable<any> {
    return this.http.get(`${this.url}?control=consultarPorUsuario&id_usuario=${idUsuario}`);
  }

  /**
   * Consulta todas las cajas abiertas con resumen de operaciones
   * @returns Observable con la lista de cajas abiertas y su resumen
   */
  consultarCajasAbiertasConResumen(): Observable<any> {
    return this.http.get<any>(`${this.url}?control=consultarCajasAbiertasConResumen`, {
      headers: {
        'Accept': 'application/json'
      }
    });
  }

  /**
   * Consulta todas las cajas cerradas con resumen de operaciones
   * @returns Observable con la lista de cajas cerradas y su resumen
   */
  consultarCajasCerradasConResumen(): Observable<any> {
    return this.http.get<any>(`${this.url}?control=consultarCajasCerradasConResumen`, {
      headers: {
        'Accept': 'application/json'
      }
    });
  }
}

import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class MovimientosCajaService {
  
  url = `${environment.apiUrl}/controllers/movimientosCajaControlador.php`;

  private httpOptions = {
    headers: new HttpHeaders({
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    })
  };

  constructor(private http: HttpClient) { }

  /**
   * Registra un movimiento de entrada o salida de dinero
   * @param datos Datos del movimiento (id_caja, id_usuario, tipo, monto, causal, metodo_pago, observacion)
   * @returns Observable con el resultado
   */
  registrarMovimiento(datos: any): Observable<any> {
    return this.http.post(
      `${this.url}?control=registrar`,
      JSON.stringify(datos),
      this.httpOptions
    );
  }

  /**
   * Consulta todos los movimientos de una caja específica
   * @param idCaja ID de la caja
   * @returns Observable con la lista de movimientos
   */
  consultarPorCaja(idCaja: number): Observable<any> {
    return this.http.get(`${this.url}?control=consultarPorCaja&id_caja=${idCaja}`);
  }

  /**
   * Consulta todos los movimientos de todas las cajas
   * @returns Observable con la lista de movimientos
   */
  consultarTodos(): Observable<any> {
    return this.http.get(`${this.url}?control=consultarTodos`);
  }
}

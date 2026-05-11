import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class PagosService {
  
  url = `${environment.apiUrl}/controllers/pagosControlador.php`;

  private httpOptions = {
    headers: new HttpHeaders({
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    })
  };

  constructor(private http: HttpClient) { }

  /**
   * Consulta todos los pagos
   * @returns Observable con la lista de pagos
   */
  consultar(): Observable<any> {
    return this.http.get(`${this.url}?control=consultar`);
  }

  /**
   * Consulta clientes de una ruta con saldo pendiente
   * @param idRuta ID de la ruta
   * @returns Observable con la lista de clientes
   */
  consultarClientesPorRuta(idRuta: number): Observable<any> {
    return this.http.get(`${this.url}?control=consultarClientesPorRuta&id_ruta=${idRuta}`);
  }

  /**
   * Registra un nuevo pago
   * @param datos Datos del pago
   * @returns Observable con el resultado
   */
  registrarPago(datos: any): Observable<any> {
    return this.http.post(
      `${this.url}?control=registrarPago`,
      JSON.stringify(datos),
      this.httpOptions
    );
  }

  /**
   * Actualiza el orden de cobranza de los clientes
   * @param idRuta ID de la ruta
   * @param orden Array con los IDs de clientes en orden
   * @returns Observable con el resultado
   */
  actualizarOrdenCobranza(idRuta: number, orden: number[]): Observable<any> {
    const datos = {
      id_ruta: idRuta,
      orden: orden
    };
    return this.http.post(
      `${this.url}?control=actualizarOrdenCobranza`,
      JSON.stringify(datos),
      this.httpOptions
    );
  }
}

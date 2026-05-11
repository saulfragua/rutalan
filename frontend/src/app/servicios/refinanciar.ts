import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class RefinanciarService {
  
  url = `${environment.apiUrl}/controllers/refinanciarControlador.php`;
  urlCreditos = `${environment.apiUrl}/controllers/creditosControlador.php`;

  private httpOptions = {
    headers: new HttpHeaders({
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    })
  };

  constructor(private http: HttpClient) { }

  /**
   * Consulta un crédito por ID
   * @param idCredito ID del crédito
   * @returns Observable con los datos del crédito
   */
  consultarCreditoPorId(idCredito: number): Observable<any> {
    return this.http.get(`${this.urlCreditos}?control=consultarPorId&id=${idCredito}`);
  }

  /**
   * Refinancia un crédito
   * @param datos Datos de la refinanciación
   * @returns Observable con el resultado
   */
  refinanciar(datos: any): Observable<any> {
    return this.http.post(
      `${this.url}?control=refinanciar`,
      JSON.stringify(datos),
      this.httpOptions
    );
  }
}

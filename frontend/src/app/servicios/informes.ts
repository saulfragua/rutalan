import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class InformesService {
  private url = `${environment.apiUrl}/controllers/informesControlador.php`;

  constructor(private http: HttpClient) {}

  /**
   * Pagos realizados en el rango de fechas
   */
  obtenerPagosPorRango(fechaDesde: string, fechaHasta: string): Observable<any> {
    const params = new HttpParams()
      .set('control', 'pagos')
      .set('fecha_desde', fechaDesde)
      .set('fecha_hasta', fechaHasta);
    return this.http.get(this.url, { params });
  }

  /**
   * Créditos realizados en el rango de fechas
   */
  obtenerCreditosPorRango(fechaDesde: string, fechaHasta: string): Observable<any> {
    const params = new HttpParams()
      .set('control', 'creditos')
      .set('fecha_desde', fechaDesde)
      .set('fecha_hasta', fechaHasta);
    return this.http.get(this.url, { params });
  }

  /**
   * Gastos realizados en el rango de fechas
   */
  obtenerGastosPorRango(fechaDesde: string, fechaHasta: string): Observable<any> {
    const params = new HttpParams()
      .set('control', 'gastos')
      .set('fecha_desde', fechaDesde)
      .set('fecha_hasta', fechaHasta);
    return this.http.get(this.url, { params });
  }
}

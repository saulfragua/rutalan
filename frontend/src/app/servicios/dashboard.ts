import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class DashboardService {
  private url = `${environment.apiUrl}/controllers/dashboardControlador.php`;

  constructor(private http: HttpClient) { }

  obtenerCreditosPorRuta(): Observable<any> {
    return this.http.get(`${this.url}?control=obtenerCreditosPorRuta`);
  }

  obtenerTotalGeneralCreditos(): Observable<any> {
    return this.http.get(`${this.url}?control=obtenerTotalGeneralCreditos`);
  }

  obtenerEstadisticasClientes(): Observable<any> {
    return this.http.get(`${this.url}?control=obtenerEstadisticasClientes`);
  }

  obtenerClientesPorRuta(fechaInicio?: string, fechaFin?: string): Observable<any> {
    let params = new HttpParams();
    if (fechaInicio) {
      params = params.set('fecha_inicio', fechaInicio);
    }
    if (fechaFin) {
      params = params.set('fecha_fin', fechaFin);
    }
    return this.http.get(`${this.url}?control=obtenerClientesPorRuta`, { params });
  }

  obtenerTotalCobradoEnDia(fechaInicio?: string, fechaFin?: string): Observable<any> {
    let params = new HttpParams();
    if (fechaInicio) {
      params = params.set('fecha_inicio', fechaInicio);
    }
    if (fechaFin) {
      params = params.set('fecha_fin', fechaFin);
    }
    return this.http.get(`${this.url}?control=obtenerTotalCobradoEnDia`, { params });
  }

  obtenerGastosPorRuta(fechaInicio?: string, fechaFin?: string): Observable<any> {
    let params = new HttpParams();
    if (fechaInicio) {
      params = params.set('fecha_inicio', fechaInicio);
    }
    if (fechaFin) {
      params = params.set('fecha_fin', fechaFin);
    }
    return this.http.get(`${this.url}?control=obtenerGastosPorRuta`, { params });
  }

  obtenerCreditosPorTipo(): Observable<any> {
    return this.http.get(`${this.url}?control=obtenerCreditosPorTipo`);
  }

  obtenerEstadisticasSeguros(fechaInicio?: string, fechaFin?: string): Observable<any> {
    let params = new HttpParams();
    if (fechaInicio) {
      params = params.set('fecha_inicio', fechaInicio);
    }
    if (fechaFin) {
      params = params.set('fecha_fin', fechaFin);
    }
    return this.http.get(`${this.url}?control=obtenerEstadisticasSeguros`, { params });
  }

  obtenerEstadisticasCajas(): Observable<any> {
    return this.http.get(`${this.url}?control=obtenerEstadisticasCajas`);
  }

  obtenerEstadisticasCuotas(): Observable<any> {
    return this.http.get(`${this.url}?control=obtenerEstadisticasCuotas`);
  }

  obtenerEvolucionPagos(fechaInicio?: string, fechaFin?: string): Observable<any> {
    let params = new HttpParams();
    if (fechaInicio) {
      params = params.set('fecha_inicio', fechaInicio);
    }
    if (fechaFin) {
      params = params.set('fecha_fin', fechaFin);
    }
    return this.http.get(`${this.url}?control=obtenerEvolucionPagos`, { params });
  }

  obtenerEstadisticasRefinanciaciones(fechaInicio?: string, fechaFin?: string): Observable<any> {
    let params = new HttpParams();
    if (fechaInicio) {
      params = params.set('fecha_inicio', fechaInicio);
    }
    if (fechaFin) {
      params = params.set('fecha_fin', fechaFin);
    }
    return this.http.get(`${this.url}?control=obtenerEstadisticasRefinanciaciones`, { params });
  }

  obtenerTopRutasPorRendimiento(fechaInicio?: string, fechaFin?: string, limite: number = 5): Observable<any> {
    let params = new HttpParams();
    if (fechaInicio) {
      params = params.set('fecha_inicio', fechaInicio);
    }
    if (fechaFin) {
      params = params.set('fecha_fin', fechaFin);
    }
    params = params.set('limite', limite.toString());
    return this.http.get(`${this.url}?control=obtenerTopRutasPorRendimiento`, { params });
  }

  obtenerEstadisticasMorosidad(): Observable<any> {
    return this.http.get(`${this.url}?control=obtenerEstadisticasMorosidad`);
  }
}

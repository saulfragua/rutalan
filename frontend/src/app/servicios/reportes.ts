import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class ReportesService {
  
  url = `${environment.apiUrl}/controllers/reportesControlador.php`;

  private httpOptions = {
    headers: new HttpHeaders({
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    })
  };

  constructor(private http: HttpClient) { }

  /**
   * Obtiene los totales de reportes según los filtros
   */
  obtenerTotales(fechaInicio: string, fechaFin: string, idUsuario?: number, idRuta?: number): Observable<any> {
    let params = new HttpParams()
      .set('control', 'obtenerTotales')
      .set('fecha_inicio', fechaInicio)
      .set('fecha_fin', fechaFin);
    
    if (idUsuario) {
      params = params.set('id_usuario', idUsuario.toString());
    }
    if (idRuta) {
      params = params.set('id_ruta', idRuta.toString());
    }
    
    return this.http.get(`${this.url}`, { params }).pipe(
      map((resp: any) => {
        if (resp && resp.resultado === 'ok') {
          return resp.datos;
        }
        throw new Error(resp?.mensaje || 'Error al obtener totales');
      })
    );
  }

  /**
   * Obtiene la caja anterior (cierre de caja del día anterior)
   */
  obtenerCajaAnterior(fecha: string, idUsuario?: number): Observable<any> {
    let params = new HttpParams()
      .set('control', 'obtenerCajaAnterior')
      .set('fecha', fecha);
    
    if (idUsuario) {
      params = params.set('id_usuario', idUsuario.toString());
    }
    
    return this.http.get(`${this.url}`, { params }).pipe(
      map((resp: any) => {
        if (resp && resp.resultado === 'ok') {
          return resp.datos;
        }
        throw new Error(resp?.mensaje || 'Error al obtener caja anterior');
      })
    );
  }

  /**
   * Obtiene registros detallados de adelantos
   */
  obtenerAdelantos(fechaInicio: string, fechaFin: string, tipo: string, idUsuario?: number, idRuta?: number, limite: number = 5): Observable<any> {
    let params = new HttpParams()
      .set('control', 'obtenerAdelantos')
      .set('fecha_inicio', fechaInicio)
      .set('fecha_fin', fechaFin)
      .set('tipo', tipo)
      .set('limite', limite.toString());
    
    if (idUsuario) {
      params = params.set('id_usuario', idUsuario.toString());
    }
    if (idRuta) {
      params = params.set('id_ruta', idRuta.toString());
    }
    
    return this.http.get(`${this.url}`, { params }).pipe(
      map((resp: any) => {
        if (resp && resp.resultado === 'ok') {
          return resp.datos;
        }
        throw new Error(resp?.mensaje || 'Error al obtener adelantos');
      })
    );
  }

  /**
   * Guarda el cierre de caja
   */
  guardarCierreCaja(fecha: string, monto: number, idUsuario: number, observaciones: string = ''): Observable<any> {
    const formData = new FormData();
    formData.append('control', 'guardarCierreCaja');
    formData.append('fecha', fecha);
    formData.append('monto', monto.toString());
    formData.append('id_usuario', idUsuario.toString());
    formData.append('observaciones', observaciones);
    
    return this.http.post(this.url, formData).pipe(
      map((resp: any) => {
        if (resp && resp.resultado === 'ok') {
          return resp;
        }
        throw new Error(resp?.mensaje || 'Error al guardar cierre de caja');
      })
    );
  }
}

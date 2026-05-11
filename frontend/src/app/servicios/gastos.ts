import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class Gastos {
  
  url = `${environment.apiUrl}/controllers/gastosControlador.php`;

  constructor(private http: HttpClient) { }

  consultar() {
    return this.http.get(`${this.url}?control=consultar`);
  }

  consultarPorUsuario(idUsuario: number) {
    return this.http.get(`${this.url}?control=consultarPorUsuario&id_usuario=${idUsuario}`);
  }

  consultarPorCaja(idCaja: number) {
    return this.http.get(`${this.url}?control=consultarPorCaja&id_caja=${idCaja}`);
  }

  consultarPorId(id: number) {
    return this.http.get(`${this.url}?control=consultarPorId&id=${id}`);
  }

  insertar(datos: any) {
    return this.http.post(`${this.url}?control=insertar`, JSON.stringify(datos), {
      headers: { 'Content-Type': 'application/json' }
    });
  }

  editar(id: number, datos: any) {
    return this.http.post(`${this.url}?control=editar&id=${id}`, JSON.stringify(datos), {
      headers: { 'Content-Type': 'application/json' }
    });
  }

  eliminar(id: number) {
    return this.http.get(`${this.url}?control=eliminar&id=${id}`);
  }

  filtrar(dato: string) {
    return this.http.get(`${this.url}?control=filtrar&dato=${dato}`);
  }
}

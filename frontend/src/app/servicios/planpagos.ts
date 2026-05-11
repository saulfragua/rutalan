import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class Planpagos {
 
  url = `${environment.apiUrl}/controllers/planpagosControlador.php`;

  constructor(private http: HttpClient) { }

  consultar(){
    return this.http.get(`${this.url}?control=consultar`);
  }

  consultarPorIdCredito(idCredito: number){
    return this.http.get(`${this.url}?control=consultarPorIdCredito&id_credito=${idCredito}`);
  }
  
  eliminar(id: number){
    return this.http.get(`${this.url}?control=eliminar&id=${id}`);
  }

  insertar(datos: any){
    return this.http.post(`${this.url}?control=insertar`, JSON.stringify(datos));
  }

  editar(id: number, datos: any){
    return this.http.post(`${this.url}?control=editar&id=${id}`, JSON.stringify(datos));
  }

  filtrar(datos: any){
    return this.http.get(`${this.url}?control=filtrar&dato=${datos}`);
  }
}



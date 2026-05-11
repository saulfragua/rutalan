import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class Clientes {

  private apiUrl = environment.apiUrl;
  url = `${this.apiUrl}/controllers/clientesControlador.php`;

  constructor(private http: HttpClient) {}

  consultar(){
    return this.http.get(`${this.url}?control=consultar`);
  }

  consultarPorId(id: number){
    return this.http.get(`${this.url}?control=consultarPorId&id=${id}`);
  }
  
  eliminar(id: number){
    return this.http.get(`${this.url}?control=eliminar&id=${id}`);
  }

  activar(id: number){
    return this.http.get(`${this.url}?control=activar&id=${id}`);
  }

  inactivar(id: number){
    return this.http.get(`${this.url}?control=inactivar&id=${id}`);
  }

  insertar(datos: any){
    return this.http.post(`${this.url}?control=insertar`, JSON.stringify(datos));
  }

  insertarFormData(formData: FormData){
    return this.http.post(`${this.url}?control=insertar`, formData);
  }

  editar(id: number, datos: any){
    return this.http.post(`${this.url}?control=editar&id=${id}`, JSON.stringify(datos));
  }

  editarFormData(id: number, formData: FormData){
    return this.http.post(`${this.url}?control=editar&id=${id}`, formData);
  }

  filtrar(datos: any){
    return this.http.get(`${this.url}?control=filtra&datos=${datos}`);
  }

  actualizarUbicacion(id: number, latitud: number, longitud: number){
    return this.http.post(`${this.url}?control=actualizarUbicacion&id=${id}`, 
      JSON.stringify({ latitud, longitud }),
      { headers: { 'Content-Type': 'application/json' } }
    );
  }

  consultarConUbicacion(id_ruta?: number){
    const url = id_ruta 
      ? `${this.url}?control=consultarConUbicacion&id_ruta=${id_ruta}`
      : `${this.url}?control=consultarConUbicacion`;
    return this.http.get(url);
  }
}

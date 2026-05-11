import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class Usuarios {

  url = `${environment.apiUrl}/controllers/usuariosControlador.php`;

  constructor(private http: HttpClient) {}



consultar() {
  return this.http.get<any[]>(`${this.url}?control=consultar`);
}

  eliminar(id: number){
    return this.http.get(`${this.url}?control=eliminar&id=${id}`);
  }

  insertar(datos: any){
    return this.http.post(`${this.url}?control=insertar`, JSON.stringify(datos),{headers: {'Content-Type': 'application/json'}});
  }

  editar(id: number, datos: any){
    return this.http.post(`${this.url}?control=editar&id=${id}`, JSON.stringify(datos), {headers: {'Content-Type': 'application/json'}});
  }

  filtrar(datos: any){
    return this.http.get(`${this.url}?control=filtra&datos=${datos}`);
  }

cambiarEstado(id: number, estado: number) {
  return this.http.post(
    `${this.url}?control=cambiarEstado&id=${id}`,
    { estado },
    { headers: { 'Content-Type': 'application/json' } }
  );
}



}
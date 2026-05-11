import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class Fiadores {

    url = `${environment.apiUrl}/controllers/fiadoresControlador.php`;

  constructor(private http: HttpClient) { }

     consultar(){
    return this.http.get(`${this.url}?control=consultar`);
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
    return this.http.get(`${this.url}?control=filtra&datos=${datos}`);
  }
}

  


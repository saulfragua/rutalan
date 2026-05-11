import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class UsuarioRutaService {

  url = `${environment.apiUrl}/controllers/usuarioRutaControlador.php`;

  constructor(private http: HttpClient) {}

  consultar() {
    return this.http.get(`${this.url}?control=consultar`);
  }

rutasPorUsuario(id_usuario: number) {
  return this.http.get<any[]>(
    `${this.url}?control=filtrar&id_usuario=${id_usuario}`
  );
}


  asignar(datos: any) {
    return this.http.post(`${this.url}?control=insertar`, JSON.stringify(datos));
  }

  insertar(datos: any) {
    return this.http.post(`${this.url}?control=insertar`, JSON.stringify(datos), {
      headers: { 'Content-Type': 'application/json' }
    });
  }

  quitar(datos: any) {
    return this.http.post(`${this.url}?control=eliminar`, JSON.stringify(datos));
  }
}

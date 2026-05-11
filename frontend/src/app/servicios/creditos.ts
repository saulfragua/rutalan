import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from '../../environments/environment';

/**
 * Servicio de Créditos
 * Maneja todas las peticiones HTTP relacionadas con créditos
 */
@Injectable({
  providedIn: 'root',
})
export class CreditosService {

  url = `${environment.apiUrl}/controllers/creditosControlador.php`;

  constructor(private http: HttpClient) { }

  /**
   * Consulta todos los créditos activos
   * @returns Observable con la lista de créditos
   */
  consultar() {
    return this.http.get(`${this.url}?control=consultar`);
  }

  /**
   * Busca créditos por término de búsqueda (nombre del cliente)
   * @param termino Término de búsqueda
   * @returns Observable con la lista de créditos filtrados
   */
  buscar(termino: string) {
    return this.http.get(`${this.url}?control=buscar&termino=${encodeURIComponent(termino)}`);
  }

  /**
   * Consulta un crédito por su ID
   * @param id ID del crédito
   * @returns Observable con los datos del crédito
   */
  consultarPorId(id: number) {
    return this.http.get(`${this.url}?control=consultarPorId&id=${id}`);
  }

  /**
   * Verifica si un crédito tiene pagos registrados
   * @param id ID del crédito
   * @returns Observable con el resultado
   */
  tienePagos(id: number) {
    return this.http.get(`${this.url}?control=tienePagos&id=${id}`);
  }

  /**
   * Verifica si un cliente tiene un crédito pendiente
   * @param idCliente ID del cliente
   * @returns Observable con el resultado
   */
  clienteTieneCreditoPendiente(idCliente: number) {
    return this.http.get(`${this.url}?control=clienteTieneCreditoPendiente&id_cliente=${idCliente}`);
  }

  /**
   * Inserta un nuevo crédito
   * @param datos Datos del crédito
   * @returns Observable con el resultado
   */
  insertar(datos: any) {
    return this.http.post(`${this.url}?control=insertar`, JSON.stringify(datos));
  }

  /**
   * Edita un crédito existente
   * @param id ID del crédito
   * @param datos Datos actualizados del crédito
   * @returns Observable con el resultado
   */
  editar(id: number, datos: any) {
    return this.http.post(`${this.url}?control=editar&id=${id}`, JSON.stringify(datos));
  }

  /**
   * Elimina un crédito
   * @param id ID del crédito
   * @returns Observable con el resultado
   */
  eliminar(id: number) {
    return this.http.get(`${this.url}?control=eliminar&id=${id}`);
  }

  /**
   * Cancela un crédito
   * @param idCredito ID del crédito a cancelar
   * @param idUsuario ID del usuario que cancela el crédito
   * @returns Observable con el resultado
   */
  cancelar(idCredito: number, idUsuario: number) {
    return this.http.post(
      `${this.url}?control=cancelar`,
      JSON.stringify({ id_credito: idCredito, id_usuario: idUsuario }),
      { headers: { 'Content-Type': 'application/json' } }
    );
  }

  /**
   * Ejecuta la refinanciación automática de créditos vencidos
   * @returns Observable con el resultado
   */
  refinanciarAutomatico() {
    return this.http.get(`${this.url}?control=refinanciar_automatico`);
  }
}

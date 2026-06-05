import { Component, Inject, PLATFORM_ID } from '@angular/core';
import { Router } from '@angular/router';
import { LoginService } from '../../servicios/login';
import { CajaService } from '../../servicios/caja';
import { isPlatformBrowser } from '@angular/common';

declare var grecaptcha: any; // ← declara la variable global

@Component({
  selector: 'app-login',
  standalone: false,
  templateUrl: './login.html',
  styleUrl: './login.css',
})
export class Login {

  usuario: string = '';
  clave: string = '';
  private isBrowser: boolean;


// Variables para modal de apertura de caja
mostrarModalAperturaCaja: boolean = false;
saldoInicial: number = 0;
abriendoCaja: boolean = false;
usuarioLogeado: any = null;

constructor(
  private loginService: LoginService,
  private cajaService: CajaService,
  private router: Router,
  @Inject(PLATFORM_ID) private platformId: Object
) {
  this.isBrowser = isPlatformBrowser(this.platformId);
}

iniciarSesion() {

  if (!this.usuario || !this.clave) {
    alert('Debe ingresar usuario y contraseña');
    return;
  }

  const token = grecaptcha.getResponse();

  if (!token) {
    alert('Por favor completa el reCAPTCHA');
    return;
  }

  // Limpiar espacios en blanco
  this.usuario = this.usuario.trim();
  this.clave = this.clave.trim();

  if (!this.usuario || !this.clave) {
    alert('Usuario y contraseña no pueden estar vacíos');
    return;
  }

  this.loginService.login(this.usuario, this.clave,token)
    .subscribe({
      next: (resp: any) => {
        // Verificar si la respuesta tiene el formato esperado
        if (!resp) {
          alert('Error: No se recibió respuesta del servidor');
          return;
        }

        // Verificar el estado
        if (resp.estado === 'ok' || resp.resultado === 'ok') {
          const usuarioData = resp.usuario || {
            id_usuario: resp.id_usuario,
            nombre: resp.nombre || resp.nombre_completo,
            rol: resp.rol,
            rutas_asignadas: resp.usuario?.rutas_asignadas || []
          };

          // Guardar sesión solo en el navegador
          if (this.isBrowser && typeof localStorage !== 'undefined') {
            localStorage.setItem('usuario', JSON.stringify(usuarioData));
          }

          // Si es cobrador y requiere apertura de caja
          if (usuarioData.rol === 'cobrador' && resp.requiere_apertura_caja) {
            this.usuarioLogeado = usuarioData;
            // Asegurar que el saldo inicial siempre esté en 0 para nueva apertura
            this.saldoInicial = 0;
            this.mostrarModalAperturaCaja = true;
            return; // No redirigir aún
          }

          // Si es admin o cobrador con caja abierta, redirigir según el rol
          if (usuarioData.rol === 'admin') {
            this.router.navigate(['/dashboard']);
          } else {
            // Cobrador va a clientes
            this.router.navigate(['/clientes']);
          }

        } else {
          const mensaje = resp?.mensaje || resp?.error || 'Usuario o contraseña incorrectos';
          alert(mensaje);
        }

      },
      error: (error) => {
        let mensajeError = 'Error de conexión con el servidor';

        if (error?.message) {
          mensajeError = error.message;
        } else if (error?.error?.mensaje) {
          mensajeError = error.error.mensaje;
        } else if (error?.error?.message) {
          mensajeError = error.error.message;
        } else if (error?.status === 0) {
          mensajeError = 'No se pudo conectar con el servidor. Verifique:\n' +
            '1. Que XAMPP esté corriendo\n' +
            '2. Que Apache esté activo\n' +
            '3. Que la URL sea correcta';
        }

        alert('Error: ' + mensajeError);
      }
    });
}

/**
 * Abre la caja del cobrador
 */
abrirCaja() {
  // Validar que el saldo inicial sea un número válido y mayor o igual a 0 (permite 0)
  const saldo = parseFloat(String(this.saldoInicial || 0));

  if (!this.usuarioLogeado || isNaN(saldo) || saldo < 0) {
    alert('Debe indicar el monto con el que inicia la caja (puede ser $0.00)');
    return;
  }

  // Obtener las rutas asignadas al usuario
  const rutasAsignadas = this.usuarioLogeado.rutas_asignadas || [];
  if (rutasAsignadas.length === 0) {
    alert('Error: El usuario no tiene rutas asignadas para abrir caja');
    return;
  }

  // Extraer solo los IDs de las rutas
  const idRutas = rutasAsignadas.map((ruta: any) => ruta.id_ruta);

  this.abriendoCaja = true;

  const datosCaja = {
    id_usuario: this.usuarioLogeado.id_usuario,
    id_rutas: idRutas, // Enviar array de IDs de rutas
    saldo_inicial: saldo, // Usar el valor convertido y validado
    nombre_caja: 'Caja ' + new Date().toLocaleDateString('es-CO', { timeZone: 'America/Bogota' })
  };

  this.cajaService.abrirCaja(datosCaja).subscribe({
    next: (resp: any) => {
      this.abriendoCaja = false;
      if (resp.resultado === 'ok') {
        // Actualizar usuario con id_caja
        if (this.isBrowser && typeof localStorage !== 'undefined') {
          const usuarioData = JSON.parse(localStorage.getItem('usuario') || '{}');
          usuarioData.id_caja = resp.id_caja;
          usuarioData.tiene_caja_abierta = true;
          localStorage.setItem('usuario', JSON.stringify(usuarioData));
        }

        // Cerrar modal, resetear saldo inicial y redirigir
        this.mostrarModalAperturaCaja = false;
        this.saldoInicial = 0;
        this.usuarioLogeado = null;
        // Cobrador va a clientes después de abrir caja
        this.router.navigate(['/clientes']);
      } else {
        alert('Error al abrir caja: ' + (resp.mensaje || 'Error desconocido'));
      }
    },
    error: (error) => {
      this.abriendoCaja = false;
      alert('Error al abrir caja. Intente nuevamente.');
    }
  });
}

/**
 * Formatea un número como moneda
 */
formatearMoneda(valor: number): string {
  return new Intl.NumberFormat('es-CO', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(valor);
}
}

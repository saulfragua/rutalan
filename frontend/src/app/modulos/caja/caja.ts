import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { Subscription } from 'rxjs';
import { CajaService } from '../../servicios/caja';
import { Rutas as RutasService } from '../../servicios/rutas';
import { MovimientosCajaService } from '../../servicios/movimientos-caja';
import { isPlatformBrowser } from '@angular/common';

@Component({
  selector: 'app-caja',
  standalone: false,
  templateUrl: './caja.html',
  styleUrl: './caja.css',
})
export class Caja implements OnInit, OnDestroy {

  listaCajasAbiertas: any[] = [];
  cargando: boolean = false;
  private routerSubscription?: Subscription;
  isBrowser: boolean = false;

  // Modal de cierre de caja
  modalCierreAbierto: boolean = false;
  cajaSeleccionada: any = null;
  observacionesCierre: string = '';
  cerrandoCaja: boolean = false;

  // Modal de cajas cerradas
  modalCajasCerradasAbierto: boolean = false;
  listaCajasCerradas: any[] = [];
  cargandoCajasCerradas: boolean = false;

  // Modal de apertura de caja (para administrador)
  modalAperturaCajaAbierto: boolean = false;
  todasLasRutas: any[] = [];
  rutasSeleccionadas: number[] = [];
  saldoInicial: number = 0;
  abriendoCaja: boolean = false;
  usuarioActual: any = null;

  // Modal de entrada/salida de dinero
  modalMovimientoAbierto: boolean = false;
  tipoMovimiento: 'entrada' | 'salida' = 'entrada';
  movimientoForm: any = {
    id_caja: null,
    monto: 0,
    causal: '',
    metodo_pago: 'efectivo',
    observacion: ''
  };
  guardandoMovimiento: boolean = false;
  
  // Historial de movimientos
  historialMovimientos: any[] = [];
  modalHistorialAbierto: boolean = false;
  cargandoHistorial: boolean = false;

  constructor(
    private cajaService: CajaService,
    private rutasService: RutasService,
    private movimientosCajaService: MovimientosCajaService,
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    // Suscribirse a los eventos de navegación para recargar datos cada vez que se accede al módulo
    this.routerSubscription = this.router.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe((event: any) => {
        if (event.url === '/caja' || event.urlAfterRedirects === '/caja') {
          this.cargarCajasAbiertas();
        }
      });
  }

  ngOnInit() {
    // Validar que sea administrador
    if (this.isBrowser && typeof localStorage !== 'undefined') {
      const usuarioStr = localStorage.getItem('usuario');
      if (usuarioStr) {
        try {
          const usuario = JSON.parse(usuarioStr);
          if (usuario.rol !== 'admin') {
            this.router.navigate(['/clientes']);
            return;
          }
        } catch (error) {
          console.error('Error al validar rol:', error);
        }
      }
    }
    
    this.cargarCajasAbiertas();
    this.cargarUsuarioActual();
  }

  /**
   * Carga el usuario actual del localStorage
   */
  cargarUsuarioActual() {
    if (this.isBrowser && typeof localStorage !== 'undefined') {
      const usuarioStr = localStorage.getItem('usuario');
      if (usuarioStr) {
        try {
          this.usuarioActual = JSON.parse(usuarioStr);
        } catch (error) {
          console.error('Error al parsear usuario:', error);
        }
      }
    }
  }

  ngOnDestroy() {
    if (this.routerSubscription) {
      this.routerSubscription.unsubscribe();
    }
  }

  /**
   * Carga las cajas abiertas con resumen de operaciones
   */
  cargarCajasAbiertas() {
    this.cargando = true;
    this.cajaService.consultarCajasAbiertasConResumen().subscribe({
      next: (resp: any) => {
        console.log('Respuesta de cajas abiertas:', resp);
        // Verificar si la respuesta es un array o un objeto con error
        if (Array.isArray(resp)) {
          this.listaCajasAbiertas = resp;
        } else if (resp && resp.resultado === 'error') {
          alert(resp.mensaje || 'Error al cargar las cajas abiertas');
          this.listaCajasAbiertas = [];
        } else {
          this.listaCajasAbiertas = [];
        }
        this.cargando = false;
      },
      error: (error) => {
        console.error('Error al cargar cajas abiertas:', error);
        console.error('Error completo:', JSON.stringify(error, null, 2));
        this.cargando = false;
        const mensajeError = error?.error?.mensaje || error?.message || 'Error al cargar las cajas abiertas';
        alert(mensajeError);
        this.listaCajasAbiertas = [];
      }
    });
  }

  /**
   * Abre el modal para cerrar una caja
   */
  cerrarCaja(caja: any) {
    this.cajaSeleccionada = caja;
    this.observacionesCierre = '';
    this.modalCierreAbierto = true;
  }

  /**
   * Cierra el modal de cierre de caja
   */
  cerrarModalCierre() {
    this.modalCierreAbierto = false;
    this.cajaSeleccionada = null;
    this.observacionesCierre = '';
  }

  /**
   * Confirma el cierre de la caja
   */
  confirmarCierreCaja() {
    if (!this.cajaSeleccionada) {
      return;
    }

    this.cerrandoCaja = true;
    const totalRecolectado = parseFloat(this.cajaSeleccionada.total_recolectado);
    const observaciones = this.observacionesCierre.trim() || undefined;

    this.cajaService.cerrarCaja(this.cajaSeleccionada.id_caja, totalRecolectado, observaciones).subscribe({
      next: (resp: any) => {
        this.cerrandoCaja = false;
        if (resp && resp.resultado === 'ok') {
          alert(resp.mensaje || 'Caja cerrada correctamente');
          this.cerrarModalCierre();
          
          // Limpiar información de caja del localStorage
          if (this.isBrowser && typeof localStorage !== 'undefined') {
            const usuarioStr = localStorage.getItem('usuario');
            if (usuarioStr) {
              const usuario = JSON.parse(usuarioStr);
              usuario.tiene_caja_abierta = false;
              usuario.id_caja = null;
              localStorage.setItem('usuario', JSON.stringify(usuario));
            }
          }
          
          this.cargarCajasAbiertas();
        } else {
          alert(resp?.mensaje || 'Error al cerrar la caja');
        }
      },
      error: (error) => {
        this.cerrandoCaja = false;
        console.error('Error al cerrar caja:', error);
        const mensajeError = error?.error?.mensaje || error?.message || 'Error al cerrar la caja';
        alert(mensajeError);
      }
    });
  }

  /**
   * Formatea una fecha y hora (Zona horaria: Colombia GMT-5)
   */
  formatearFechaHora(fechaHora: string): string {
    if (!fechaHora) return '-';
    const date = new Date(fechaHora);
    return date.toLocaleString('es-CO', { 
      timeZone: 'America/Bogota',
      year: 'numeric', 
      month: '2-digit', 
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  /**
   * Formatea un monto como moneda
   */
  formatearMonto(monto: number | string): string {
    if (!monto && monto !== 0) return '$0.00';
    const num = typeof monto === 'string' ? parseFloat(monto) : monto;
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      minimumFractionDigits: 2
    }).format(num);
  }

  /**
   * Abre el modal de cajas cerradas
   */
  abrirModalCajasCerradas() {
    this.modalCajasCerradasAbierto = true;
    this.cargarCajasCerradas();
  }

  /**
   * Cierra el modal de cajas cerradas
   */
  cerrarModalCajasCerradas() {
    this.modalCajasCerradasAbierto = false;
    this.listaCajasCerradas = [];
  }

  /**
   * Carga las cajas cerradas
   */
  cargarCajasCerradas() {
    this.cargandoCajasCerradas = true;
    this.cajaService.consultarCajasCerradasConResumen().subscribe({
      next: (resp: any) => {
        console.log('Respuesta de cajas cerradas:', resp);
        if (Array.isArray(resp)) {
          this.listaCajasCerradas = resp;
        } else if (resp && resp.resultado === 'error') {
          alert(resp.mensaje || 'Error al cargar las cajas cerradas');
          this.listaCajasCerradas = [];
        } else {
          this.listaCajasCerradas = [];
        }
        this.cargandoCajasCerradas = false;
      },
      error: (error) => {
        console.error('Error al cargar cajas cerradas:', error);
        this.cargandoCajasCerradas = false;
        const mensajeError = error?.error?.mensaje || error?.message || 'Error al cargar las cajas cerradas';
        alert(mensajeError);
        this.listaCajasCerradas = [];
      }
    });
  }

  /**
   * Abre el modal para crear una nueva caja (solo administrador)
   */
  abrirModalAperturaCaja() {
    this.cargarUsuarioActual();
    if (!this.usuarioActual) {
      alert('No hay sesión activa');
      return;
    }

    // Verificar si ya tiene caja abierta
    this.cajaService.obtenerCajaAbierta(this.usuarioActual.id_usuario).subscribe({
      next: (caja: any) => {
        if (caja) {
          alert('Ya tiene una caja abierta. Debe cerrarla antes de abrir una nueva.');
          return;
        }
        // Cargar todas las rutas disponibles
        this.cargarTodasLasRutas();
        this.modalAperturaCajaAbierto = true;
        this.saldoInicial = 0;
        this.rutasSeleccionadas = [];
      },
      error: (error) => {
        console.error('Error al verificar caja:', error);
        // Continuar con la apertura aunque haya error
        this.cargarTodasLasRutas();
        this.modalAperturaCajaAbierto = true;
        this.saldoInicial = 0;
        this.rutasSeleccionadas = [];
      }
    });
  }

  /**
   * Carga todas las rutas disponibles
   */
  cargarTodasLasRutas() {
    this.rutasService.consultar().subscribe({
      next: (resp: any) => {
        this.todasLasRutas = resp || [];
      },
      error: (error) => {
        console.error('Error al cargar rutas:', error);
        this.todasLasRutas = [];
      }
    });
  }

  /**
   * Cierra el modal de apertura de caja
   */
  cerrarModalAperturaCaja() {
    this.modalAperturaCajaAbierto = false;
    this.saldoInicial = 0;
    this.rutasSeleccionadas = [];
  }

  /**
   * Toggle de selección de ruta
   */
  toggleRutaSeleccionada(idRuta: number) {
    const index = this.rutasSeleccionadas.indexOf(idRuta);
    if (index > -1) {
      this.rutasSeleccionadas.splice(index, 1);
    } else {
      this.rutasSeleccionadas.push(idRuta);
    }
  }

  /**
   * Verifica si una ruta está seleccionada
   */
  rutaEstaSeleccionada(idRuta: number): boolean {
    return this.rutasSeleccionadas.includes(idRuta);
  }

  /**
   * Abre la caja del administrador
   */
  abrirCajaAdmin() {
    if (!this.usuarioActual) {
      alert('No hay sesión activa');
      return;
    }

    // Validar que el saldo inicial sea un número válido y mayor o igual a 0
    // Convertir explícitamente a número y permitir 0
    const saldo = parseFloat(String(this.saldoInicial || 0));
    
    console.log('Saldo inicial recibido:', this.saldoInicial, 'Convertido:', saldo);
    
    if (isNaN(saldo) || saldo < 0) {
      alert('El monto inicial debe ser mayor o igual a 0');
      return;
    }

    // Las rutas son opcionales - permitir caja general sin rutas
    this.abriendoCaja = true;

    const datosCaja = {
      id_usuario: this.usuarioActual.id_usuario,
      id_rutas: this.rutasSeleccionadas.length > 0 ? this.rutasSeleccionadas : [],
      saldo_inicial: saldo,
      nombre_caja: this.rutasSeleccionadas.length > 0 
        ? 'Caja Admin ' + new Date().toLocaleDateString('es-CO', { timeZone: 'America/Bogota' })
        : 'Caja General ' + new Date().toLocaleDateString('es-CO', { timeZone: 'America/Bogota' })
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

          alert('Caja abierta correctamente');
          this.cerrarModalAperturaCaja();
          this.cargarCajasAbiertas();
        } else {
          alert('Error al abrir caja: ' + (resp.mensaje || 'Error desconocido'));
        }
      },
      error: (error) => {
        this.abriendoCaja = false;
        console.error('Error al abrir caja:', error);
        const mensajeError = error?.error?.mensaje || error?.message || 'Error al abrir caja. Intente nuevamente.';
        alert(mensajeError);
      }
    });
  }

  /**
   * Abre el modal para registrar entrada de dinero
   */
  abrirModalEntradaDinero() {
    this.tipoMovimiento = 'entrada';
    this.movimientoForm = {
      id_caja: null,
      monto: 0,
      causal: '',
      metodo_pago: 'efectivo',
      observacion: ''
    };
    this.modalMovimientoAbierto = true;
  }

  /**
   * Abre el modal para registrar salida de dinero
   */
  abrirModalSalidaDinero() {
    this.tipoMovimiento = 'salida';
    this.movimientoForm = {
      id_caja: null,
      monto: 0,
      causal: '',
      metodo_pago: 'efectivo',
      observacion: ''
    };
    this.modalMovimientoAbierto = true;
  }

  /**
   * Cierra el modal de movimiento
   */
  cerrarModalMovimiento() {
    this.modalMovimientoAbierto = false;
    this.movimientoForm = {
      id_caja: null,
      monto: 0,
      causal: '',
      metodo_pago: 'efectivo',
      observacion: ''
    };
  }

  /**
   * Registra un movimiento de entrada o salida de dinero
   */
  guardarMovimiento() {
    if (!this.movimientoForm.id_caja) {
      alert('Debe seleccionar una caja');
      return;
    }

    if (!this.movimientoForm.monto || this.movimientoForm.monto <= 0) {
      alert('El monto debe ser mayor a 0');
      return;
    }

    if (!this.movimientoForm.causal || this.movimientoForm.causal.trim() === '') {
      alert('Debe ingresar la causal del movimiento');
      return;
    }

    if (!this.usuarioActual || !this.usuarioActual.id_usuario) {
      alert('No hay sesión activa');
      return;
    }

    this.guardandoMovimiento = true;

    const datosMovimiento = {
      id_caja: this.movimientoForm.id_caja,
      id_usuario: this.usuarioActual.id_usuario,
      tipo: this.tipoMovimiento,
      monto: parseFloat(this.movimientoForm.monto),
      causal: this.movimientoForm.causal.trim(),
      metodo_pago: this.movimientoForm.metodo_pago,
      observacion: this.movimientoForm.observacion.trim() || ''
    };

    this.movimientosCajaService.registrarMovimiento(datosMovimiento).subscribe({
      next: (resp: any) => {
        this.guardandoMovimiento = false;
        if (resp && resp.resultado === 'ok') {
          alert(`Movimiento de ${this.tipoMovimiento === 'entrada' ? 'entrada' : 'salida'} registrado correctamente`);
          this.cerrarModalMovimiento();
          // Recargar cajas para actualizar los totales
          this.cargarCajasAbiertas();
        } else {
          const mensaje = resp?.mensaje || 'Error al registrar el movimiento';
          alert(mensaje);
          console.error('Respuesta del servidor:', resp);
        }
      },
      error: (error) => {
        this.guardandoMovimiento = false;
        console.error('Error completo al registrar movimiento:', error);
        console.error('Error details:', {
          status: error.status,
          statusText: error.statusText,
          error: error.error,
          message: error.message
        });
        
        let mensajeError = 'Error al registrar el movimiento';
        
        // Si hay un error en el body, intentar parsearlo
        if (error.error) {
          if (typeof error.error === 'string') {
            try {
              const errorParsed = JSON.parse(error.error);
              mensajeError = errorParsed.mensaje || errorParsed.message || mensajeError;
            } catch (e) {
              mensajeError = error.error;
            }
          } else if (error.error.mensaje) {
            mensajeError = error.error.mensaje;
          } else if (error.error.message) {
            mensajeError = error.error.message;
          }
        } else if (error.message) {
          mensajeError = error.message;
        }
        
        // Si el error es de parsing JSON, podría ser que la tabla no existe
        if (error.status === 200 && !error.ok) {
          mensajeError = 'Error al procesar la respuesta del servidor. Verifique que la tabla movimientos_caja existe en la base de datos.';
        }
        
        alert(mensajeError);
      }
    });
  }

  /**
   * Obtiene las cajas abiertas para el select
   */
  obtenerCajasAbiertas(): any[] {
    return this.listaCajasAbiertas || [];
  }

  /**
   * Abre el modal de historial de movimientos
   */
  abrirModalHistorial() {
    this.modalHistorialAbierto = true;
    this.cargarHistorialMovimientos();
  }

  /**
   * Cierra el modal de historial
   */
  cerrarModalHistorial() {
    this.modalHistorialAbierto = false;
    this.historialMovimientos = [];
  }

  /**
   * Carga el historial de movimientos de todas las cajas
   */
  cargarHistorialMovimientos() {
    this.cargandoHistorial = true;
    this.movimientosCajaService.consultarTodos().subscribe({
      next: (resp: any) => {
        this.historialMovimientos = resp || [];
        this.cargandoHistorial = false;
      },
      error: (error) => {
        console.error('Error al cargar historial:', error);
        this.cargandoHistorial = false;
        alert('Error al cargar el historial de movimientos');
      }
    });
  }

  /**
   * Formatea un monto como moneda
   */
  formatearMoneda(monto: number | string): string {
    if (!monto && monto !== 0) return '$0.00';
    const num = typeof monto === 'string' ? parseFloat(monto) : monto;
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      minimumFractionDigits: 2
    }).format(num);
  }

  /**
   * Formatea una fecha para mostrar
   */
  formatearFecha(fecha: string): string {
    if (!fecha) return '';
    const date = new Date(fecha);
    return date.toLocaleDateString('es-CO', { 
      timeZone: 'America/Bogota',
      year: 'numeric', 
      month: '2-digit', 
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  /**
   * Calcula y formatea el total recolectado de todas las cajas abiertas
   */
  formatearMontoTotalRecolectado(): string {
    const total = this.listaCajasAbiertas.reduce((sum, caja) => {
      return sum + (parseFloat(caja.total_recolectado) || 0);
    }, 0);
    return this.formatearMonto(total);
  }

  /**
   * Calcula y formatea el total de entradas de todas las cajas abiertas
   */
  formatearMontoTotalEntradas(): string {
    const total = this.listaCajasAbiertas.reduce((sum, caja) => {
      return sum + (parseFloat(caja.total_entradas) || 0);
    }, 0);
    return this.formatearMonto(total);
  }

  /**
   * Calcula y formatea el total de salidas de todas las cajas abiertas
   */
  formatearMontoTotalSalidas(): string {
    const total = this.listaCajasAbiertas.reduce((sum, caja) => {
      return sum + (parseFloat(caja.total_salidas) || 0);
    }, 0);
    return this.formatearMonto(total);
  }
}

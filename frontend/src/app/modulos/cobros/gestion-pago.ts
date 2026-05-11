import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { PagosService } from '../../servicios/pagos';
import { CajaService } from '../../servicios/caja';
import { isPlatformBrowser } from '@angular/common';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-gestion-pago',
  standalone: false,
  templateUrl: './gestion-pago.html',
  styleUrl: './gestion-pago.css',
})
export class GestionPago implements OnInit, OnDestroy {

  isBrowser: boolean = false;

  // Ruta y cliente
  idRuta: number | null = null;
  nombreRuta: string = '';
  indiceActual: number = 0;
  listaClientes: any[] = [];
  clienteActual: any = null;

  // Formulario de pago
  montoPago: number = 0;
  descuento: number = 0;
  procesandoPago: boolean = false;

  // Estado del cliente
  diasMora: number = 0;
  colorFondo: string = 'background-color: white;';
  leyenda: string = 'AL DÍA';
  mostrarCheck: boolean = false;

  // Usuario y caja
  usuarioActual: any = null;
  idCaja: number | null = null;

  // Foto del cliente
  mostrarFoto: boolean = false;
  mostrarModalFoto: boolean = false;

  // Estado de carga
  cargando: boolean = false;

  // Variables para swipe/touch
  touchStartX: number = 0;
  touchEndX: number = 0;
  minSwipeDistance: number = 50;

  constructor(
    private router: Router,
    private route: ActivatedRoute,
    private pagosService: PagosService,
    private cajaService: CajaService,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
  }

  ngOnInit() {
    this.cargarDatos();
  }

  ngOnDestroy() {
    // Cerrar modal de foto si está abierto
    this.cerrarModalFoto();
  }

  /**
   * Carga los datos iniciales
   */
  cargarDatos() {
    if (!this.isBrowser || typeof localStorage === 'undefined') {
      return;
    }

    // Obtener parámetros de la ruta
    this.route.queryParams.subscribe(params => {
      this.idRuta = params['ruta'] ? parseInt(params['ruta']) : null;
      this.indiceActual = params['indice'] ? parseInt(params['indice']) : 0;
      this.mostrarFoto = params['cargar_foto'] === 'true';

      if (this.idRuta) {
        this.cargarUsuario();
        this.cargarClientes();
      } else {
        alert('Debe seleccionar una ruta');
        this.router.navigate(['/cobros']);
      }
    });
  }

  /**
   * Carga la información del usuario actual
   */
  cargarUsuario() {
    const usuarioData = localStorage.getItem('usuario');
    if (usuarioData) {
      this.usuarioActual = JSON.parse(usuarioData);
      this.verificarCajaAbierta();
    }
  }

  /**
   * Verifica si el usuario tiene caja abierta
   * Para cobradores: obligatorio al iniciar sesión
   * Para administradores: obligatorio solo al realizar cobros
   */
  verificarCajaAbierta() {
    if (!this.usuarioActual) {
      return;
    }

    // Solo verificar automáticamente para cobradores (obligatorio al iniciar sesión)
    if (this.usuarioActual.rol === 'cobrador') {
      this.cajaService.obtenerCajaAbierta(this.usuarioActual.id_usuario).subscribe({
        next: (caja: any) => {
          if (caja) {
            this.idCaja = caja.id_caja;
          } else {
            alert('Debe abrir una caja para realizar cobros');
            this.router.navigate(['/caja']);
          }
        },
        error: (error) => {
        }
      });
    }
    // Para admin, verificar pero no bloquear (se validará al intentar cobrar)
    else if (this.usuarioActual.rol === 'admin') {
      this.cajaService.obtenerCajaAbierta(this.usuarioActual.id_usuario).subscribe({
        next: (caja: any) => {
          if (caja) {
            this.idCaja = caja.id_caja;
          }
          // No bloquear si no tiene caja, solo guardar el estado
        },
        error: (error) => {
        }
      });
    }
  }

  /**
   * Carga los clientes de la ruta
   */
  cargarClientes() {
    if (!this.idRuta) return;

    this.cargando = true;
    this.pagosService.consultarClientesPorRuta(this.idRuta).subscribe({
      next: (resp: any) => {
        this.listaClientes = resp || [];
        this.cargando = false;
        if (this.listaClientes.length > 0) {
          // Obtener nombre de la ruta del primer cliente
          if (this.listaClientes[0].nombre_ruta) {
            this.nombreRuta = this.listaClientes[0].nombre_ruta;
          }
          
          // Validar índice
          if (this.indiceActual >= this.listaClientes.length) {
            this.indiceActual = 0;
          }
          
          this.cargarClienteActual();
        } else {
          this.cargando = false;
          alert('No hay clientes con saldo pendiente en esta ruta');
          this.router.navigate(['/cobros'], { queryParams: { ruta: this.idRuta } });
        }
      },
      error: (error) => {
        this.cargando = false;
        alert('Error al cargar los clientes');
      }
    });
  }

  /**
   * Carga el cliente actual y calcula su estado
   */
  cargarClienteActual() {
    if (this.indiceActual >= 0 && this.indiceActual < this.listaClientes.length) {
      // Guardar el estado de mostrarFoto antes de cambiar de cliente
      const fotoAnteriorVisible = this.mostrarFoto;
      
      // Cerrar modal de foto si está abierto
      this.cerrarModalFoto();
      
      this.clienteActual = this.listaClientes[this.indiceActual];
      this.calcularEstadoCliente();
      this.montoPago = 0;
      this.descuento = 0;
      
      // Si el nuevo cliente tiene foto y la foto estaba visible, mantenerla visible
      // Si no tiene foto, ocultarla
      if (!this.clienteActual.foto_cliente) {
        this.mostrarFoto = false;
      }
      // Si tiene foto, mantener el estado anterior (si estaba visible, seguir visible)
      // Esto permite que si el usuario quiere ver fotos, se mantenga la preferencia
    }
  }

  /**
   * Calcula el estado del cliente (semáforo)
   */
  calcularEstadoCliente() {
    if (!this.clienteActual) return;

    // Si el crédito es refinanciado por sistema, mostrar RECLAVO en rojo
    if (this.clienteActual.tipo_credito === 'refinanciado_por_sistema') {
      this.colorFondo = 'background-color: #dc3545; color: white;';
      this.leyenda = 'RECLAVO';
      return;
    }

    // Verificar si el último pago fue hoy
    const fechaUltimoPago = this.clienteActual.ultimo_pago;
    const hoy = new Date().toISOString().split('T')[0];
    const esRefinanciadoHoy =
      this.clienteActual.tipo_credito === 'refinanciado' &&
      this.clienteActual.fecha_toma_credito === hoy;
    this.mostrarCheck = fechaUltimoPago === hoy || esRefinanciadoHoy;

    // Calcular días de mora desde fecha de crédito
    const fechaCredito = new Date(this.clienteActual.fecha_toma_credito);
    const hoyObj = new Date();
    const diffTime = hoyObj.getTime() - fechaCredito.getTime();
    this.diasMora = Math.floor(diffTime / (1000 * 60 * 60 * 24));

    // Determinar color y leyenda según cuotas vencidas
    const cuotasVencidas = this.clienteActual.cuotas_vencidas || 0;

    if (cuotasVencidas === 0) {
      this.colorFondo = 'background-color: white; color: black;';
      this.leyenda = 'AL DÍA';
    } else if (this.diasMora >= 1 && this.diasMora <= 30) {
      this.colorFondo = 'background-color: #28a745; color: white;';
      this.leyenda = `PENDIENTE (${this.diasMora} días)`;
    } else if (this.diasMora >= 31 && this.diasMora <= 40) {
      this.colorFondo = 'background-color: #ffc107; color: black;';
      this.leyenda = `VENCIDO (${this.diasMora} días)`;
    } else if (this.diasMora >= 41 && this.diasMora <= 70) {
      this.colorFondo = 'background-color: #fd7e14; color: white;';
      this.leyenda = `CLAVO (${this.diasMora} días)`;
    } else if (this.diasMora >= 71) {
      this.colorFondo = 'background-color: #dc3545; color: white;';
      this.leyenda = `RECLAVO (${this.diasMora} días)`;
    }
  }

  /**
   * Navega al cliente anterior
   */
  clienteAnterior() {
    if (this.indiceActual > 0) {
      this.indiceActual--;
      this.router.navigate(['/gestion-pago'], {
        queryParams: { ruta: this.idRuta, indice: this.indiceActual }
      });
      this.cargarClienteActual();
    }
  }

  /**
   * Navega al siguiente cliente
   */
  clienteSiguiente() {
    if (this.indiceActual < this.listaClientes.length - 1) {
      this.indiceActual++;
      this.router.navigate(['/gestion-pago'], {
        queryParams: { ruta: this.idRuta, indice: this.indiceActual }
      });
      this.cargarClienteActual();
    }
  }

  /**
   * Registra el pago
   */
  registrarPago() {
    if (!this.clienteActual || !this.usuarioActual) {
      alert('Error: No hay información del cliente o usuario');
      return;
    }

    // Validar caja abierta OBLIGATORIA para cobradores y administradores
    if (!this.idCaja) {
      if (this.usuarioActual.rol === 'admin') {
        const confirmar = confirm('Para realizar un cobro, debe tener una caja abierta. ¿Desea abrir una caja ahora?');
        if (confirmar) {
          this.router.navigate(['/caja']);
        }
      } else {
        alert('Debe abrir una caja para realizar cobros');
        this.router.navigate(['/caja']);
      }
      return;
    }

    const montoNeto = this.montoPago + this.descuento;
    const saldoActual = parseFloat(this.clienteActual.saldo_actual);

    if (montoNeto <= 0) {
      alert('El monto a pagar debe ser mayor a 0');
      return;
    }

    if (montoNeto > saldoActual) {
      alert('El monto a pagar no puede ser mayor que el saldo actual');
      return;
    }

    this.procesandoPago = true;

    const datosPago = {
      id_cliente: this.clienteActual.id_cliente,
      id_credito: this.clienteActual.id_credito,
      monto_pagado: this.montoPago,
      descuento: this.descuento,
      id_usuario: this.usuarioActual.id_usuario,
      id_ruta: this.idRuta,
      id_caja: this.idCaja
    };

    this.pagosService.registrarPago(datosPago).subscribe({
      next: (resp: any) => {
        this.procesandoPago = false;
        if (resp && resp.resultado === 'ok') {
          alert('Pago registrado correctamente');
          // Recargar clientes para actualizar saldos
          this.cargarClientes();
        } else {
          alert(resp?.mensaje || 'Error al registrar el pago');
        }
      },
      error: (error) => {
        this.procesandoPago = false;
        console.error('Error al registrar pago:', error);
        const mensajeError = error?.error?.mensaje || error?.message || 'Error al registrar el pago';
        alert(mensajeError);
      }
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
   * Carga la foto del cliente - Toggle para mostrar/ocultar
   */
  cargarFoto() {
    if (!this.clienteActual) {
      return;
    }
    
    if (this.clienteActual.foto_cliente) {
      // Si el cliente tiene foto, alternar la visualización
      this.mostrarFoto = !this.mostrarFoto;
    } else {
      // Si no tiene foto, mostrar mensaje
      alert('Este cliente no tiene foto registrada');
    }
  }

  /**
   * Oculta la foto del cliente
   */
  ocultarFoto() {
    this.mostrarFoto = false;
  }

  /**
   * Abre el modal de foto del cliente
   */
  abrirModalFoto() {
    if (!this.clienteActual || !this.clienteActual.foto_cliente) {
      alert('Este cliente no tiene foto registrada');
      return;
    }
    
    this.mostrarModalFoto = true;
    if (this.isBrowser) {
      document.body.style.overflow = 'hidden';
    }
  }

  /**
   * Cierra el modal de foto del cliente
   */
  cerrarModalFoto() {
    this.mostrarModalFoto = false;
    if (this.isBrowser) {
      document.body.style.overflow = '';
    }
  }

  /**
   * Obtiene la ruta completa de la foto del cliente
   */
  obtenerRutaFoto(fotoCliente: string): string {
    if (!fotoCliente) {
      return 'assets/dist/img/documentos/foto.jpg';
    }
    
    // Si ya es una URL completa, retornarla tal cual
    if (fotoCliente.startsWith('http://') || fotoCliente.startsWith('https://')) {
      return fotoCliente;
    }
    
    // Construir la URL completa del backend
    return environment.apiUrl + '/' + fotoCliente;
  }

  /**
   * Maneja errores al cargar la imagen
   */
  manejarErrorImagen(event: any) {
    // Si hay error, mostrar imagen por defecto pero mantener la foto visible
    event.target.src = 'assets/dist/img/documentos/foto.jpg';
  }

  /**
   * Filtra clientes no cobrados hoy
   */
  filtrarClientesNoCobradosHoy(event?: any) {
    const hoy = new Date().toISOString().split('T')[0];
    let indiceSeleccionado = event ? parseInt(event.target.value) : null;
    
    if (indiceSeleccionado !== null && indiceSeleccionado >= 0 && indiceSeleccionado < this.listaClientes.length) {
      this.indiceActual = indiceSeleccionado;
      this.router.navigate(['/gestion-pago'], {
        queryParams: { ruta: this.idRuta, indice: this.indiceActual }
      });
      this.cargarClienteActual();
    } else {
      // Buscar el primer cliente no cobrado hoy
      const clientesNoCobrados = this.listaClientes.filter(c => !c.ultimo_pago || c.ultimo_pago !== hoy);
      
      if (clientesNoCobrados.length > 0) {
        const indice = this.listaClientes.findIndex(c => c.id_cliente === clientesNoCobrados[0].id_cliente);
        if (indice !== -1) {
          this.indiceActual = indice;
          this.router.navigate(['/gestion-pago'], {
            queryParams: { ruta: this.idRuta, indice: this.indiceActual }
          });
          this.cargarClienteActual();
        }
      } else {
        alert('Todos los clientes ya fueron cobrados hoy');
      }
    }
  }

  /**
   * Obtiene la lista de clientes no cobrados hoy para el select
   */
  obtenerClientesNoCobradosHoy(): any[] {
    const hoy = new Date().toISOString().split('T')[0];
    return this.listaClientes.filter(c => !c.ultimo_pago || c.ultimo_pago !== hoy);
  }

  /**
   * Obtiene el índice de un cliente en la lista completa
   */
  obtenerIndiceCliente(idCliente: number): number {
    return this.listaClientes.findIndex(c => c.id_cliente === idCliente);
  }

  /**
   * Maneja el inicio del touch para swipe
   */
  onTouchStart(event: TouchEvent) {
    this.touchStartX = event.changedTouches[0].screenX;
  }

  /**
   * Maneja el fin del touch para detectar swipe
   */
  onTouchEnd(event: TouchEvent) {
    this.touchEndX = event.changedTouches[0].screenX;
    this.handleSwipe();
  }

  /**
   * Procesa el gesto de swipe
   */
  handleSwipe() {
    const swipeDistance = this.touchStartX - this.touchEndX;
    
    // Swipe izquierda (deslizar hacia izquierda) = siguiente cliente
    if (Math.abs(swipeDistance) > this.minSwipeDistance) {
      if (swipeDistance > 0) {
        // Swipe izquierda - siguiente cliente
        this.clienteSiguiente();
      } else {
        // Swipe derecha - cliente anterior
        this.clienteAnterior();
      }
    }
  }

  /**
   * Detecta si es dispositivo móvil
   */
  esMovil(): boolean {
    if (!this.isBrowser) return false;
    return window.innerWidth < 768;
  }

  /**
   * Maneja el hover del botón anterior
   */
  onMouseEnterAnterior(event: MouseEvent) {
    if (this.indiceActual !== 0) {
      (event.target as HTMLElement).style.transform = 'translateY(-2px)';
      (event.target as HTMLElement).style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.15)';
    }
  }

  /**
   * Maneja el hover out del botón anterior
   */
  onMouseLeaveAnterior(event: MouseEvent) {
    (event.target as HTMLElement).style.transform = 'translateY(0)';
    (event.target as HTMLElement).style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.1)';
  }

  /**
   * Maneja el hover del botón siguiente
   */
  onMouseEnterSiguiente(event: MouseEvent) {
    if (this.indiceActual !== this.listaClientes.length - 1) {
      (event.target as HTMLElement).style.transform = 'translateY(-2px)';
      (event.target as HTMLElement).style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.15)';
    }
  }

  /**
   * Maneja el hover out del botón siguiente
   */
  onMouseLeaveSiguiente(event: MouseEvent) {
    (event.target as HTMLElement).style.transform = 'translateY(0)';
    (event.target as HTMLElement).style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.1)';
  }

  /**
   * Maneja el hover del botón pagar
   */
  onMouseEnterPagar(event: MouseEvent) {
    if (!this.procesandoPago) {
      (event.target as HTMLElement).style.transform = 'translateY(-2px)';
      (event.target as HTMLElement).style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.15)';
    }
  }

  /**
   * Maneja el hover out del botón pagar
   */
  onMouseLeavePagar(event: MouseEvent) {
    (event.target as HTMLElement).style.transform = 'translateY(0)';
    (event.target as HTMLElement).style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.1)';
  }

  /**
   * Verifica si el cliente actual tiene ubicación GPS
   */
  tieneUbicacionGPS(): boolean {
    if (!this.clienteActual) {
      return false;
    }
    const latitud = this.clienteActual.latitud || this.clienteActual.lat;
    const longitud = this.clienteActual.longitud || this.clienteActual.lng || this.clienteActual.lon;
    return latitud != null && longitud != null && latitud !== '' && longitud !== '' && 
           !isNaN(parseFloat(latitud)) && !isNaN(parseFloat(longitud));
  }

  /**
   * Abre la ruta GPS del cliente en la aplicación de mapas del dispositivo
   */
  abrirRutaGPS() {
    if (!this.clienteActual || !this.tieneUbicacionGPS()) {
      if (this.isBrowser && typeof alert !== 'undefined') {
        alert('El cliente no tiene ubicación GPS registrada');
      }
      return;
    }

    const latitud = this.clienteActual.latitud || this.clienteActual.lat;
    const longitud = this.clienteActual.longitud || this.clienteActual.lng || this.clienteActual.lon;

    if (!this.isBrowser) {
      return;
    }

    // Intentar abrir en Google Maps (funciona en móviles y desktop)
    const urlGoogleMaps = `https://www.google.com/maps/dir/?api=1&destination=${latitud},${longitud}`;
    
    // Para iOS, intentar primero con Apple Maps
    const userAgent = navigator.userAgent || navigator.vendor || (window as any).opera;
    const isIOS = /iPad|iPhone|iPod/.test(userAgent) && !(window as any).MSStream;
    
    if (isIOS) {
      // Intentar abrir en Apple Maps primero
      const urlAppleMaps = `http://maps.apple.com/?daddr=${latitud},${longitud}&dirflg=d`;
      window.open(urlAppleMaps, '_blank');
    } else {
      // Para Android y otros, usar Google Maps
      window.open(urlGoogleMaps, '_blank');
    }
  }
}

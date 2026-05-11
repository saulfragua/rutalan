import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { Subscription } from 'rxjs';
import { isPlatformBrowser } from '@angular/common';
import { CreditosService } from '../../servicios/creditos';
import { Clientes as ClientesService } from '../../servicios/cliente';
import { Planpagos } from '../../servicios/planpagos';
import { CajaService } from '../../servicios/caja';
import { UsuarioRutaService } from '../../servicios/usuarioruta';

/**
 * Componente de Gestión de Créditos
 * Maneja la creación, edición, eliminación y consulta de créditos
 */
@Component({
  selector: 'app-creditos',
  standalone: false,
  templateUrl: './creditos.html',
  styleUrl: './creditos.css',
})
export class Creditos implements OnInit, OnDestroy {

  // Lista de créditos
  listaCreditos: any[] = [];
  cargando: boolean = false;

  // Plan de pagos
  listaPlanPagos: any[] = [];
  planPagosInfo: any = null;
  cargandoPlanPagos: boolean = false;

  // Búsqueda
  terminoBusqueda: string = '';

  // Modal
  modoEdicion: boolean = false;
  creditoEditando: any = null;
  tienePagos: boolean = false;
  clienteTieneCreditoPendiente: boolean = false;
  creditosPendientesCliente: any[] = [];

  // Lista de clientes
  listaClientes: any[] = [];
  listaClientesFiltrados: any[] = [];
  terminoBusquedaCliente: string = '';
  
  // Rutas del usuario (para cobradores)
  rutasUsuario: any[] = [];

  // Formulario
  formularioCredito: any = {
    id_cliente: '',
    monto_credito: 0,
    cuotas: 31,
    frecuencia_pago: 'diario',
    incluir_seguro: true,
    fecha_toma_credito: '',
    hora_toma_credito: ''
  };

  // Resumen calculado
  resumen: any = {
    monto_credito: 0,
    seguro: 0,
    monto_entregar: 0,
    intereses: 0,
    total_pagar: 0,
    fecha_finalizacion: '-'
  };

  private isBrowser: boolean;
  private routerSubscription?: Subscription;
  
  // Rol del usuario logueado
  rolUsuario: string = '';

  constructor(
    private creditosService: CreditosService,
    private clientesService: ClientesService,
    private planPagosService: Planpagos,
    private cajaService: CajaService,
    private usuarioRutaService: UsuarioRutaService,
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    
    // Suscribirse a los eventos de navegación para recargar datos cada vez que se accede al módulo
    this.routerSubscription = this.router.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe((event: any) => {
        if (event.url === '/creditos' || event.urlAfterRedirects === '/creditos') {
          this.obtenerRolUsuario();
          // Si es cobrador, cargar rutas primero (esto también cargará clientes y créditos)
          // Si es admin, cargar directamente clientes y créditos
          if (this.rolUsuario === 'cobrador') {
            this.cargarRutasUsuario();
          } else {
            this.cargarClientes();
            this.cargarCreditos();
          }
        }
      });
  }

  /**
   * Inicializa el componente
   */
  ngOnInit() {
    this.obtenerRolUsuario();
    
    // Si es cobrador, cargar sus rutas primero (esto también cargará clientes y créditos)
    // Si es admin, cargar directamente clientes y créditos
    if (this.rolUsuario === 'cobrador') {
      this.cargarRutasUsuario();
    } else {
      this.cargarClientes();
      this.cargarCreditos();
    }
    
    // Inicializar cálculo del resumen
    this.calcularResumen();
  }
  
  /**
   * Obtiene el rol del usuario logueado
   */
  obtenerRolUsuario() {
    if (!this.isBrowser || typeof localStorage === 'undefined') {
      this.rolUsuario = '';
      return;
    }
    
    const usuarioData = localStorage.getItem('usuario');
    if (!usuarioData) {
      this.rolUsuario = '';
      return;
    }
    
    try {
      const usuario = JSON.parse(usuarioData);
      this.rolUsuario = usuario.rol || '';
    } catch (error) {
      console.error('Error al obtener rol del usuario:', error);
      this.rolUsuario = '';
    }
  }

  /**
   * Limpia las suscripciones al destruir el componente
   */
  ngOnDestroy() {
    if (this.routerSubscription) {
      this.routerSubscription.unsubscribe();
    }
  }

  /**
   * Carga la lista de créditos desde el servidor
   * - Administrador: ve todos los créditos activos con saldo > 0
   * - Cobrador: solo ve créditos de sus rutas asignadas con saldo > 0
   */
  cargarCreditos() {
    this.cargando = true;
    this.creditosService.consultar().subscribe({
      next: (resp: any) => {
        let creditosFiltrados = resp || [];
        
        // Filtrar créditos con saldo en 0 (sin saldo pendiente)
        creditosFiltrados = creditosFiltrados.filter((credito: any) => {
          const saldoActual = parseFloat(credito.saldo_actual || 0);
          return saldoActual > 0;
        });
        
        // Si es cobrador, filtrar solo créditos de sus rutas asignadas
        if (this.rolUsuario === 'cobrador' && this.rutasUsuario.length > 0) {
          const idRutasUsuario = this.rutasUsuario.map((r: any) => r.id_ruta);
          creditosFiltrados = creditosFiltrados.filter((credito: any) => 
            credito.id_ruta && idRutasUsuario.includes(parseInt(credito.id_ruta))
          );
          console.log('🟡 Cobrador - Créditos filtrados por rutas y saldo > 0:', creditosFiltrados.length, 'de', (resp || []).length);
        } else if (this.rolUsuario === 'cobrador' && this.rutasUsuario.length === 0) {
          // Si es cobrador pero no tiene rutas asignadas, no mostrar créditos
          console.warn('⚠️ Cobrador sin rutas asignadas - No se mostrarán créditos');
          creditosFiltrados = [];
        } else {
          console.log('🔵 Administrador - Créditos con saldo > 0:', creditosFiltrados.length, 'de', (resp || []).length);
        }
        
        this.listaCreditos = creditosFiltrados;
        this.cargando = false;
      },
      error: (error) => {
        console.error('Error al cargar créditos:', error);
        this.cargando = false;
        alert('Error al cargar los créditos');
      }
    });
  }

  /**
   * Carga las rutas asignadas al usuario (solo para cobradores)
   */
  cargarRutasUsuario() {
    if (!this.isBrowser || typeof localStorage === 'undefined') {
      return;
    }
    
    const usuarioData = localStorage.getItem('usuario');
    if (!usuarioData) {
      return;
    }
    
    try {
      const usuario = JSON.parse(usuarioData);
      const idUsuario = usuario.id_usuario;
      
      if (idUsuario && !isNaN(parseInt(String(idUsuario)))) {
        this.usuarioRutaService.rutasPorUsuario(parseInt(String(idUsuario))).subscribe({
          next: (resp: any) => {
            this.rutasUsuario = resp || [];
            console.log('🟡 Cobrador - Rutas asignadas:', this.rutasUsuario);
            // Después de cargar rutas, cargar clientes y créditos filtrados por esas rutas
            this.cargarClientes();
            this.cargarCreditos();
          },
          error: (error) => {
            console.error('Error al cargar rutas del usuario:', error);
            this.rutasUsuario = [];
            // Intentar cargar clientes y créditos de todas formas
            this.cargarClientes();
            this.cargarCreditos();
          }
        });
      } else {
        // Si no hay idUsuario válido, cargar todos los clientes
        this.cargarClientes();
      }
    } catch (error) {
      console.error('Error al parsear datos del usuario:', error);
      this.rutasUsuario = [];
      this.cargarClientes();
    }
  }

  /**
   * Carga la lista de clientes activos
   * - Administrador: ve todos los clientes activos
   * - Cobrador: solo ve clientes de sus rutas asignadas
   */
  cargarClientes() {
    this.clientesService.consultar().subscribe({
      next: (resp: any) => {
        // Filtrar solo clientes activos
        let clientesActivos = (resp || []).filter((c: any) => c.activo == 1);
        
        // Si es cobrador, filtrar solo clientes de sus rutas asignadas
        if (this.rolUsuario === 'cobrador' && this.rutasUsuario.length > 0) {
          const idRutasUsuario = this.rutasUsuario.map((r: any) => r.id_ruta);
          clientesActivos = clientesActivos.filter((c: any) => 
            c.id_ruta && idRutasUsuario.includes(parseInt(c.id_ruta))
          );
          console.log('🟡 Cobrador - Clientes filtrados por rutas:', clientesActivos.length, 'de', (resp || []).length);
        } else if (this.rolUsuario === 'cobrador' && this.rutasUsuario.length === 0) {
          // Si es cobrador pero no tiene rutas asignadas, no mostrar clientes
          console.warn('⚠️ Cobrador sin rutas asignadas - No se mostrarán clientes');
          clientesActivos = [];
        } else {
          console.log('🔵 Administrador - Todos los clientes activos:', clientesActivos.length);
        }
        
        this.listaClientes = clientesActivos;
        this.listaClientesFiltrados = this.listaClientes;
      },
      error: (error) => {
        console.error('Error al cargar clientes:', error);
        this.listaClientes = [];
        this.listaClientesFiltrados = [];
      }
    });
  }

  /**
   * Busca créditos por término de búsqueda
   * Aplica los mismos filtros: rutas del usuario (si es cobrador) y saldo > 0
   */
  buscarCreditos() {
    const termino = this.terminoBusqueda.trim();
    
    if (!termino) {
      this.cargarCreditos();
      return;
    }

    this.cargando = true;
    this.creditosService.buscar(termino).subscribe({
      next: (resp: any) => {
        // Verificar si la respuesta es un array o un objeto con error
        if (Array.isArray(resp)) {
          let creditosFiltrados = resp;
          
          // Filtrar créditos con saldo en 0 (sin saldo pendiente)
          creditosFiltrados = creditosFiltrados.filter((credito: any) => {
            const saldoActual = parseFloat(credito.saldo_actual || 0);
            return saldoActual > 0;
          });
          
          // Si es cobrador, filtrar solo créditos de sus rutas asignadas
          if (this.rolUsuario === 'cobrador' && this.rutasUsuario.length > 0) {
            const idRutasUsuario = this.rutasUsuario.map((r: any) => r.id_ruta);
            creditosFiltrados = creditosFiltrados.filter((credito: any) => 
              credito.id_ruta && idRutasUsuario.includes(parseInt(credito.id_ruta))
            );
            console.log('🟡 Cobrador - Búsqueda: Créditos filtrados por rutas y saldo > 0:', creditosFiltrados.length);
          } else if (this.rolUsuario === 'cobrador' && this.rutasUsuario.length === 0) {
            // Si es cobrador pero no tiene rutas asignadas, no mostrar créditos
            creditosFiltrados = [];
          }
          
          this.listaCreditos = creditosFiltrados;
        } else if (resp && resp.resultado === 'error') {
          alert(resp.mensaje || 'Error al buscar los créditos');
          this.listaCreditos = [];
        } else {
          this.listaCreditos = [];
        }
        this.cargando = false;
      },
      error: (error) => {
        console.error('Error al buscar créditos:', error);
        this.cargando = false;
        const mensajeError = error?.error?.mensaje || error?.message || 'Error al buscar los créditos';
        alert(mensajeError);
        this.listaCreditos = [];
      }
    });
  }

  /**
   * Limpia la búsqueda y recarga todos los créditos
   */
  limpiarBusqueda() {
    this.terminoBusqueda = '';
    this.cargarCreditos();
  }

  /**
   * Filtra la lista de clientes según el término de búsqueda
   */
  filtrarClientes() {
    if (!this.terminoBusquedaCliente.trim()) {
      this.listaClientesFiltrados = this.listaClientes;
      return;
    }

    const termino = this.terminoBusquedaCliente.toLowerCase();
    this.listaClientesFiltrados = this.listaClientes.filter((cliente: any) => {
      const nombreCompleto = `${cliente.nombres} ${cliente.apellidos}`.toLowerCase();
      return nombreCompleto.includes(termino) || cliente.documento.includes(termino);
    });
  }

  /**
   * Abre el modal para crear un nuevo crédito
   */
  abrirModalCredito() {
    this.modoEdicion = false;
    this.creditoEditando = null;
    this.tienePagos = false;
    this.clienteTieneCreditoPendiente = false;
    this.creditosPendientesCliente = [];
    
    // Asegurar que el rol esté actualizado y los clientes cargados correctamente
    this.obtenerRolUsuario();
    if (this.rolUsuario === 'cobrador' && this.rutasUsuario.length === 0) {
      // Si es cobrador y no tiene rutas cargadas, cargarlas primero
      this.cargarRutasUsuario();
    } else if (this.listaClientes.length === 0) {
      // Si no hay clientes cargados, recargarlos
      if (this.rolUsuario === 'cobrador') {
        this.cargarRutasUsuario();
      } else {
        this.cargarClientes();
      }
    }
    
    this.resetearFormulario();
    this.mostrarModal();
  }

  /**
   * Abre el modal para editar un crédito existente
   * @param idCredito ID del crédito a editar
   */
  editarCredito(idCredito: number) {
    this.modoEdicion = true;
    this.cargando = true;

    // Verificar si tiene pagos
    this.creditosService.tienePagos(idCredito).subscribe({
      next: (resp: any) => {
        this.tienePagos = resp.tiene_pagos || false;

        // Cargar datos del crédito
        this.creditosService.consultarPorId(idCredito).subscribe({
          next: (credito: any) => {
            this.creditoEditando = credito;
            this.formularioCredito = {
              id_cliente: credito.id_cliente,
              monto_credito: parseFloat(credito.monto_credito),
              cuotas: parseInt(credito.cuotas),
              frecuencia_pago: credito.frecuencia_pago,
              incluir_seguro: parseFloat(credito.seguro) > 0,
              fecha_toma_credito: credito.fecha_toma_credito || new Date().toISOString().split('T')[0],
              hora_toma_credito: credito.hora_toma_credito || new Date().toTimeString().split(' ')[0].substring(0, 5)
            };
            this.calcularResumen();
            this.mostrarModal();
            this.cargando = false;
          },
          error: (error) => {
            console.error('Error al cargar crédito:', error);
            this.cargando = false;
            alert('Error al cargar el crédito');
          }
        });
      },
      error: (error) => {
        console.error('Error al verificar pagos:', error);
        this.cargando = false;
      }
    });
  }

  /**
   * Verifica si el cliente seleccionado tiene un crédito pendiente (solo informativo)
   * Los clientes pueden tener múltiples créditos activos
   */
  validarCliente() {
    if (!this.formularioCredito.id_cliente || this.modoEdicion) {
      this.clienteTieneCreditoPendiente = false;
      this.creditosPendientesCliente = [];
      return;
    }

    // Solo verificar para mostrar información, no para bloquear
    this.creditosService.clienteTieneCreditoPendiente(this.formularioCredito.id_cliente).subscribe({
      next: (resp: any) => {
        this.clienteTieneCreditoPendiente = resp.tiene_credito_pendiente || false;
        this.creditosPendientesCliente = resp.creditos || [];
      },
      error: (error) => {
        console.error('Error al validar cliente:', error);
        this.clienteTieneCreditoPendiente = false;
        this.creditosPendientesCliente = [];
      }
    });
  }

  /**
   * Calcula el seguro según el monto y las cuotas
   * @param montoCredito Monto del crédito
   * @param cuotas Número de cuotas (días)
   * @returns Monto del seguro
   */
  calcularSeguro(montoCredito: number, cuotas: number): number {
    let seguro = 0;

    if (!this.formularioCredito.incluir_seguro) {
      return 0;
    }

    // Calcular seguro según rangos
    if (montoCredito >= 0 && montoCredito <= 100) {
      seguro = 5;
    } else if (montoCredito >= 101 && montoCredito <= 200) {
      seguro = 10;
    } else if (montoCredito >= 201 && montoCredito <= 300) {
      seguro = 15;
    } else if (montoCredito >= 301 && montoCredito <= 400) {
      seguro = 20;
    } else if (montoCredito >= 401 && montoCredito <= 500) {
      seguro = 25;
    } else if (montoCredito >= 501 && montoCredito <= 600) {
      seguro = 30;
    } else if (montoCredito >= 601 && montoCredito <= 700) {
      seguro = 35;
    } else if (montoCredito >= 701 && montoCredito <= 800) {
      seguro = 40;
    } else if (montoCredito >= 801 && montoCredito <= 900) {
      seguro = 45;
    } else if (montoCredito >= 901 && montoCredito <= 1000) {
      seguro = 50;
    } else {
      // Para montos mayores a 1000: $50 + $5 por cada $100 adicionales
      seguro = 50 + (Math.floor((montoCredito - 1000) / 100) * 5);
    }

    // Doble del seguro si el crédito es de 70 días
    if (cuotas === 70) {
      seguro *= 2;
    }

    return seguro;
  }

  /**
   * Calcula la tasa de interés según las cuotas
   * @param cuotas Número de cuotas (días)
   * @returns Tasa de interés
   */
  calcularTasaInteres(cuotas: number): number {
    return cuotas === 70 ? 48 : 24;
  }

  /**
   * Calcula y actualiza el resumen del crédito
   */
  calcularResumen() {
    const montoCredito = parseFloat(this.formularioCredito.monto_credito) || 0;
    const cuotas = parseInt(this.formularioCredito.cuotas) || 31;
    const seguro = this.calcularSeguro(montoCredito, cuotas);
    const tasaInteres = this.calcularTasaInteres(cuotas);
    const intereses = montoCredito * (tasaInteres / 100);
    const montoEntregar = montoCredito - seguro;
    const totalPagar = montoCredito + intereses;

    // Calcular fecha de finalización
    // Si está editando y tiene fecha_toma_credito, usar esa fecha; sino usar fecha actual
    let fechaBase: Date;
    if (this.modoEdicion && this.formularioCredito.fecha_toma_credito) {
      fechaBase = new Date(this.formularioCredito.fecha_toma_credito);
    } else {
      fechaBase = new Date();
    }
    
    const fechaFinalizacion = new Date(fechaBase);
    fechaFinalizacion.setDate(fechaBase.getDate() + cuotas);
    const fechaFormateada = fechaFinalizacion.toLocaleDateString('es-CO', {
      timeZone: 'America/Bogota',
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });

    this.resumen = {
      monto_credito: montoCredito,
      seguro: seguro,
      monto_entregar: montoEntregar,
      intereses: intereses,
      total_pagar: totalPagar,
      fecha_finalizacion: fechaFormateada
    };
  }

  /**
   * Valida el formulario antes de guardar
   * @returns True si el formulario es válido
   */
  validarFormulario(): boolean {
    if (!this.formularioCredito.id_cliente) {
      alert('Debe seleccionar un cliente');
      return false;
    }

    const montoCredito = parseFloat(this.formularioCredito.monto_credito);
    const cuotas = parseInt(this.formularioCredito.cuotas);
    const frecuenciaPago = this.formularioCredito.frecuencia_pago;

    if (montoCredito <= 0) {
      alert('El monto del crédito debe ser mayor a 0');
      return false;
    }

    if (cuotas <= 0) {
      alert('Las cuotas deben ser mayores a 0');
      return false;
    }

    // Validar que las cuotas de 40 y 70 días no tengan frecuencia mensual
    if ((cuotas === 40 || cuotas === 70) && frecuenciaPago === 'mensual') {
      alert('Las cuotas de 40 y 70 días no pueden tener una frecuencia de pago mensual');
      return false;
    }

    return true;
  }

  /**
   * Guarda el crédito (crear o editar)
   * @param event Evento del formulario
   */
  async guardarCredito(event: Event) {
    event.preventDefault();

    if (!this.validarFormulario()) {
      return;
    }

    if (!this.isBrowser || typeof localStorage === 'undefined') {
      alert('Error: No se puede acceder al almacenamiento local');
      return;
    }

    // Obtener usuario logueado
    const usuarioData = localStorage.getItem('usuario');
    if (!usuarioData) {
      alert('No hay sesión activa. Por favor, inicie sesión nuevamente.');
      return;
    }

    const usuario = JSON.parse(usuarioData);
    let idUsuario = usuario.id_usuario;
    const rolUsuario = usuario.rol || '';

    // Validar que el id_usuario sea un número válido
    if (!idUsuario || isNaN(parseInt(String(idUsuario)))) {
      console.warn('Advertencia: No se pudo obtener un ID de usuario válido. Se intentará guardar sin id_usuario.');
      idUsuario = null; // El backend manejará esto como NULL
    } else {
      idUsuario = parseInt(String(idUsuario));
    }
    
    console.log('ID Usuario obtenido del localStorage:', idUsuario);

    // Validar y convertir valores
    const montoCredito = parseFloat(String(this.formularioCredito.monto_credito || 0));
    const cuotas = parseInt(String(this.formularioCredito.cuotas || 31));
    const idCliente = parseInt(String(this.formularioCredito.id_cliente || 0));
    
    // Validaciones adicionales
    if (!idCliente || idCliente <= 0) {
      alert('Debe seleccionar un cliente válido');
      return;
    }
    
    if (isNaN(montoCredito) || montoCredito <= 0) {
      alert('El monto del crédito debe ser mayor a 0');
      return;
    }
    
    if (isNaN(cuotas) || cuotas <= 0) {
      alert('Las cuotas deben ser mayores a 0');
      return;
    }
    
    if (!this.formularioCredito.frecuencia_pago) {
      alert('Debe seleccionar una frecuencia de pago');
      return;
    }

    // Validar caja abierta OBLIGATORIA para administradores y cobradores al crear crédito
    if ((rolUsuario === 'admin' || rolUsuario === 'cobrador') && idUsuario) {
      this.cajaService.obtenerCajaAbierta(idUsuario).subscribe({
        next: (cajaAbierta: any) => {
          if (!cajaAbierta || !cajaAbierta.id_caja) {
            if (rolUsuario === 'admin') {
              const confirmar = confirm('Para crear un crédito, debe tener una caja abierta. ¿Desea abrir una caja ahora?');
              if (confirmar) {
                this.router.navigate(['/caja']);
              }
            } else {
              alert('Debe abrir una caja para crear créditos');
              this.router.navigate(['/caja']);
            }
            return;
          }
          // Si tiene caja, continuar con el proceso de guardar crédito
          this.continuarGuardadoCredito(usuario, idUsuario, idCliente, montoCredito, cuotas);
        },
        error: (error) => {
          console.error('Error al verificar caja:', error);
          if (rolUsuario === 'admin') {
            const confirmar = confirm('Error al verificar caja. Para crear un crédito, debe tener una caja abierta. ¿Desea abrir una caja ahora?');
            if (confirmar) {
              this.router.navigate(['/caja']);
            }
          } else {
            alert('Error al verificar caja. Debe abrir una caja para crear créditos');
            this.router.navigate(['/caja']);
          }
        }
      });
      return; // Salir aquí, el guardado continuará en continuarGuardadoCredito si tiene caja
    } else {
      // Si no es admin ni cobrador, o no tiene idUsuario, continuar normalmente
      this.continuarGuardadoCredito(usuario, idUsuario, idCliente, montoCredito, cuotas);
    }
  }

  /**
   * Continúa con el guardado del crédito después de validar caja
   */
  async continuarGuardadoCredito(usuario: any, idUsuario: number | null, idCliente: number, montoCredito: number, cuotas: number) {
    const seguro = this.calcularSeguro(montoCredito, cuotas);
    const tasaInteres = this.calcularTasaInteres(cuotas);

    // Obtener id_ruta del cliente seleccionado
    const clienteSeleccionado = this.listaClientes.find(c => c.id_cliente === idCliente);
    const idRuta = clienteSeleccionado?.id_ruta || null;

    const datosCredito: any = {
      id_cliente: idCliente,
      monto_credito: montoCredito,
      cuotas: cuotas,
      frecuencia_pago: this.formularioCredito.frecuencia_pago,
      incluir_seguro: this.formularioCredito.incluir_seguro || false
    };
    
    // Agregar id_usuario del usuario logueado (si es válido)
    if (idUsuario && !isNaN(idUsuario)) {
      datosCredito.id_usuario = idUsuario;
    }
    
    // Agregar id_ruta del cliente seleccionado (si existe)
    if (idRuta && !isNaN(parseInt(String(idRuta)))) {
      datosCredito.id_ruta = parseInt(String(idRuta));
    }
    
    // Si está editando y no tiene pagos, permitir cambiar la fecha de creación
    if (this.modoEdicion && !this.tienePagos) {
      if (this.formularioCredito.fecha_toma_credito) {
        datosCredito.fecha_toma_credito = this.formularioCredito.fecha_toma_credito;
      }
      if (this.formularioCredito.hora_toma_credito) {
        // Asegurar formato HH:MM:SS
        const hora = this.formularioCredito.hora_toma_credito;
        datosCredito.hora_toma_credito = hora.length === 5 ? hora + ':00' : hora;
      }
    }
    
    console.log('ID Usuario (del localStorage):', idUsuario);
    console.log('ID Ruta (del cliente):', idRuta);

    try {
      console.log('Datos a enviar:', datosCredito);
      
      if (this.modoEdicion) {
        // Editar crédito
        const respuesta: any = await this.creditosService.editar(this.creditoEditando.id_credito, datosCredito).toPromise();
        console.log('Respuesta del servidor (editar):', respuesta);
        
        if (respuesta && (respuesta.resultado === 'ok' || respuesta.resultado === 'success')) {
          alert(respuesta.mensaje || 'Crédito actualizado correctamente');
          this.cerrarModalCredito();
          this.cargarCreditos();
        } else {
          alert(respuesta?.mensaje || 'Error al actualizar el crédito');
        }
      } else {
        // Crear crédito
        try {
          const respuesta: any = await this.creditosService.insertar(datosCredito).toPromise();
          console.log('Respuesta del servidor (insertar):', respuesta);
          
          // Verificar respuesta (puede ser 'ok' o 'success')
          if (respuesta && (respuesta.resultado === 'ok' || respuesta.resultado === 'success')) {
            // Mostrar mensaje (puede incluir advertencia sobre plan de pagos)
            const mensaje = respuesta.advertencia || respuesta.mensaje || 'Crédito creado correctamente';
            alert(mensaje);
            this.cerrarModalCredito();
            this.cargarCreditos();
          } else {
            // Si hay un error pero el crédito se guardó (tiene id_credito), cerrar modal y recargar
            if (respuesta && respuesta.id_credito) {
              console.warn('Advertencia: El crédito se guardó pero hubo un problema:', respuesta.mensaje);
              alert('Crédito creado correctamente. ' + (respuesta.mensaje || ''));
              this.cerrarModalCredito();
              this.cargarCreditos();
            } else {
              const mensajeError = respuesta?.mensaje || 'Error al crear el crédito';
              alert(mensajeError);
              console.error('Error en la respuesta:', respuesta);
            }
          }
        } catch (insertError: any) {
          // Si el error menciona que el crédito se insertó, cerrar modal y recargar
          const errorMessage = insertError?.error?.mensaje || insertError?.message || '';
          if (errorMessage.includes('insertado') || errorMessage.includes('creado') || errorMessage.includes('id_credito')) {
            console.warn('Advertencia: El crédito se guardó pero hubo un problema:', errorMessage);
            alert('Crédito creado correctamente. Puede haber un problema menor con el plan de pagos.');
            this.cerrarModalCredito();
            this.cargarCreditos();
          } else {
            throw insertError; // Re-lanzar el error si no es relacionado con éxito
          }
        }
      }
    } catch (error: any) {
      console.error('Error al guardar crédito:', error);
      const mensajeError = error?.error?.mensaje || error?.message || 'Error al guardar el crédito';
      
      // Verificar si el crédito se guardó a pesar del error
      if (mensajeError.includes('Invalid parameter number') && mensajeError.includes('planpagos')) {
        // El error es en el plan de pagos, pero el crédito probablemente se guardó
        alert('Crédito creado correctamente. Hubo un problema menor al crear el plan de pagos, pero puede continuar.');
        this.cerrarModalCredito();
        this.cargarCreditos();
      } else {
        alert(mensajeError);
      }
    }
  }

  /**
   * Elimina un crédito (solo si no tiene pagos)
   */
  eliminarCredito() {
    if (!this.creditoEditando) {
      return;
    }

    if (!confirm('¿Estás seguro de que deseas eliminar este crédito?')) {
      return;
    }

    this.creditosService.eliminar(this.creditoEditando.id_credito).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'success') {
          alert('Crédito eliminado correctamente');
          this.cerrarModalCredito();
          this.cargarCreditos();
        } else {
          alert(resp.mensaje || 'Error al eliminar el crédito');
        }
      },
      error: (error) => {
        console.error('Error al eliminar crédito:', error);
        alert('Error al eliminar el crédito');
      }
    });
  }

  /**
   * Muestra el plan de pagos de un crédito
   * @param idCredito ID del crédito
   */
  verPlanPagos(idCredito: number) {
    this.cargandoPlanPagos = true;
    this.listaPlanPagos = [];
    this.planPagosInfo = null;
    
    // Primero obtener información del crédito para verificar si es refinanciado por sistema
    this.creditosService.consultarPorId(idCredito).subscribe({
      next: (creditoResp: any) => {
        const credito = Array.isArray(creditoResp) ? creditoResp[0] : creditoResp;
        
        // Si es refinanciado por sistema, no tiene plan de pagos
        if (credito && credito.tipo_credito === 'refinanciado_por_sistema') {
          // Obtener estadísticas de cuotas del crédito anterior refinanciado
          this.planPagosService.consultarPorIdCredito(idCredito).subscribe({
            next: (cuotasResp: any) => {
              const cuotasPagadas = cuotasResp ? cuotasResp.filter((c: any) => c.estado === 'pagada').length : 0;
              const cuotasPendientes = cuotasResp ? cuotasResp.filter((c: any) => c.estado === 'pendiente').length : 0;
              const cuotasVencidas = cuotasResp ? cuotasResp.filter((c: any) => c.estado === 'vencida').length : 0;
              
              // Crear objeto de información sin plan de pagos
              this.planPagosInfo = {
                id_credito: credito.id_credito,
                nombres_cliente: credito.nombres_cliente || '',
                apellidos_cliente: credito.apellidos_cliente || '',
                monto_credito: credito.monto_credito,
                cuotas: credito.cuotas,
                tasa_interes: credito.tasa_interes,
                frecuencia_pago: credito.frecuencia_pago,
                saldo_actual: credito.saldo_actual,
                fecha_toma_credito: credito.fecha_toma_credito,
                fecha_finaliza_credito: credito.fecha_finaliza_credito,
                tipo_credito: credito.tipo_credito,
                cuotas_pagadas: cuotasPagadas,
                cuotas_pendientes: cuotasPendientes,
                cuotas_vencidas: cuotasVencidas
              };
              this.listaPlanPagos = [];
              this.cargandoPlanPagos = false;
              this.mostrarModalPlanPagos();
            },
            error: () => {
              // Si no hay plan de pagos, usar valores por defecto
              this.planPagosInfo = {
                id_credito: credito.id_credito,
                nombres_cliente: credito.nombres_cliente || '',
                apellidos_cliente: credito.apellidos_cliente || '',
                monto_credito: credito.monto_credito,
                cuotas: credito.cuotas,
                tasa_interes: credito.tasa_interes,
                frecuencia_pago: credito.frecuencia_pago,
                saldo_actual: credito.saldo_actual,
                fecha_toma_credito: credito.fecha_toma_credito,
                fecha_finaliza_credito: credito.fecha_finaliza_credito,
                tipo_credito: credito.tipo_credito,
                cuotas_pagadas: 0,
                cuotas_pendientes: 0,
                cuotas_vencidas: 0
              };
              this.listaPlanPagos = [];
              this.cargandoPlanPagos = false;
              this.mostrarModalPlanPagos();
            }
          });
        } else {
          // Cargar plan de pagos normal
          this.planPagosService.consultarPorIdCredito(idCredito).subscribe({
            next: (resp: any) => {
              if (Array.isArray(resp) && resp.length > 0) {
                this.listaPlanPagos = resp;
                // Tomar la información del crédito del primer elemento (todos tienen la misma info)
                this.planPagosInfo = resp[0];
              } else {
                this.listaPlanPagos = [];
                this.planPagosInfo = null;
              }
              this.cargandoPlanPagos = false;
              this.mostrarModalPlanPagos();
            },
            error: (error) => {
              console.error('Error al cargar plan de pagos:', error);
              this.cargandoPlanPagos = false;
              alert('Error al cargar el plan de pagos');
            }
          });
        }
      },
      error: (error) => {
        console.error('Error al consultar crédito:', error);
        // Intentar cargar plan de pagos de todas formas
        this.planPagosService.consultarPorIdCredito(idCredito).subscribe({
          next: (resp: any) => {
            if (Array.isArray(resp) && resp.length > 0) {
              this.listaPlanPagos = resp;
              this.planPagosInfo = resp[0];
            } else {
              this.listaPlanPagos = [];
              this.planPagosInfo = null;
            }
            this.cargandoPlanPagos = false;
            this.mostrarModalPlanPagos();
          },
          error: (error2) => {
            console.error('Error al cargar plan de pagos:', error2);
            this.cargandoPlanPagos = false;
            alert('Error al cargar el plan de pagos');
          }
        });
      }
    });
  }

  /**
   * Muestra el modal de plan de pagos
   */
  mostrarModalPlanPagos() {
    const modal = document.getElementById('modalPlanPagos');
    if (modal) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
  }

  /**
   * Cierra el modal de plan de pagos
   */
  cerrarModalPlanPagos() {
    const modal = document.getElementById('modalPlanPagos');
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
    this.listaPlanPagos = [];
    this.planPagosInfo = null;
  }

  /**
   * Muestra el modal
   */
  mostrarModal() {
    const modal = document.getElementById('modalCredito');
    if (modal) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
  }

  /**
   * Cierra el modal y resetea el formulario
   */
  cerrarModalCredito() {
    const modal = document.getElementById('modalCredito');
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
    // Siempre resetear el formulario al cerrar para que inicie limpio
    this.resetearFormulario();
    this.modoEdicion = false;
    this.creditoEditando = null;
    this.tienePagos = false;
  }

  /**
   * Resetea el formulario a sus valores por defecto
   */
  resetearFormulario() {
    // Siempre iniciar sin cliente seleccionado
    this.formularioCredito = {
      id_cliente: '',
      monto_credito: 0.00,
      cuotas: 31,
      frecuencia_pago: 'diario',
      incluir_seguro: true,
      fecha_toma_credito: '',
      hora_toma_credito: ''
    };
    this.resumen = {
      monto_credito: 0.00,
      seguro: 0,
      monto_entregar: 0.00,
      intereses: 0,
      total_pagar: 0.00,
      fecha_finalizacion: '-'
    };
    this.terminoBusquedaCliente = '';
    this.calcularResumen(); // Recalcular con los valores por defecto
    this.listaClientesFiltrados = this.listaClientes;
    this.clienteTieneCreditoPendiente = false;
    this.creditosPendientesCliente = [];
  }

  /**
   * Formatea una fecha en español (Zona horaria: Colombia GMT-5)
   * @param fecha Fecha en formato YYYY-MM-DD
   * @returns Fecha formateada
   */
  formatearFecha(fecha: string): string {
    if (!fecha) return '-';
    const fechaObj = new Date(fecha);
    return fechaObj.toLocaleDateString('es-CO', {
      timeZone: 'America/Bogota',
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
  }

  /**
   * Formatea una fecha en formato corto (dd/mm/yyyy)
   * @param fecha Fecha en formato YYYY-MM-DD
   * @returns Fecha formateada
   */
  formatearFechaCorta(fecha: string): string {
    if (!fecha) return '-';
    const fechaObj = new Date(fecha);
    return fechaObj.toLocaleDateString('es-CO', {
      timeZone: 'America/Bogota',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  }

  /**
   * Formatea un número a dos decimales
   * @param valor Valor numérico
   * @returns String formateado con dos decimales
   */
  formatearMoneda(valor: any): string {
    const num = parseFloat(valor) || 0;
    return num.toFixed(2);
  }

  /**
   * Cancela un crédito
   * @param idCredito ID del crédito a cancelar
   */
  cancelarCredito(idCredito: number) {
    // Solo administradores pueden cancelar créditos
    if (this.rolUsuario !== 'admin') {
      alert('No tiene permisos para cancelar créditos. Solo los administradores pueden realizar esta acción.');
      return;
    }
    
    if (!confirm('¿Está seguro de que desea cancelar este crédito? Esta acción no se puede deshacer.')) {
      return;
    }

    // Obtener id_usuario del localStorage
    let idUsuario: number | null = null;
    if (this.isBrowser && typeof localStorage !== 'undefined') {
      const usuarioStr = localStorage.getItem('usuario');
      if (usuarioStr) {
        try {
          const usuario = JSON.parse(usuarioStr);
          idUsuario = usuario.id_usuario || null;
        } catch (e) {
          console.error('Error al parsear usuario:', e);
        }
      }
    }

    if (!idUsuario) {
      alert('No se pudo obtener la información del usuario. Por favor, inicie sesión nuevamente.');
      return;
    }

    this.creditosService.cancelar(idCredito, idUsuario).subscribe({
      next: (resp: any) => {
        if (resp && resp.resultado === 'ok') {
          alert('Crédito cancelado correctamente');
          this.cargarCreditos(); // Recargar la lista
        } else {
          alert(resp?.mensaje || 'Error al cancelar el crédito');
        }
      },
      error: (error) => {
        console.error('Error al cancelar crédito:', error);
        const mensajeError = error?.error?.mensaje || error?.message || 'Error de conexión con el servidor';
        alert('Error: ' + mensajeError);
      }
    });
  }

  // Métodos para manejar eventos de hover en las filas de la tabla
  onRowMouseEnter(event: MouseEvent, credito: any): void {
    const target = event.currentTarget as HTMLElement;
    if (credito.estado_credito !== 'cancelado') {
      target.style.backgroundColor = 'rgba(174, 221, 43, 0.05)';
    }
  }

  onRowMouseLeave(event: MouseEvent, credito: any): void {
    const target = event.currentTarget as HTMLElement;
    if (credito.estado_credito === 'cancelado') {
      target.style.backgroundColor = 'rgba(220, 53, 69, 0.05)';
    } else {
      target.style.backgroundColor = '#ffffff';
    }
  }
}

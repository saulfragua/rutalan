import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID, AfterViewInit, ViewChild, ElementRef } from '@angular/core';
import { Router, NavigationEnd, ActivatedRoute } from '@angular/router';
import { filter } from 'rxjs/operators';
import { Subscription } from 'rxjs';
import { PagosService } from '../../servicios/pagos';
import { Rutas } from '../../servicios/rutas';
import { UsuarioRutaService } from '../../servicios/usuarioruta';
import { CajaService } from '../../servicios/caja';
import { CreditosService } from '../../servicios/creditos';
import { isPlatformBrowser } from '@angular/common';

declare var Sortable: any;

@Component({
  selector: 'app-cobros',
  standalone: false,
  templateUrl: './cobros.html',
  styleUrl: './cobros.css',
})
export class Cobros implements OnInit, OnDestroy, AfterViewInit {

  @ViewChild('tbodyClientes', { static: false }) tbodyClientes!: ElementRef;

  private routerSubscription?: Subscription;
  isBrowser: boolean = false;

  // Rutas
  rutas: any[] = [];
  rutasUsuario: any[] = [];
  rutaSeleccionada: number | null = null;
  nombreRutaSeleccionada: string = '';

  // Clientes
  listaClientes: any[] = [];
  cargando: boolean = false;

  // Usuario
  usuarioActual: any = null;
  rolUsuario: string = '';

  // Caja
  tieneCajaAbierta: boolean = false;
  idCaja: number | null = null;

  constructor(
    private router: Router,
    private route: ActivatedRoute,
    private pagosService: PagosService,
    private rutasService: Rutas,
    private usuarioRutaService: UsuarioRutaService,
    private cajaService: CajaService,
    private creditosService: CreditosService,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    this.routerSubscription = this.router.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe((event: any) => {
        if (event.url === '/cobros' || event.urlAfterRedirects === '/cobros') {
          this.cargarDatos();
        }
      });
  }

  ngOnInit() {
    this.cargarDatos();
  }

  ngOnDestroy() {
    if (this.routerSubscription) {
      this.routerSubscription.unsubscribe();
    }
  }

  /**
   * Carga los datos del módulo de cobros
   */
  cargarDatos() {
    if (!this.isBrowser || typeof localStorage === 'undefined') {
      return;
    }

    const usuarioData = localStorage.getItem('usuario');
    if (!usuarioData) {
      alert('No hay sesión activa. Por favor, inicie sesión nuevamente.');
      this.router.navigate(['/login']);
      return;
    }

    try {
      this.usuarioActual = JSON.parse(usuarioData);
      this.rolUsuario = this.usuarioActual.rol || '';

      // Cargar rutas según el rol PRIMERO (antes de refinanciación)
      if (this.rolUsuario === 'admin') {
        this.cargarTodasLasRutas();
      } else if (this.rolUsuario === 'cobrador') {
        this.cargarRutasUsuario();
      }

      // Ejecutar refinanciación automática de créditos vencidos (en paralelo, no bloquea)
      this.ejecutarRefinanciacionAutomatica();

      // Verificar caja abierta si es cobrador
      if (this.rolUsuario === 'cobrador') {
        this.verificarCajaAbierta();
      } else {
        this.tieneCajaAbierta = true; // Admin no requiere caja
      }
    } catch (error) {
      alert('Error al obtener la información del usuario.');
    }
  }

  /**
   * Ejecuta la refinanciación automática de créditos vencidos
   */
  ejecutarRefinanciacionAutomatica() {
    this.creditosService.refinanciarAutomatico().subscribe({
      next: (resp: any) => {
        // Refinanciación automática ejecutada
      },
      error: (error) => {
        // Error silencioso en refinanciación automática
      }
    });
  }

  /**
   * Carga todas las rutas (solo admin)
   */
  cargarTodasLasRutas() {
    this.rutasService.consultar().subscribe({
      next: (resp: any) => {
        this.rutas = resp || [];
        // Si hay una ruta seleccionada en la URL, cargarla
        const urlParams = new URLSearchParams(window.location.search);
        const rutaParam = urlParams.get('ruta');
        if (rutaParam) {
          this.seleccionarRuta(parseInt(rutaParam));
        }
      },
      error: (error) => {
        this.rutas = [];
        alert('Error al cargar las rutas. Por favor, recarga la página.');
      }
    });
  }

  /**
   * Carga las rutas asignadas al usuario (cobrador)
   */
  cargarRutasUsuario() {
    if (!this.usuarioActual || !this.usuarioActual.id_usuario) {
      return;
    }

    this.usuarioRutaService.rutasPorUsuario(this.usuarioActual.id_usuario).subscribe({
      next: (resp: any) => {
        this.rutasUsuario = resp || [];
        // Si el cobrador tiene solo una ruta, seleccionarla automáticamente
        if (this.rutasUsuario.length === 1) {
          this.seleccionarRuta(this.rutasUsuario[0].id_ruta);
        } else {
          // Si hay múltiples rutas, verificar si hay una en la URL
          const urlParams = new URLSearchParams(window.location.search);
          const rutaParam = urlParams.get('ruta');
          if (rutaParam) {
            const rutaId = parseInt(rutaParam);
            // Validar que la ruta pertenezca al usuario
            if (this.rutasUsuario.some(r => r.id_ruta === rutaId)) {
              this.seleccionarRuta(rutaId);
            } else {
              // Si intenta acceder a una ruta que no le pertenece, redirigir a la primera
              if (this.rutasUsuario.length > 0) {
                this.router.navigate(['/cobros'], { queryParams: { ruta: this.rutasUsuario[0].id_ruta } });
                this.seleccionarRuta(this.rutasUsuario[0].id_ruta);
              }
            }
          }
        }
      },
      error: (error) => {
        this.rutasUsuario = [];
        alert('Error al cargar las rutas asignadas. Por favor, verifica que tengas rutas asignadas.');
      }
    });
  }

  /**
   * Verifica si el cobrador tiene caja abierta
   */
  verificarCajaAbierta() {
    if (!this.usuarioActual || !this.usuarioActual.id_usuario) {
      return;
    }

    this.cajaService.obtenerCajaAbierta(this.usuarioActual.id_usuario).subscribe({
      next: (caja: any) => {
        this.tieneCajaAbierta = !!caja;
        if (caja) {
          this.idCaja = caja.id_caja;
        }
      },
      error: (error) => {
        this.tieneCajaAbierta = false;
      }
    });
  }

  /**
   * Selecciona una ruta y carga sus clientes
   */
  seleccionarRuta(idRuta: number) {
    this.rutaSeleccionada = idRuta;
    
    // Obtener nombre de la ruta
    const todasLasRutas = this.rolUsuario === 'admin' ? this.rutas : this.rutasUsuario;
    const ruta = todasLasRutas.find(r => r.id_ruta === idRuta);
    this.nombreRutaSeleccionada = ruta?.nombre_ruta || '';

    // Actualizar URL
    this.router.navigate(['/cobros'], { queryParams: { ruta: idRuta } });

    // Cargar clientes de la ruta
    this.cargarClientesPorRuta(idRuta);
  }

  /**
   * Carga los clientes de una ruta con saldo pendiente
   */
  cargarClientesPorRuta(idRuta: number) {
    this.cargando = true;
    this.pagosService.consultarClientesPorRuta(idRuta).subscribe({
      next: (resp: any) => {
        // Ordenar los clientes por orden_cobranza y luego por id_credito
        this.listaClientes = (resp || []).sort((a: any, b: any) => {
          const ordenA = parseInt(a.orden_cobranza) || 0;
          const ordenB = parseInt(b.orden_cobranza) || 0;
          if (ordenA !== ordenB) {
            return ordenA - ordenB;
          }
          // Si tienen el mismo orden, ordenar por id_credito
          return (a.id_credito || 0) - (b.id_credito || 0);
        });
        this.cargando = false;
        // Inicializar Sortable después de cargar los clientes
        setTimeout(() => {
          this.inicializarSortable();
        }, 100);
      },
      error: (error) => {
        this.cargando = false;
        alert('Error al cargar los clientes de la ruta');
        this.listaClientes = [];
      }
    });
  }

  /**
   * Inicializa Sortable para el ordenamiento drag and drop
   */
  inicializarSortable() {
    if (!this.isBrowser || typeof Sortable === 'undefined') {
      // Cargar SortableJS desde CDN si no está disponible
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js';
      script.onload = () => {
        this.crearSortable();
      };
      document.head.appendChild(script);
    } else {
      this.crearSortable();
    }
  }

  /**
   * Crea la instancia de Sortable
   */
  crearSortable() {
    if (!this.tbodyClientes || !this.tbodyClientes.nativeElement) {
      return;
    }

    if (this.isBrowser && typeof Sortable !== 'undefined') {
      new Sortable(this.tbodyClientes.nativeElement, {
        animation: 150,
        handle: '.fa-arrows-alt-v',
        onEnd: () => {
          // Obtener el nuevo orden de las filas después del drag and drop
          const filas = Array.from(this.tbodyClientes.nativeElement.querySelectorAll('tr[data-id]'));
          
          // Actualizar orden_cobranza según la nueva posición (empezando desde 1)
          filas.forEach((tr: any, index: number) => {
            const idCredito = parseInt(tr.getAttribute('data-id'));
            const cliente = this.listaClientes.find(c => c.id_credito === idCredito);
            if (cliente) {
              const nuevoOrden = index + 1;
              cliente.orden_cobranza = nuevoOrden;
              // Actualizar también el valor del input
              const inputOrden = tr.querySelector('input[type="number"]') as HTMLInputElement;
              if (inputOrden) {
                inputOrden.value = nuevoOrden.toString();
              }
            }
          });
          
          // Reordenar la lista de clientes basándose en el nuevo orden
          const clientesOrdenados: any[] = [];
          filas.forEach((tr: any) => {
            const idCredito = parseInt(tr.getAttribute('data-id'));
            const cliente = this.listaClientes.find(c => c.id_credito === idCredito);
            if (cliente) {
              clientesOrdenados.push(cliente);
            }
          });
          
          // Ordenar por orden_cobranza y luego por id_credito para asegurar consistencia
          clientesOrdenados.sort((a, b) => {
            const ordenA = parseInt(a.orden_cobranza) || 0;
            const ordenB = parseInt(b.orden_cobranza) || 0;
            if (ordenA !== ordenB) {
              return ordenA - ordenB;
            }
            return (a.id_credito || 0) - (b.id_credito || 0);
          });
          
          this.listaClientes = clientesOrdenados;
        }
      });
    }
  }

  ngAfterViewInit() {
    // Inicializar Sortable después de que la vista esté lista
    if (this.listaClientes.length > 0) {
      setTimeout(() => {
        this.inicializarSortable();
      }, 500);
    }
  }

  /**
   * Navega a la gestión de pago de un cliente específico
   */
  irAGestionPago(indice: number = 0) {
    if (!this.rutaSeleccionada) {
      alert('Debe seleccionar una ruta primero');
      return;
    }
    this.router.navigate(['/gestion-pago'], { 
      queryParams: { 
        ruta: this.rutaSeleccionada,
        indice: indice 
      } 
    });
  }

  /**
   * Calcula los días sin pagar
   */
  calcularDiasSinPagar(ultimoPago: string | null): number {
    if (!ultimoPago) {
      return 0;
    }
    const fechaUltimoPago = new Date(ultimoPago);
    const hoy = new Date();
    const diffTime = hoy.getTime() - fechaUltimoPago.getTime();
    return Math.floor(diffTime / (1000 * 60 * 60 * 24));
  }

  /**
   * Obtiene la clase CSS para la fila según el tipo de crédito y estado
   */
  obtenerClaseFila(cliente: any): string {
    // Refinanciado por sistema: Rojo
    if (cliente.tipo_credito === 'refinanciado_por_sistema') {
      return 'bg-red-600 text-white border-t border-red-700 hover:opacity-90 cursor-move';
    }
    
    // Refinanciado manual: Amarillo claro
    if (cliente.tipo_credito === 'refinanciado') {
      return 'bg-yellow-100 border-t border-yellow-300 hover:opacity-90 cursor-move text-gray-800';
    }
    
    // Común: Verde claro (según especificación)
    if (cliente.tipo_credito === 'comun' || !cliente.tipo_credito) {
      return 'bg-green-100 border-t border-green-300 hover:opacity-90 cursor-move text-gray-800';
    }
    
    // Fallback: usar lógica de días sin pagar para otros casos
    return this.obtenerColorFondo(this.calcularDiasSinPagar(cliente.ultimo_pago), cliente.cuotas_vencidas);
  }

  /**
   * Obtiene el color de fondo según los días sin pagar (para casos especiales)
   */
  obtenerColorFondo(dias: number, cuotasVencidas: number): string {
    if (cuotasVencidas > 0) {
      if (dias >= 1 && dias <= 30) {
        return 'bg-green-500 text-white';
      } else if (dias >= 31 && dias <= 40) {
        return 'bg-yellow-500 text-black';
      } else if (dias >= 41 && dias <= 70) {
        return 'bg-orange-500 text-white';
      } else if (dias >= 71) {
        return 'bg-red-600 text-white';
      }
    }
    return 'bg-white text-black';
  }

  /**
   * Obtiene la leyenda del estado
   */
  obtenerLeyenda(dias: number, cuotasVencidas: number): string {
    if (cuotasVencidas === 0) {
      return 'AL DÍA';
    }
    if (dias >= 1 && dias <= 30) {
      return `PENDIENTE (${dias} días)`;
    } else if (dias >= 31 && dias <= 40) {
      return `VENCIDO (${dias} días)`;
    } else if (dias >= 41 && dias <= 70) {
      return `CLAVO (${dias} días)`;
    } else if (dias >= 71) {
      return `RECLAVO (${dias} días)`;
    }
    return 'AL DÍA';
  }

  /**
   * TrackBy function para mejorar el rendimiento de *ngFor
   */
  trackByCreditoId(index: number, cliente: any): number {
    return cliente.id_credito || index;
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
   * Guarda el orden de cobranza después de arrastrar y soltar
   * Ahora trabaja con créditos en lugar de clientes
   */
  guardarOrden() {
    if (!this.rutaSeleccionada) {
      alert('Debe seleccionar una ruta');
      return;
    }

    // Obtener los IDs de créditos en el orden actual
    const orden = this.listaClientes.map(c => c.id_credito);
    this.pagosService.actualizarOrdenCobranza(this.rutaSeleccionada, orden).subscribe({
      next: (resp: any) => {
        if (resp && resp.resultado === 'ok') {
          alert(resp.mensaje || 'Orden guardado correctamente');
          // Recargar clientes para reflejar el nuevo orden
          this.cargarClientesPorRuta(this.rutaSeleccionada!);
        } else {
          alert(resp?.mensaje || 'Error al guardar el orden');
        }
      },
      error: (error) => {
        alert('Error al guardar el orden');
      }
    });
  }

  /**
   * Verifica si el último pago fue hoy
   */
  ultimoPagoHoy(ultimoPago: string | null): boolean {
    if (!ultimoPago) return false;
    const hoy = new Date().toISOString().split('T')[0];
    return ultimoPago === hoy;
  }

  /**
   * Actualiza el orden manualmente cuando se edita el campo
   */
  actualizarOrdenManual(cliente: any) {
    // Validar que el orden sea un número válido
    const nuevoOrden = parseInt(cliente.orden_cobranza) || 1;
    if (nuevoOrden < 1) {
      cliente.orden_cobranza = 1;
    } else {
      cliente.orden_cobranza = nuevoOrden;
    }
    
    // Reordenar la lista localmente por orden_cobranza y luego por id_credito
    this.listaClientes.sort((a, b) => {
      const ordenA = parseInt(a.orden_cobranza) || 0;
      const ordenB = parseInt(b.orden_cobranza) || 0;
      if (ordenA !== ordenB) {
        return ordenA - ordenB;
      }
      // Si tienen el mismo orden, ordenar por id_credito
      return (a.id_credito || 0) - (b.id_credito || 0);
    });
    
    // Guardar automáticamente después de un pequeño delay
    setTimeout(() => {
      this.guardarOrden();
    }, 500);
  }
}

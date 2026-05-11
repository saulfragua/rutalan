import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { Subscription } from 'rxjs';
import { ReportesService } from '../../servicios/reportes';
import { Usuarios } from '../../servicios/usuarios';
import { Rutas } from '../../servicios/rutas';
import { isPlatformBrowser } from '@angular/common';

@Component({
  selector: 'app-reportes',
  standalone: false,
  templateUrl: './reportes.html',
  styleUrl: './reportes.css',
})
export class Reportes implements OnInit, OnDestroy {

  // Filtros
  fechaInicio: string = '';
  fechaFin: string = '';
  fechaCajaAnterior: string = '';
  idUsuario: number | null = null;
  idRuta: number | null = null;
  
  // Listas para filtros
  listaUsuarios: any[] = [];
  listaRutas: any[] = [];
  
  // Totales
  totales: any = {
    creditos_cobrados: 0,
    clientes_cobrados: 0,
    prestamos_realizados: 0,
    creditos_nuevos: 0,
    clientes_nuevos: 0,
    seguros_cobrados: 0,
    gastos_ruta: 0,
    adelantos_ingresos: 0,
    adelantos_egresos: 0,
    descuentos_creditos: 0,
    creditos_cancelados: 0,
    cierre_actual: 0,
    caja_anterior: 0,
    cierre_caja: 0,
    usuario_caja_anterior: ''
  };
  
  // Registros detallados
  registrosAdelantosIngresos: any[] = [];
  registrosAdelantosEgresos: any[] = [];
  
  // Estados
  cargando: boolean = false;
  mostrarResultados: boolean = false;
  
  // Modal de cierre de caja
  modalCierreAbierto: boolean = false;
  fechaCierre: string = '';
  montoCierre: number = 0;
  idUsuarioCierre: number | null = null;
  observacionesCierre: string = '';
  guardandoCierre: boolean = false;
  
  private routerSubscription?: Subscription;
  isBrowser: boolean = false;

  constructor(
    private reportesService: ReportesService,
    private usuariosService: Usuarios,
    private rutasService: Rutas,
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    
    // Inicializar fechas
    if (this.isBrowser) {
      const hoy = new Date();
      const ayer = new Date();
      ayer.setDate(ayer.getDate() - 1);
      
      this.fechaInicio = hoy.toISOString().split('T')[0];
      this.fechaFin = hoy.toISOString().split('T')[0];
      this.fechaCajaAnterior = ayer.toISOString().split('T')[0];
      this.fechaCierre = hoy.toISOString().split('T')[0];
    }
    
    // Suscribirse a los eventos de navegación
    this.routerSubscription = this.router.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe((event: any) => {
        if (event.url === '/reportes' || event.urlAfterRedirects === '/reportes') {
          this.cargarListas();
        }
      });
  }

  ngOnInit() {
    this.cargarListas();
  }

  ngOnDestroy() {
    if (this.routerSubscription) {
      this.routerSubscription.unsubscribe();
    }
  }

  /**
   * Carga las listas de usuarios y rutas para los filtros
   */
  cargarListas() {
    // Cargar usuarios
    this.usuariosService.consultar().subscribe({
      next: (resp: any) => {
        if (Array.isArray(resp)) {
          this.listaUsuarios = resp;
        }
      },
      error: (error) => {
        console.error('Error al cargar usuarios:', error);
      }
    });
    
    // Cargar rutas
    this.rutasService.consultar().subscribe({
      next: (resp: any) => {
        if (Array.isArray(resp)) {
          this.listaRutas = resp.filter((r: any) => r.activo === 1 || r.activo === true);
        }
      },
      error: (error) => {
        console.error('Error al cargar rutas:', error);
      }
    });
  }

  /**
   * Genera el reporte con los filtros seleccionados
   */
  generarReporte() {
    if (!this.fechaInicio || !this.fechaFin) {
      alert('Las fechas son obligatorias');
      return;
    }
    
    if (new Date(this.fechaInicio) > new Date(this.fechaFin)) {
      alert('La fecha de inicio no puede ser mayor que la fecha de fin');
      return;
    }
    
    this.cargando = true;
    this.mostrarResultados = false;
    
    // Obtener caja anterior primero
    this.reportesService.obtenerCajaAnterior(this.fechaCajaAnterior, this.idUsuario || undefined).subscribe({
      next: (cajaAnterior: any) => {
        this.totales.caja_anterior = cajaAnterior.caja_anterior || 0;
        this.totales.usuario_caja_anterior = cajaAnterior.usuario_caja_anterior || 'No registrado';
        
        // Obtener totales
        this.reportesService.obtenerTotales(
          this.fechaInicio,
          this.fechaFin,
          this.idUsuario || undefined,
          this.idRuta || undefined
        ).subscribe({
          next: (totales: any) => {
            this.totales = { ...this.totales, ...totales };
            
            // Calcular cierre actual
            this.totales.cierre_actual = (this.totales.creditos_cobrados + (this.totales.seguros_cobrados * 0.7)) 
                                        - this.totales.gastos_ruta 
                                        + this.totales.adelantos_ingresos 
                                        - this.totales.adelantos_egresos 
                                        - this.totales.prestamos_realizados
                                        - this.totales.descuentos_creditos;
            
            // Calcular cierre total
            this.totales.cierre_caja = this.totales.caja_anterior + this.totales.cierre_actual;
            this.montoCierre = this.totales.cierre_caja;
            
            // Cargar adelantos
            this.cargarAdelantos();
            
            this.cargando = false;
            this.mostrarResultados = true;
          },
          error: (error) => {
            console.error('Error al obtener totales:', error);
            alert('Error al generar el reporte');
            this.cargando = false;
          }
        });
      },
      error: (error) => {
        console.error('Error al obtener caja anterior:', error);
        // Continuar aunque falle la caja anterior
        this.totales.caja_anterior = 0;
        this.totales.usuario_caja_anterior = 'No registrado';
        
        // Obtener totales
        this.reportesService.obtenerTotales(
          this.fechaInicio,
          this.fechaFin,
          this.idUsuario || undefined,
          this.idRuta || undefined
        ).subscribe({
          next: (totales: any) => {
            this.totales = { ...this.totales, ...totales };
            this.cargarAdelantos();
            this.cargando = false;
            this.mostrarResultados = true;
          },
          error: (error) => {
            console.error('Error al obtener totales:', error);
            alert('Error al generar el reporte');
            this.cargando = false;
          }
        });
      }
    });
  }

  /**
   * Carga los registros de adelantos
   */
  cargarAdelantos() {
    // Adelantos ingresos
    this.reportesService.obtenerAdelantos(
      this.fechaInicio,
      this.fechaFin,
      'ingreso',
      this.idUsuario || undefined,
      this.idRuta || undefined,
      5
    ).subscribe({
      next: (adelantos: any) => {
        this.registrosAdelantosIngresos = adelantos || [];
      },
      error: (error) => {
        console.error('Error al obtener adelantos ingresos:', error);
        this.registrosAdelantosIngresos = [];
      }
    });
    
    // Adelantos egresos
    this.reportesService.obtenerAdelantos(
      this.fechaInicio,
      this.fechaFin,
      'egreso',
      this.idUsuario || undefined,
      this.idRuta || undefined,
      5
    ).subscribe({
      next: (adelantos: any) => {
        this.registrosAdelantosEgresos = adelantos || [];
      },
      error: (error) => {
        console.error('Error al obtener adelantos egresos:', error);
        this.registrosAdelantosEgresos = [];
      }
    });
  }

  /**
   * Abre el modal para guardar el cierre de caja
   */
  abrirModalCierre() {
    this.fechaCierre = this.fechaInicio;
    this.montoCierre = this.totales.cierre_caja;
    this.idUsuarioCierre = this.idUsuario;
    this.observacionesCierre = '';
    this.modalCierreAbierto = true;
  }

  /**
   * Cierra el modal de cierre de caja
   */
  cerrarModalCierre() {
    this.modalCierreAbierto = false;
  }

  /**
   * Guarda el cierre de caja
   */
  guardarCierreCaja() {
    if (!this.fechaCierre || !this.montoCierre || !this.idUsuarioCierre) {
      alert('Complete todos los campos obligatorios');
      return;
    }
    
    this.guardandoCierre = true;
    
    this.reportesService.guardarCierreCaja(
      this.fechaCierre,
      this.montoCierre,
      this.idUsuarioCierre,
      this.observacionesCierre
    ).subscribe({
      next: (resp: any) => {
        alert('Cierre de caja guardado correctamente');
        this.cerrarModalCierre();
        this.guardandoCierre = false;
      },
      error: (error) => {
        console.error('Error al guardar cierre de caja:', error);
        alert('Error al guardar el cierre de caja');
        this.guardandoCierre = false;
      }
    });
  }

  /**
   * Formatea un número como moneda
   */
  formatearMoneda(valor: number): string {
    if (!valor && valor !== 0) return '$ 0';
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(valor).replace('COP', '$');
  }

  /**
   * Formatea un número como moneda (alias para compatibilidad)
   */
  formatearMonto(valor: number): string {
    return this.formatearMoneda(valor);
  }

  /**
   * Formatea una fecha
   */
  formatearFecha(fecha: string): string {
    if (!fecha) return '';
    const date = new Date(fecha);
    return date.toLocaleDateString('es-CO', { timeZone: 'America/Bogota' });
  }
}

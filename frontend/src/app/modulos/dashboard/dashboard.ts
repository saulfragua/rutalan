import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { Subscription } from 'rxjs';
import { DashboardService } from '../../servicios/dashboard';
import { isPlatformBrowser } from '@angular/common';

declare var Chart: any;

@Component({
  selector: 'app-dashboard',
  standalone: false,
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.css',
})
export class Dashboard implements OnInit, OnDestroy {
  private routerSubscription?: Subscription;
  private isBrowser: boolean;

  // Datos para gráficos
  creditosPorRuta: any[] = [];
  totalGeneralCreditos: number = 0;
  estadisticasClientes: any = {};
  clientesPorRuta: any[] = [];
  totalCobradoEnDia: number = 0;
  gastosPorRuta: any[] = [];
  
  // Nuevas estadísticas
  creditosPorTipo: any[] = [];
  estadisticasSeguros: any = {};
  estadisticasCajas: any = {};
  estadisticasCuotas: any = {};
  evolucionPagos: any[] = [];
  estadisticasRefinanciaciones: any = {};
  topRutas: any[] = [];
  estadisticasMorosidad: any = {};

  // Filtros de fecha
  fechaInicio: string = '';
  fechaFin: string = '';
  periodoSeleccionado: string = 'hoy'; // 'hoy', 'semana', 'mes', 'año', 'personalizado'
  mostrarRangoPersonalizado: boolean = false;

  // Referencias a los gráficos
  private chartCreditosPorRuta: any = null;
  private chartClientesComparativo: any = null;
  private chartGastosPorRuta: any = null;
  private chartCreditosPorTipo: any = null;
  private chartEvolucionPagos: any = null;
  private chartCuotas: any = null;
  private chartTopRutas: any = null;

  cargando: boolean = false;

  constructor(
    private router: Router,
    private dashboardService: DashboardService,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    
    // Suscribirse a los eventos de navegación para recargar datos cada vez que se accede al módulo
    this.routerSubscription = this.router.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe((event: any) => {
        if (event.url === '/dashboard' || event.urlAfterRedirects === '/dashboard' || event.url === '/' || event.urlAfterRedirects === '/') {
          this.cargarDatos();
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
    
    // Inicializar con período "Hoy"
    this.aplicarPeriodo('hoy');
    
    this.cargarDatos();
  }

  ngOnDestroy() {
    if (this.routerSubscription) {
      this.routerSubscription.unsubscribe();
    }
    // Destruir gráficos
    this.destruirGraficos();
  }

  /**
   * Carga todos los datos del dashboard
   */
  cargarDatos() {
    if (!this.isBrowser) return;
    
    this.cargando = true;
    
    // Cargar créditos por ruta
    this.dashboardService.obtenerCreditosPorRuta().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.creditosPorRuta = resp.datos || [];
          this.crearGraficoCreditosPorRuta();
        }
      },
      error: (error) => {
        console.error('Error al cargar créditos por ruta:', error);
      }
    });

    // Cargar total general de créditos
    this.dashboardService.obtenerTotalGeneralCreditos().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.totalGeneralCreditos = resp.total_general || 0;
        }
      },
      error: (error) => {
        console.error('Error al cargar total general de créditos:', error);
      }
    });

    // Cargar estadísticas de clientes
    this.dashboardService.obtenerEstadisticasClientes().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasClientes = resp.datos || {};
          this.crearGraficoClientesComparativo();
        }
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de clientes:', error);
      }
    });

    // Cargar clientes por ruta
    this.dashboardService.obtenerClientesPorRuta(this.fechaInicio, this.fechaFin).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.clientesPorRuta = resp.datos || [];
        }
      },
      error: (error) => {
        console.error('Error al cargar clientes por ruta:', error);
      }
    });

    // Cargar total cobrado en el día
    this.dashboardService.obtenerTotalCobradoEnDia(this.fechaInicio, this.fechaFin).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.totalCobradoEnDia = resp.total_cobrado || 0;
        }
        this.cargando = false;
      },
      error: (error) => {
        console.error('Error al cargar total cobrado en el día:', error);
        this.cargando = false;
      }
    });

    // Cargar gastos por ruta
    this.cargarGastosPorRuta();

    // Cargar nuevas estadísticas
    this.cargarCreditosPorTipo();
    this.cargarEstadisticasSeguros();
    this.cargarEstadisticasCajas();
    this.cargarEstadisticasCuotas();
    this.cargarEvolucionPagos();
    this.cargarEstadisticasRefinanciaciones();
    this.cargarTopRutas();
    this.cargarEstadisticasMorosidad();
  }

  /**
   * Carga los gastos por ruta con filtros de fecha
   */
  cargarGastosPorRuta() {
    this.dashboardService.obtenerGastosPorRuta(this.fechaInicio, this.fechaFin).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.gastosPorRuta = resp.datos || [];
          this.crearGraficoGastosPorRuta();
        }
      },
      error: (error) => {
        console.error('Error al cargar gastos por ruta:', error);
      }
    });
  }

  /**
   * Crea el gráfico de barras de créditos por ruta
   */
  crearGraficoCreditosPorRuta() {
    if (!this.isBrowser) return;
    
    // Esperar a que Chart.js esté disponible
    if (typeof Chart === 'undefined') {
      setTimeout(() => this.crearGraficoCreditosPorRuta(), 100);
      return;
    }

    const canvas = document.getElementById('chartCreditosPorRuta') as HTMLCanvasElement;
    if (!canvas) {
      setTimeout(() => this.crearGraficoCreditosPorRuta(), 100);
      return;
    }

    // Destruir gráfico anterior si existe
    if (this.chartCreditosPorRuta) {
      this.chartCreditosPorRuta.destroy();
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.creditosPorRuta.map((ruta: any) => ruta.nombre_ruta);
    const datos = this.creditosPorRuta.map((ruta: any) => parseFloat(ruta.total_credito));

    this.chartCreditosPorRuta = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Valor Total Crédito',
          backgroundColor: 'rgba(60, 141, 188, 0.9)',
          borderColor: 'rgba(60, 141, 188, 0.8)',
          data: datos
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          yAxes: [{
            ticks: {
              beginAtZero: true,
              callback: function(value: any) {
                return '$' + value.toLocaleString('es-CO');
              }
            }
          }]
        },
        tooltips: {
          callbacks: {
            label: function(tooltipItem: any, data: any) {
              return '$' + parseFloat(tooltipItem.yLabel).toLocaleString('es-CO');
            }
          }
        }
      }
    });
  }

  /**
   * Crea el gráfico comparativo de clientes (total vs con crédito)
   */
  crearGraficoClientesComparativo() {
    if (!this.isBrowser) return;
    
    // Esperar a que Chart.js esté disponible
    if (typeof Chart === 'undefined') {
      setTimeout(() => this.crearGraficoClientesComparativo(), 100);
      return;
    }

    const canvas = document.getElementById('chartClientesComparativo') as HTMLCanvasElement;
    if (!canvas) {
      setTimeout(() => this.crearGraficoClientesComparativo(), 100);
      return;
    }

    // Destruir gráfico anterior si existe
    if (this.chartClientesComparativo) {
      this.chartClientesComparativo.destroy();
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const totalClientes = parseInt(this.estadisticasClientes.total_clientes || 0);
    const clientesConCredito = parseInt(this.estadisticasClientes.clientes_con_credito || 0);
    const clientesSinCredito = totalClientes - clientesConCredito;

    this.chartClientesComparativo = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Clientes con Crédito', 'Clientes sin Crédito'],
        datasets: [{
          data: [clientesConCredito, clientesSinCredito],
          backgroundColor: [
            'rgba(40, 167, 69, 0.9)',
            'rgba(220, 53, 69, 0.9)'
          ],
          borderColor: [
            'rgba(40, 167, 69, 1)',
            'rgba(220, 53, 69, 1)'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: {
          position: 'bottom'
        }
      }
    });
  }

  /**
   * Crea el gráfico de gastos por ruta
   */
  crearGraficoGastosPorRuta() {
    if (!this.isBrowser) return;
    
    // Esperar a que Chart.js esté disponible
    if (typeof Chart === 'undefined') {
      setTimeout(() => this.crearGraficoGastosPorRuta(), 100);
      return;
    }

    const canvas = document.getElementById('chartGastosPorRuta') as HTMLCanvasElement;
    if (!canvas) {
      setTimeout(() => this.crearGraficoGastosPorRuta(), 100);
      return;
    }

    // Destruir gráfico anterior si existe
    if (this.chartGastosPorRuta) {
      this.chartGastosPorRuta.destroy();
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.gastosPorRuta.map((ruta: any) => ruta.nombre_ruta);
    const datos = this.gastosPorRuta.map((ruta: any) => parseFloat(ruta.total_gastos));

    this.chartGastosPorRuta = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Total Gastos',
          backgroundColor: 'rgba(220, 53, 69, 0.9)',
          borderColor: 'rgba(220, 53, 69, 0.8)',
          data: datos
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          yAxes: [{
            ticks: {
              beginAtZero: true,
              callback: function(value: any) {
                return '$' + value.toLocaleString('es-CO');
              }
            }
          }]
        },
        tooltips: {
          callbacks: {
            label: function(tooltipItem: any, data: any) {
              return '$' + parseFloat(tooltipItem.yLabel).toLocaleString('es-CO');
            }
          }
        }
      }
    });
  }

  /**
   * Cambia el período seleccionado y calcula las fechas correspondientes
   */
  cambiarPeriodo(periodo: string) {
    this.periodoSeleccionado = periodo;
    this.aplicarPeriodo(periodo);
  }

  /**
   * Aplica el período seleccionado y calcula las fechas
   */
  aplicarPeriodo(periodo: string) {
    // Obtener fecha actual en zona horaria de Colombia
    const ahora = new Date();
    const fechaColombia = new Date(ahora.toLocaleString('en-US', { timeZone: 'America/Bogota' }));
    
    let fechaInicio: Date;
    let fechaFin: Date;

    switch (periodo) {
      case 'hoy':
        fechaInicio = new Date(fechaColombia);
        fechaInicio.setHours(0, 0, 0, 0);
        fechaFin = new Date(fechaColombia);
        fechaFin.setHours(23, 59, 59, 999);
        this.mostrarRangoPersonalizado = false;
        break;
      
      case 'semana':
        // Lunes de esta semana (la semana empieza el lunes)
        fechaInicio = new Date(fechaColombia);
        const diaSemana = fechaInicio.getDay(); // 0 = Domingo, 1 = Lunes, ..., 6 = Sábado
        const diffLunes = diaSemana === 0 ? -6 : 1 - diaSemana; // Si es domingo, retroceder 6 días
        fechaInicio.setDate(fechaInicio.getDate() + diffLunes);
        fechaInicio.setHours(0, 0, 0, 0);
        
        // Hasta hoy
        fechaFin = new Date(fechaColombia);
        fechaFin.setHours(23, 59, 59, 999);
        this.mostrarRangoPersonalizado = false;
        break;
      
      case 'mes':
        // Primer día del mes actual
        fechaInicio = new Date(fechaColombia.getFullYear(), fechaColombia.getMonth(), 1);
        fechaInicio.setHours(0, 0, 0, 0);
        
        // Último día del mes actual
        fechaFin = new Date(fechaColombia.getFullYear(), fechaColombia.getMonth() + 1, 0);
        fechaFin.setHours(23, 59, 59, 999);
        this.mostrarRangoPersonalizado = false;
        break;
      
      case 'año':
        // Primer día del año actual
        fechaInicio = new Date(fechaColombia.getFullYear(), 0, 1);
        fechaInicio.setHours(0, 0, 0, 0);
        
        // Último día del año actual
        fechaFin = new Date(fechaColombia.getFullYear(), 11, 31);
        fechaFin.setHours(23, 59, 59, 999);
        this.mostrarRangoPersonalizado = false;
        break;
      
      case 'personalizado':
        // Mantener las fechas actuales o usar hoy si no hay fechas
        if (!this.fechaInicio || !this.fechaFin) {
          fechaInicio = new Date(fechaColombia);
          fechaInicio.setHours(0, 0, 0, 0);
          fechaFin = new Date(fechaColombia);
          fechaFin.setHours(23, 59, 59, 999);
        } else {
          fechaInicio = new Date(this.fechaInicio);
          fechaInicio.setHours(0, 0, 0, 0);
          fechaFin = new Date(this.fechaFin);
          fechaFin.setHours(23, 59, 59, 999);
        }
        this.mostrarRangoPersonalizado = true;
        break;
      
      default:
        fechaInicio = new Date(fechaColombia);
        fechaInicio.setHours(0, 0, 0, 0);
        fechaFin = new Date(fechaColombia);
        fechaFin.setHours(23, 59, 59, 999);
        this.mostrarRangoPersonalizado = false;
    }

    // Formatear fechas como YYYY-MM-DD (solo la fecha, sin hora)
    this.fechaInicio = fechaInicio.toISOString().split('T')[0];
    this.fechaFin = fechaFin.toISOString().split('T')[0];
    
    // Recargar datos automáticamente si no es personalizado
    if (periodo !== 'personalizado') {
      this.cargarDatos();
    }
  }

  /**
   * Aplica los filtros de fecha y recarga los datos (para modo personalizado)
   */
  aplicarFiltros() {
    if (this.periodoSeleccionado === 'personalizado') {
      // Validar que las fechas sean válidas
      if (!this.fechaInicio || !this.fechaFin) {
        alert('Por favor seleccione ambas fechas');
        return;
      }
      
      if (new Date(this.fechaInicio) > new Date(this.fechaFin)) {
        alert('La fecha de inicio no puede ser mayor que la fecha de fin');
        return;
      }
    }
    
    this.cargarDatos();
  }

  /**
   * Formatea un número como moneda
   */
  formatearMoneda(valor: any): string {
    if (valor === null || valor === undefined) return '$0.00';
    return '$' + parseFloat(valor).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  /**
   * Carga créditos por tipo
   */
  cargarCreditosPorTipo() {
    this.dashboardService.obtenerCreditosPorTipo().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.creditosPorTipo = resp.datos || [];
          this.crearGraficoCreditosPorTipo();
        }
      },
      error: (error) => {
        console.error('Error al cargar créditos por tipo:', error);
      }
    });
  }

  /**
   * Carga estadísticas de seguros
   */
  cargarEstadisticasSeguros() {
    this.dashboardService.obtenerEstadisticasSeguros(this.fechaInicio, this.fechaFin).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasSeguros = resp.datos || {};
        }
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de seguros:', error);
      }
    });
  }

  /**
   * Carga estadísticas de cajas
   */
  cargarEstadisticasCajas() {
    this.dashboardService.obtenerEstadisticasCajas().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasCajas = resp.datos || {};
        }
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de cajas:', error);
      }
    });
  }

  /**
   * Carga estadísticas de cuotas
   */
  cargarEstadisticasCuotas() {
    this.dashboardService.obtenerEstadisticasCuotas().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasCuotas = resp.datos || {};
          this.crearGraficoCuotas();
        }
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de cuotas:', error);
      }
    });
  }

  /**
   * Carga evolución de pagos
   */
  cargarEvolucionPagos() {
    // Para evolución, usar un rango más amplio si no hay fechas específicas
    let fechaInicioEvolucion = this.fechaInicio;
    let fechaFinEvolucion = this.fechaFin;
    
    if (!fechaInicioEvolucion || !fechaFinEvolucion || this.periodoSeleccionado === 'hoy') {
      // Por defecto, últimos 30 días para ver evolución
      const fechaHoy = new Date();
      fechaFinEvolucion = fechaHoy.toISOString().split('T')[0];
      const fecha30DiasAtras = new Date();
      fecha30DiasAtras.setDate(fecha30DiasAtras.getDate() - 30);
      fechaInicioEvolucion = fecha30DiasAtras.toISOString().split('T')[0];
    }
    
    this.dashboardService.obtenerEvolucionPagos(fechaInicioEvolucion, fechaFinEvolucion).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.evolucionPagos = resp.datos || [];
          this.crearGraficoEvolucionPagos();
        }
      },
      error: (error) => {
        console.error('Error al cargar evolución de pagos:', error);
      }
    });
  }

  /**
   * Carga estadísticas de refinanciaciones
   */
  cargarEstadisticasRefinanciaciones() {
    this.dashboardService.obtenerEstadisticasRefinanciaciones(this.fechaInicio, this.fechaFin).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasRefinanciaciones = resp.datos || {};
        }
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de refinanciaciones:', error);
      }
    });
  }

  /**
   * Carga top rutas por rendimiento
   */
  cargarTopRutas() {
    this.dashboardService.obtenerTopRutasPorRendimiento(this.fechaInicio, this.fechaFin, 5).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.topRutas = resp.datos || [];
          this.crearGraficoTopRutas();
        }
      },
      error: (error) => {
        console.error('Error al cargar top rutas:', error);
      }
    });
  }

  /**
   * Carga estadísticas de morosidad
   */
  cargarEstadisticasMorosidad() {
    this.dashboardService.obtenerEstadisticasMorosidad().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasMorosidad = resp.datos || {};
        }
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de morosidad:', error);
      }
    });
  }

  /**
   * Crea el gráfico de créditos por tipo
   */
  crearGraficoCreditosPorTipo() {
    if (!this.isBrowser) return;
    
    if (typeof Chart === 'undefined') {
      setTimeout(() => this.crearGraficoCreditosPorTipo(), 100);
      return;
    }

    const canvas = document.getElementById('chartCreditosPorTipo') as HTMLCanvasElement;
    if (!canvas) {
      setTimeout(() => this.crearGraficoCreditosPorTipo(), 100);
      return;
    }

    if (this.chartCreditosPorTipo) {
      this.chartCreditosPorTipo.destroy();
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.creditosPorTipo.map((item: any) => {
      const tipo = item.tipo_credito || 'comun';
      if (tipo === 'comun') return 'Común';
      if (tipo === 'refinanciado') return 'Refinanciado';
      if (tipo === 'refinanciado_por_sistema') return 'Refinanciado por Sistema';
      return tipo;
    });
    const datos = this.creditosPorTipo.map((item: any) => parseFloat(item.total_saldo || 0));
    const cantidades = this.creditosPorTipo.map((item: any) => parseInt(item.cantidad || 0));

    this.chartCreditosPorTipo = new Chart(ctx, {
      type: 'pie',
      data: {
        labels: labels,
        datasets: [{
          label: 'Valor Total',
          data: datos,
          backgroundColor: [
            'rgba(6, 102, 153, 0.9)',
            'rgba(255, 193, 7, 0.9)',
            'rgba(220, 53, 69, 0.9)'
          ],
          borderColor: [
            'rgba(6, 102, 153, 1)',
            'rgba(255, 193, 7, 1)',
            'rgba(220, 53, 69, 1)'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        tooltips: {
          callbacks: {
            label: function(tooltipItem: any, data: any) {
              const label = data.labels[tooltipItem.index] || '';
              const value = '$' + parseFloat(data.datasets[0].data[tooltipItem.index]).toLocaleString('es-CO');
              const cantidad = cantidades[tooltipItem.index];
              return label + ': ' + value + ' (' + cantidad + ' créditos)';
            }
          }
        }
      }
    });
  }

  /**
   * Crea el gráfico de evolución de pagos
   */
  crearGraficoEvolucionPagos() {
    if (!this.isBrowser) return;
    
    if (typeof Chart === 'undefined') {
      setTimeout(() => this.crearGraficoEvolucionPagos(), 100);
      return;
    }

    const canvas = document.getElementById('chartEvolucionPagos') as HTMLCanvasElement;
    if (!canvas) {
      setTimeout(() => this.crearGraficoEvolucionPagos(), 100);
      return;
    }

    if (this.chartEvolucionPagos) {
      this.chartEvolucionPagos.destroy();
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.evolucionPagos.map((item: any) => {
      const fecha = new Date(item.fecha);
      return fecha.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit' });
    });
    const datosCobrado = this.evolucionPagos.map((item: any) => parseFloat(item.total_cobrado || 0));
    const datosSeguros = this.evolucionPagos.map((item: any) => parseFloat(item.total_seguros || 0));

    this.chartEvolucionPagos = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Total Cobrado',
            data: datosCobrado,
            borderColor: 'rgba(6, 102, 153, 1)',
            backgroundColor: 'rgba(6, 102, 153, 0.1)',
            tension: 0.4,
            fill: true
          },
          {
            label: 'Seguros',
            data: datosSeguros,
            borderColor: 'rgba(174, 221, 43, 1)',
            backgroundColor: 'rgba(174, 221, 43, 0.1)',
            tension: 0.4,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          yAxes: [{
            ticks: {
              beginAtZero: true,
              callback: function(value: any) {
                return '$' + value.toLocaleString('es-CO');
              }
            }
          }]
        },
        tooltips: {
          callbacks: {
            label: function(tooltipItem: any, data: any) {
              return data.datasets[tooltipItem.datasetIndex].label + ': $' + 
                     parseFloat(tooltipItem.yLabel).toLocaleString('es-CO');
            }
          }
        }
      }
    });
  }

  /**
   * Crea el gráfico de cuotas
   */
  crearGraficoCuotas() {
    if (!this.isBrowser) return;
    
    if (typeof Chart === 'undefined') {
      setTimeout(() => this.crearGraficoCuotas(), 100);
      return;
    }

    const canvas = document.getElementById('chartCuotas') as HTMLCanvasElement;
    if (!canvas) {
      setTimeout(() => this.crearGraficoCuotas(), 100);
      return;
    }

    if (this.chartCuotas) {
      this.chartCuotas.destroy();
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const pagadas = parseInt(this.estadisticasCuotas.cuotas_pagadas || 0);
    const pendientes = parseInt(this.estadisticasCuotas.cuotas_pendientes || 0);
    const vencidas = parseInt(this.estadisticasCuotas.cuotas_vencidas || 0);

    this.chartCuotas = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Pagadas', 'Pendientes', 'Vencidas'],
        datasets: [{
          data: [pagadas, pendientes, vencidas],
          backgroundColor: [
            'rgba(40, 167, 69, 0.9)',
            'rgba(255, 193, 7, 0.9)',
            'rgba(220, 53, 69, 0.9)'
          ],
          borderColor: [
            'rgba(40, 167, 69, 1)',
            'rgba(255, 193, 7, 1)',
            'rgba(220, 53, 69, 1)'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: {
          position: 'bottom'
        }
      }
    });
  }

  /**
   * Crea el gráfico de top rutas
   */
  crearGraficoTopRutas() {
    if (!this.isBrowser) return;
    
    if (typeof Chart === 'undefined') {
      setTimeout(() => this.crearGraficoTopRutas(), 100);
      return;
    }

    const canvas = document.getElementById('chartTopRutas') as HTMLCanvasElement;
    if (!canvas) {
      setTimeout(() => this.crearGraficoTopRutas(), 100);
      return;
    }

    if (this.chartTopRutas) {
      this.chartTopRutas.destroy();
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const labels = this.topRutas.map((ruta: any) => ruta.nombre_ruta);
    const datos = this.topRutas.map((ruta: any) => parseFloat(ruta.total_cobrado || 0));

    this.chartTopRutas = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Total Cobrado',
          backgroundColor: 'rgba(174, 221, 43, 0.9)',
          borderColor: 'rgba(174, 221, 43, 1)',
          data: datos
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          yAxes: [{
            ticks: {
              beginAtZero: true,
              callback: function(value: any) {
                return '$' + value.toLocaleString('es-CO');
              }
            }
          }]
        },
        tooltips: {
          callbacks: {
            label: function(tooltipItem: any, data: any) {
              return '$' + parseFloat(tooltipItem.yLabel).toLocaleString('es-CO');
            }
          }
        }
      }
    });
  }

  /**
   * Destruye todos los gráficos
   */
  destruirGraficos() {
    if (this.chartCreditosPorRuta) {
      this.chartCreditosPorRuta.destroy();
      this.chartCreditosPorRuta = null;
    }
    if (this.chartClientesComparativo) {
      this.chartClientesComparativo.destroy();
      this.chartClientesComparativo = null;
    }
    if (this.chartGastosPorRuta) {
      this.chartGastosPorRuta.destroy();
      this.chartGastosPorRuta = null;
    }
    if (this.chartCreditosPorTipo) {
      this.chartCreditosPorTipo.destroy();
      this.chartCreditosPorTipo = null;
    }
    if (this.chartEvolucionPagos) {
      this.chartEvolucionPagos.destroy();
      this.chartEvolucionPagos = null;
    }
    if (this.chartCuotas) {
      this.chartCuotas.destroy();
      this.chartCuotas = null;
    }
    if (this.chartTopRutas) {
      this.chartTopRutas.destroy();
      this.chartTopRutas = null;
    }
  }
}

import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID, ChangeDetectorRef } from '@angular/core';
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

  // ── Control de carga ──────────────────────────────────────────────────────
  private llamadasPendientes: number = 0;
  cargando: boolean = false;

  private iniciarCarga() {
    this.llamadasPendientes++;
    this.cargando = true;
  }

  private finalizarCarga() {
    this.llamadasPendientes--;
    if (this.llamadasPendientes <= 0) {
      this.llamadasPendientes = 0;
      this.cargando = false;
      this.cdr.detectChanges();
    }
  }
  // ─────────────────────────────────────────────────────────────────────────

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
  periodoSeleccionado: string = 'hoy';
  mostrarRangoPersonalizado: boolean = false;

  // Referencias a los gráficos
  private chartCreditosPorRuta: any = null;
  private chartClientesComparativo: any = null;
  private chartGastosPorRuta: any = null;
  private chartCreditosPorTipo: any = null;
  private chartEvolucionPagos: any = null;
  private chartCuotas: any = null;
  private chartTopRutas: any = null;

  constructor(
    private router: Router,
    private dashboardService: DashboardService,
    private cdr: ChangeDetectorRef,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);

    this.routerSubscription = this.router.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe((event: any) => {
        if (
          event.url === '/dashboard' ||
          event.urlAfterRedirects === '/dashboard' ||
          event.url === '/' ||
          event.urlAfterRedirects === '/'
        ) {
          this.cargarDatos();
        }
      });
  }

  ngOnInit() {
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

    this.aplicarPeriodo('hoy');
    // this.cargarDatos();
  }

  ngOnDestroy() {
    if (this.routerSubscription) {
      this.routerSubscription.unsubscribe();
    }
    this.destruirGraficos();
  }

  // ── Carga de datos ────────────────────────────────────────────────────────

  cargarDatos() {
    if (!this.isBrowser) return;

    // Reiniciar contador antes de lanzar todas las llamadas
    // this.llamadasPendientes = 0;
    // this.cargando = true;

    this.iniciarCarga();
    this.dashboardService.obtenerCreditosPorRuta().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.creditosPorRuta = resp.datos || [];
          this.crearGraficoCreditosPorRuta();
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar créditos por ruta:', error);
        this.finalizarCarga();
      }
    });

    this.iniciarCarga();
    this.dashboardService.obtenerTotalGeneralCreditos().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.totalGeneralCreditos = resp.total_general || 0;
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar total general de créditos:', error);
        this.finalizarCarga();
      }
    });

    this.iniciarCarga();
    this.dashboardService.obtenerEstadisticasClientes().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasClientes = resp.datos || {};
          this.crearGraficoClientesComparativo();
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de clientes:', error);
        this.finalizarCarga();
      }
    });

    this.iniciarCarga();
    this.dashboardService.obtenerClientesPorRuta(this.fechaInicio, this.fechaFin).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.clientesPorRuta = resp.datos || [];
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar clientes por ruta:', error);
        this.finalizarCarga();
      }
    });

    this.iniciarCarga();
    this.dashboardService.obtenerTotalCobradoEnDia(this.fechaInicio, this.fechaFin).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.totalCobradoEnDia = resp.total_cobrado || 0;
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar total cobrado en el día:', error);
        this.finalizarCarga();
      }
    });

    this.cargarGastosPorRuta();
    this.cargarCreditosPorTipo();
    this.cargarEstadisticasSeguros();
    this.cargarEstadisticasCajas();
    this.cargarEstadisticasCuotas();
    this.cargarEvolucionPagos();
    this.cargarEstadisticasRefinanciaciones();
    this.cargarTopRutas();
    this.cargarEstadisticasMorosidad();

    this.finalizarCarga(); // Finalizar la carga inicial después de lanzar todas las llamadas 
  }

  cargarGastosPorRuta() {
    this.iniciarCarga();
    this.dashboardService.obtenerGastosPorRuta(this.fechaInicio, this.fechaFin).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.gastosPorRuta = resp.datos || [];
          this.crearGraficoGastosPorRuta();
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar gastos por ruta:', error);
        this.finalizarCarga();
      }
    });
  }

  cargarCreditosPorTipo() {
    this.iniciarCarga();
    this.dashboardService.obtenerCreditosPorTipo().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.creditosPorTipo = resp.datos || [];
          this.crearGraficoCreditosPorTipo();
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar créditos por tipo:', error);
        this.finalizarCarga();
      }
    });
  }

  cargarEstadisticasSeguros() {
    this.iniciarCarga();
    this.dashboardService.obtenerEstadisticasSeguros(this.fechaInicio, this.fechaFin).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasSeguros = resp.datos || {};
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de seguros:', error);
        this.finalizarCarga();
      }
    });
  }

  cargarEstadisticasCajas() {
    this.iniciarCarga();
    this.dashboardService.obtenerEstadisticasCajas().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasCajas = resp.datos || {};
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de cajas:', error);
        this.finalizarCarga();
      }
    });
  }

  cargarEstadisticasCuotas() {
    this.iniciarCarga();
    this.dashboardService.obtenerEstadisticasCuotas().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasCuotas = resp.datos || {};
          this.crearGraficoCuotas();
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de cuotas:', error);
        this.finalizarCarga();
      }
    });
  }

  cargarEvolucionPagos() {
    let fechaInicioEvolucion = this.fechaInicio;
    let fechaFinEvolucion = this.fechaFin;

    if (!fechaInicioEvolucion || !fechaFinEvolucion || this.periodoSeleccionado === 'hoy') {
      const fechaHoy = new Date();
      fechaFinEvolucion = fechaHoy.toISOString().split('T')[0];
      const fecha30DiasAtras = new Date();
      fecha30DiasAtras.setDate(fecha30DiasAtras.getDate() - 30);
      fechaInicioEvolucion = fecha30DiasAtras.toISOString().split('T')[0];
    }

    this.iniciarCarga();
    this.dashboardService.obtenerEvolucionPagos(fechaInicioEvolucion, fechaFinEvolucion).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.evolucionPagos = resp.datos || [];
          this.crearGraficoEvolucionPagos();
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar evolución de pagos:', error);
        this.finalizarCarga();
      }
    });
  }

  cargarEstadisticasRefinanciaciones() {
    this.iniciarCarga();
    this.dashboardService.obtenerEstadisticasRefinanciaciones(this.fechaInicio, this.fechaFin).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasRefinanciaciones = resp.datos || {};
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de refinanciaciones:', error);
        this.finalizarCarga();
      }
    });
  }

  cargarTopRutas() {
    this.iniciarCarga();
    this.dashboardService.obtenerTopRutasPorRendimiento(this.fechaInicio, this.fechaFin, 5).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.topRutas = resp.datos || [];
          this.crearGraficoTopRutas();
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar top rutas:', error);
        this.finalizarCarga();
      }
    });
  }

  cargarEstadisticasMorosidad() {
    this.iniciarCarga();
    this.dashboardService.obtenerEstadisticasMorosidad().subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.estadisticasMorosidad = resp.datos || {};
        }
        this.finalizarCarga();
      },
      error: (error) => {
        console.error('Error al cargar estadísticas de morosidad:', error);
        this.finalizarCarga();
      }
    });
  }

  // ── Período y filtros ─────────────────────────────────────────────────────

  cambiarPeriodo(periodo: string) {
    this.periodoSeleccionado = periodo;
    this.aplicarPeriodo(periodo);
  }

  aplicarPeriodo(periodo: string) {
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
        fechaInicio = new Date(fechaColombia);
        const diaSemana = fechaInicio.getDay();
        const diffLunes = diaSemana === 0 ? -6 : 1 - diaSemana;
        fechaInicio.setDate(fechaInicio.getDate() + diffLunes);
        fechaInicio.setHours(0, 0, 0, 0);
        fechaFin = new Date(fechaColombia);
        fechaFin.setHours(23, 59, 59, 999);
        this.mostrarRangoPersonalizado = false;
        break;

      case 'mes':
        fechaInicio = new Date(fechaColombia.getFullYear(), fechaColombia.getMonth(), 1);
        fechaInicio.setHours(0, 0, 0, 0);
        fechaFin = new Date(fechaColombia.getFullYear(), fechaColombia.getMonth() + 1, 0);
        fechaFin.setHours(23, 59, 59, 999);
        this.mostrarRangoPersonalizado = false;
        break;

      case 'año':
        fechaInicio = new Date(fechaColombia.getFullYear(), 0, 1);
        fechaInicio.setHours(0, 0, 0, 0);
        fechaFin = new Date(fechaColombia.getFullYear(), 11, 31);
        fechaFin.setHours(23, 59, 59, 999);
        this.mostrarRangoPersonalizado = false;
        break;

      case 'personalizado':
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

    this.fechaInicio = fechaInicio.toISOString().split('T')[0];
    this.fechaFin = fechaFin.toISOString().split('T')[0];

    if (periodo !== 'personalizado') {
      this.cargarDatos();
    }
  }

  aplicarFiltros() {
    if (this.periodoSeleccionado === 'personalizado') {
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

  // ── Utilidades ────────────────────────────────────────────────────────────

  formatearMoneda(valor: any): string {
    if (valor === null || valor === undefined) return '$0.00';
    return '$' + parseFloat(valor).toLocaleString('es-CO', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  // ── Gráficos ──────────────────────────────────────────────────────────────

  crearGraficoCreditosPorRuta() {
    if (!this.isBrowser) return;
    if (typeof Chart === 'undefined') {
      setTimeout(() => this.crearGraficoCreditosPorRuta(), 100);
      return;
    }
    const canvas = document.getElementById('chartCreditosPorRuta') as HTMLCanvasElement;
    if (!canvas) { setTimeout(() => this.crearGraficoCreditosPorRuta(), 100); return; }
    if (this.chartCreditosPorRuta) this.chartCreditosPorRuta.destroy();
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    this.chartCreditosPorRuta = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: this.creditosPorRuta.map((r: any) => r.nombre_ruta),
        datasets: [{
          label: 'Valor Total Crédito',
          backgroundColor: 'rgba(60, 141, 188, 0.9)',
          borderColor: 'rgba(60, 141, 188, 0.8)',
          data: this.creditosPorRuta.map((r: any) => parseFloat(r.total_credito))
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { yAxes: [{ ticks: { beginAtZero: true, callback: (v: any) => '$' + v.toLocaleString('es-CO') } }] },
        tooltips: { callbacks: { label: (item: any) => '$' + parseFloat(item.yLabel).toLocaleString('es-CO') } }
      }
    });
  }

  crearGraficoClientesComparativo() {
    if (!this.isBrowser) return;
    if (typeof Chart === 'undefined') { setTimeout(() => this.crearGraficoClientesComparativo(), 100); return; }
    const canvas = document.getElementById('chartClientesComparativo') as HTMLCanvasElement;
    if (!canvas) { setTimeout(() => this.crearGraficoClientesComparativo(), 100); return; }
    if (this.chartClientesComparativo) this.chartClientesComparativo.destroy();
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const total = parseInt(this.estadisticasClientes.total_clientes || 0);
    const conCredito = parseInt(this.estadisticasClientes.clientes_con_credito || 0);

    this.chartClientesComparativo = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Clientes con Crédito', 'Clientes sin Crédito'],
        datasets: [{
          data: [conCredito, total - conCredito],
          backgroundColor: ['rgba(40, 167, 69, 0.9)', 'rgba(220, 53, 69, 0.9)'],
          borderColor: ['rgba(40, 167, 69, 1)', 'rgba(220, 53, 69, 1)']
        }]
      },
      options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom' } }
    });
  }

  crearGraficoGastosPorRuta() {
    if (!this.isBrowser) return;
    if (typeof Chart === 'undefined') { setTimeout(() => this.crearGraficoGastosPorRuta(), 100); return; }
    const canvas = document.getElementById('chartGastosPorRuta') as HTMLCanvasElement;
    if (!canvas) { setTimeout(() => this.crearGraficoGastosPorRuta(), 100); return; }
    if (this.chartGastosPorRuta) this.chartGastosPorRuta.destroy();
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    this.chartGastosPorRuta = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: this.gastosPorRuta.map((r: any) => r.nombre_ruta),
        datasets: [{
          label: 'Total Gastos',
          backgroundColor: 'rgba(220, 53, 69, 0.9)',
          borderColor: 'rgba(220, 53, 69, 0.8)',
          data: this.gastosPorRuta.map((r: any) => parseFloat(r.total_gastos))
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { yAxes: [{ ticks: { beginAtZero: true, callback: (v: any) => '$' + v.toLocaleString('es-CO') } }] },
        tooltips: { callbacks: { label: (item: any) => '$' + parseFloat(item.yLabel).toLocaleString('es-CO') } }
      }
    });
  }

  crearGraficoCreditosPorTipo() {
    if (!this.isBrowser) return;
    if (typeof Chart === 'undefined') { setTimeout(() => this.crearGraficoCreditosPorTipo(), 100); return; }
    const canvas = document.getElementById('chartCreditosPorTipo') as HTMLCanvasElement;
    if (!canvas) { setTimeout(() => this.crearGraficoCreditosPorTipo(), 100); return; }
    if (this.chartCreditosPorTipo) this.chartCreditosPorTipo.destroy();
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const cantidades = this.creditosPorTipo.map((i: any) => parseInt(i.cantidad || 0));

    this.chartCreditosPorTipo = new Chart(ctx, {
      type: 'pie',
      data: {
        labels: this.creditosPorTipo.map((i: any) => {
          const t = i.tipo_credito || 'comun';
          if (t === 'comun') return 'Común';
          if (t === 'refinanciado') return 'Refinanciado';
          if (t === 'refinanciado_por_sistema') return 'Refinanciado por Sistema';
          return t;
        }),
        datasets: [{
          data: this.creditosPorTipo.map((i: any) => parseFloat(i.total_saldo || 0)),
          backgroundColor: ['rgba(6,102,153,0.9)', 'rgba(255,193,7,0.9)', 'rgba(220,53,69,0.9)'],
          borderColor: ['rgba(6,102,153,1)', 'rgba(255,193,7,1)', 'rgba(220,53,69,1)']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        tooltips: {
          callbacks: {
            label: (item: any, data: any) => {
              const label = data.labels[item.index] || '';
              const value = '$' + parseFloat(data.datasets[0].data[item.index]).toLocaleString('es-CO');
              return label + ': ' + value + ' (' + cantidades[item.index] + ' créditos)';
            }
          }
        }
      }
    });
  }

  crearGraficoEvolucionPagos() {
    if (!this.isBrowser) return;
    if (typeof Chart === 'undefined') { setTimeout(() => this.crearGraficoEvolucionPagos(), 100); return; }
    const canvas = document.getElementById('chartEvolucionPagos') as HTMLCanvasElement;
    if (!canvas) { setTimeout(() => this.crearGraficoEvolucionPagos(), 100); return; }
    if (this.chartEvolucionPagos) this.chartEvolucionPagos.destroy();
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    this.chartEvolucionPagos = new Chart(ctx, {
      type: 'line',
      data: {
        labels: this.evolucionPagos.map((i: any) =>
          new Date(i.fecha).toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit' })
        ),
        datasets: [
          {
            label: 'Total Cobrado',
            data: this.evolucionPagos.map((i: any) => parseFloat(i.total_cobrado || 0)),
            borderColor: 'rgba(6,102,153,1)',
            backgroundColor: 'rgba(6,102,153,0.1)',
            tension: 0.4,
            fill: true
          },
          {
            label: 'Seguros',
            data: this.evolucionPagos.map((i: any) => parseFloat(i.total_seguros || 0)),
            borderColor: 'rgba(174,221,43,1)',
            backgroundColor: 'rgba(174,221,43,0.1)',
            tension: 0.4,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { yAxes: [{ ticks: { beginAtZero: true, callback: (v: any) => '$' + v.toLocaleString('es-CO') } }] },
        tooltips: {
          callbacks: {
            label: (item: any, data: any) =>
              data.datasets[item.datasetIndex].label + ': $' + parseFloat(item.yLabel).toLocaleString('es-CO')
          }
        }
      }
    });
  }

  crearGraficoCuotas() {
    if (!this.isBrowser) return;
    if (typeof Chart === 'undefined') { setTimeout(() => this.crearGraficoCuotas(), 100); return; }
    const canvas = document.getElementById('chartCuotas') as HTMLCanvasElement;
    if (!canvas) { setTimeout(() => this.crearGraficoCuotas(), 100); return; }
    if (this.chartCuotas) this.chartCuotas.destroy();
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    this.chartCuotas = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Pagadas', 'Pendientes', 'Vencidas'],
        datasets: [{
          data: [
            parseInt(this.estadisticasCuotas.cuotas_pagadas || 0),
            parseInt(this.estadisticasCuotas.cuotas_pendientes || 0),
            parseInt(this.estadisticasCuotas.cuotas_vencidas || 0)
          ],
          backgroundColor: ['rgba(40,167,69,0.9)', 'rgba(255,193,7,0.9)', 'rgba(220,53,69,0.9)'],
          borderColor: ['rgba(40,167,69,1)', 'rgba(255,193,7,1)', 'rgba(220,53,69,1)']
        }]
      },
      options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom' } }
    });
  }

  crearGraficoTopRutas() {
    if (!this.isBrowser) return;
    if (typeof Chart === 'undefined') { setTimeout(() => this.crearGraficoTopRutas(), 100); return; }
    const canvas = document.getElementById('chartTopRutas') as HTMLCanvasElement;
    if (!canvas) { setTimeout(() => this.crearGraficoTopRutas(), 100); return; }
    if (this.chartTopRutas) this.chartTopRutas.destroy();
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    this.chartTopRutas = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: this.topRutas.map((r: any) => r.nombre_ruta),
        datasets: [{
          label: 'Total Cobrado',
          backgroundColor: 'rgba(174,221,43,0.9)',
          borderColor: 'rgba(174,221,43,1)',
          data: this.topRutas.map((r: any) => parseFloat(r.total_cobrado || 0))
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { yAxes: [{ ticks: { beginAtZero: true, callback: (v: any) => '$' + v.toLocaleString('es-CO') } }] },
        tooltips: { callbacks: { label: (item: any) => '$' + parseFloat(item.yLabel).toLocaleString('es-CO') } }
      }
    });
  }

  destruirGraficos() {
    [
      'chartCreditosPorRuta', 'chartClientesComparativo', 'chartGastosPorRuta',
      'chartCreditosPorTipo', 'chartEvolucionPagos', 'chartCuotas', 'chartTopRutas'
    ].forEach(key => {
      if ((this as any)[key]) {
        (this as any)[key].destroy();
        (this as any)[key] = null;
      }
    });
  }
}
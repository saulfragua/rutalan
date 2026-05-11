import { Component } from '@angular/core';
import { InformesService } from '../../servicios/informes';

@Component({
  selector: 'app-informes',
  standalone: false,
  templateUrl: './informes.html',
  styleUrl: './informes.css',
})
export class Informes {
  fechaDesde: string = '';
  fechaHasta: string = '';
  tipoInforme: 'pagos' | 'creditos' | 'gastos' = 'pagos';
  cargando = false;
  lista: any[] = [];
  error: string | null = null;

  constructor(private informesService: InformesService) {
    const hoy = new Date().toISOString().split('T')[0];
    this.fechaDesde = hoy;
    this.fechaHasta = hoy;
  }

  generarInforme(): void {
    this.error = null;
    this.lista = [];
    if (!this.fechaDesde || !this.fechaHasta) {
      this.error = 'Seleccione rango de fechas.';
      return;
    }
    if (this.fechaDesde > this.fechaHasta) {
      this.error = 'La fecha inicial no puede ser mayor que la final.';
      return;
    }
    this.cargando = true;
    const req =
      this.tipoInforme === 'pagos'
        ? this.informesService.obtenerPagosPorRango(this.fechaDesde, this.fechaHasta)
        : this.tipoInforme === 'creditos'
          ? this.informesService.obtenerCreditosPorRango(this.fechaDesde, this.fechaHasta)
          : this.informesService.obtenerGastosPorRango(this.fechaDesde, this.fechaHasta);

    req.subscribe({
      next: (resp: any) => {
        this.cargando = false;
        if (resp?.resultado === 'ok' && Array.isArray(resp.datos)) {
          this.lista = resp.datos;
        } else {
          this.error = resp?.mensaje || 'No se obtuvieron datos.';
        }
      },
      error: (err) => {
        this.cargando = false;
        this.error = err?.error?.mensaje || err?.message || 'Error al generar el informe.';
      },
    });
  }

  formatearMonto(val: number | string | null | undefined): string {
    if (val == null || val === '') return '0';
    const n = typeof val === 'string' ? parseFloat(val) : val;
    return isNaN(n) ? '0' : n.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  formatearHora(val: string | null | undefined): string {
    if (!val) return '-';
    const part = String(val).substring(0, 5);
    return part.length >= 5 ? part : val;
  }

  /** Total del informe actual: pagos, créditos o gastos según tipo */
  get totalInforme(): number {
    if (!this.lista.length) return 0;
    if (this.tipoInforme === 'pagos') {
      return this.lista.reduce((s, i) => s + (Number(i.monto_pagado) || 0), 0);
    }
    if (this.tipoInforme === 'creditos') {
      return this.lista.reduce((s, i) => s + (Number(i.monto_credito) || 0), 0);
    }
    if (this.tipoInforme === 'gastos') {
      return this.lista.reduce((s, i) => s + (Number(i.monto) || 0), 0);
    }
    return 0;
  }
}

import { Component, Inject, PLATFORM_ID, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { isPlatformBrowser } from '@angular/common';
import { InformesService } from '../../servicios/informes';

import * as XLSX from 'xlsx';
import { jsPDF } from 'jspdf';
import { autoTable } from 'jspdf-autotable';

@Component({
  selector: 'app-informes',
  standalone: false,
  templateUrl: './informes.html',
  styleUrl: './informes.css',
})
export class Informes implements OnInit {
  fechaDesde: string = '';
  fechaHasta: string = '';
  tipoInforme: 'pagos' | 'creditos' | 'gastos' = 'pagos';
  cargando = false;
  lista: any[] = [];
  error: string | null = null;

  private isBrowser: boolean;

  constructor(
    private informesService: InformesService,
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    const hoy = new Date().toISOString().split('T')[0];
    this.fechaDesde = hoy;
    this.fechaHasta = hoy;
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

  /**
 * Devuelve el nombre descriptivo del informe actual.
 */
private obtenerTituloInforme(): string {
  switch (this.tipoInforme) {
    case 'pagos':
      return 'Informe de pagos';
    case 'creditos':
      return 'Informe de créditos';
    case 'gastos':
      return 'Informe de gastos';
    default:
      return 'Informe';
  }
}

/**
 * Prepara los datos con nombres de columnas legibles
 * para descargarlos en Excel.
 */
private obtenerDatosExcel(): Record<string, string | number>[] {
  if (this.tipoInforme === 'pagos') {
    return this.lista.map((item) => ({
      Fecha: item.fecha_pago || '',
      Hora: this.formatearHora(item.hora_pago),
      Cliente: item.nombre_completo_cliente || '',
      Documento: item.documento_cliente || '',
      Teléfono: item.telefono_cliente || item.telefono2_cliente || '',
      Dirección: item.direccion_cliente || '',
      Monto: Number(item.monto_pagado) || 0,
      'Registrado por': item.usuario_registro || '',
      Ruta: item.nombre_ruta || '',
    }));
  }

  if (this.tipoInforme === 'creditos') {
    return this.lista.map((item) => ({
      Fecha: item.fecha_toma_credito || '',
      Hora: this.formatearHora(item.hora_toma_credito),
      Cliente: item.nombre_completo_cliente || '',
      Documento: item.documento_cliente || '',
      Teléfono: item.telefono_cliente || item.telefono2_cliente || '',
      Dirección: item.direccion_cliente || '',
      Monto: Number(item.monto_credito) || 0,
      Cuotas: Number(item.cuotas) || 0,
      'Registrado por': item.usuario_registro || '',
      Ruta: item.nombre_ruta || '',
    }));
  }

  return this.lista.map((item) => ({
    Fecha: item.fecha_gasto || '',
    Hora: this.formatearHora(item.hora_gasto),
    Descripción: item.descripcion || '',
    Monto: Number(item.monto) || 0,
    'Registrado por': item.usuario_registro || '',
    Ruta: item.nombre_ruta || '',
  }));
}

/**
 * Descargar informe en formato Excel.
 */
descargarExcel(): void {
  if (!this.isBrowser) {
    return;
  }

  if (!this.lista.length) {
    this.error = 'Primero debe generar un informe.';
    return;
  }

  try {
    const datos = this.obtenerDatosExcel();

    // Agregar fila del total.
    datos.push({
      Fecha: '',
      Hora: '',
      Monto: this.totalInforme,
      Cliente: `TOTAL ${this.tipoInforme.toUpperCase()}`,
    });

    const hoja = XLSX.utils.json_to_sheet(datos);

    // Ancho aproximado de las columnas.
    const columnas = Object.keys(datos[0] || {}).map((columna) => ({
      wch: Math.max(
        columna.length + 2,
        ...datos.map((fila) => String(fila[columna] ?? '').length + 2)
      ),
    }));

    hoja['!cols'] = columnas;

    const libro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(libro, hoja, 'Informe');

    const nombreArchivo =
      `${this.tipoInforme}_${this.fechaDesde}_${this.fechaHasta}.xlsx`;

    XLSX.writeFile(libro, nombreArchivo);
  } catch (error) {
    console.error('Error al generar Excel:', error);
    this.error = 'No fue posible generar el archivo Excel.';
  }
}

/**
 * Descargar informe en formato PDF.
 */
descargarPDF(): void {
  if (!this.isBrowser) {
    return;
  }

  if (!this.lista.length) {
    this.error = 'Primero debe generar un informe.';
    return;
  }

  try {
    const documento = new jsPDF({
      orientation: 'landscape',
      unit: 'mm',
      format: 'a4',
    });

    const titulo = this.obtenerTituloInforme();

    documento.setFontSize(16);
    documento.text(titulo, 14, 15);

    documento.setFontSize(10);
    documento.text(
      `Periodo: ${this.fechaDesde} hasta ${this.fechaHasta}`,
      14,
      22
    );

    documento.text(
      `Total de registros: ${this.lista.length}`,
      14,
      28
    );

    documento.text(
      `Valor total: $${this.formatearMonto(this.totalInforme)}`,
      14,
      34
    );

    let encabezados: string[][] = [];
    let filas: Array<Array<string | number>> = [];

    if (this.tipoInforme === 'pagos') {
      encabezados = [[
        'Fecha',
        'Hora',
        'Cliente',
        'Documento',
        'Teléfono',
        'Dirección',
        'Monto',
        'Registrado por',
        'Ruta',
      ]];

      filas = this.lista.map((item) => [
        item.fecha_pago || '-',
        this.formatearHora(item.hora_pago),
        item.nombre_completo_cliente || '-',
        item.documento_cliente || '-',
        item.telefono_cliente || item.telefono2_cliente || '-',
        item.direccion_cliente || '-',
        `$${this.formatearMonto(item.monto_pagado)}`,
        item.usuario_registro || '-',
        item.nombre_ruta || '-',
      ]);
    } else if (this.tipoInforme === 'creditos') {
      encabezados = [[
        'Fecha',
        'Hora',
        'Cliente',
        'Documento',
        'Teléfono',
        'Dirección',
        'Monto',
        'Cuotas',
        'Registrado por',
        'Ruta',
      ]];

      filas = this.lista.map((item) => [
        item.fecha_toma_credito || '-',
        this.formatearHora(item.hora_toma_credito),
        item.nombre_completo_cliente || '-',
        item.documento_cliente || '-',
        item.telefono_cliente || item.telefono2_cliente || '-',
        item.direccion_cliente || '-',
        `$${this.formatearMonto(item.monto_credito)}`,
        item.cuotas || '-',
        item.usuario_registro || '-',
        item.nombre_ruta || '-',
      ]);
    } else {
      encabezados = [[
        'Fecha',
        'Hora',
        'Descripción',
        'Monto',
        'Registrado por',
        'Ruta',
      ]];

      filas = this.lista.map((item) => [
        item.fecha_gasto || '-',
        this.formatearHora(item.hora_gasto),
        item.descripcion || '-',
        `$${this.formatearMonto(item.monto)}`,
        item.usuario_registro || '-',
        item.nombre_ruta || '-',
      ]);
    }

    autoTable(documento, {
      head: encabezados,
      body: filas,
      startY: 40,
      theme: 'grid',
      styles: {
        fontSize: 7,
        cellPadding: 2,
        overflow: 'linebreak',
        valign: 'middle',
      },
      headStyles: {
        fillColor: [6, 102, 153],
        textColor: [255, 255, 255],
        fontStyle: 'bold',
      },
      alternateRowStyles: {
        fillColor: [248, 248, 236],
      },
      margin: {
        left: 8,
        right: 8,
      },
      didDrawPage: (data) => {
        const numeroPagina = documento.getNumberOfPages();

        documento.setFontSize(8);
        documento.text(
          `Página ${numeroPagina}`,
          documento.internal.pageSize.getWidth() - 25,
          documento.internal.pageSize.getHeight() - 7
        );
      },
    });

    documento.save(
      `${this.tipoInforme}_${this.fechaDesde}_${this.fechaHasta}.pdf`
    );
  } catch (error) {
    console.error('Error al generar PDF:', error);
    this.error = 'No fue posible generar el archivo PDF.';
  }
}
}

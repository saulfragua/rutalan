import { Component, OnInit, Inject, PLATFORM_ID } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { RefinanciarService } from '../../servicios/refinanciar';
import { CajaService } from '../../servicios/caja';
import { isPlatformBrowser } from '@angular/common';

@Component({
  selector: 'app-refinanciar',
  standalone: false,
  templateUrl: './refinanciar.html',
  styleUrl: './refinanciar.css',
})
export class Refinanciar implements OnInit {

  isBrowser: boolean = false;

  // Parámetros de la URL
  idCliente: number | null = null;
  idCredito: number | null = null;
  saldo: number = 0;
  idRuta: number | null = null;
  indice: number = 0;

  // Datos del crédito y cliente
  credito: any = null;
  cliente: any = null;

  // Formulario
  nuevoMonto: number = 0;
  cuotas: number = 31;
  frecuenciaPago: string = 'diario';
  tipoRefinanciacion: string = 'descontar';
  incluirSeguro: boolean = true;

  // Resumen calculado
  seguro: number = 0;
  intereses: number = 0;
  montoEntregar: number = 0;
  totalPagar: number = 0;
  fechaFinalizacion: string = '';

  // Usuario y caja
  usuarioActual: any = null;
  idCaja: number | null = null;

  // Estado
  procesando: boolean = false;

  constructor(
    private router: Router,
    private route: ActivatedRoute,
    private refinanciarService: RefinanciarService,
    private cajaService: CajaService,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
  }

  ngOnInit() {
    this.cargarDatos();
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
      this.idCliente = params['id_cliente'] ? parseInt(params['id_cliente']) : null;
      this.idCredito = params['id_credito'] ? parseInt(params['id_credito']) : null;
      this.saldo = params['saldo'] ? parseFloat(params['saldo']) : 0;
      this.idRuta = params['ruta'] ? parseInt(params['ruta']) : null;
      this.indice = params['indice'] ? parseInt(params['indice']) : 0;

      if (!this.idCliente || !this.idCredito) {
        alert('Parámetros incompletos para refinanciar');
        this.router.navigate(['/cobros']);
        return;
      }

      this.cargarUsuario();
      this.cargarCredito();
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
   * Verifica si el cobrador tiene caja abierta
   */
  verificarCajaAbierta() {
    if (!this.usuarioActual || this.usuarioActual.rol !== 'cobrador') {
      return;
    }

    this.cajaService.obtenerCajaAbierta(this.usuarioActual.id_usuario).subscribe({
      next: (caja: any) => {
        if (caja) {
          this.idCaja = caja.id_caja;
        }
      },
      error: (error) => {
        console.error('Error al verificar caja:', error);
      }
    });
  }

  /**
   * Carga los datos del crédito
   */
  cargarCredito() {
    if (!this.idCredito) return;

    this.refinanciarService.consultarCreditoPorId(this.idCredito).subscribe({
      next: (resp: any) => {
        // Verificar si es un error
        if (resp && resp.resultado === 'error') {
          alert(resp.mensaje || 'No se encontró el crédito');
          this.router.navigate(['/cobros']);
          return;
        }

        // Verificar si tiene los datos del crédito
        if (resp && resp.id_credito) {
          this.credito = resp;
          this.cuotas = this.credito.cuotas || 31;
          this.frecuenciaPago = this.credito.frecuencia_pago || 'diario';
          this.saldo = parseFloat(this.credito.saldo_actual) || 0;
          this.calcularResumen();
        } else {
          alert('No se encontró el crédito');
          this.router.navigate(['/cobros']);
        }
      },
      error: (error) => {
        console.error('Error al cargar crédito:', error);
        const mensajeError = error?.error?.mensaje || error?.message || 'Error al cargar los datos del crédito';
        alert(mensajeError);
        this.router.navigate(['/cobros']);
      }
    });
  }

  /**
   * Calcula el resumen de la refinanciación
   */
  calcularResumen() {
    // Asegurar que las cuotas se traten siempre como número
    const cuotasNumero = Number(this.cuotas);

    // Calcular seguro
    this.seguro = 0;
    if (this.incluirSeguro && this.nuevoMonto > 0) {
      // Regla de negocio: el seguro en refinanciacion se calcula solo sobre el nuevo monto.
      const baseSeguro = this.nuevoMonto;
      this.seguro = (Math.floor((baseSeguro - 1) / 100) + 1) * 5;
      if (cuotasNumero === 70) {
        this.seguro *= 2;
      }
    }

    // Calcular tasa de interés
    const tasaInteres = (cuotasNumero === 70) ? 48 : 24;
    this.intereses = this.nuevoMonto * (tasaInteres / 100);

    // Calcular montos según tipo de refinanciación
    if (this.tipoRefinanciacion === 'descontar') {
      this.montoEntregar = this.nuevoMonto - this.saldo - this.seguro;
      this.totalPagar = this.nuevoMonto + this.intereses;
    } else {
      this.montoEntregar = this.nuevoMonto - this.seguro;
      this.totalPagar = this.nuevoMonto + this.intereses + this.saldo;
    }

    // Calcular fecha finalización
    const fechaFinalizacion = new Date();
    fechaFinalizacion.setDate(fechaFinalizacion.getDate() + cuotasNumero);
    this.fechaFinalizacion = fechaFinalizacion.toLocaleDateString('es-CO', { 
      timeZone: 'America/Bogota',
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    });
  }

  /**
   * Valida el formulario antes de enviar
   */
  validarFormulario(): boolean {
    const cuotasNumero = Number(this.cuotas);
    if (this.nuevoMonto <= 0) {
      alert('El monto del crédito debe ser mayor a 0');
      return false;
    }

    if ((cuotasNumero === 40 || cuotasNumero === 70) && this.frecuenciaPago === 'mensual') {
      alert('Las cuotas de 40 y 70 días no pueden tener frecuencia mensual');
      return false;
    }

    if (this.tipoRefinanciacion === 'descontar') {
      if (this.nuevoMonto <= (this.saldo + this.seguro)) {
        alert('El monto debe ser mayor al saldo pendiente más el seguro');
        return false;
      }
    }

    return true;
  }

  /**
   * Procesa la refinanciación
   */
  refinanciar() {
    if (!this.validarFormulario()) {
      return;
    }

    if (!this.usuarioActual || !this.idCredito) {
      alert('Error: No hay información del usuario o crédito');
      return;
    }

    this.procesando = true;

    const datos = {
      id_credito_anterior: this.idCredito,
      id_cliente: this.idCliente,
      saldo_pendiente: this.saldo,
      nuevo_monto: this.nuevoMonto,
      cuotas: this.cuotas,
      frecuencia_pago: this.frecuenciaPago,
      tipo_refinanciacion: this.tipoRefinanciacion,
      incluir_seguro: this.incluirSeguro,
      id_usuario: this.usuarioActual.id_usuario,
      id_ruta: this.idRuta,
      id_caja: this.idCaja
    };

    this.refinanciarService.refinanciar(datos).subscribe({
      next: (resp: any) => {
        this.procesando = false;
        if (resp && resp.resultado === 'ok') {
          alert('Refinanciación completada exitosamente');
          // Redirigir a gestión de pago
          this.router.navigate(['/gestion-pago'], {
            queryParams: {
              ruta: this.idRuta,
              indice: this.indice
            }
          });
        } else {
          alert(resp?.mensaje || 'Error al refinanciar el crédito');
        }
      },
      error: (error) => {
        this.procesando = false;
        console.error('Error al refinanciar:', error);
        const mensajeError = error?.error?.mensaje || error?.message || 'Error al refinanciar el crédito';
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
}

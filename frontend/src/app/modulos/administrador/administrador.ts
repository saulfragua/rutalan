import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID, ChangeDetectorRef } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { forkJoin, Subscription } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { Rutas as RutasService } from '../../servicios/rutas';
import { Usuarios as UsuariosService } from '../../servicios/usuarios';
import { UsuarioRutaService as UsuarioRutaService } from '../../servicios/usuarioruta';
import { ClavesCobradorService } from '../../servicios/claves-cobrador';
import { isPlatformBrowser } from '@angular/common';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-administrador',
  standalone: false,
  templateUrl: './administrador.html',
  styleUrl: './administrador.css',
})
export class Administrador implements OnInit, OnDestroy {

  /* =========================
     ESTADOS / VARIABLES
  ========================== */
  modalEditarUsuario = false;

  modalEditar = false;
  usuarioEdit: any = {};
  nuevaClave: string = '';
  confirmarNuevaClave: string = '';
  // Asignar Rutas

  modalAsignarRuta = false;
  usuarioSeleccionado: any = null;

  rutasAsignadas: number[] = [];
  rutasSeleccionadas: number[] = [];

  rutasPorUsuario: { [key: number]: string[] } = {};

  // rutas y usuarios
  rutas: any[] = [];
  usuarios: any[] = [];

  tabActiva: string = 'usuarios';

  // WhatsApp
  whatsappConectado: boolean = false;
  qrCodeDataUrl: string = '';
  cargandoQR: boolean = false;
  reiniciandoServicio: boolean = false;
  intervaloQR?: any;
  mensajeEstadoWhatsapp: string = '';
  timeoutReinicio?: any;

  // Errores
  errores: any[] = [];
  cargandoErrores: boolean = false;
  totalErrores: number = 0;
  offsetErrores: number = 0;
  limiteErrores: number = 100;
  archivoLog: string = '';
  mensajeError: string = '';
  Math = Math; // Para usar Math en el template

  // Usuario (formulario)
  usuario: any = {
    nombre_completo: '',
    nombre_usuario: '',
    rol: '',
    clave: '',
    estado: '',
    email: '',
    id_ruta: null
  };
  confirmarClave: string = '';
  verFormularioUsuario: boolean = false;

  // Mostrar/ocultar contraseña
  mostrarClave: boolean = false;
  mostrarConfirmarClave: boolean = false;

  // Mostrar/ocultar contraseña (edición de usuario)
  mostrarNuevaClave: boolean = false;
  mostrarConfirmarNuevaClave: boolean = false;

  // Rutas
  modalRuta: boolean = false;
  nombreRuta: string = '';

  modalEditarRuta: boolean = false;
  idRutaEditar: number = 0;
  nombreRutaEditar: string = '';

  // Modal de clave dinámica
  modalClaveDinamica: boolean = false;
  claveGenerada: string = '';
  fechaVigencia: string = '';
  usuarioClave: any = null;

  private routerSubscription?: Subscription;
  private isBrowser: boolean;


  // PaginaciónUsuarios
  paginaActualUsuarios: number = 1;
  usuariosPorPagina: number = 5;
  totalPaginasUsuarios: number = 0;
  usuariosPaginados: any[] = [];
  opcionesPorPaginaUsuarios: number[] = [5, 10, 25, 50, 100];
  MathPagina = Math;

  // PaginaciónRuta
  paginaActualRutas: number = 1;
  rutasPorPagina: number = 5;
  totalPaginasRutas: number = 0;
  rutasPaginadas: any[] = [];
  opcionesPorPaginaRutas: number[] = [5, 10, 25, 50, 100];
  MathPaginaRutas = Math;

  // PaginaciónErrores
  paginaActualErrores: number = 1;
  erroresPorPagina: number = 5;
  totalPaginasErrores: number = 0;
  erroresPaginados: any[] = [];
  opcionesPorPaginaErrores: number[] = [5, 10, 25, 50, 100];
  MathPaginaErrores = Math;

  /* =========================
     CONSTRUCTOR
  ========================== */

  constructor(
    private rutasService: RutasService,
    private usuariosService: UsuariosService,
    private usuarioRutaService: UsuarioRutaService,
    private clavesCobradorService: ClavesCobradorService,
    private router: Router,
    private http: HttpClient,
    private cdr: ChangeDetectorRef,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    // Suscribirse a los eventos de navegación para recargar datos cada vez que se accede al módulo
    this.routerSubscription = this.router.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe((event: any) => {
        if (event.url === '/administrador' || event.urlAfterRedirects === '/administrador') {
          this.cargarUsuarios();
          this.listarUsuarios();
          this.cargarRutas();
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

    this.cargarUsuarios();
    this.listarUsuarios();
    this.cargarRutas();
  }

  ngOnDestroy() {
    if (this.intervaloQR) {
      clearInterval(this.intervaloQR);
    }
    if (this.timeoutReinicio) {
      clearTimeout(this.timeoutReinicio);
    }
    if (this.routerSubscription) {
      this.routerSubscription.unsubscribe();
    }
  }

  /* =========================
     CONTROL DE PESTAÑAS
  ========================== */

  listarUsuarios() {
    this.usuariosService.consultar().subscribe({
      next: (data: any) => {
        this.usuarios = data;
      },
      error: () => {
        console.error('Error al listar usuarios');
      }
    });
  }

  abrirTab(tab: string) {
    this.tabActiva = tab;

    if (tab === 'whatsapp') {
      // Verificar estado primero para mostrar correctamente si está conectado
      this.verificarEstadoWhatsApp();
      // Luego obtener QR si es necesario después de un breve delay
      setTimeout(() => {
        // Solo obtener QR si no está conectado
        if (!this.whatsappConectado) {
          this.obtenerQRWhatsApp();
        }
      }, 1000);
    }

    if (tab === 'usuarios') {
      this.cargarUsuarios();
    }

    if (tab === 'rutas') {
      this.cargarRutas();
    }

    if (tab === 'errores') {
      this.cargarErrores();
    }
  }

  abrirTabUsuario(tab: string) {
    this.tabActiva = tab;

    if (tab === 'usuarios') {
      this.cargarUsuarios();
    }
  }

  /* =========================
     WHATSAPP
  ========================== */

  obtenerQRWhatsApp() {
    this.cargandoQR = true;
    this.qrCodeDataUrl = '';

    // Primero verificar el estado del servicio
    fetch(`${environment.whatsappApiUrl}/api/status`)
      .then(response => {
        // Si el servicio no está disponible (404 o cualquier error), manejar apropiadamente
        if (!response.ok) {
          // Si es 404, el servicio no existe o no está corriendo
          if (response.status === 404) {
            throw new Error('SERVICIO_NO_DISPONIBLE');
          }
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then(statusData => {
        console.log('Estado del servicio WhatsApp:', statusData);

        // Si WhatsApp está deshabilitado en desarrollo, mostrar mensaje informativo
        if (statusData.disabled) {
          this.cargandoQR = false;
          alert(`⚠️ WhatsApp está deshabilitado en modo desarrollo.\n\nPara habilitarlo:\n1. Establece la variable de entorno: ENABLE_WHATSAPP=true\n2. Reinicia el servicio de WhatsApp`);
          return;
        }

        // Si el servicio no está disponible o no tiene cliente, inicializar
        if (!statusData.clientExists) {
          console.log('Cliente no existe, inicializando...');
          // Intentar obtener QR de todas formas, el servidor debería inicializar el cliente
        }

        // Si ya está conectado, no necesitamos QR
        if (statusData.ready) {
          this.whatsappConectado = true;
          this.qrCodeDataUrl = '';
          this.cargandoQR = false;
          this.cdr.detectChanges();
          return;
        }

        // Si hay QR disponible o el servicio está disponible, intentar obtenerlo
        return fetch(`${environment.whatsappApiUrl}/api/qr`);
      })
      .then(response => {
        if (!response) return; // Ya se manejó en el paso anterior

        // Manejar 404 específicamente para el endpoint QR
        if (!response.ok) {
          if (response.status === 404) {
            throw new Error('SERVICIO_NO_DISPONIBLE');
          }
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then(data => {
        this.cargandoQR = false;

        // Si WhatsApp está deshabilitado
        if (data && data.disabled) {
          alert(`⚠️ WhatsApp está deshabilitado en modo desarrollo.\n\nPara habilitarlo:\n1. Establece la variable de entorno: ENABLE_WHATSAPP=true\n2. Reinicia el servicio de WhatsApp`);
          return;
        }

        if (data && data.success && data.qr) {
          console.log('📲 QR obtenido correctamente');
          this.qrCodeDataUrl = data.qr;
          this.whatsappConectado = false;
          this.cdr.detectChanges();
          // Iniciar verificación automática para detectar cuando se escanea el QR
          this.iniciarVerificacionEstado();
          return;
        }

        if (data && data.ready) {
          console.log('✅ WhatsApp ya está conectado');
          this.whatsappConectado = true;
          this.qrCodeDataUrl = '';
          this.cargandoQR = false;
          this.cdr.detectChanges();
          // Detener verificación si está activa
          if (this.intervaloQR) {
            clearInterval(this.intervaloQR);
            this.intervaloQR = undefined;
          }
          return;
        }

        // Si no hay QR pero el servicio está disponible, reintentar después
        if (data && !data.qr && !data.ready) {
          console.log('⏳ Esperando código QR...');
          // Iniciar verificación para detectar cuando se genere el QR
          if (!this.intervaloQR) {
            this.iniciarVerificacionEstado();
          }
          // Reintentar obtener QR después de un tiempo
          setTimeout(() => {
            if (!this.whatsappConectado && !this.qrCodeDataUrl) {
              this.obtenerQRWhatsApp();
            }
          }, 3000);
        }
      })
      .catch(error => {
        console.error('Error al obtener QR:', error);
        this.cargandoQR = false;
        this.whatsappConectado = false;
        this.cdr.detectChanges();

        // Mensaje de error más descriptivo y específico
        let mensajeError = '';

        // Si el servicio no está disponible (404 o error de conexión)
        if (error.message === 'SERVICIO_NO_DISPONIBLE' ||
          error.message.includes('Failed to fetch') ||
          error.message.includes('NetworkError') ||
          error.message.includes('404')) {
          mensajeError = `⚠️ El servicio de WhatsApp no está disponible.\n\n`;
          mensajeError += `URL del servicio: ${environment.whatsappApiUrl}\n\n`;
          mensajeError += `Para solucionar esto:\n`;
          mensajeError += `1. Verifica que el servicio esté corriendo:\n`;
          mensajeError += `   pm2 status rutalan-whatsapp\n\n`;
          mensajeError += `2. Si no está corriendo, inícialo:\n`;
          mensajeError += `   pm2 start rutalan-whatsapp\n\n`;
          mensajeError += `3. Verifica que el puerto 3000 esté disponible:\n`;
          mensajeError += `   netstat -an | findstr :3000\n\n`;
          mensajeError += `4. Prueba acceder manualmente a:\n`;
          mensajeError += `   ${environment.whatsappApiUrl}/status`;
        } else {
          // Otros errores
          mensajeError = `Error al conectar con el servicio de WhatsApp.\n\n`;
          mensajeError += `URL intentada: ${environment.whatsappApiUrl}\n\n`;
          mensajeError += `Error: ${error.message}`;
        }

        // Solo mostrar alert si el usuario está en la pestaña de WhatsApp
        if (this.tabActiva === 'whatsapp') {
          alert(mensajeError);
        }
      });
  }

  iniciarVerificacionEstado() {
    // Detener verificación anterior si existe
    if (this.intervaloQR) {
      clearInterval(this.intervaloQR);
      this.intervaloQR = undefined;
    }

    console.log('🔄 Iniciando verificación automática de estado...');

    // Verificar inmediatamente
    this.verificarEstadoWhatsApp();

    // Luego verificar cada 3 segundos (más frecuente para detectar conexión más rápido)
    this.intervaloQR = setInterval(() => {
      this.verificarEstadoWhatsApp();
    }, 3000);
  }

  verificarEstadoWhatsApp() {
    fetch(`${environment.whatsappApiUrl}/api/status`)
      .then(response => {
        if (!response.ok) {
          if (response.status === 404) {
            console.warn('Servicio de WhatsApp no disponible');
            if (this.intervaloQR) {
              clearInterval(this.intervaloQR);
              this.intervaloQR = undefined;
            }
            this.whatsappConectado = false;
            this.qrCodeDataUrl = '';
            this.finalizarReinicio('El servicio de WhatsApp no responde.');
            return null;
          }
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then(data => {
        if (!data) return;

        console.log('📊 Estado verificado:', {
          ready: data.ready,
          hasQR: data.hasQR,
          clientExists: data.clientExists,
          clientState: data.clientState || 'no disponible'
        });

        // Solo 'ready' confirma que WhatsApp terminó de sincronizar
        if (data.ready) {
          console.log('✅ WhatsApp está conectado!');
          this.whatsappConectado = true;
          this.qrCodeDataUrl = '';
          this.finalizarReinicio('');
          if (this.intervaloQR) {
            clearInterval(this.intervaloQR);
            this.intervaloQR = undefined;
          }
          return;
        }

        this.whatsappConectado = false;

        // Hay QR disponible: el reinicio terminó su parte pesada
        if (data.hasQR) {
          if (!this.qrCodeDataUrl && !this.cargandoQR) {
            console.log('📲 Hay QR disponible, obteniéndolo...');
            this.obtenerQRWhatsApp();
          }
          if (this.reiniciandoServicio) {
            this.finalizarReinicio('Código QR listo. Escanéalo con tu teléfono.');
          } else {
            this.mensajeEstadoWhatsapp = 'Código QR listo. Escanéalo con tu teléfono.';
          }
          this.cdr.detectChanges();
          return;
        }

        // Sin QR todavía: reportar en qué va el arranque
        if (this.reiniciandoServicio || this.cargandoQR) {
          switch (data.clientState) {
            case 'UNLAUNCHED':
              this.mensajeEstadoWhatsapp = 'Abriendo el navegador interno...';
              break;
            case 'PAIRING':
            case 'UNPAIRED':
              this.mensajeEstadoWhatsapp = 'Generando el código QR...';
              break;
            case 'OPENING':
            case 'CONNECTING':
              this.mensajeEstadoWhatsapp = 'Conectando con WhatsApp...';
              break;
            case 'CONNECTED':
              this.mensajeEstadoWhatsapp = 'Sincronizando conversaciones, ya casi...';
              break;
            default:
              this.mensajeEstadoWhatsapp = data.clientExists
                ? 'Iniciando el servicio de WhatsApp...'
                : 'Esperando al servicio de WhatsApp...';
          }
        }
        this.cdr.detectChanges();
      })
      .catch(error => {
        console.error('Error al verificar estado:', error);
        if (error.message && error.message.includes('404')) {
          if (this.intervaloQR) {
            clearInterval(this.intervaloQR);
            this.intervaloQR = undefined;
          }
          this.whatsappConectado = false;
          this.qrCodeDataUrl = '';
          this.finalizarReinicio('');
        }
      });
  }

  reiniciarSesionWhatsApp() {
    if (!confirm('¿Está seguro de que desea reiniciar la sesión de WhatsApp?\n\nEsto destruirá la sesión actual y deberá escanear el código QR nuevamente.')) {
      return;
    }

    console.log('🔄 Iniciando reinicio de sesión de WhatsApp...');

    if (this.intervaloQR) {
      clearInterval(this.intervaloQR);
      this.intervaloQR = undefined;
    }

    // Bloquear botones y mostrar estado
    this.reiniciandoServicio = true;
    this.cargandoQR = true;
    this.qrCodeDataUrl = '';
    this.whatsappConectado = false;
    this.mensajeEstadoWhatsapp = 'Destruyendo la sesión actual...';
    this.cdr.detectChanges();

    fetch(`${environment.whatsappApiUrl}/api/restart`, { method: 'POST' })
      .then(response => {
        if (!response.ok) {
          if (response.status === 404) {
            throw new Error('SERVICIO_NO_DISPONIBLE');
          }
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log('✅ Sesión reiniciada en el servidor:', data);
        this.mensajeEstadoWhatsapp = 'Iniciando WhatsApp, esto puede tardar hasta 2 minutos...';
        this.cdr.detectChanges();

        // El sondeo detecta cuándo aparece el QR y apaga el indicador
        this.iniciarVerificacionEstado();
        this.activarTimeoutReinicio();
      })
      .catch(error => {
        console.error('❌ Error al reiniciar sesión:', error);
        this.finalizarReinicio('');
        this.mostrarErrorReinicio(error, 'la sesión');
      });
  }

  reiniciarServiciosAPI() {
    if (!confirm('¿Está seguro de que desea reiniciar los servicios de la API de WhatsApp?\n\nEsto reiniciará completamente los servicios y deberá escanear el código QR nuevamente.')) {
      return;
    }

    console.log('🔄 Iniciando reinicio de servicios de API de WhatsApp...');

    if (this.intervaloQR) {
      clearInterval(this.intervaloQR);
      this.intervaloQR = undefined;
    }

    this.reiniciandoServicio = true;
    this.cargandoQR = true;
    this.qrCodeDataUrl = '';
    this.whatsappConectado = false;
    this.mensajeEstadoWhatsapp = 'Reiniciando los servicios de la API...';
    this.cdr.detectChanges();

    fetch(`${environment.whatsappApiUrl}/api/restart-service`, { method: 'POST' })
      .then(response => {
        if (!response.ok) {
          if (response.status === 404) {
            throw new Error('SERVICIO_NO_DISPONIBLE');
          }
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log('✅ Servicios reiniciados en el servidor:', data);
        this.mensajeEstadoWhatsapp = 'Iniciando WhatsApp, esto puede tardar hasta 2 minutos...';
        this.cdr.detectChanges();

        this.iniciarVerificacionEstado();
        this.activarTimeoutReinicio();
      })
      .catch(error => {
        console.error('❌ Error al reiniciar servicios:', error);
        this.finalizarReinicio('');
        this.mostrarErrorReinicio(error, 'los servicios');
      });
  }

  /** Apaga los indicadores de carga y fija el mensaje final */
  private finalizarReinicio(mensaje: string) {
    this.reiniciandoServicio = false;
    this.cargandoQR = false;
    this.mensajeEstadoWhatsapp = mensaje;
    if (this.timeoutReinicio) {
      clearTimeout(this.timeoutReinicio);
      this.timeoutReinicio = undefined;
    }
    this.cdr.detectChanges();
  }

  /** Red de seguridad: si en 60s no pasa nada, libera los botones */
  private activarTimeoutReinicio() {
    if (this.timeoutReinicio) {
      clearTimeout(this.timeoutReinicio);
    }
    this.timeoutReinicio = setTimeout(() => {
      if (this.reiniciandoServicio) {
        console.warn('⚠️ El reinicio superó el tiempo esperado');
        this.finalizarReinicio('El reinicio está tardando más de lo normal. Revisa los logs del servidor o intenta de nuevo.');
      }
    }, 240000);
  }

  private mostrarErrorReinicio(error: any, que: string) {
    let mensajeError = '';
    if (error.message === 'SERVICIO_NO_DISPONIBLE' ||
      error.message.includes('Failed to fetch') ||
      error.message.includes('404')) {
      mensajeError = 'El servicio de WhatsApp no está disponible.\n\n';
      mensajeError += `Verifica que el servicio esté corriendo en: ${environment.whatsappApiUrl}\n\n`;
      mensajeError += `Para iniciar el servicio:\n`;
      mensajeError += `1. Ve a la carpeta whatsapp-api\n`;
      mensajeError += `2. Ejecuta: $env:ENABLE_WHATSAPP="true"; node server.js`;
    } else if (error.message.includes('500') || error.message.includes('Internal Server Error')) {
      mensajeError = `Error interno del servidor al reiniciar ${que}.\n\n`;
      mensajeError += `Revisa los logs del servidor de WhatsApp. `;
      mensajeError += `Si el problema persiste, detén el servicio y elimina manualmente la carpeta .wwebjs_auth`;
    } else {
      mensajeError = `No se pudo reiniciar ${que}.\n\nError: ${error.message}`;
    }
    alert(mensajeError);
  }

  /* =========================
     USUARIOS
  ========================== */

  mostrarFormulario() {
    this.verFormularioUsuario = true;
    // Asegurar que todas las rutas estén cargadas cuando se abre el formulario
    if (this.rutas.length === 0) {
      this.cargarRutas();
      this.actualizarPaginacion(); // Actualizar paginación después de cargar rutas
    }
  }

  cancelarFormularioUsuarios() {
    this.verFormularioUsuario = false;
    this.limpiarFormulario();
    this.actualizarPaginacion();
  }

  guardarUsuario() {

    if (this.usuario.clave !== this.confirmarClave) {
      alert('Las contraseñas no coinciden');
      return;
    }

    // Si es cobrador y tiene id_ruta, guardar primero el usuario y luego asignar la ruta
    if (this.usuario.rol === 'cobrador' && this.usuario.id_ruta) {
      this.usuariosService.insertar(this.usuario).subscribe({
        next: (resp: any) => {
          if (resp.resultado === 'ok' || resp.estado === 'ok') {
            // Asignar la ruta al usuario recién creado
            const idUsuario = resp.id_usuario || resp.usuario?.id_usuario;
            if (idUsuario) {
              const datosAsignacion = {
                id_usuario: idUsuario,
                id_ruta: this.usuario.id_ruta
              };

              this.usuarioRutaService.insertar(datosAsignacion).subscribe({
                next: (respRuta: any) => {
                  alert(resp.mensaje + '. Ruta asignada correctamente.');
                  this.limpiarFormulario();
                  this.cargarUsuarios();
                  this.cdr.detectChanges();
                },
                error: (errorRuta: any) => {
                  console.error('Error al asignar ruta:', errorRuta);
                  alert(resp.mensaje + '. Pero hubo un error al asignar la ruta.');
                  this.limpiarFormulario();
                  this.cargarUsuarios();
                  this.cdr.detectChanges();
                }
              });
            } else {
              alert(resp.mensaje);
              this.limpiarFormulario();
              this.cargarUsuarios();
            }
          } else {
            alert(resp.mensaje || 'Error al guardar el usuario');
          }
        },
        error: (error) => {
          console.error('Error al guardar usuario', error);
          alert('Error al guardar el usuario');
        }
      });
    } else {
      // Si no es cobrador o no tiene ruta, guardar normalmente
      this.usuariosService.insertar(this.usuario).subscribe({
        next: (resp: any) => {
          alert(resp.mensaje);
          this.limpiarFormulario();
          this.cargarUsuarios();
          this.actualizarPaginacion();
        },
        error: (error) => {
          console.error('Error al guardar usuario', error);
          this.actualizarPaginacion();
          alert('Error al guardar el usuario');
        }
      });
    }
  }

  cargarUsuarios() {
    this.usuariosService.consultar().subscribe({
      next: (usuarios: any[]) => {
        this.usuarios = usuarios;

        // 🔹 Por cada usuario, cargar sus rutas
        usuarios.forEach(u => {
          this.usuarioRutaService
            .rutasPorUsuario(u.id_usuario)
            .subscribe({
              next: (rutas: any[]) => {
                this.rutasPorUsuario[u.id_usuario] =
                  rutas.map(r => r.nombre_ruta);
                this.cdr.detectChanges();
              }
            });
        });
        this.actualizarPaginacion();
      },
      error: (error) => {
        console.error('Error al cargar usuarios', error);
        this.actualizarPaginacion(); // Asegurar que la paginación se actualice incluso si hay error
      }
    });
  }



  cancelarFormulario() {
    this.limpiarFormulario();

  }

  limpiarFormulario() {
    this.usuario = {
      nombre_completo: '',
      nombre_usuario: '',
      rol: '',
      clave: '',
      id_ruta: null,
      estado: 'activo',
      email: ''
    };
    this.confirmarClave = '';
    // Asegurar que las rutas estén cargadas cuando se abre el formulario
    if (this.rutas.length === 0) {
      this.cargarRutas();
    }
  }

  // Mostrar/ocultar contraseña

  toggleMostrarClave() {
    this.mostrarClave = !this.mostrarClave;
  }

  toggleMostrarConfirmarClave() {
    this.mostrarConfirmarClave = !this.mostrarConfirmarClave;
  }

  // Mostrar/ocultar contraseña (edición de usuario)

  toggleMostrarNuevaClave() {
    this.mostrarNuevaClave = !this.mostrarNuevaClave;
  }

  toggleMostrarConfirmarNuevaClave() {
    this.mostrarConfirmarNuevaClave = !this.mostrarConfirmarNuevaClave;
  }

  actualizarPaginacion() {
    this.totalPaginasUsuarios = Math.ceil(this.usuarios.length / this.usuariosPorPagina);
    if (this.paginaActualUsuarios > this.totalPaginasUsuarios) this.paginaActualUsuarios = 1;
    const inicio = (this.paginaActualUsuarios - 1) * this.usuariosPorPagina;
    const fin = inicio + this.usuariosPorPagina;
    this.usuariosPaginados = this.usuarios.slice(inicio, fin);
    this.cdr.detectChanges();
  }

  cambiarPagina(pagina: number) {
    if (pagina < 1 || pagina > this.totalPaginasUsuarios) return;
    this.paginaActualUsuarios = pagina;
    this.actualizarPaginacion();
  }

  cambiarPorPagina(cantidad: number) {
    this.usuariosPorPagina = Number(cantidad);
    this.paginaActualUsuarios = 1;
    this.actualizarPaginacion();
  }

  getPaginas(): number[] {
    const paginas: number[] = [];
    const rango = 2;
    for (let i = Math.max(1, this.paginaActualUsuarios - rango);
      i <= Math.min(this.totalPaginasUsuarios, this.paginaActualUsuarios + rango); i++) {
      paginas.push(i);
    }
    return paginas;
  }

  /* =========================
     RUTAS
  ========================== */

  abrirModalRuta() {
    this.modalRuta = true;
    this.actualizarPaginacionRutas(); // Asegurar que la paginación se actualice al abrir el modal 
  }

  cerrarModalRuta() {
    this.modalRuta = false;
    this.nombreRuta = '';
    this.actualizarPaginacionRutas();
  }

  cargarRutas() {
    this.rutasService.consultar().subscribe({
      next: (resp: any) => {
        this.rutas = resp;
        this.actualizarPaginacionRutas();
      },
      error: (error) => {
        console.error('Error al cargar rutas', error);
        this.actualizarPaginacionRutas(); // Asegurar que la paginación se actualice incluso si hay error
      }
    });
  }

  actualizarPaginacionRutas() {
    this.totalPaginasRutas = Math.ceil(this.rutas.length / this.rutasPorPagina);
    if (this.paginaActualRutas > this.totalPaginasRutas) this.paginaActualRutas = 1;
    const inicio = (this.paginaActualRutas - 1) * this.rutasPorPagina;
    const fin = inicio + this.rutasPorPagina;
    this.rutasPaginadas = this.rutas.slice(inicio, fin);
    this.cdr.detectChanges();
  }

  cambiarPaginaRutas(pagina: number) {
    if (pagina < 1 || pagina > this.totalPaginasRutas) return;
    this.paginaActualRutas = pagina;
    this.actualizarPaginacionRutas();
  }

  cambiarPorPaginaRutas(cantidad: number) {
    this.rutasPorPagina = Number(cantidad);
    this.paginaActualRutas = 1;
    this.actualizarPaginacionRutas();
  }

  getPaginasRutas(): number[] {
    const paginas: number[] = [];
    const rango = 2;
    for (let i = Math.max(1, this.paginaActualRutas - rango);
      i <= Math.min(this.totalPaginasRutas, this.paginaActualRutas + rango); i++) {
      paginas.push(i);
    }
    return paginas;
  }

  guardarRuta() {

    if (this.nombreRuta.trim() === '') {
      alert('El nombre de la ruta es obligatorio');
      return;
    }

    const datos = { nombre: this.nombreRuta };

    this.rutasService.insertar(datos).subscribe({
      next: (resp: any) => {
        alert(resp.mensaje);
        this.cerrarModalRuta();
        this.cargarRutas();
        this.actualizarPaginacionRutas();
      },
      error: (error) => {
        console.error('Error al insertar la ruta', error);
        this.actualizarPaginacionRutas();
        alert('Error al guardar la ruta');
      }
    });
  }

  eliminarRuta(id: number) {

    if (!confirm('¿Está seguro de eliminar esta ruta?')) {
      return;
    }

    this.rutasService.eliminar(id).subscribe({
      next: (resp: any) => {
        alert(resp.mensaje);
        this.cargarRutas();
        this.actualizarPaginacionRutas();
      },
      error: (error) => {
        console.error('Error al eliminar ruta', error);
        this.actualizarPaginacionRutas();
        alert('Error al eliminar la ruta');
      }
    });
  }

  cambiarEstadoRuta(id: number, estadoActual: number) {

    const nuevoEstado = estadoActual == 1 ? 0 : 1;
    const accion = nuevoEstado === 1 ? 'activar' : 'inactivar';

    if (!confirm(`¿Está seguro de ${accion} esta ruta?`)) {
      return;
    }

    this.rutasService.activarInactivar(id, nuevoEstado).subscribe({
      next: (resp: any) => {
        alert(resp.mensaje);
        this.cargarRutas();
        this.actualizarPaginacionRutas();
      },
      error: (error) => {
        console.error('Error al cambiar estado', error);
        this.actualizarPaginacionRutas();
        alert('Error al cambiar el estado de la ruta');
      }
    });
  }

  cambiarEstadoUsuario(id: number, estadoActual: number) {

    const nuevoEstado = estadoActual === 1 ? 0 : 1;
    const accion = nuevoEstado === 1 ? 'activar' : 'inactivar';

    if (!confirm(`¿Desea ${accion} este usuario?`)) {
      return;
    }

    this.usuariosService.cambiarEstado(id, nuevoEstado).subscribe({
      next: (resp: any) => {
        alert(resp.mensaje);
        this.cargarUsuarios(); // 🔄 refrescar tabla
        this.actualizarPaginacion(); // 🔄 actualizar paginación

      },
      error: (err) => {
        console.error('Error al cambiar estado', err);
        this.actualizarPaginacion(); // 🔄 actualizar paginación incluso si hay error
        alert('No se pudo cambiar el estado del usuario');
      }
    });
  }






  abrirModalEditar(ruta: any) {
    this.idRutaEditar = ruta.id_ruta;
    this.nombreRutaEditar = ruta.nombre_ruta;
    this.modalEditar = true;
  }

  cerrarModalEditar() {
    this.modalEditar = false;
    this.idRutaEditar = 0;
    this.nombreRutaEditar = '';
  }

  editarRuta() {

    if (this.nombreRutaEditar.trim() === '') {
      alert('El nombre de la ruta es obligatorio');
      return;
    }

    const datos = { nombre: this.nombreRutaEditar };

    this.rutasService.editar(this.idRutaEditar, datos).subscribe({
      next: (resp: any) => {
        alert(resp.mensaje);
        this.cerrarModalEditar();
        this.cargarRutas();
        this.actualizarPaginacionRutas();
      },
      error: (error) => {
        console.error('Error al editar ruta', error);
        this.actualizarPaginacionRutas();
        alert('Error al editar la ruta');
      }
    });
  }

  insertar() {

    // 1️⃣ Validar contraseñas
    if (this.usuario.clave !== this.confirmarClave) {
      alert('Las contraseñas no coinciden');
      return;
    }

    // 2️⃣ Validar rol
    if (this.usuario.rol !== 'admin' && this.usuario.rol !== 'cobrador') {
      alert('Rol no permitido');
      return;
    }

    // 3️⃣ Si no es cobrador, id_ruta debe ir NULL
    if (this.usuario.rol !== 'cobrador') {
      this.usuario.id_ruta = null;
    }

    // 4️⃣ Enviar al backend
    this.usuariosService.insertar(this.usuario).subscribe({
      next: (resp: any) => {
        alert(resp.mensaje);
        this.cancelarFormularioUsuarios();
        this.cargarUsuarios(); // refresca la tabla
        this.actualizarPaginacion(); // 🔄 actualizar paginación
      },
      error: (error) => {
        console.error('Error al guardar usuario', error);
        this.actualizarPaginacion(); // 🔄 actualizar paginación incluso si hay error
        alert('Error al guardar el usuario');
      }
    });
  }

  abrirModalAsignarRuta(usuario: any) {
    this.usuarioSeleccionado = usuario;
    this.modalAsignarRuta = true;
    this.cdr.detectChanges();

    // 1. Todas las rutas
    this.rutasService.consultar().subscribe({
      next: (rutas: any) => {
        this.rutas = rutas;
        this.cdr.detectChanges();
      }
    });

    // 2. Rutas ya asignadas
    this.usuarioRutaService.rutasPorUsuario(usuario.id_usuario).subscribe({
      next: (resp: any) => {
        this.rutasAsignadas = resp.map((r: any) => r.id_ruta);

        // Copia para checklist
        this.rutasSeleccionadas = [...this.rutasAsignadas];
        this.cdr.detectChanges();
      }
    });
  }

  cerrarModalAsignarRuta() {
    this.modalAsignarRuta = false;
    this.usuarioSeleccionado = null;
    this.rutasAsignadas = [];
    this.rutasSeleccionadas = [];
    this.cdr.detectChanges();
  }

  toggleRutaSeleccionada(id_ruta: number, checked: boolean) {

    if (checked) {
      if (!this.rutasSeleccionadas.includes(id_ruta)) {
        this.rutasSeleccionadas.push(id_ruta);
        this.cdr.detectChanges();
      }
    } else {
      this.rutasSeleccionadas =
        this.rutasSeleccionadas.filter(r => r !== id_ruta);
      this.cdr.detectChanges();
    }
  }

  guardarAsignacionRutas() {
    const id_usuario = this.usuarioSeleccionado.id_usuario;

    // ➕ Rutas nuevas
    const nuevas = this.rutasSeleccionadas.filter(
      r => !this.rutasAsignadas.includes(r)
    );

    // ➖ Rutas quitadas
    const quitadas = this.rutasAsignadas.filter(
      r => !this.rutasSeleccionadas.includes(r)
    );

    const peticiones = [
      ...nuevas.map(id_ruta => this.usuarioRutaService.asignar({ id_usuario, id_ruta })),
      ...quitadas.map(id_ruta => this.usuarioRutaService.quitar({ id_usuario, id_ruta }))
    ];

    // Sin cambios: solo cerrar
    if (peticiones.length === 0) {
      this.cerrarModalAsignarRuta();
      return;
    }

    // Esperar a que TODAS terminen antes de refrescar la tabla
    forkJoin(peticiones).subscribe({
      next: () => {
        this.refrescarRutasDeUsuario(id_usuario);
        alert('Rutas actualizadas correctamente');
        this.cerrarModalAsignarRuta();
      },
      error: (error) => {
        console.error('Error al actualizar rutas', error);
        // Aunque falle alguna, refrescamos para mostrar el estado real
        this.refrescarRutasDeUsuario(id_usuario);
        alert('Ocurrió un error al actualizar las rutas');
      }
    });
  }

  /** Recarga solo las rutas de un usuario y actualiza la columna de la tabla */
  private refrescarRutasDeUsuario(id_usuario: number) {
    this.usuarioRutaService.rutasPorUsuario(id_usuario).subscribe({
      next: (rutas: any[]) => {
        this.rutasPorUsuario[id_usuario] = rutas.map(r => r.nombre_ruta);
        this.cdr.detectChanges();
      }
    });
  }


  cerrarModal() {
    this.modalEditar = false;
  }

  guardarEdicion() {
    this.usuariosService.editar(this.usuarioEdit.id_usuario, this.usuarioEdit)
      .subscribe({
        next: (resp: any) => {
          alert(resp.resultado);
          this.modalEditar = false;
          this.listarUsuarios(); // recarga tabla
        },
        error: () => {
          alert('Error al editar usuario');
        }
      });
  }

  abrirModalEditarUsuario(usuario: any) {
    this.usuarioEdit = { ...usuario }; // copia segura
    this.nuevaClave = '';
    this.confirmarNuevaClave = '';
    this.modalEditarUsuario = true;
  }

  cerrarModalEditarUsuario() {
    this.modalEditarUsuario = false;
    this.usuarioEdit = {};
    this.nuevaClave = '';
    this.confirmarNuevaClave = '';
    this.mostrarNuevaClave = false;
    this.mostrarConfirmarNuevaClave = false;
  }

  guardarEdicionUsuario() {
    // Validar que las contraseñas coincidan si se proporcionaron
    if (this.nuevaClave || this.confirmarNuevaClave) {
      if (this.nuevaClave !== this.confirmarNuevaClave) {
        alert('Las contraseñas no coinciden');
        return;
      }
      if (this.nuevaClave.trim() === '') {
        alert('La contraseña no puede estar vacía');
        return;
      }
      // Agregar la clave al objeto de edición
      this.usuarioEdit.clave = this.nuevaClave;
    }

    // Preparar datos para enviar (sin incluir la clave si está vacía)
    const datosEdicion = { ...this.usuarioEdit };
    if (!this.nuevaClave || this.nuevaClave.trim() === '') {
      delete datosEdicion.clave;
    }

    this.usuariosService
      .editar(this.usuarioEdit.id_usuario, datosEdicion)
      .subscribe({
        next: (resp: any) => {
          alert(resp.mensaje || resp.resultado);
          this.cerrarModalEditarUsuario();
          this.cargarUsuarios(); // refresca tabla
          this.cdr.detectChanges();
        },
        error: () => {
          alert('Error al editar usuario');
        }
      });
  }

  //  ELIMINAR USUARIO


  eliminarUsuario(id: number) {

    if (!confirm('¿Está seguro de eliminar este usuario?')) {
      return;
    }

    this.usuariosService.eliminar(id).subscribe({
      next: (resp: any) => {
        alert(resp.mensaje || resp.resultado);
        this.cargarUsuarios(); // refresca la tabla
      },
      error: (error) => {
        console.error('Error al eliminar usuario', error);
        alert('No se pudo eliminar el usuario');
      }
    });
  }

  /* =========================
     CLAVE DINÁMICA
  ========================== */

  /**
   * Abre el modal para generar clave dinámica
   * Solo disponible para cobradores
   */
  abrirModalClaveDinamica(usuario: any) {
    if (usuario.rol !== 'cobrador') {
      alert('Las claves dinámicas solo están disponibles para cobradores');
      return;
    }

    this.usuarioClave = usuario;

    // Verificar si ya tiene una clave activa del día
    this.clavesCobradorService.obtenerClaveActiva(usuario.id_usuario).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok' && resp.clave) {
          // Ya tiene una clave activa
          this.claveGenerada = resp.clave.clave;
          this.fechaVigencia = resp.clave.fecha;
          this.modalClaveDinamica = true;
          this.cdr.detectChanges();
        } else {
          // No tiene clave activa, generar una nueva
          this.generarNuevaClave(usuario.id_usuario);
        }
      },
      error: () => {
        // Error al consultar, generar nueva clave
        this.generarNuevaClave(usuario.id_usuario);
        this.cdr.detectChanges();
      }
    });
  }

  /**
   * Genera una nueva clave dinámica
   */
  generarNuevaClave(idUsuario: number) {
    this.clavesCobradorService.generarClave(idUsuario).subscribe({
      next: (resp: any) => {
        if (resp.resultado === 'ok') {
          this.claveGenerada = resp.clave;
          this.fechaVigencia = resp.fecha;
          this.modalClaveDinamica = true;
          this.cdr.detectChanges();
        } else {
          alert(resp.mensaje || 'Error al generar la clave');
        }
      },
      error: (error) => {
        console.error('Error al generar clave:', error);
        alert('Error al generar la clave dinámica');
      }
    });
  }

  /**
   * Cierra el modal de clave dinámica
   */
  cerrarModalClaveDinamica() {
    this.modalClaveDinamica = false;
    this.claveGenerada = '';
    this.fechaVigencia = '';
    this.usuarioClave = null;
    this.cdr.detectChanges();
  }

  /**
   * Copia la clave al portapapeles
   */
  copiarClave() {
    if (this.claveGenerada) {
      navigator.clipboard.writeText(this.claveGenerada).then(() => {
        alert('Clave copiada al portapapeles');
      }).catch(() => {
        alert('No se pudo copiar la clave');
      });
    }
  }

  /* =========================
     ERRORES
  ========================== */

  cargarErrores() {
    this.cargandoErrores = true;
    this.mensajeError = '';
    const url = `${environment.apiUrl}/controllers/erroresControlador.php?control=obtenerErrores&limite=${this.limiteErrores}&offset=${this.offsetErrores}`;

    this.http.get<any>(url).subscribe({
      next: (resp) => {
        this.cargandoErrores = false;
        if (resp.resultado === 'ok') {
          this.errores = resp.errores || [];
          this.totalErrores = resp.total || 0;
          this.archivoLog = resp.archivo_log || '';

          // Mostrar información de debug en consola
          if (resp.debug && resp.debug.length > 0) {
            console.log('Debug - Información del log:', resp.debug);
          }

          // Si no hay errores pero hay mensaje, mostrarlo
          if (this.errores.length === 0 && resp.mensaje) {
            this.mensajeError = resp.mensaje;
          } else {
            this.mensajeError = '';
          }
          this.actualizarPaginacionErrores();
        } else {
          console.error('Error al cargar errores:', resp.mensaje);
          console.error('Debug info:', resp.debug);
          this.mensajeError = resp.mensaje || 'Error desconocido';
          if (resp.debug) {
            this.mensajeError += '\nDebug: ' + resp.debug.join('\n');
          }
          this.errores = [];
          this.totalErrores = 0;
          this.archivoLog = '';
          this.actualizarPaginacionErrores();
        }
      },
      error: (error) => {
        this.cargandoErrores = false;
        console.error('Error HTTP al cargar errores:', error);
        this.mensajeError = 'Error de conexión al cargar errores: ' + (error.message || 'Error desconocido');
        this.errores = [];
        this.totalErrores = 0;
        this.archivoLog = '';
        this.actualizarPaginacionErrores();
      }
    });
  }

  actualizarPaginacionErrores() {
    this.totalPaginasErrores = Math.ceil(this.errores.length / this.erroresPorPagina);
    if (this.paginaActualErrores > this.totalPaginasErrores) this.paginaActualErrores = 1;
    const inicio = (this.paginaActualErrores - 1) * this.erroresPorPagina;
    const fin = inicio + this.erroresPorPagina;
    this.erroresPaginados = this.errores.slice(inicio, fin);
    this.cdr.detectChanges();
  }

  cambiarPaginaErrores(pagina: number) {
    if (pagina < 1 || pagina > this.totalPaginasErrores) return;
    this.paginaActualErrores = pagina;
    this.actualizarPaginacionErrores();
  }

  cambiarPorPaginaErrores(cantidad: number) {
    this.erroresPorPagina = Number(cantidad);
    this.paginaActualErrores = 1;
    this.actualizarPaginacionErrores();
  }

  getPaginasErrores(): number[] {
    const paginas: number[] = [];
    const rango = 2;
    for (let i = Math.max(1, this.paginaActualErrores - rango);
      i <= Math.min(this.totalPaginasErrores, this.paginaActualErrores + rango); i++) {
      paginas.push(i);
    }
    return paginas;
  }


  // cargarErroresSiguientes() {
  //   if (this.offsetErrores + this.limiteErrores < this.totalErrores) {
  //     this.offsetErrores += this.limiteErrores;
  //     this.cargarErrores();
  //   }
  // }

  // cargarErroresAnteriores() {
  //   if (this.offsetErrores > 0) {
  //     this.offsetErrores = Math.max(0, this.offsetErrores - this.limiteErrores);
  //     this.cargarErrores();
  //   }
  // }

  limpiarErrores() {
    if (!confirm('¿Está seguro de que desea limpiar todos los errores? Se creará un backup antes de limpiar.')) {
      return;
    }

    this.cargandoErrores = true;
    const url = `${environment.apiUrl}/controllers/erroresControlador.php?control=limpiarErrores`;

    this.http.post<any>(url, {}).subscribe({
      next: (resp) => {
        this.cargandoErrores = false;
        if (resp.resultado === 'ok') {
          alert('Errores limpiados correctamente. ' + (resp.mensaje || ''));
          this.offsetErrores = 0;
          this.cargarErrores();
          this.actualizarPaginacionErrores();
        } else {
          alert('Error al limpiar errores: ' + (resp.mensaje || 'Error desconocido'));
        }
      },
      error: (error) => {
        this.cargandoErrores = false;
        console.error('Error HTTP al limpiar errores:', error);
        this.actualizarPaginacionErrores();
        alert('Error de conexión al limpiar errores');
      }
    });
  }

  escribirErrorPrueba() {
    this.cargandoErrores = true;
    const url = `${environment.apiUrl}/controllers/erroresControlador.php?control=escribirErrorPrueba`;

    this.http.get<any>(url).subscribe({
      next: (resp) => {
        this.cargandoErrores = false;
        if (resp.resultado === 'ok') {
          alert('Mensaje de prueba escrito correctamente. ' + (resp.mensaje || ''));
          // Recargar errores después de escribir
          setTimeout(() => {
            this.cargarErrores();
          }, 500);
        } else {
          alert('Error al escribir mensaje de prueba: ' + (resp.mensaje || 'Error desconocido'));
        }
        this.actualizarPaginacionErrores();
      },
      error: (error) => {
        this.cargandoErrores = false;
        console.error('Error HTTP al escribir mensaje de prueba:', error);
        this.actualizarPaginacionErrores();
        alert('Error de conexión al escribir mensaje de prueba');
      }
    });
  }

}



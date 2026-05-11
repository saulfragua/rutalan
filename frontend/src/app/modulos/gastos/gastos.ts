import { Component, OnInit, OnDestroy, Inject, PLATFORM_ID, AfterViewInit } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { Subscription } from 'rxjs';
import { Gastos as GastosService } from '../../servicios/gastos';
import { UsuarioRutaService } from '../../servicios/usuarioruta';
import { Rutas as RutasService } from '../../servicios/rutas';
import { CajaService } from '../../servicios/caja';
import { isPlatformBrowser } from '@angular/common';

@Component({
  selector: 'app-gastos',
  standalone: false,
  templateUrl: './gastos.html',
  styleUrl: './gastos.css',
})
export class Gastos implements OnInit, AfterViewInit, OnDestroy {

  listaGastos: any[] = [];
  gastosFiltrados: any[] = [];
  terminoBusqueda: string = '';
  cargando: boolean = false;
  private routerSubscription?: Subscription;
  
  // Modal
  modoEdicion: boolean = false;
  gastoEditando: any = null;
  
  // Rutas del usuario logueado
  rutasUsuario: any[] = [];
  // Todas las rutas activas (para administrador)
  todasLasRutas: any[] = [];
  isBrowser: boolean = false;
  
  // Rol del usuario logueado
  rolUsuario: string = '';
  
  // Formulario
  gastoForm: any = {
    id_ruta: '',
    descripcion: '',
    monto: '',
    fecha_gasto: new Date().toISOString().split('T')[0]
  };

  constructor(
    private gastosService: GastosService,
    private usuarioRutaService: UsuarioRutaService,
    private rutasService: RutasService,
    private cajaService: CajaService,
    private router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    // Suscribirse a los eventos de navegación para recargar datos cada vez que se accede al módulo
    this.routerSubscription = this.router.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe((event: any) => {
        if (event.url === '/gastos' || event.urlAfterRedirects === '/gastos') {
          this.obtenerRolUsuario();
          // Recargar rutas al navegar al módulo (con delay para asegurar que el rol esté disponible)
          setTimeout(() => {
            this.cargarRutasUsuario();
          }, 100);
          this.cargarGastos();
          // Si es cobrador, abrir modal automáticamente después de que el DOM esté listo
          if (this.rolUsuario === 'cobrador') {
            setTimeout(() => {
              this.abrirModalGasto();
            }, 200);
          }
        }
      });
  }

  ngOnInit() {
    // Primero obtener el rol del usuario
    this.obtenerRolUsuario();
    
    // Cargar rutas después de obtener el rol (usar setTimeout para asegurar que el rol esté disponible)
    setTimeout(() => {
      this.cargarRutasUsuario();
    }, 100);
    
    // Luego cargar datos según el rol
    if (this.rolUsuario === 'admin') {
      this.cargarGastos();
    }
  }

  ngAfterViewInit() {
    // Si es cobrador, abrir modal automáticamente después de que el DOM esté listo
    if (this.rolUsuario === 'cobrador') {
      // Pequeño delay para asegurar que Angular haya terminado de renderizar
      setTimeout(() => {
        this.abrirModalGasto();
      }, 100);
    }
  }
  
  // Función para obtener el rol del usuario logueado
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

  ngOnDestroy() {
    if (this.routerSubscription) {
      this.routerSubscription.unsubscribe();
    }
  }

  // Función para cargar gastos (solo para administradores)
  cargarGastos() {
    // Solo cargar gastos si es administrador
    if (this.rolUsuario !== 'admin') {
      this.cargando = false;
      return;
    }
    
    this.cargando = true;
    
    // Obtener usuario logueado
    if (!this.isBrowser || typeof localStorage === 'undefined') {
      this.cargando = false;
      return;
    }
    
    const usuarioData = localStorage.getItem('usuario');
    if (!usuarioData) {
      this.cargando = false;
      return;
    }
    
    try {
      const usuario = JSON.parse(usuarioData);
      const idUsuario = usuario.id_usuario;
      
      if (idUsuario && !isNaN(parseInt(String(idUsuario)))) {
        this.gastosService.consultarPorUsuario(parseInt(String(idUsuario))).subscribe({
          next: (resp: any) => {
            this.listaGastos = resp || [];
            this.gastosFiltrados = this.listaGastos;
            // Aplicar búsqueda si hay término activo
            if (this.terminoBusqueda) {
              this.buscarGastos();
            }
            this.cargando = false;
          },
          error: (error) => {
            console.error('Error al cargar gastos:', error);
            this.cargando = false;
            alert('Error al cargar los gastos');
          }
        });
      } else {
        this.cargando = false;
      }
    } catch (error) {
      console.error('Error al parsear datos del usuario:', error);
      this.cargando = false;
    }
  }

  /**
   * Busca gastos por término de búsqueda
   */
  buscarGastos() {
    if (!this.terminoBusqueda || this.terminoBusqueda.trim() === '') {
      this.gastosFiltrados = this.listaGastos;
      return;
    }

    const termino = this.terminoBusqueda.toLowerCase().trim();
    this.gastosFiltrados = this.listaGastos.filter(gasto => {
      const descripcion = (gasto.descripcion || '').toLowerCase();
      const ruta = (gasto.nombre_ruta || '').toLowerCase();
      const usuario = (gasto.nombre_usuario || '').toLowerCase();
      const monto = (gasto.monto || '').toString().toLowerCase();
      
      return descripcion.includes(termino) || 
             ruta.includes(termino) || 
             usuario.includes(termino) ||
             monto.includes(termino);
    });
  }

  /**
   * Limpia la búsqueda y muestra todos los gastos
   */
  limpiarBusqueda() {
    this.terminoBusqueda = '';
    this.gastosFiltrados = this.listaGastos;
  }

  // Función para cargar rutas del usuario logueado o todas las rutas activas si es admin
  cargarRutasUsuario() {
    if (!this.isBrowser || typeof localStorage === 'undefined') {
      return;
    }
    
    // Asegurar que el rol esté actualizado
    if (!this.rolUsuario) {
      this.obtenerRolUsuario();
    }
    
    const usuarioData = localStorage.getItem('usuario');
    if (!usuarioData) {
      return;
    }
    
    try {
      const usuario = JSON.parse(usuarioData);
      const idUsuario = usuario.id_usuario;
      const rolUsuario = this.rolUsuario || usuario.rol || '';
      
      // Si es administrador, cargar todas las rutas activas del sistema
      if (rolUsuario === 'admin') {
        this.rutasService.consultar().subscribe({
          next: (resp: any) => {
            const todasLasRutas = resp || [];
            // Filtrar solo las rutas activas (activo puede ser 1, '1', true, o 'true')
            this.rutasUsuario = todasLasRutas.filter((ruta: any) => {
              const activo = ruta.activo;
              // Verificar si la ruta está activa
              return activo == 1 || activo === 1 || activo === '1' || activo === true || activo === 'true';
            });
            console.log('Admin - Rutas activas cargadas:', this.rutasUsuario.length, 'de', todasLasRutas.length);
          },
          error: (error) => {
            console.error('Error al cargar rutas del sistema:', error);
            this.rutasUsuario = [];
            alert('Error al cargar las rutas. Por favor, recarga la página.');
          }
        });
      } else {
        // Si es cobrador, cargar solo sus rutas asignadas
        if (idUsuario && !isNaN(parseInt(String(idUsuario)))) {
          this.usuarioRutaService.rutasPorUsuario(parseInt(String(idUsuario))).subscribe({
            next: (resp: any) => {
              this.rutasUsuario = resp || [];
              console.log('Cobrador - Rutas asignadas cargadas:', this.rutasUsuario.length);
            },
            error: (error) => {
              console.error('Error al cargar rutas del usuario:', error);
              this.rutasUsuario = [];
            }
          });
        }
      }
    } catch (error) {
      console.error('Error al parsear datos del usuario:', error);
      this.rutasUsuario = [];
    }
  }

  // Función para abrir modal (nuevo gasto)
  abrirModalGasto() {
    // Verificar que tenga caja abierta
    if (!this.isBrowser || typeof localStorage === 'undefined') {
      alert('Error: No se puede acceder al almacenamiento local');
      return;
    }
    
    const usuarioData = localStorage.getItem('usuario');
    if (!usuarioData) {
      alert('No hay sesión activa. Por favor, inicie sesión nuevamente.');
      return;
    }

    const usuario = JSON.parse(usuarioData);
    const idUsuario = usuario.id_usuario;
    
    if (!idUsuario || isNaN(parseInt(idUsuario))) {
      alert('Error: No se pudo obtener el ID de usuario');
      return;
    }

    // Asegurar que el rol esté actualizado antes de cargar rutas
    this.obtenerRolUsuario();

    // Preparar formulario
    this.modoEdicion = false;
    this.gastoEditando = null;
    this.gastoForm = {
      id_ruta: '',
      descripcion: '',
      monto: '',
      fecha_gasto: new Date().toISOString().split('T')[0]
    };

    // Cargar rutas según el rol:
    // - Administrador: puede ver TODAS las rutas activas del sistema
    // - Cobrador: solo puede ver sus rutas asignadas
    if (this.rolUsuario === 'admin') {
      this.cargarTodasLasRutasActivas();
      console.log('🔵 Administrador: Cargando todas las rutas activas disponibles');
    } else {
      this.cargarRutasUsuario();
      console.log('🟡 Cobrador: Cargando solo rutas asignadas al usuario');
    }

    // Verificar caja abierta
    this.cajaService.obtenerCajaAbierta(parseInt(idUsuario)).subscribe({
      next: (caja: any) => {
        if (!caja || !caja.id_caja) {
          alert('Debe tener una caja abierta para registrar gastos. Por favor, abra una caja primero.');
          // Aún así abrir el modal para que el usuario vea el formulario
          this.mostrarModal();
          return;
        }
        
        // Abrir modal
        this.mostrarModal();
      },
      error: (error) => {
        console.error('Error al verificar caja:', error);
        alert('Error al verificar el estado de la caja');
        // Aún así intentar abrir el modal
        this.mostrarModal();
      }
    });
  }

  // Función para cargar todas las rutas activas (para administrador)
  cargarTodasLasRutasActivas() {
    this.rutasService.consultar().subscribe({
      next: (resp: any) => {
        const todasLasRutas = resp || [];
        // Filtrar solo las rutas activas (activo puede ser 1, '1', true, o 'true')
        this.todasLasRutas = todasLasRutas.filter((ruta: any) => {
          const activo = ruta.activo;
          // Verificar si la ruta está activa
          return activo == 1 || activo === 1 || activo === '1' || activo === true || activo === 'true';
        });
        console.log('Admin - Rutas activas cargadas:', this.todasLasRutas.length, 'de', todasLasRutas.length);
      },
      error: (error) => {
        console.error('Error al cargar rutas del sistema:', error);
        this.todasLasRutas = [];
        alert('Error al cargar las rutas. Por favor, recarga la página.');
      }
    });
  }

  // Función auxiliar para mostrar el modal
  mostrarModal() {
    const modal = document.getElementById('modalGasto');
    if (modal) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
  }

  // Función para abrir modal en modo edición
  editarGasto(gasto: any) {
    // Solo administradores pueden editar gastos
    if (this.rolUsuario !== 'admin') {
      alert('No tiene permisos para editar gastos. Solo los administradores pueden realizar esta acción.');
      return;
    }
    
    this.modoEdicion = true;
    this.gastoEditando = gasto;
    
    this.gastosService.consultarPorId(gasto.id_gasto).subscribe({
      next: (resp: any) => {
        if (resp) {
          this.gastoForm = {
            id_ruta: resp.id_ruta || '',
            descripcion: resp.descripcion || '',
            monto: resp.monto || '',
            fecha_gasto: resp.fecha_gasto || new Date().toISOString().split('T')[0]
          };
          
          this.mostrarModal();
        } else {
          alert('Error al cargar los datos del gasto');
        }
      },
      error: (error) => {
        console.error('Error al cargar gasto:', error);
        alert('Error al cargar los datos del gasto');
      }
    });
  }

  // Función para cerrar modal
  cerrarModalGasto() {
    const modal = document.getElementById('modalGasto');
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
    // Limpiar formulario y resetear modo edición
    this.limpiarFormulario();
    this.modoEdicion = false;
    this.gastoEditando = null;
  }

  // Función para limpiar formulario
  limpiarFormulario() {
    this.gastoForm = {
      id_ruta: '',
      descripcion: '',
      monto: '',
      fecha_gasto: new Date().toISOString().split('T')[0]
    };
  }

  // Función para guardar gasto
  guardarGasto(event: Event) {
    event.preventDefault();
    
    // Si está en modo edición, solo administradores pueden guardar
    if (this.modoEdicion && this.rolUsuario !== 'admin') {
      alert('No tiene permisos para editar gastos. Solo los administradores pueden realizar esta acción.');
      return;
    }
    
    // Verificar que estamos en el navegador
    if (typeof window === 'undefined' || typeof localStorage === 'undefined') {
      alert('Error: No se puede acceder al almacenamiento local');
      return;
    }
    
    // Obtener usuario logueado del localStorage
    const usuarioData = localStorage.getItem('usuario');
    if (!usuarioData) {
      alert('No hay sesión activa. Por favor, inicie sesión nuevamente.');
      return;
    }

    const usuario = JSON.parse(usuarioData);
    const idUsuario = usuario.id_usuario;

    // Validar que el id_usuario sea un número válido
    if (!idUsuario || isNaN(parseInt(idUsuario))) {
      alert('Error: No se pudo obtener un ID de usuario válido');
      return;
    }

    // Validar campos obligatorios
    if (!this.gastoForm.descripcion || !this.gastoForm.monto || !this.gastoForm.fecha_gasto) {
      alert('Por favor complete todos los campos obligatorios');
      return;
    }

    const monto = parseFloat(this.gastoForm.monto);
    if (isNaN(monto) || monto <= 0) {
      alert('El monto debe ser mayor a cero');
      return;
    }

    const datosGasto = {
      id_usuario: parseInt(idUsuario),
      id_ruta: this.gastoForm.id_ruta ? parseInt(this.gastoForm.id_ruta) : null,
      descripcion: this.gastoForm.descripcion.trim(),
      monto: monto,
      fecha_gasto: this.gastoForm.fecha_gasto
    };

    if (this.modoEdicion && this.gastoEditando) {
      // Modo edición
      this.gastosService.editar(this.gastoEditando.id_gasto, datosGasto).subscribe({
        next: (resp: any) => {
          if (resp && resp.resultado === 'success') {
            alert(resp.mensaje || 'Gasto actualizado correctamente');
            this.cerrarModalGasto();
            // Solo recargar gastos si es admin
            if (this.rolUsuario === 'admin') {
              this.cargarGastos();
            }
          } else {
            alert(resp?.mensaje || 'Error al actualizar el gasto');
          }
        },
        error: (error) => {
          console.error('Error al actualizar gasto:', error);
          alert('Error al actualizar el gasto');
        }
      });
    } else {
      // Modo creación
      this.gastosService.insertar(datosGasto).subscribe({
        next: (resp: any) => {
          if (resp && resp.resultado === 'success') {
            alert(resp.mensaje || 'Gasto guardado correctamente');
            this.cerrarModalGasto();
            // Solo recargar gastos si es admin
            if (this.rolUsuario === 'admin') {
              this.cargarGastos();
            } else {
              // Si es cobrador, limpiar el formulario para permitir agregar otro gasto
              this.limpiarFormulario();
            }
          } else {
            alert(resp?.mensaje || 'Error al guardar el gasto');
          }
        },
        error: (error) => {
          console.error('Error al guardar gasto:', error);
          const mensajeError = error?.error?.mensaje || error?.message || 'Error al guardar el gasto';
          alert(mensajeError);
        }
      });
    }
  }

  // Función para eliminar gasto
  eliminarGasto(gasto: any) {
    // Solo administradores pueden eliminar gastos
    if (this.rolUsuario !== 'admin') {
      alert('No tiene permisos para eliminar gastos. Solo los administradores pueden realizar esta acción.');
      return;
    }
    
    if (!confirm(`¿Está seguro de eliminar el gasto "${gasto.descripcion}" por ${this.formatearMonto(gasto.monto)}?`)) {
      return;
    }

    this.gastosService.eliminar(gasto.id_gasto).subscribe({
      next: (resp: any) => {
        if (resp && resp.resultado === 'success') {
          alert(resp.mensaje || 'Gasto eliminado correctamente');
          // Solo recargar gastos si es admin
          if (this.rolUsuario === 'admin') {
            this.cargarGastos();
          }
        } else {
          alert(resp?.mensaje || 'Error al eliminar el gasto');
        }
      },
      error: (error) => {
        console.error('Error al eliminar gasto:', error);
        alert('Error al eliminar el gasto');
      }
    });
  }

  // Función para formatear fecha (Zona horaria: Colombia GMT-5)
  formatearFecha(fecha: string): string {
    if (!fecha) return '-';
    const date = new Date(fecha);
    return date.toLocaleDateString('es-CO', { 
      timeZone: 'America/Bogota',
      year: 'numeric', 
      month: '2-digit', 
      day: '2-digit' 
    });
  }

  // Función para formatear monto
  formatearMonto(monto: number | string): string {
    if (!monto) return '$0.00';
    const num = typeof monto === 'string' ? parseFloat(monto) : monto;
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      minimumFractionDigits: 2
    }).format(num);
  }
}

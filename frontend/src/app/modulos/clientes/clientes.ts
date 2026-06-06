import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { Subject } from 'rxjs';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { Subscription } from 'rxjs';
import { Usuarios as UsuariosService } from '../../servicios/usuarios';
import { Fiadores } from '../../servicios/fiadores';
import { Clientes as ClientesService } from '../../servicios/cliente';
import { UsuarioRutaService } from '../../servicios/usuarioruta';
import { Rutas } from '../../servicios/rutas';
import { isPlatformBrowser } from '@angular/common';
import { PLATFORM_ID, Inject } from '@angular/core';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-clientes',
  standalone: false,
  templateUrl: './clientes.html',
  styleUrl: './clientes.css',
})
export class Clientes implements OnInit, OnDestroy {

  environment = environment;

  listaClientes: any[] = [];
  clientesFiltrados: any[] = [];
  terminoBusqueda: string = '';
  cargando: boolean = false;
  private routerSubscription?: Subscription;

  // Modal de documentos
  modalDocumentosAbierto: boolean = false;
  clienteSeleccionado: any = null;
  mostrarSoloFiador: boolean = false;

  // Modal de foto ampliada
  modalFotoAbierto: boolean = false;
  fotoSeleccionada: string = '';
  tituloFoto: string = '';
  esFiador: boolean = false;

  // Rutas del usuario logueado
  rutasUsuario: any[] = [];
  // Todas las rutas (para modo edición)
  todasLasRutas: any[] = [];
  isBrowser: boolean = false;

  // Rol del usuario logueado
  rolUsuario: string = '';

  // Modo edición
  modoEdicion: boolean = false;
  clienteEditando: any = null;

  // Ubicación GPS
  latitud: number | null = null;
  longitud: number | null = null;
  capturandoUbicacion: boolean = false;
  ubicacionCapturada: boolean = false;

  // Paginación
  paginaActual: number = 1;
  clientesPorPagina: number = 5;
  totalPaginas: number = 0;
  clientesPaginados: any[] = [];
  opcionesPorPagina: number[] = [5,10, 25, 50, 100];
  Math= Math;

  constructor(
    public usuarios: UsuariosService,
    public fiadores: Fiadores,
    public clientes: ClientesService,
    private usuarioRutaService: UsuarioRutaService,
    private rutasService: Rutas,
    public router: Router,
    private cdr: ChangeDetectorRef, 
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
    // Suscribirse a los eventos de navegación para recargar datos cada vez que se accede al módulo
    this.routerSubscription = this.router.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe((event: any) => {
        if (event.url === '/clientes' || event.urlAfterRedirects === '/clientes') {
          this.cargarClientes();
        }
      });
  }

  ngOnInit() {
    this.obtenerRolUsuario();
    this.cargarClientes();
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
      this.rolUsuario = '';
    }
  }

  ngOnDestroy() {
    if (this.routerSubscription) {
      this.routerSubscription.unsubscribe();
    }
  }

  // Función para cargar clientes
  cargarClientes() {
    this.cargando = true;
    this.clientes.consultar().subscribe({
      next: (resp: any) => {
        this.listaClientes = resp || [];
        this.clientesFiltrados = resp || [];
        this.cargando = false;
        this.actualizarPaginacion();
      },
      error: (error) => {
        this.cargando = false;
        alert('Error al cargar los clientes');
      }
    });
  }

  // Función para filtrar clientes por nombre o documento
  filtrarClientes() {
    if (!this.terminoBusqueda || this.terminoBusqueda.trim() === '') {
      this.clientesFiltrados = this.listaClientes;
      return;
    }

    const termino = this.terminoBusqueda.toLowerCase().trim();

    this.clientesFiltrados = this.listaClientes.filter((cliente: any) => {
      // Buscar por nombre completo
      const nombreCompleto = `${cliente.nombres || ''} ${cliente.apellidos || ''}`.toLowerCase();
      const coincideNombre = nombreCompleto.includes(termino);

      // Buscar por documento
      const documento = (cliente.documento || '').toLowerCase();
      const coincideDocumento = documento.includes(termino);

      return coincideNombre || coincideDocumento;
    });
    this.paginaActual = 1;
    this.actualizarPaginacion();
  }

  actualizarPaginacion() {
    this.totalPaginas = Math.ceil(this.clientesFiltrados.length / this.clientesPorPagina);
    if (this.paginaActual > this.totalPaginas) this.paginaActual = 1;
    const inicio = (this.paginaActual - 1) * this.clientesPorPagina;
    const fin = inicio + this.clientesPorPagina;
    this.clientesPaginados = this.clientesFiltrados.slice(inicio, fin);
    this.cdr.detectChanges();
  }

  cambiarPagina(pagina: number) {
    if (pagina < 1 || pagina > this.totalPaginas) return;
    this.paginaActual = pagina;
    this.actualizarPaginacion();
  }

  cambiarPorPagina(cantidad: number) {
    this.clientesPorPagina = cantidad;
    this.paginaActual = 1;
    this.actualizarPaginacion();
  }

  getPaginas(): number[] {
    const paginas: number[] = [];
    const rango = 2;
    for (let i = Math.max(1, this.paginaActual - rango);
      i <= Math.min(this.totalPaginas, this.paginaActual + rango); i++) {
      paginas.push(i);
    }
    return paginas;
  }

  // Función para limpiar la búsqueda
  limpiarBusqueda() {
    this.terminoBusqueda = '';
    this.clientesFiltrados = this.listaClientes;
    this.paginaActual = 1;
    this.actualizarPaginacion();
  }

  // Función para abrir modal (nuevo cliente)
  abrirModalCliente() {
    this.modoEdicion = false;
    this.clienteEditando = null;

    // Asegurar que el rol esté actualizado
    this.obtenerRolUsuario();

    const modal = document.getElementById('modalCliente');
    if (modal) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    // Cargar rutas según el rol:
    // - Administrador: puede ver TODAS las rutas del sistema sin restricción
    // - Cobrador: solo puede ver sus rutas asignadas
    if (this.rolUsuario === 'admin') {
      this.cargarTodasLasRutas();
    } else {
      this.cargarRutasUsuario();
    }

    // Limpiar formulario
    this.limpiarFormulario();
  }

  // Función para cargar todas las rutas (para modo edición)
  cargarTodasLasRutas() {
    this.rutasService.consultar().subscribe({
      next: (resp: any) => {
        this.todasLasRutas = resp || [];
      },
      error: (error) => {
        this.todasLasRutas = [];
      }
    });
  }

  // Función para abrir modal en modo edición
  editarCliente(cliente: any) {
    this.modoEdicion = true;
    this.clienteEditando = cliente;

    // Cargar todas las rutas para poder seleccionar
    this.cargarTodasLasRutas();

    // Cargar datos del cliente
    this.clientes.consultarPorId(cliente.id_cliente).subscribe({
      next: (resp: any) => {
        if (resp) {
          this.cargarDatosEnFormulario(resp);
          const modal = document.getElementById('modalCliente');
          if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
          }
        } else {
          alert('Error al cargar los datos del cliente');
        }
      },
      error: (error) => {
        alert('Error al cargar los datos del cliente');
      }
    });
  }

  // Función para cargar datos en el formulario
  cargarDatosEnFormulario(cliente: any) {
    const form = document.querySelector('#modalCliente form') as HTMLFormElement;
    if (!form) return;

    // Cargar datos del cliente
    (form.querySelector('[name="documento"]') as HTMLInputElement).value = cliente.documento || '';
    (form.querySelector('[name="nombres"]') as HTMLInputElement).value = cliente.nombres || '';
    (form.querySelector('[name="apellidos"]') as HTMLInputElement).value = cliente.apellidos || '';
    (form.querySelector('[name="direccion"]') as HTMLInputElement).value = cliente.direccion || '';
    (form.querySelector('[name="telefono"]') as HTMLInputElement).value = cliente.telefono || '';
    (form.querySelector('[name="telefono2"]') as HTMLInputElement).value = cliente.telefono2 || '';

    // Cargar ubicación GPS si existe
    this.latitud = cliente.latitud ? parseFloat(cliente.latitud) : null;
    this.longitud = cliente.longitud ? parseFloat(cliente.longitud) : null;
    this.ubicacionCapturada = (this.latitud !== null && this.longitud !== null);

    // Actualizar indicador visual
    this.actualizarIndicadorUbicacion();

    // Cargar ruta si existe
    const selectRuta = form.querySelector('[name="id_ruta"]') as HTMLSelectElement;
    if (selectRuta && cliente.id_ruta) {
      selectRuta.value = cliente.id_ruta.toString();
    }

    // Cargar imágenes del cliente
    if (cliente.foto_cliente) {
      const img = document.getElementById('prevFotoCliente') as HTMLImageElement;
      if (img) {
        img.src = environment.apiUrl + '/' + cliente.foto_cliente;
      }
    }
    if (cliente.foto_cedula_frontal) {
      const img = document.getElementById('prevCedulaFrontal') as HTMLImageElement;
      if (img) {
        img.src = environment.apiUrl + '/' + cliente.foto_cedula_frontal;
      }
    }
    if (cliente.foto_cedula_atras) {
      const img = document.getElementById('prevCedulaAtras') as HTMLImageElement;
      if (img) {
        img.src = environment.apiUrl + '/' + cliente.foto_cedula_atras;
      }
    }

    // Si tiene fiador, cargar datos del fiador
    if (cliente.id_fiador && cliente.documento_fiador) {
      const checkFiador = document.getElementById('checkFiador') as HTMLInputElement;
      const formFiador = document.getElementById('formFiador');

      if (checkFiador) {
        checkFiador.checked = true;
      }
      if (formFiador) {
        formFiador.classList.remove('hidden');
      }

      // Cargar datos del fiador
      (form.querySelector('[name="documento_fiador"]') as HTMLInputElement).value = cliente.documento_fiador || '';
      (form.querySelector('[name="nombres_fiador"]') as HTMLInputElement).value = cliente.nombres_fiador || '';
      (form.querySelector('[name="apellidos_fiador"]') as HTMLInputElement).value = cliente.apellidos_fiador || '';
      (form.querySelector('[name="direccion_fiador"]') as HTMLInputElement).value = cliente.direccion_fiador || '';
      (form.querySelector('[name="telefono_fiador"]') as HTMLInputElement).value = cliente.telefono_fiador || '';
      (form.querySelector('[name="telefono2_fiador"]') as HTMLInputElement).value = cliente.telefono2_fiador || '';

      // Cargar imágenes del fiador
      if (cliente.foto_fiador) {
        const img = document.getElementById('prevFotoFiador') as HTMLImageElement;
        if (img) {
          img.src = environment.apiUrl + '/' + cliente.foto_fiador;
        }
      }
      if (cliente.foto_cedula_frontal_fiador) {
        const img = document.getElementById('prevCedulaFrontalFiador') as HTMLImageElement;
        if (img) {
          img.src = environment.apiUrl + '/' + cliente.foto_cedula_frontal_fiador;
        }
      }
      if (cliente.foto_cedula_atras_fiador) {
        const img = document.getElementById('prevCedulaAtrasFiador') as HTMLImageElement;
        if (img) {
          img.src = environment.apiUrl + '/' + cliente.foto_cedula_atras_fiador;
        }
      }
    }
  }

  // Función para cargar rutas del usuario logueado
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
          },
          error: (error) => {
            this.rutasUsuario = [];
          }
        });
      }
    } catch (error) {
      this.rutasUsuario = [];
    }
  }

  // Función para cerrar modal
  cerrarModalCliente() {
    const modal = document.getElementById('modalCliente');
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
    // Limpiar formulario y resetear modo edición
    this.limpiarFormulario();
    this.modoEdicion = false;
    this.clienteEditando = null;
  }

  // Función para toggle del fiador
  toggleFiador() {
    const checkFiador = document.getElementById('checkFiador') as HTMLInputElement;
    const formFiador = document.getElementById('formFiador');

    if (checkFiador && formFiador) {
      if (checkFiador.checked) {
        formFiador.classList.remove('hidden');
      } else {
        formFiador.classList.add('hidden');
      }
    }
  }

  // Función para preview de imágenes
  previewImage(event: any, previewId: string) {
    try {
      // Manejar tanto si se pasa el evento completo como si se pasa solo el target
      let input: HTMLInputElement | null = null;

      if (event && event.target) {
        input = event.target as HTMLInputElement;
      } else if (event && event.files) {
        // Si se pasa directamente el input element
        input = event as HTMLInputElement;
      } else if (event && event.currentTarget) {
        input = event.currentTarget as HTMLInputElement;
      }

      if (!input) {
        return;
      }

      if (!input.files || input.files.length === 0) {
        return;
      }

      const file = input.files[0];
      if (!file) {
        return;
      }

      // Validar que sea una imagen
      if (!file.type.startsWith('image/')) {
        alert('Por favor seleccione un archivo de imagen válido');
        return;
      }

      const reader = new FileReader();
      reader.onload = (e: any) => {
        const preview = document.getElementById(previewId);
        if (preview) {
          (preview as HTMLImageElement).src = e.target.result;
        }
      };
      reader.onerror = (error) => {
        alert('Error al cargar la imagen. Por favor, intente con otra imagen.');
      };
      reader.readAsDataURL(file);
    } catch (error) {
      alert('Error al procesar la imagen. Por favor, intente nuevamente.');
    }
  }

  // Función para capturar ubicación GPS
  capturarUbicacion() {
    if (!this.isBrowser || !navigator.geolocation) {
      alert('La geolocalización no está disponible en este navegador');
      return;
    }

    this.capturandoUbicacion = true;

    navigator.geolocation.getCurrentPosition(
      (position) => {
        this.latitud = position.coords.latitude;
        this.longitud = position.coords.longitude;
        this.ubicacionCapturada = true;
        this.capturandoUbicacion = false;

        // Actualizar campos ocultos en el formulario
        const form = document.querySelector('#modalCliente form') as HTMLFormElement;
        if (form) {
          let latInput = form.querySelector('[name="latitud"]') as HTMLInputElement;
          let lngInput = form.querySelector('[name="longitud"]') as HTMLInputElement;

          if (!latInput) {
            latInput = document.createElement('input');
            latInput.type = 'hidden';
            latInput.name = 'latitud';
            form.appendChild(latInput);
          }

          if (!lngInput) {
            lngInput = document.createElement('input');
            lngInput.type = 'hidden';
            lngInput.name = 'longitud';
            form.appendChild(lngInput);
          }

          latInput.value = this.latitud.toString();
          lngInput.value = this.longitud.toString();
        }

        this.actualizarIndicadorUbicacion();
        alert(`Ubicación capturada:\nLatitud: ${this.latitud.toFixed(6)}\nLongitud: ${this.longitud.toFixed(6)}`);
      },
      (error) => {
        this.capturandoUbicacion = false;
        let mensaje = 'Error al obtener la ubicación: ';
        switch (error.code) {
          case error.PERMISSION_DENIED:
            mensaje += 'Permiso denegado. Por favor, permite el acceso a la ubicación en tu navegador.';
            break;
          case error.POSITION_UNAVAILABLE:
            mensaje += 'Ubicación no disponible.';
            break;
          case error.TIMEOUT:
            mensaje += 'Tiempo de espera agotado.';
            break;
          default:
            mensaje += 'Error desconocido.';
            break;
        }
        alert(mensaje);
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
      }
    );
  }

  // Función para actualizar indicador visual de ubicación
  actualizarIndicadorUbicacion() {
    const indicador = document.getElementById('indicadorUbicacion');
    if (indicador) {
      if (this.ubicacionCapturada && this.latitud && this.longitud) {
        indicador.innerHTML = `
          <span style="color: #10b981; font-weight: 600;">
            <i class="fas fa-check-circle"></i> Ubicación: ${this.latitud.toFixed(6)}, ${this.longitud.toFixed(6)}
          </span>
        `;
        indicador.style.display = 'block';
      } else {
        indicador.style.display = 'none';
      }
    }
  }

  // Función para limpiar formulario
  limpiarFormulario() {
    const form = document.querySelector('#modalCliente form') as HTMLFormElement;
    if (form) {
      form.reset();
    }

    // Limpiar ubicación GPS
    this.latitud = null;
    this.longitud = null;
    this.ubicacionCapturada = false;
    this.actualizarIndicadorUbicacion();

    // Resetear previews de imágenes
    const previews = ['prevFotoCliente', 'prevCedulaFrontal', 'prevCedulaAtras',
      'prevFotoFiador', 'prevCedulaFrontalFiador', 'prevCedulaAtrasFiador'];
    previews.forEach(id => {
      const preview = document.getElementById(id) as HTMLImageElement;
      if (preview) {
        if (id.includes('Cliente')) {
          preview.src = 'assets/dist/img/documentos/foto.jpg';
        } else if (id.includes('Frontal')) {
          preview.src = 'assets/dist/img/documentos/cedulafrontal.png';
        } else if (id.includes('Atras')) {
          preview.src = 'assets/dist/img/documentos/cedulaatras.png';
        }
      }
    });

    // Ocultar formulario de fiador
    const formFiador = document.getElementById('formFiador');
    if (formFiador) {
      formFiador.classList.add('hidden');
    }
    const checkFiador = document.getElementById('checkFiador') as HTMLInputElement;
    if (checkFiador) {
      checkFiador.checked = false;
    }
  }

  // Función para guardar cliente
  async guardarCliente(event: Event) {
    event.preventDefault();

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
    const idRutaUsuario = usuario.id_ruta; // Obtener id_ruta del usuario logueado

    // Validar que el id_usuario sea un número válido
    if (!idUsuario || isNaN(parseInt(idUsuario))) {
      // Continuar sin id_usuario si no es válido
    }

    const form = event.target as HTMLFormElement;

    if (!form) {
      alert('Error: No se pudo acceder al formulario');
      return;
    }

    const formData = new FormData();

    // Obtener valores del cliente
    const documento = (form.querySelector('[name="documento"]') as HTMLInputElement)?.value?.trim() || '';
    const nombres = (form.querySelector('[name="nombres"]') as HTMLInputElement)?.value?.trim() || '';
    const apellidos = (form.querySelector('[name="apellidos"]') as HTMLInputElement)?.value?.trim() || '';
    const direccion = (form.querySelector('[name="direccion"]') as HTMLInputElement)?.value?.trim() || '';
    const telefono = (form.querySelector('[name="telefono"]') as HTMLInputElement)?.value?.trim() || '';
    const telefono2 = (form.querySelector('[name="telefono2"]') as HTMLInputElement)?.value?.trim() || '';

    // Validar campos obligatorios del cliente
    if (!documento || !nombres || !apellidos) {
      alert('Por favor complete los campos obligatorios del cliente (Documento, Nombres, Apellidos)');
      return;
    }

    // Agregar datos del cliente al FormData
    formData.append('documento', documento);
    formData.append('nombres', nombres);
    formData.append('apellidos', apellidos);
    formData.append('direccion', direccion);
    formData.append('telefono', telefono);
    formData.append('telefono2', telefono2);

    // Agregar ubicación GPS si está disponible
    if (this.latitud !== null && this.longitud !== null) {
      formData.append('latitud', this.latitud.toString());
      formData.append('longitud', this.longitud.toString());
    }

    // Obtener ruta seleccionada del select (tanto en creación como en edición)
    const selectRuta = form.querySelector('[name="id_ruta"]') as HTMLSelectElement;
    if (selectRuta && selectRuta.value) {
      formData.append('id_ruta', selectRuta.value);
    } else {
      // Si no se seleccionó ruta y estamos en modo creación
      if (!this.modoEdicion) {
        // Si es administrador, usar primera ruta de todas las rutas disponibles
        if (this.rolUsuario === 'admin' && this.todasLasRutas.length > 0) {
          formData.append('id_ruta', this.todasLasRutas[0].id_ruta.toString());
        }
        // Si es cobrador, usar primera ruta asignada al usuario
        else if (this.rolUsuario === 'cobrador' && this.rutasUsuario.length > 0) {
          formData.append('id_ruta', this.rutasUsuario[0].id_ruta.toString());
        }
      }
    }

    // Solo agregar id_usuario si es válido
    if (idUsuario && !isNaN(parseInt(idUsuario))) {
      formData.append('id_usuario', idUsuario.toString());
    }

    // Obtener archivos del cliente
    const fotoCliente = (form.querySelector('[name="foto_cliente"]') as HTMLInputElement)?.files?.[0];
    if (fotoCliente) {
      formData.append('foto_cliente', fotoCliente);
    }

    const cedulaFrontal = (form.querySelector('[name="cedula_frontal"]') as HTMLInputElement)?.files?.[0];
    if (cedulaFrontal) {
      formData.append('cedula_frontal', cedulaFrontal);
    }

    const cedulaAtras = (form.querySelector('[name="cedula_atras"]') as HTMLInputElement)?.files?.[0];
    if (cedulaAtras) {
      formData.append('cedula_atras', cedulaAtras);
    }

    // Obtener checkbox de fiador
    const checkFiador = document.getElementById('checkFiador') as HTMLInputElement;
    const tieneFiador = checkFiador?.checked || false;
    formData.append('tiene_fiador', tieneFiador.toString());

    // Si tiene fiador, agregar datos del fiador
    if (tieneFiador) {
      const documentoFiador = (form.querySelector('[name="documento_fiador"]') as HTMLInputElement)?.value?.trim() || '';
      const nombresFiador = (form.querySelector('[name="nombres_fiador"]') as HTMLInputElement)?.value?.trim() || '';
      const apellidosFiador = (form.querySelector('[name="apellidos_fiador"]') as HTMLInputElement)?.value?.trim() || '';
      const direccionFiador = (form.querySelector('[name="direccion_fiador"]') as HTMLInputElement)?.value?.trim() || '';
      const telefonoFiador = (form.querySelector('[name="telefono_fiador"]') as HTMLInputElement)?.value?.trim() || '';
      const telefono2Fiador = (form.querySelector('[name="telefono2_fiador"]') as HTMLInputElement)?.value?.trim() || '';

      // Validar campos obligatorios del fiador
      if (!documentoFiador || !nombresFiador || !apellidosFiador) {
        alert('Por favor complete los campos obligatorios del fiador (Documento, Nombres, Apellidos)');
        return;
      }

      formData.append('documento_fiador', documentoFiador);
      formData.append('nombres_fiador', nombresFiador);
      formData.append('apellidos_fiador', apellidosFiador);
      formData.append('direccion_fiador', direccionFiador);
      formData.append('telefono_fiador', telefonoFiador);
      formData.append('telefono2_fiador', telefono2Fiador);

      // Obtener archivos del fiador
      const fotoFiador = (form.querySelector('[name="foto_fiador"]') as HTMLInputElement)?.files?.[0];
      if (fotoFiador) {
        formData.append('foto_fiador', fotoFiador);
      }

      const cedulaFrontalFiador = (form.querySelector('[name="cedula_frontal_fiador"]') as HTMLInputElement)?.files?.[0];
      if (cedulaFrontalFiador) {
        formData.append('cedula_frontal_fiador', cedulaFrontalFiador);
      }

      const cedulaAtrasFiador = (form.querySelector('[name="cedula_atras_fiador"]') as HTMLInputElement)?.files?.[0];
      if (cedulaAtrasFiador) {
        formData.append('cedula_atras_fiador', cedulaAtrasFiador);
      }
    }

    try {
      let respuesta: any;

      if (this.modoEdicion && this.clienteEditando) {
        // Modo edición
        respuesta = await this.clientes.editarFormData(this.clienteEditando.id_cliente, formData).toPromise();
      } else {
        // Modo creación
        respuesta = await this.clientes.insertarFormData(formData).toPromise();
      }

      // Verificar respuesta (puede ser 'success' o 'ok')
      if (respuesta && (respuesta.resultado === 'success' || respuesta.resultado === 'ok')) {
        alert(respuesta.mensaje || (this.modoEdicion ? 'Cliente actualizado correctamente' : 'Cliente guardado correctamente'));
        this.cerrarModalCliente();
        // Recargar la lista de clientes
        this.cargarClientes();
      } else {
        const mensajeError = respuesta?.mensaje || (this.modoEdicion ? 'Error al actualizar el cliente' : 'Error al guardar el cliente');
        alert(mensajeError);
      }
    } catch (error: any) {
      const mensajeError = error?.error?.mensaje || error?.message || (this.modoEdicion ? 'Error al actualizar el cliente' : 'Error al guardar el cliente');
      alert(mensajeError);
    }
  }

  // Función para abrir modal de documentos del cliente
  abrirModalDocumentos(cliente: any) {
    this.clienteSeleccionado = cliente;
    this.mostrarSoloFiador = false;
    this.modalDocumentosAbierto = true;
  }

  // Función para abrir modal de documentos del fiador
  abrirModalDocumentosFiador(cliente: any) {
    this.clienteSeleccionado = cliente;
    this.mostrarSoloFiador = true;
    this.modalDocumentosAbierto = true;
  }

  // Función para cerrar modal de documentos
  cerrarModalDocumentos() {
    this.modalDocumentosAbierto = false;
    this.clienteSeleccionado = null;
    this.mostrarSoloFiador = false;
  }

  // Función para abrir modal de foto ampliada del cliente
  abrirModalFotoCliente(cliente: any) {
    if (cliente.foto_cliente) {
      this.fotoSeleccionada = environment.apiUrl + '/' + cliente.foto_cliente;
      this.tituloFoto = `${cliente.nombres} ${cliente.apellidos}`;
      this.esFiador = false;
      this.modalFotoAbierto = true;
    } else {
      // Si no tiene foto, mostrar la imagen por defecto
      this.fotoSeleccionada = 'assets/dist/img/documentos/foto.jpg';
      this.tituloFoto = `${cliente.nombres} ${cliente.apellidos}`;
      this.esFiador = false;
      this.modalFotoAbierto = true;
    }
  }

  // Función para abrir modal de foto ampliada del fiador
  abrirModalFotoFiador(cliente: any) {
    if (cliente.foto_fiador) {
      this.fotoSeleccionada = environment.apiUrl + '/' + cliente.foto_fiador;
      this.tituloFoto = cliente.nombre_completo_fiador || 'Fiador';
      this.esFiador = true;
      this.modalFotoAbierto = true;
    } else if (cliente.id_fiador) {
      // Si tiene fiador pero no foto, mostrar imagen por defecto
      this.fotoSeleccionada = 'assets/dist/img/documentos/foto.jpg';
      this.tituloFoto = cliente.nombre_completo_fiador || 'Fiador';
      this.esFiador = true;
      this.modalFotoAbierto = true;
    }
  }

  // Función para cerrar modal de foto ampliada
  cerrarModalFoto() {
    this.modalFotoAbierto = false;
    this.fotoSeleccionada = '';
    this.tituloFoto = '';
    this.esFiador = false;
  }

  // Función para activar cliente
  activarCliente(cliente: any) {
    // Administrador y cobrador pueden activar clientes inactivos
    if (this.rolUsuario !== 'admin' && this.rolUsuario !== 'cobrador') {
      alert('No tiene permisos para activar clientes.');
      return;
    }

    if (cliente.activo) {
      alert('El cliente ya se encuentra activo.');
      return;
    }

    if (!confirm(`¿Está seguro de activar al cliente ${cliente.nombres} ${cliente.apellidos}?`)) {
      return;
    }

    this.clientes.activar(cliente.id_cliente).subscribe({
      next: (resp: any) => {
        if (resp && resp.resultado === 'success') {
          alert(resp.mensaje || 'Cliente activado correctamente');
          this.cargarClientes();
        } else {
          alert(resp?.mensaje || 'Error al activar el cliente');
        }
      },
      error: (error) => {
        alert('Error al activar el cliente');
      }
    });
  }

  // Función para inactivar cliente
  inactivarCliente(cliente: any) {
    // Solo administradores pueden activar/inactivar clientes
    if (this.rolUsuario !== 'admin') {
      alert('No tiene permisos para inactivar clientes. Solo los administradores pueden realizar esta acción.');
      return;
    }

    if (!confirm(`¿Está seguro de inactivar al cliente ${cliente.nombres} ${cliente.apellidos}?`)) {
      return;
    }

    this.clientes.inactivar(cliente.id_cliente).subscribe({
      next: (resp: any) => {
        if (resp && resp.resultado === 'success') {
          alert(resp.mensaje || 'Cliente inactivado correctamente');
          this.cargarClientes();
        } else {
          alert(resp?.mensaje || 'Error al inactivar el cliente');
        }
      },
      error: (error) => {
        alert('Error al inactivar el cliente');
      }
    });
  }

  // Función para eliminar cliente
  eliminarCliente(cliente: any) {
    // Solo administradores pueden eliminar clientes
    if (this.rolUsuario !== 'admin') {
      alert('No tiene permisos para eliminar clientes. Solo los administradores pueden realizar esta acción.');
      return;
    }

    if (!confirm(`¿Está seguro de ELIMINAR permanentemente al cliente ${cliente.nombres} ${cliente.apellidos}?\n\nEsta acción no se puede deshacer.`)) {
      return;
    }

    this.clientes.eliminar(cliente.id_cliente).subscribe({
      next: (resp: any) => {
        if (resp && (resp.resultado === 'success' || resp.resultado === 'Cliente eliminado correctamente')) {
          alert(resp.mensaje || 'Cliente eliminado correctamente');
          this.cargarClientes();
        } else {
          alert(resp?.mensaje || 'Error al eliminar el cliente');
        }
      },
      error: (error) => {
        alert('Error al eliminar el cliente');
      }
    });
  }

}

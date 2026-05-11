import { Component, OnInit, OnDestroy, AfterViewInit, ViewChild, ElementRef } from '@angular/core';
import { Router } from '@angular/router';
import { Clientes as ClientesService } from '../../servicios/cliente';
import { Rutas } from '../../servicios/rutas';
import { isPlatformBrowser } from '@angular/common';
import { PLATFORM_ID, Inject } from '@angular/core';

declare var google: any;
declare var window: any;

@Component({
  selector: 'app-mapa-clientes',
  standalone: false,
  templateUrl: './mapa-clientes.html',
  styleUrl: './mapa-clientes.css',
})
export class MapaClientes implements OnInit, AfterViewInit, OnDestroy {
  
  @ViewChild('mapaContainer', { static: false }) mapaContainer!: ElementRef;
  
  clientesConUbicacion: any[] = [];
  rutas: any[] = [];
  rutaSeleccionada: number | null = null;
  cargando: boolean = false;
  isBrowser: boolean = false;
  
  private map: any = null;
  private markers: any[] = [];
  private infoWindow: any = null;
  private mapaInicializado: boolean = false;

  constructor(
    private clientesService: ClientesService,
    private rutasService: Rutas,
    public router: Router,
    @Inject(PLATFORM_ID) private platformId: Object
  ) {
    this.isBrowser = isPlatformBrowser(this.platformId);
  }

  ngOnInit() {
    this.cargarRutas();
  }

  ngAfterViewInit() {
    if (this.isBrowser) {
      // Esperar a que el DOM esté completamente renderizado
      setTimeout(() => {
        this.verificarYInicializar();
      }, 500);
    }
  }

  verificarYInicializar() {
    // Verificar si Google Maps ya está cargado
    if (typeof google !== 'undefined' && google.maps) {
      console.log('Google Maps ya está cargado');
      this.inicializarMapaCompleto();
    } else if (window.googleMapsReady) {
      console.log('Google Maps listo (flag)');
      setTimeout(() => {
        this.inicializarMapaCompleto();
      }, 300);
    } else {
      console.log('Esperando a que Google Maps se cargue...');
      // Esperar a que Google Maps se cargue
      const checkGoogleMaps = setInterval(() => {
        if (typeof google !== 'undefined' && google.maps) {
          clearInterval(checkGoogleMaps);
          console.log('Google Maps detectado');
          this.inicializarMapaCompleto();
        }
      }, 100);

      // Timeout de seguridad después de 5 segundos
      setTimeout(() => {
        clearInterval(checkGoogleMaps);
        if (typeof google !== 'undefined' && google.maps) {
          this.inicializarMapaCompleto();
        } else {
          console.error('Google Maps no se cargó después de 5 segundos');
          alert('Error: Google Maps no se pudo cargar. Por favor, recarga la página.');
        }
      }, 5000);

      // También escuchar el evento personalizado
      window.addEventListener('googlemapsready', () => {
        clearInterval(checkGoogleMaps);
        console.log('Evento googlemapsready recibido');
        setTimeout(() => {
          this.inicializarMapaCompleto();
        }, 300);
      });
    }
  }

  inicializarMapaCompleto() {
    if (this.mapaInicializado) {
      console.log('Mapa ya inicializado, cargando clientes...');
      this.cargarClientes();
      return;
    }

    console.log('Iniciando inicialización del mapa...');

    // Verificar que el contenedor existe
    if (!this.mapaContainer || !this.mapaContainer.nativeElement) {
      console.warn('Contenedor del mapa no disponible, reintentando...');
      setTimeout(() => {
        this.inicializarMapaCompleto();
      }, 200);
      return;
    }

    const mapaElement = document.getElementById('mapa');
    if (!mapaElement) {
      console.warn('Elemento del mapa no encontrado, reintentando...');
      setTimeout(() => {
        this.inicializarMapaCompleto();
      }, 200);
      return;
    }

    // Verificar que el elemento tenga dimensiones válidas
    const rect = mapaElement.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) {
      console.warn('Elemento del mapa sin dimensiones válidas, reintentando...');
      setTimeout(() => {
        this.inicializarMapaCompleto();
      }, 200);
      return;
    }

    console.log('Elemento del mapa encontrado, inicializando...');
    
    // Inicializar el mapa primero - cargarClientes se llamará automáticamente cuando el mapa esté listo
    this.inicializarMapa();
  }

  ngOnDestroy() {
    // Limpiar marcadores
    if (this.markers) {
      this.markers.forEach(marker => {
        if (marker) {
          marker.setMap(null);
        }
      });
    }
  }

  // Ya no necesitamos cargar Google Maps dinámicamente, se carga en index.html

  cargarRutas() {
    this.rutasService.consultar().subscribe({
      next: (resp: any) => {
        this.rutas = resp || [];
      },
      error: (error) => {
        console.error('Error al cargar rutas:', error);
      }
    });
  }

  cargarClientes() {
    this.cargando = true;
    
    const idRuta = this.rutaSeleccionada || undefined;
    
    this.clientesService.consultarConUbicacion(idRuta).subscribe({
      next: (resp: any) => {
        this.clientesConUbicacion = resp || [];
        this.cargando = false;
        console.log('Clientes cargados:', this.clientesConUbicacion.length, idRuta ? `(Ruta: ${idRuta})` : '(Todas las rutas)');
        
        // Mostrar marcadores si el mapa ya está inicializado
        if (this.mapaInicializado && this.map) {
          this.mostrarMarcadores();
        } else {
          console.warn('Mapa no inicializado, esperando...');
          setTimeout(() => {
            if (this.mapaInicializado && this.map) {
              this.mostrarMarcadores();
            }
          }, 500);
        }
      },
      error: (error) => {
        console.error('Error al cargar clientes con ubicación:', error);
        this.cargando = false;
        alert('Error al cargar las ubicaciones de los clientes');
      }
    });
  }

  inicializarMapa() {
    if (this.mapaInicializado) {
      console.log('Mapa ya inicializado, solo cargando marcadores');
      this.cargarClientes();
      return;
    }

    if (!this.isBrowser) {
      console.warn('No estamos en el navegador');
      return;
    }

    if (typeof google === 'undefined' || !google.maps) {
      console.error('Google Maps no está disponible');
      setTimeout(() => {
        this.verificarYInicializar();
      }, 500);
      return;
    }

    const mapaElement = document.getElementById('mapa');
    if (!mapaElement) {
      console.error('Elemento del mapa no encontrado');
      return;
    }

    // Verificar que el elemento sea un Element válido
    if (!(mapaElement instanceof Element)) {
      console.error('El elemento del mapa no es un Element válido');
      return;
    }

    const rect = mapaElement.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) {
      console.warn('Elemento del mapa sin dimensiones válidas');
      setTimeout(() => {
        this.inicializarMapa();
      }, 200);
      return;
    }

    // Coordenadas por defecto (Colombia)
    const defaultCenter = { lat: 4.6097, lng: -74.0817 };

    try {
      // Asegurar que el elemento tenga dimensiones explícitas
      if (!mapaElement.style.height || mapaElement.style.height === '0px') {
        mapaElement.style.height = '600px';
      }
      if (!mapaElement.style.width || mapaElement.style.width === '0px') {
        mapaElement.style.width = '100%';
      }

      console.log('Creando instancia del mapa...');

      // Esperar un frame más para asegurar que el elemento esté completamente renderizado
      requestAnimationFrame(() => {
        try {
          // Crear mapa sin mapId por ahora para evitar problemas con AdvancedMarkerElement
          // Usaremos Marker clásico que es más estable
          this.map = new google.maps.Map(mapaElement, {
            zoom: 10,
            center: defaultCenter,
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true
            // mapId removido temporalmente para evitar errores
          });

          console.log('Mapa creado exitosamente');

          this.infoWindow = new google.maps.InfoWindow();
          this.mapaInicializado = true;

          // Esperar a que el mapa esté completamente listo antes de cargar clientes
          google.maps.event.addListenerOnce(this.map, 'idle', () => {
            console.log('Mapa completamente cargado, cargando clientes...');
            // Cargar clientes después de que el mapa esté completamente inicializado
            setTimeout(() => {
              this.cargarClientes();
            }, 200);
          });
        } catch (error) {
          console.error('Error al crear el mapa:', error);
          alert('Error al inicializar el mapa: ' + (error as Error).message);
        }
      });
    } catch (error) {
      console.error('Error al inicializar el mapa:', error);
      alert('Error al inicializar el mapa: ' + (error as Error).message);
    }
  }

  mostrarMarcadores() {
    if (!this.map || !this.isBrowser || typeof google === 'undefined') {
      return;
    }

    // Limpiar marcadores anteriores
    this.markers.forEach(marker => {
      if (marker) {
        if (marker.map) {
          marker.map = null;
        }
        if (marker.setMap) {
          marker.setMap(null);
        }
      }
    });
    this.markers = [];

    if (this.clientesConUbicacion.length === 0) {
      return;
    }

    // Usar Marker clásico (más estable y compatible)
    // AdvancedMarkerElement requiere configuración adicional que puede causar errores
    const bounds = new google.maps.LatLngBounds();

    console.log('Creando marcadores para', this.clientesConUbicacion.length, 'clientes');

    this.clientesConUbicacion.forEach((cliente: any) => {
      if (cliente.latitud && cliente.longitud) {
        const lat = parseFloat(cliente.latitud);
        const lng = parseFloat(cliente.longitud);

        if (!isNaN(lat) && !isNaN(lng)) {
          const position = { lat, lng };

          try {
            // Usar Marker clásico (más estable)
            const marker = new google.maps.Marker({
              position: position,
              map: this.map,
              title: `${cliente.nombres} ${cliente.apellidos}`,
              animation: google.maps.Animation.DROP
            });

          // Crear contenido del info window
          const contenido = `
            <div style="padding: 0.5rem; min-width: 200px;">
              <h3 style="margin: 0 0 0.5rem 0; font-size: 1rem; font-weight: 700; color: #02416d;">
                ${cliente.nombres} ${cliente.apellidos}
              </h3>
              <p style="margin: 0.25rem 0; font-size: 0.875rem; color: #6b7280;">
                <strong>Documento:</strong> ${cliente.documento}
              </p>
              <p style="margin: 0.25rem 0; font-size: 0.875rem; color: #6b7280;">
                <strong>Dirección:</strong> ${cliente.direccion || 'No especificada'}
              </p>
              <p style="margin: 0.25rem 0; font-size: 0.875rem; color: #6b7280;">
                <strong>Teléfono:</strong> ${cliente.telefono || 'No especificado'}
              </p>
              ${cliente.nombre_ruta ? `<p style="margin: 0.25rem 0; font-size: 0.875rem; color: #6b7280;"><strong>Ruta:</strong> ${cliente.nombre_ruta}</p>` : ''}
              <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: #9ca3af;">
                Coordenadas: ${lat.toFixed(6)}, ${lng.toFixed(6)}
              </p>
            </div>
          `;

            // Agregar listener para el click
            marker.addListener('click', () => {
              this.infoWindow.setContent(contenido);
              this.infoWindow.open(this.map, marker);
            });

            this.markers.push(marker);
            bounds.extend(position);
          } catch (error) {
            console.error('Error al crear marcador para cliente:', cliente.nombres, error);
            // Continuar con el siguiente cliente
          }
        }
      }
    });

    console.log('Marcadores creados:', this.markers.length);

    // Ajustar el mapa para mostrar todos los marcadores
    if (this.markers.length > 0) {
      this.map.fitBounds(bounds);
      
      // Si solo hay un marcador, establecer un zoom mínimo
      if (this.markers.length === 1) {
        this.map.setZoom(15);
      }
    }
  }

  filtrarPorRuta() {
    this.cargarClientes();
  }

  limpiarFiltro() {
    this.rutaSeleccionada = null;
    this.cargarClientes();
  }
}

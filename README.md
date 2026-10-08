<div align="center">

# Rutalan

**Plataforma para la gestión de créditos y el control de rutas de cobranza**

Digitaliza el registro de clientes, créditos, pagos, gastos y cajas de los cobradores en negocios de préstamos "gota a gota".

![Angular](https://img.shields.io/badge/Angular-21-DD0031?logo=angular&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white)
![Node.js](https://img.shields.io/badge/Node.js-WhatsApp_service-339933?logo=node.js&logoColor=white)

</div>

---

## Tabla de contenidos

1. [Características](#características)
2. [Arquitectura](#arquitectura)
3. [Tecnologías](#tecnologías)
4. [Estructura del repositorio](#estructura-del-repositorio)
5. [Instalación](#instalación)
6. [Configuración](#configuración)
7. [Roles y permisos](#roles-y-permisos)
8. [Seguridad de la API](#seguridad-de-la-api)
9. [Protección de datos personales](#protección-de-datos-personales)
10. [Documentación académica](#documentación-académica)
11. [Autores](#autores)

---

## Características

- **Clientes y fiadores**: registro, edición, ubicación en mapa y fotos de soporte. Un mismo fiador puede respaldar a varios clientes, con advertencia en pantalla.
- **Créditos y plan de pagos**: creación, cuotas, cancelación y refinanciación (manual y automática).
- **Cobros por ruta**: orden de cobranza, registro de pagos y gestión de clientes por ruta.
- **Cajas**: apertura y cierre por cobrador, movimientos de caja y cierres diarios.
- **Gastos operativos** asociados a cada ruta y caja.
- **Dashboard** administrativo con indicadores de cartera, morosidad, recaudo y rendimiento por ruta.
- **Informes** de pagos, créditos y gastos por rango de fechas, con exportación a **Excel** (`xlsx`) y **PDF** (`jspdf`), generada 100 % en el cliente.
- **Mensajería por WhatsApp** automática mediante un servicio Node.js independiente.
- **Seguridad**: login con reCAPTCHA, token firmado, control de roles en el servidor y claves dinámicas para cobradores.

## Arquitectura

Rutalan se compone de tres partes independientes:

| Componente | Tecnología | Ejecución |
|---|---|---|
| **Aplicación principal** | Angular (frontend) + PHP/MySQL (backend, patrón MVC) | `ng serve` + Apache/XAMPP |
| **Servicio de WhatsApp** | Node.js + `whatsapp-web.js` | `node server.js` (puerto `3000`) |
| **Landing page** | HTML/CSS/JS estático | Servidor estático (Live Server) |

```
 Navegador ──► Angular (4200) ──HTTP + Bearer token──► API PHP (Apache) ──► MySQL
                    │
                    └──────────────► Servicio WhatsApp (3000)
```

## Tecnologías

| Capa | Tecnología |
|---|---|
| Frontend | Angular 21, Tailwind CSS 4, RxJS |
| Backend | PHP (PDO, arquitectura MVC) |
| Base de datos | MySQL / MariaDB (`backend/config/rutalan.sql`) |
| Mensajería | Node.js, Express, `whatsapp-web.js` |
| Exportación | SheetJS (`xlsx`), jsPDF + `jspdf-autotable` |
| Anti-bots | Google reCAPTCHA v2 |

## Estructura del repositorio

```
rutalan/
├── backend/
│   ├── config/        # Conexión a BD, auth.php (token y roles), key_example.php, rutalan.sql
│   ├── controllers/   # Un controlador por módulo (login, clientes, créditos, pagos, cajas…)
│   ├── models/        # Acceso a datos (PDO)
│   ├── services/      # Integraciones (WhatsApp)
│   └── uploads/       # Archivos subidos (contenido ignorado por git)
├── frontend/          # Aplicación Angular
│   └── src/app/
│       ├── estructura/  # Navbar, sidebar, layout principal
│       ├── guards/      # AdminGuard e interceptor de autenticación
│       ├── modulos/     # Pantallas: clientes, créditos, cobros, caja, gastos, informes…
│       └── servicios/   # Servicios HTTP hacia la API
├── whatsapp-api/      # Servicio Node.js de mensajería
├── landing/           # Página de presentación
└── README.md
```

## Instalación

### Requisitos previos

<<<<<<< HEAD
<!-- completar: versiones exactas -->
- Node.js `<20.19 o superior (probado con v20.19.3) y npm 10 o superior>`
- Angular CLI `21` <probado con 21.2.11>
- PHP `8.0 o superior` (probado con 8.4.8)
- MySQL `5.7 o superior`, o MariaDB equivalente (probado con MariaDB 10.4.32, incluida en XAMPP) 
- XAMPP (o entorno equivalente) para el backend
- Para el servicio de WhatsApp: Node.js 18 o superior (ver `whatsapp-api/README.md`)
=======
- **Node.js** 18 o superior y **npm** (Angular 21 requiere una versión LTS reciente)
- **Angular CLI** (`npm i -g @angular/cli`)
- **PHP** 8.x, **Apache** y **MySQL/MariaDB** (por ejemplo, XAMPP)
- Una cuenta de WhatsApp (solo para el servicio de mensajería)
>>>>>>> ec5f834657e02f5a960ad89975500a855fe07e46

### 1. Backend y base de datos

1. Clona o copia el repositorio en `C:\xampp\htdocs\rutalan`.
2. Inicia Apache y MySQL desde XAMPP.
3. Crea la base de datos `rutalan` e importa el script:
   ```bash
   mysql -u root -e "CREATE DATABASE rutalan CHARACTER SET utf8mb4"
   mysql -u root rutalan < backend/config/rutalan.sql
   ```
4. Copia `backend/config/key_example.php` como `backend/config/key.php` y completa los valores (ver [Configuración](#configuración)).

En desarrollo la conexión usa `root` sin contraseña sobre `localhost`; en producción (dominio `rutalan.tech`) toma las credenciales de `key.php`.


### 3.1 API del backend (PHP)

El backend expone una API REST en `backend/controllers/`. Cada controlador atiende un módulo y se invoca con
`<nombre>Controlador.php?control=<acción>`. Las consultas usan `GET`; las operaciones que envían datos usan
`POST` con cuerpo JSON o `FormData` (los diagramas de secuencia del documento de diseño, sección 2.2.3,
detallan el método de cada flujo). Las respuestas son JSON con `resultado: "ok"` en caso de éxito
(el login usa `estado: "ok"`).

| Controlador | Acciones (`control=`) | Descripción |
|---|---|---|
| `loginControlador` | `login` | Valida usuario, contraseña (o clave dinámica de 8 dígitos para cobradores) y reCAPTCHA; devuelve el usuario, su rol y rutas asignadas. |
| `clientesControlador` | `consultar`, `consultarPorId`, `filtrar`, `insertar`, `editar`, `eliminar`, `activar`, `inactivar`, `actualizarUbicacion`, `consultarConUbicacion` | Gestión de clientes, incluida la ubicación GPS para el mapa. |
| `fiadoresControlador` | `consultar`, `filtrar`, `buscarPorDocumento`, `contarClientes`, `insertar`, `editar`, `eliminar` | Gestión de fiadores y conteo de clientes asociados. |
| `creditosControlador` | `consultar`, `buscar`, `consultarPorId`, `tienePagos`, `clienteTieneCreditoPendiente`, `insertar`, `editar`, `eliminar`, `cancelar`, `refinanciar_automatico` | Gestión de créditos. |
| `planPagosControlador` | `consultar`, `consultarPorIdCredito`, `filtrar`, `insertar`, `editar`, `eliminar` | Plan de amortización (cuotas) de cada crédito. |
| `refinanciarControlador` | `consultarPorId`, `refinanciar` | Refinanciación de un crédito desde cobros. |
| `pagosControlador` | `consultar`, `consultarClientesPorRuta`, `registrarPago`, `actualizarOrdenCobranza` | Panel de cobros por ruta, registro de pagos y orden de cobranza. |
| `gastosControlador` | `consultar`, `consultarPorUsuario`, `consultarPorCaja`, `consultarPorId`, `filtrar`, `insertar`, `editar`, `eliminar` | Gastos operativos. |
| `cajasControlador` | `obtenerCajaAbierta`, `tieneCajaAbierta`, `abrirCaja`, `cerrarCaja`, `consultarPorUsuario`, `consultarCajasAbiertasConResumen`, `consultarCajasCerradasConResumen` | Apertura, cierre y consulta de cajas. |
| `movimientosCajaControlador` | `registrar`, `consultarPorCaja`, `consultarTodos` | Entradas y salidas de dinero. |
| `informesControlador` | `pagos`, `creditos`, `gastos` | Informes por tipo y rango de fechas. |
| `dashboardControlador` | 14 consultas (`obtenerCreditosPorRuta`, `obtenerTotalGeneralCreditos`, `obtenerEstadisticasClientes`, `obtenerClientesPorRuta`, `obtenerTotalCobradoEnDia`, `obtenerGastosPorRuta`, `obtenerCreditosPorTipo`, `obtenerEstadisticasSeguros`, `obtenerEstadisticasCajas`, `obtenerEstadisticasCuotas`, `obtenerEvolucionPagos`, `obtenerEstadisticasRefinanciaciones`, `obtenerTopRutasPorRendimiento`, `obtenerEstadisticasMorosidad`) | Datos de las tarjetas y gráficas del dashboard. |
| `rutasControlador` | `consultar`, `filtrar`, `estado`, `insertar`, `editar`, `eliminar` | Gestión de rutas. |
| `usuariosControlador` | `consultar`, `filtrar`, `insertar`, `editar`, `eliminar`, `cambiarEstado` | Gestión de usuarios. |
| `usuarioRutaControlador` | `consultar`, `filtrar`, `insertar`, `eliminar` | Asignación de rutas a usuarios. |
| `clavesCobradorControlador` | `generarClave`, `obtenerClaveActiva`, `consultarPorUsuario`, `validarClave`, `desactivarClavesExpiradas` | Claves temporales de cobradores. |
| `erroresControlador` | `obtenerErrores`, `limpiarErrores`, `escribirErrorPrueba` | Log de errores del sistema. |

El servicio de WhatsApp es independiente y su API se documenta en [`whatsapp-api/README.md`](whatsapp-api/README.md).

### 2. Frontend

```bash
cd frontend
npm install
ng serve
```

Disponible en `http://localhost:4200`. La URL de la API se define en `frontend/src/environments/environment.ts` (`apiUrl: '/rutalan/backend'`).

<<<<<<< HEAD
## API del backend (PHP)

El backend expone una API REST en `backend/controllers/`. Cada controlador atiende un módulo y se invoca con
`<nombre>Controlador.php?control=<acción>`. Las consultas usan `GET`; las operaciones que envían datos usan
`POST` con cuerpo JSON o `FormData` (los diagramas de secuencia del documento de diseño, sección 2.2.3,
detallan el método de cada flujo). Las respuestas son JSON con `resultado: "ok"` en caso de éxito
(el login usa `estado: "ok"`).

| Controlador | Acciones (`control=`) | Descripción |
|---|---|---|
| `loginControlador` | `login` | Valida usuario, contraseña (o clave dinámica de 8 dígitos para cobradores) y reCAPTCHA; devuelve el usuario, su rol y rutas asignadas. |
| `clientesControlador` | `consultar`, `consultarPorId`, `filtrar`, `insertar`, `editar`, `eliminar`, `activar`, `inactivar`, `actualizarUbicacion`, `consultarConUbicacion` | Gestión de clientes, incluida la ubicación GPS para el mapa. |
| `fiadoresControlador` | `consultar`, `filtrar`, `buscarPorDocumento`, `contarClientes`, `insertar`, `editar`, `eliminar` | Gestión de fiadores y conteo de clientes asociados. |
| `creditosControlador` | `consultar`, `buscar`, `consultarPorId`, `tienePagos`, `clienteTieneCreditoPendiente`, `insertar`, `editar`, `eliminar`, `cancelar`, `refinanciar_automatico` | Gestión de créditos. |
| `planPagosControlador` | `consultar`, `consultarPorIdCredito`, `filtrar`, `insertar`, `editar`, `eliminar` | Plan de amortización (cuotas) de cada crédito. |
| `refinanciarControlador` | `consultarPorId`, `refinanciar` | Refinanciación de un crédito desde cobros. |
| `pagosControlador` | `consultar`, `consultarClientesPorRuta`, `registrarPago`, `actualizarOrdenCobranza` | Panel de cobros por ruta, registro de pagos y orden de cobranza. |
| `gastosControlador` | `consultar`, `consultarPorUsuario`, `consultarPorCaja`, `consultarPorId`, `filtrar`, `insertar`, `editar`, `eliminar` | Gastos operativos. |
| `cajasControlador` | `obtenerCajaAbierta`, `tieneCajaAbierta`, `abrirCaja`, `cerrarCaja`, `consultarPorUsuario`, `consultarCajasAbiertasConResumen`, `consultarCajasCerradasConResumen` | Apertura, cierre y consulta de cajas. |
| `movimientosCajaControlador` | `registrar`, `consultarPorCaja`, `consultarTodos` | Entradas y salidas de dinero. |
| `informesControlador` | `pagos`, `creditos`, `gastos` | Informes por tipo y rango de fechas. |
| `dashboardControlador` | 14 consultas (`obtenerCreditosPorRuta`, `obtenerTotalGeneralCreditos`, `obtenerEstadisticasClientes`, `obtenerClientesPorRuta`, `obtenerTotalCobradoEnDia`, `obtenerGastosPorRuta`, `obtenerCreditosPorTipo`, `obtenerEstadisticasSeguros`, `obtenerEstadisticasCajas`, `obtenerEstadisticasCuotas`, `obtenerEvolucionPagos`, `obtenerEstadisticasRefinanciaciones`, `obtenerTopRutasPorRendimiento`, `obtenerEstadisticasMorosidad`) | Datos de las tarjetas y gráficas del dashboard. |
| `rutasControlador` | `consultar`, `filtrar`, `estado`, `insertar`, `editar`, `eliminar` | Gestión de rutas. |
| `usuariosControlador` | `consultar`, `filtrar`, `insertar`, `editar`, `eliminar`, `cambiarEstado` | Gestión de usuarios. |
| `usuarioRutaControlador` | `consultar`, `filtrar`, `insertar`, `eliminar` | Asignación de rutas a usuarios. |
| `clavesCobradorControlador` | `generarClave`, `obtenerClaveActiva`, `consultarPorUsuario`, `validarClave`, `desactivarClavesExpiradas` | Claves temporales de cobradores. |
| `erroresControlador` | `obtenerErrores`, `limpiarErrores`, `escribirErrorPrueba` | Log de errores del sistema. |

El servicio de WhatsApp es independiente y su API se documenta en [`whatsapp-api/README.md`](whatsapp-api/README.md).

### Limitaciones conocidas de seguridad

- El control de roles se aplica en la interfaz (Angular); los endpoints todavía no verifican sesión ni rol
  en el servidor. Se recomienda agregar tokens y validación por endpoint en una versión posterior.
- El backend permite solicitudes desde cualquier origen (CORS abierto) para facilitar el desarrollo.
=======


### 3. Servicio de WhatsApp (opcional)

```bash
cd whatsapp-api
npm install
# Windows PowerShell
$env:ENABLE_WHATSAPP="true"; node server.js
```
>>>>>>> ec5f834657e02f5a960ad89975500a855fe07e46

Escanea el código QR desde el módulo **Administrador** de la aplicación. Más detalles y solución de problemas en [whatsapp-api/README.md](whatsapp-api/README.md) y [SOLUCION-PROBLEMAS.md](whatsapp-api/SOLUCION-PROBLEMAS.md).

### 4. Landing page

Sirve la carpeta `landing/` con cualquier servidor estático (no requiere build).

## Configuración

`backend/config/key.php` (ignorado por git) define:

| Constante | Descripción |
|---|---|
| `RECAPTCHA_SECRET` / `RECAPTCHA_SITE` | Claves de [reCAPTCHA v2](https://www.google.com/recaptcha/admin) (casilla "No soy un robot") |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Credenciales de base de datos (solo producción) |
| `AUTH_SECRET` | Secreto para firmar los tokens de sesión. Si queda vacío se genera `backend/config/.auth_secret`. Generar uno: `php -r "echo bin2hex(random_bytes(32));"` |

> Cambiar `AUTH_SECRET` invalida todas las sesiones activas.

## Roles y permisos

| Rol | Alcance |
|---|---|
| **Administrador** | Acceso completo: dashboard, caja, administración de usuarios/rutas/claves e informes |
| **Cobrador** | Clientes, créditos, cobros, gastos, reportes y mapa de clientes |

## Seguridad de la API

La API valida sesión y rol **en el servidor**; el `AdminGuard` de Angular solo mejora la navegación.

- **Token firmado**: `loginControlador.php` devuelve un token (HMAC-SHA256, 12 h). El frontend lo guarda en `localStorage` y el interceptor `guards/auth.interceptor.ts` lo envía como `Authorization: Bearer <token>`. Ante un `401`, cierra la sesión local y redirige a `/login`.
- **Guard central**: `backend/config/auth.php` expone `emitirToken()` y `requerirAuth()`. Todos los controladores, salvo el de login, lo invocan: sin token válido responde `401`; si la acción es exclusiva de admin y el rol no lo es, `403`. También atiende el preflight `OPTIONS`.
- **Acciones solo admin** (`ACCIONES_SOLO_ADMIN` en `auth.php`): dashboard, informes, errores y claves de cobrador; escritura en usuarios, rutas y asignación usuario-ruta; resúmenes de cajas y consulta global de movimientos de caja.

**Límites conocidos**

- El token no se revoca antes de vencer: desactivar un usuario no cierra su sesión hasta que expire.
- Las fotos de `backend/uploads/` se sirven como archivos estáticos, sin autenticación.
- El servicio de WhatsApp (puerto 3000) no usa este token.
- CORS permite cualquier origen (`*`); conviene restringirlo en producción.

## Protección de datos personales

La aplicación almacena datos personales y fotos de documentos de identidad de clientes y fiadores, sujetos a la **Ley 1581 de 2012** (Colombia).

- `backend/uploads/` y `backend/config/key.php` **no se versionan** (ver `.gitignore`); solo se conservan las carpetas con `.gitkeep`.
- Nunca subas fotos reales, claves ni respaldos de base de datos con datos reales al repositorio.
- Usa un repositorio privado y rota cualquier secreto que haya estado expuesto.

## Documentación académica

Producto integrador del programa **Análisis y Desarrollo de Software (ADSO) — SENA**. Incluye:

- Especificación de Requisitos de Software (ERS) bajo el estándar **IEEE 830**.
- Diagramas UML (casos de uso, clases, componentes y secuencia) elaborados en Dia.
- Modelo entidad-relación (MER/ERD).
- Plan de negocio: fichas técnicas, activos, nómina, organigrama y aspectos legales.

## Autores

- **Sebastian Moreno** ([@hsmorenom](https://github.com/hsmorenom)) — Frontend (Angular), documentación y gestión administrativa.
- **Saúl Fragua** ([@saulfragua](https://github.com/saulfragua)) — Backend (PHP/MVC/MySQL) y operación comercial.

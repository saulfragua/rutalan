# Rutalan

**Rutalan** es una plataforma SaaS para la gestión de créditos y el control de rutas diarias de cobranza, pensada para negocios de préstamos informales o "gota a gota" que necesitan digitalizar el registro de clientes, créditos, pagos, gastos y rutas de sus cobradores.

<!-- completar: logo -->
<!-- <p align="center"><img src="ruta/al/logo.svg" alt="Rutalan" width="200"/></p> -->

---

## ✨ Características principales

- **Gestión de clientes**: registro, edición y consulta de clientes con datos de contacto y dirección.
- **Gestión de créditos**: creación de créditos, cuotas, y lógica de **fiador compartido** (un mismo fiador puede respaldar a varios clientes, con advertencia en el frontend).
- **Registro de pagos**: control de pagos realizados por cliente y por ruta.
- **Gestión de gastos**: registro de gastos operativos asociados a cada ruta.
- **Rutas de cobro**: organización de clientes y cobradores por rutas diarias.
- **Informes**: generación de informes de pagos, créditos y gastos por rango de fechas, con:
  - Exportación a **Excel** (`xlsx`)
  - Exportación a **PDF** (`jspdf` + `jspdf-autotable`)
  - Ambas exportaciones se generan **100% en el cliente**, a partir de los datos ya cargados en pantalla (sin llamadas adicionales al backend).
- **Historial de accesos** (`LoginHistory`) y gestión de claves de cobradores (`ClavesCobrador`).
- **Autenticación con reCAPTCHA** en el login.
- **Paginación manual** en las tablas principales (clientes, pagos, créditos, gastos).
- **Control de roles**: acceso restringido a módulos administrativos (ej. Informes) solo para usuarios con rol `admin`.

---

## 🛠️ Tecnologías

| Capa | Tecnología |
|---|---|
| Frontend | Angular |
| Backend | PHP (arquitectura MVC) |
| Base de datos | MySQL |
| Exportación de datos | [xlsx (SheetJS)](https://www.npmjs.com/package/xlsx), [jsPDF](https://www.npmjs.com/package/jspdf) + [jspdf-autotable](https://www.npmjs.com/package/jspdf-autotable) |
| Diagramación / documentación | UML (Dia), IEEE 830 (ERS) |

---

## 🧩 Componentes del proyecto

Rutalan no es un único proyecto monolítico: está compuesto por tres partes independientes.

| Componente | Tecnología | Cómo se ejecuta |
|---|---|---|
| **Aplicación principal** | Angular (frontend) + PHP/MySQL (backend) | `ng serve` (frontend) + servidor PHP/XAMPP (backend) |
| **Landing page** | Página estática (HTML/CSS/JS) | Vive fuera del proyecto central; se carga con **Live Server** (no requiere Angular ni build) |
| **Servicio de WhatsApp** | Node.js (`whatsapp-web.js`) | Servicio aparte, corre en su propio puerto (por defecto `3000`) con `node server.js`; expone una API REST (`/api/status`, `/api/qr`, `/api/restart`, `/api/send-message`) que la app principal consume desde el módulo de Administrador para envío de mensajes automáticos. Ver README propio del servicio para detalles de instalación, variables de entorno y solución de problemas. |

---

## 📁 Estructura del proyecto

<!-- completar: estructura real de carpetas del repo, por ejemplo: -->
```
rutalan/
├── backend/
│   ├── config/          # Conexión a BD, configuración general, script SQL (rutalan.sql)
│   ├── controllers/
│   ├── models/
│   ├── services/
│   └── uploads/         # Archivos subidos por la aplicación
├── frontend/            # Aplicación Angular
│   ├── public/
│   └── src/
│       └── app/
│           ├── estructura/
│           ├── guards/
│           └── ...
└── README.md
```

> El **landing page** y el **servicio de WhatsApp** no viven dentro de esta estructura: son componentes independientes (ver sección [Componentes del proyecto](#-componentes-del-proyecto)).

---

## 🚀 Instalación y ejecución

### Requisitos previos

<!-- completar: versiones exactas -->
- Node.js `<20.19 o superior (probado con v20.19.3) y npm 10 o superior>`
- Angular CLI `21` <probado con 21.2.11>
- PHP `8.0 o superior` (probado con 8.4.8)
- MySQL `5.7 o superior`, o MariaDB equivalente (probado con MariaDB 10.4.32, incluida en XAMPP) 
- XAMPP (o entorno equivalente) para el backend
- Para el servicio de WhatsApp: Node.js 18 o superior (ver `whatsapp-api/README.md`)

### Frontend (Angular)

```bash
cd frontend
npm install
ng serve
```

La aplicación quedará disponible en `http://localhost:4200`.

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

---

## 👥 Roles de usuario

| Rol | Permisos |
|---|---|
| Administrador | Acceso completo, incluyendo módulo de Informes |
| Cobrador | Acceso a 4 componentes |

---

## 📄 Documentación académica

Este proyecto es el producto integrador del programa **Análisis y Desarrollo de Software (ADSO) - SENA**, e incluye:

- Especificación de Requisitos de Software (ERS) bajo estándar **IEEE 830**.
- Diagramas UML: casos de uso, clases, componentes y secuencia (elaborados en Dia).
- Modelo entidad-relación (MER/ERD).
- Plan de negocio: fichas técnicas, tabla de activos, nómina, organigrama y aspectos legales.

---

## 👤 Autores

- **Sebastian Moreno**([@hsmorenom](https://github.com/hsmorenom)) — Frontend (Angular), documentación y gestión administrativa.
- **Saúl Fragua** ([@saulfragua](https://github.com/saulfragua)) — Backend (PHP/MVC/MySQL) y operación comercial.

---



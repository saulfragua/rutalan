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
- Node.js `<versión>`
- Angular CLI `<versión>`
- PHP `<versión>`
- MySQL / MariaDB
- XAMPP (o entorno equivalente) para el backend

### Frontend (Angular)

```bash
cd frontend
npm install
ng serve
```

La aplicación quedará disponible en `http://localhost:4200`.

### Backend (PHP)

<!-- completar: pasos reales de configuración del backend -->
```bash
# Copiar el proyecto backend a la carpeta htdocs de XAMPP
# Configurar la conexión a la base de datos en <archivo de configuración>
# Importar el script SQL en MySQL
```

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

- **Sebastian Moreno** — Frontend (Angular), documentación y gestión administrativa.
- **Saúl Fragua** ([@saulfragua](https://github.com/saulfragua)) — Backend (PHP/MVC/MySQL) y operación comercial.

---



# Guía de contribución a Rutalan

¡Gracias por querer mejorar Rutalan! Esta guía explica cómo proponer cambios de forma ordenada y segura. Los detalles técnicos de estilo y arquitectura están en el documento [Estándares de Codificación](docs/Estandares_de_Codificacion_Rutalan.docx).

## Tabla de contenido

1. [Antes de empezar](#antes-de-empezar)
2. [Preparar el entorno](#preparar-el-entorno)
3. [Flujo de trabajo](#flujo-de-trabajo)
4. [Convención de ramas](#convención-de-ramas)
5. [Convención de commits](#convención-de-commits)
6. [Estándares de código (resumen)](#estándares-de-código-resumen)
7. [Pruebas antes de abrir un Pull Request](#pruebas-antes-de-abrir-un-pull-request)
8. [Pull Requests y revisión](#pull-requests-y-revisión)
9. [Seguridad y datos personales](#seguridad-y-datos-personales)
10. [Reportar errores y proponer mejoras](#reportar-errores-y-proponer-mejoras)

---

## Antes de empezar

- Lee el [README](README.md) para entender la arquitectura: frontend Angular, backend PHP/MySQL (MVC) y servicio de WhatsApp en Node.js.
- Busca primero si ya existe un *issue* o un Pull Request sobre lo mismo.
- Para cambios grandes (nuevos módulos, cambios en la base de datos o en la seguridad), abre un *issue* y coméntalo con los mantenedores antes de escribir código.
- Sé respetuoso y constructivo en las discusiones y revisiones.

## Preparar el entorno

Requisitos: Node.js LTS, Angular CLI, PHP 8.x, Apache y MySQL/MariaDB (por ejemplo, XAMPP).

```bash
# 1. Clona el repositorio dentro de htdocs de XAMPP
git clone https://github.com/saulfragua/rutalan.git
cd rutalan

# 2. Backend: base de datos y configuración
#    - Crea la base "rutalan" e importa backend/config/rutalan.sql
#    - Copia backend/config/key_example.php a backend/config/key.php y completa los valores

# 3. Frontend
cd frontend
npm install
ng serve        # http://localhost:4200

# 4. Servicio de WhatsApp (opcional)
cd ../whatsapp-api
npm install
```

> **Importante:** usa siempre datos de prueba. Nunca cargues datos reales de clientes ni fotos de cédulas en tu entorno de desarrollo.

## Flujo de trabajo

1. Haz un *fork* del repositorio (o crea una rama si eres colaborador).
2. Crea una rama desde `main` con un nombre descriptivo (ver más abajo).
3. Haz cambios pequeños y enfocados: **un Pull Request = un propósito**.
4. Verifica que todo compile y funcione (ver [Pruebas](#pruebas-antes-de-abrir-un-pull-request)).
5. Sube tu rama y abre un Pull Request hacia `main`.
6. Atiende los comentarios de la revisión con nuevos commits en la misma rama.

Mantén tu rama al día con `main` antes de abrir el Pull Request:

```bash
git fetch origin
git merge origin/main
```

## Convención de ramas

Formato: `tipo/descripcion-corta-en-minusculas`

| Prefijo | Uso | Ejemplo |
|---|---|---|
| `feature/` | Nueva funcionalidad | `feature/exportar-informe-gastos` |
| `fix/` | Corrección de errores | `fix/saldo-negativo-en-caja` |
| `docs/` | Solo documentación | `docs/manual-usuario` |
| `refactor/` | Mejora interna sin cambiar el comportamiento | `refactor/servicio-creditos` |
| `chore/` | Tareas de mantenimiento, dependencias, configuración | `chore/actualizar-angular` |

## Convención de commits

Usamos el formato **Conventional Commits** (ya presente en el historial del proyecto):

```
tipo(ámbito opcional): descripción corta en imperativo

Cuerpo opcional: qué cambió y por qué (no el cómo).
```

Tipos: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`, `security`.

Ejemplos:

```
feat(informes): agrega exportación a Excel y PDF
fix(caja): corrige el cálculo del total recolectado
security(api): exige token y valida el rol en todos los controladores
docs: agrega manual de usuario
```

Reglas:

- Primera línea de **máximo 72 caracteres**, sin punto final.
- Escribe en español (o en inglés), pero **no mezcles idiomas** dentro del mismo mensaje.
- Un commit debe dejar el proyecto funcionando. No mezcles formato masivo con cambios de lógica.
- **Nunca** hagas commit de `key.php`, `.env`, `backend/uploads/`, `node_modules/`, respaldos de base de datos ni archivos con datos personales.

## Estándares de código (resumen)

Consulta el documento completo en [docs/Estandares_de_Codificacion_Rutalan.docx](docs/Estandares_de_Codificacion_Rutalan.docx). Lo esencial:

**General**
- Identificadores del dominio en **español** (`consultarPorId`, `listaGastos`), código técnico en inglés cuando sea el estándar del lenguaje.
- Codificación UTF-8, fin de línea con salto final y sin espacios sobrantes (ver `.editorconfig`).
- Los comentarios explican el *por qué*, no lo que el código ya dice.

**PHP (backend)**
- Patrón MVC: el controlador valida y responde; el modelo ejecuta las consultas.
- **Siempre** sentencias preparadas con PDO. Nunca concatenes variables dentro del SQL.
- Cada controlador (excepto `loginControlador.php`) debe llamar a `requerirAuth()`; si la acción es solo de administrador, regístrala en `ACCIONES_SOLO_ADMIN` (`backend/config/auth.php`).
- Las acciones que modifican datos usan `POST`, no `GET`.
- No devuelvas contraseñas, hashes ni mensajes de excepción al cliente; regístralos con `error_log()`.
- Indentación de 4 espacios; clases en `PascalCase`, métodos y variables en `camelCase`.

**TypeScript / Angular (frontend)**
- Archivos en `kebab-case`, clases en `PascalCase`, métodos y propiedades en `camelCase`.
- Prettier: 2 espacios, comillas simples, ancho de línea 100.
- Las llamadas HTTP van solo en servicios (`src/app/servicios/`), nunca en las plantillas.
- Evita `any` en código nuevo: define interfaces. No dejes `console.log` en los commits.
- Cancela las suscripciones en `ngOnDestroy` y protege el uso de `window`/`localStorage` con `isPlatformBrowser`.
- Los permisos se aplican en el servidor; el `AdminGuard` solo mejora la navegación.

**Base de datos**
- Nombres de tablas y columnas en `snake_case`; llaves primarias `id_<entidad>`.
- Todo cambio de estructura debe reflejarse en `backend/config/rutalan.sql`, sin datos reales.

## Pruebas antes de abrir un Pull Request

Marca estos puntos en la descripción de tu PR:

- [ ] `cd frontend && ng build` compila sin errores.
- [ ] `ng test` pasa (si tocaste servicios o lógica con pruebas).
- [ ] Todos los archivos PHP modificados pasan `php -l archivo.php`.
- [ ] Probé el flujo afectado en el navegador con **rol administrador** y **rol cobrador**.
- [ ] Probé en pantalla de escritorio y en móvil (la app debe ser responsive).
- [ ] Probé los casos de error (campos vacíos, sin sesión, sin caja abierta, sin permisos).
- [ ] No hay `console.log`, código comentado ni credenciales en el diff.
- [ ] Actualicé el README, el manual de usuario o los estándares si el cambio lo requiere.

## Pull Requests y revisión

Un buen Pull Request incluye:

- **Título** con el mismo formato de commit (`feat(caja): ...`).
- **Descripción**: qué problema resuelve, cómo se probó y capturas de pantalla si cambia la interfaz (con los datos personales **ocultos**).
- Referencia al *issue* relacionado (`Closes #12`).
- Cambios acotados: si crece demasiado, divídelo.

Criterios de revisión:

- Funciona y no rompe otros módulos.
- Respeta los estándares de este documento.
- No introduce riesgos de seguridad ni expone datos personales.
- Es legible y mantenible por otra persona del equipo.

Se requiere al menos **una aprobación** de un mantenedor antes de integrar a `main`. Los mantenedores harán *squash* o *merge* según corresponda.

## Seguridad y datos personales

Rutalan maneja datos personales y fotos de documentos de identidad, protegidos por la **Ley 1581 de 2012** (Colombia).

- **No** subas al repositorio fotos de cédulas, bases de datos con datos reales, capturas sin ocultar datos ni archivos de sesión de WhatsApp.
- **No** subas secretos: `key.php`, claves de reCAPTCHA, credenciales de base de datos, `AUTH_SECRET`. Usa `key_example.php` como plantilla.
- Si expones un secreto por error, **avisa de inmediato** y rótalo; borrar el commit no basta porque queda en el historial.
- Para reportar una vulnerabilidad **no abras un issue público**: escribe directamente a los mantenedores ([@saulfragua](https://github.com/saulfragua) o [@hsmorenom](https://github.com/hsmorenom)) con el detalle para reproducirla.
- Ante la duda, trata cualquier dato de clientes como confidencial.

## Reportar errores y proponer mejoras

Al abrir un *issue*, incluye:

**Para un error**
- Qué esperabas que pasara y qué pasó realmente.
- Pasos para reproducirlo y rol con el que lo probaste (administrador o cobrador).
- Navegador y dispositivo, y capturas de pantalla **sin datos personales**.
- Mensajes de la consola del navegador o del módulo *Administrador → Errores*, si existen.

**Para una mejora**
- El problema que se quiere resolver y a quién beneficia.
- Una propuesta de solución y alternativas consideradas.

---

Gracias por contribuir a Rutalan.

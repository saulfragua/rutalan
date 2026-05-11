# Servicio de WhatsApp para Rutalan

## Descripción
Este servicio permite enviar mensajes automáticos de WhatsApp desde la aplicación Rutalan. Utiliza la librería `whatsapp-web.js` para conectarse a WhatsApp Web.

## Requisitos
- Node.js (versión 14 o superior)
- npm o yarn
- Una cuenta de WhatsApp (normal o Business)

## Instalación

1. Instalar dependencias:
```bash
npm install
```

2. Configurar variables de entorno (opcional):
```bash
# En Windows (PowerShell)
$env:ENABLE_WHATSAPP="true"
$env:PORT="3000"

# En Linux/Mac
export ENABLE_WHATSAPP=true
export PORT=3000
```

## Ejecución

### Modo Desarrollo (con WhatsApp habilitado)
```bash
# Windows PowerShell
$env:ENABLE_WHATSAPP="true"; node server.js

# Linux/Mac
ENABLE_WHATSAPP=true node server.js
```

### Modo Producción
```bash
node server.js
```

### Con PM2 (Recomendado para producción)
```bash
# Iniciar
pm2 start server.js --name rutalan-whatsapp --env ENABLE_WHATSAPP=true

# Ver estado
pm2 status rutalan-whatsapp

# Ver logs
pm2 logs rutalan-whatsapp

# Reiniciar
pm2 restart rutalan-whatsapp

# Detener
pm2 stop rutalan-whatsapp
```

## Endpoints de la API

### GET `/api/status`
Verifica el estado de la conexión de WhatsApp.

**Respuesta:**
```json
{
  "ready": true,
  "hasQR": false,
  "clientExists": true,
  "timestamp": "2024-01-01T00:00:00.000Z"
}
```

### GET `/api/qr`
Obtiene el código QR para escanear con WhatsApp.

**Respuesta:**
```json
{
  "success": true,
  "qr": "data:image/png;base64,...",
  "ready": false
}
```

### POST `/api/restart`
Reinicia la sesión de WhatsApp y genera un nuevo código QR.

**Respuesta:**
```json
{
  "success": true,
  "message": "Sesión reiniciada. Genera un nuevo QR."
}
```

### POST `/api/send-message`
Envía un mensaje de WhatsApp.

**Body:**
```json
{
  "to": "573001234567",
  "message": "Mensaje de prueba"
}
```

**Respuesta:**
```json
{
  "success": true,
  "messageId": "..."
}
```

## Uso en la Aplicación

1. **Iniciar el servicio:**
   ```bash
   ENABLE_WHATSAPP=true node server.js
   ```

2. **Acceder al módulo de Administrador** en la aplicación web

3. **Ir a la pestaña "WhatsApp"**

4. **Hacer clic en "Actualizar Código QR"**

5. **Escanear el código QR** con WhatsApp:
   - Abre WhatsApp en tu teléfono
   - Ve a Configuración → Dispositivos vinculados
   - Selecciona "Vincular un dispositivo"
   - Escanea el código QR que aparece en la pantalla

6. **Esperar confirmación** de que WhatsApp está conectado

## Solución de Problemas

### El servicio no inicia
- Verifica que el puerto 3000 esté disponible
- Revisa los logs del servidor
- Asegúrate de tener Node.js instalado correctamente

### No se genera el código QR
- Verifica que `ENABLE_WHATSAPP=true` esté configurado
- Reinicia el servicio
- Revisa los logs para ver errores

### WhatsApp no se conecta después de escanear
- Asegúrate de tener conexión a internet estable
- Verifica que WhatsApp Web esté habilitado en tu cuenta
- Intenta reiniciar la sesión desde la aplicación

### Error 404 al acceder a los endpoints
- Verifica que el servicio esté corriendo en el puerto 3000
- Asegúrate de usar las rutas correctas (`/api/status`, `/api/qr`, etc.)
- Revisa la configuración de `whatsappApiUrl` en `environment.ts`

## Notas Importantes

- El servicio debe estar corriendo para que la aplicación pueda enviar mensajes automáticos
- La primera vez que se escanea el QR, WhatsApp guarda la sesión localmente
- Si cambias de dispositivo o reinstalas WhatsApp, necesitarás escanear el QR nuevamente
- El servicio funciona tanto con WhatsApp Normal como WhatsApp Business

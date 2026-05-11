# Solución de Problemas - WhatsApp API

## Problema: "Inicializando cliente de WhatsApp" pero no carga

Si ves el mensaje `🔄 Inicializando cliente de WhatsApp (intento 1/5)...` pero el cliente no carga, sigue estos pasos:

### Paso 1: Verificar que no haya procesos bloqueando

1. **Detén todos los procesos de Node.js:**
   ```bash
   # Windows PowerShell
   Get-Process node | Stop-Process -Force
   
   # O busca procesos manualmente
   tasklist | findstr node
   taskkill /F /IM node.exe
   ```

2. **Verifica que no haya otros procesos usando los archivos:**
   - Cierra todas las ventanas de Chrome/Chromium
   - Cierra cualquier otra aplicación que pueda estar usando WhatsApp Web

### Paso 2: Limpiar la sesión bloqueada

1. **Ejecuta el script de limpieza:**
   ```bash
   cd whatsapp-api
   node limpiar-sesion.js
   ```

2. **O elimina manualmente la carpeta:**
   ```bash
   # Windows PowerShell
   Remove-Item -Recurse -Force .wwebjs_auth
   ```

### Paso 3: Verificar dependencias

1. **Verifica que Chrome/Chromium esté instalado:**
   - WhatsApp Web.js requiere Chrome o Chromium
   - Si no está instalado, instálalo desde: https://www.google.com/chrome/

2. **Reinstala las dependencias si es necesario:**
   ```bash
   cd whatsapp-api
   npm install
   ```

### Paso 4: Reiniciar el servicio

1. **Inicia el servicio con WhatsApp habilitado:**
   ```bash
   # Windows PowerShell
   $env:ENABLE_WHATSAPP="true"
   node server.js
   ```

2. **Espera a ver los logs:**
   - Deberías ver: `🔄 Inicializando cliente de WhatsApp...`
   - Luego: `⏳ Cargando: X% - mensaje`
   - Finalmente: `📲 CÓDIGO QR GENERADO` o `✅ CLIENTE DE WHATSAPP LISTO`

### Paso 5: Verificar logs

Si el problema persiste, revisa los logs para ver qué está pasando:

- **Si ves "Error de protocolo":** Los archivos de sesión están bloqueados, elimina `.wwebjs_auth`
- **Si ves "Target closed":** Hay un problema con Puppeteer, reinstala dependencias
- **Si no ves ningún log después de "Inicializando":** El proceso se quedó colgado, reinicia el servidor

### Solución Rápida

Si nada funciona, ejecuta estos comandos en orden:

```bash
# 1. Detener procesos
taskkill /F /IM node.exe

# 2. Limpiar sesión
cd whatsapp-api
Remove-Item -Recurse -Force .wwebjs_auth -ErrorAction SilentlyContinue

# 3. Reiniciar servicio
$env:ENABLE_WHATSAPP="true"
node server.js
```

### Verificar que funciona

1. Abre en el navegador: `http://localhost:3000/api/status`
2. Deberías ver un JSON con el estado
3. Si `ready: false` y `hasQR: true`, hay un QR disponible
4. Si `ready: true`, WhatsApp está conectado

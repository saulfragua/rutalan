/**
 * Servicio API de WhatsApp para Rutalan
 * 
 * IMPORTANTE SOBRE TIPOS DE CUENTA:
 * - whatsapp-web.js funciona IGUAL con WhatsApp Normal y WhatsApp Business
 * - No hay diferencia técnica en el código para usar uno u otro
 * - Puedes usar cualquier tipo de cuenta que tengas disponible
 * - El error "markedUnread" es un bug conocido de la librería que ocurre con ambos tipos
 * 
 * RECOMENDACIÓN:
 * - Usa WhatsApp Business si planeas enviar muchos mensajes comerciales
 * - Usa WhatsApp Normal si es para uso personal o pocos mensajes
 * - Ambos funcionarán correctamente con este código
 */

// Configurar zona horaria de Colombia (GMT-5) para Node.js
process.env.TZ = 'America/Bogota';

const express = require("express");
const { Client, LocalAuth } = require("whatsapp-web.js");
const qrcode = require("qrcode-terminal");
const qrcodeLib = require("qrcode");

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// CORS - Configuración para producción y desarrollo
const isProduction = process.env.NODE_ENV === 'production' || 
                     process.env.PRODUCTION === 'true' ||
                     process.cwd().includes('production') ||
                     (process.env.HOST && process.env.HOST.includes('rutalan.cloud'));

app.use((req, res, next) => {
  // En producción, permitir solo los dominios específicos
  if (isProduction) {
    const origin = req.headers.origin;
    const allowedOrigins = [
      'https://rutalan.cloud',
      'https://www.rutalan.cloud',
      'http://rutalan.cloud',
      'http://www.rutalan.cloud'
    ];
    
    if (origin && allowedOrigins.includes(origin)) {
      res.header("Access-Control-Allow-Origin", origin);
    } else {
      // Si no hay origin (petición directa), permitir desde rutalan.cloud
      res.header("Access-Control-Allow-Origin", "https://rutalan.cloud");
    }
  } else {
    // En desarrollo, permitir todo
    res.header("Access-Control-Allow-Origin", "*");
  }
  
  res.header("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
  res.header("Access-Control-Allow-Headers", "Content-Type, Authorization");
  res.header("Access-Control-Allow-Credentials", "true");
  
  if (req.method === "OPTIONS") {
    return res.sendStatus(200);
  }
  next();
});

// Variables globales
let qrCodeData = null;
let clientReady = false;
let client = null;

let intentosInicializacion = 0;
const maxIntentosInicializacion = 5;
let estaInicializando = false;

function inicializarClienteWhatsApp() {
  // Evitar múltiples inicializaciones simultáneas
  if (estaInicializando) {
    console.log("⚠️ Ya hay una inicialización en curso, esperando...");
    return;
  }
  
  try {
    estaInicializando = true;
    
    // Destruir cliente anterior si existe
    if (client) {
      try {
        console.log("🔄 Destruyendo cliente anterior...");
        client.destroy().catch(err => {
          console.log("⚠️ Error al destruir cliente:", err.message);
        });
        client = null;
      } catch (e) {
        console.log("⚠️ Error al destruir cliente anterior:", e.message);
        client = null;
      }
    }
    
    // Limpiar estado
    qrCodeData = null;
    clientReady = false;
    
    console.log("⏳ Esperando 3 segundos antes de inicializar nuevo cliente...");
    console.log("   Esto permite que los archivos de sesión se liberen");
    
    // Esperar más tiempo para asegurar que los archivos se liberen
    setTimeout(() => {
      continuarInicializacion();
    }, 3000);
    
    return;
  } catch (error) {
    console.error("❌ Error al preparar inicialización:", error.message);
    estaInicializando = false;
  }
}

function continuarInicializacion() {
  try {
    // Verificar si ya hay un cliente activo
    if (client && clientReady) {
      console.log("✅ Ya hay un cliente activo y conectado, no es necesario inicializar");
      estaInicializando = false;
      return;
    }

    // Limpiar estado
    clientReady = false;
    qrCodeData = null;
    intentosInicializacion++;

    console.log(`🔄 Inicializando cliente de WhatsApp (intento ${intentosInicializacion}/${maxIntentosInicializacion})...`);
    console.log("   - Limpiando estado anterior...");
    console.log("   - Creando nuevo cliente...");

    client = new Client({
      puppeteer: {
        headless: true,
        args: [
          '--no-sandbox',
          '--disable-setuid-sandbox',
          '--disable-dev-shm-usage',
          '--disable-accelerated-2d-canvas',
          '--no-first-run',
          '--disable-gpu',
          '--disable-web-security',
          '--disable-features=IsolateOrigins,site-per-process',
          '--disable-site-isolation-trials'
        ],
        // Configuración adicional para estabilidad
        ignoreDefaultArgs: ['--disable-extensions'],
        timeout: 60000, // 60 segundos de timeout
        protocolTimeout: 60000
      },
      // LocalAuth para persistir la sesión
      authStrategy: new LocalAuth({
        clientId: "rutalan-whatsapp"
      }),
      // Configuración adicional del cliente
      webVersionCache: {
        type: 'remote',
        remotePath: 'https://raw.githubusercontent.com/wppconnect-team/wa-version/main/html/2.2413.51-beta.html',
      }
    });

    client.on("qr", (qr) => {
      console.log("📲 ========================================");
      console.log("📲 CÓDIGO QR GENERADO - Escanea con WhatsApp");
      console.log("📲 ========================================");
      qrcode.generate(qr, { small: true });
      qrCodeData = qr;
      clientReady = false;
      intentosInicializacion = 0; // Resetear contador si se genera QR
      estaInicializando = false; // Permitir nuevas inicializaciones
      console.log("✅ QR guardado en memoria, disponible en /api/qr");
      console.log("📱 Instrucciones:");
      console.log("   1. Abre WhatsApp en tu teléfono");
      console.log("   2. Ve a Configuración > Dispositivos vinculados");
      console.log("   3. Toca 'Vincular un dispositivo'");
      console.log("   4. Escanea el código QR que aparece en la pantalla");
    });

    client.on("authenticated", () => {
      console.log("✅ Cliente autenticado correctamente");
      console.log("⏳ Esperando sincronización completa (evento 'ready')...");
    });

    client.on("ready", () => {
      console.log("✅ ========================================");
      console.log("✅ CLIENTE DE WHATSAPP LISTO Y CONECTADO");
      console.log("✅ ========================================");
      console.log("✅ WhatsApp está sincronizado y listo para enviar mensajes");
      clientReady = true;
      qrCodeData = null; // Limpiar QR ya que está conectado
      intentosInicializacion = 0; // Resetear contador al estar listo
      estaInicializando = false; // Permitir nuevas inicializaciones
      console.log("✅ Estado actualizado: ready=true, qrCodeData=null");
      
      // Verificar estado del cliente para confirmar
      client.getState().then(state => {
        console.log("✅ Estado confirmado del cliente:", state);
        if (state !== 'CONNECTED') {
          console.warn("⚠️ Advertencia: El evento 'ready' se disparó pero el estado es:", state);
        }
      }).catch(err => {
        console.log("⚠️ No se pudo verificar el estado del cliente:", err.message);
      });
    });

    client.on("disconnected", (reason) => {
      console.log("⚠️ Cliente desconectado:", reason);
      clientReady = false;
      qrCodeData = null;
      client = null;
      estaInicializando = false;
      
      // Intentar reconectar después de 5 segundos
      setTimeout(() => {
        console.log("🔄 Intentando reconectar...");
        intentosInicializacion = 0; // Resetear contador para reconexión
        inicializarClienteWhatsApp();
      }, 5000);
    });

    client.on("auth_failure", (msg) => {
      console.error("❌ Error de autenticación:", msg);
      clientReady = false;
      qrCodeData = null;
      intentosInicializacion = 0; // Resetear contador
      estaInicializando = false;
    });

    client.on("loading_screen", (percent, message) => {
      console.log(`⏳ Cargando: ${percent}% - ${message}`);
    });

    client.on("change_state", (state) => {
      console.log(`🔄 Cambio de estado: ${state}`);
      // Si el estado cambia a CONNECTED, actualizar clientReady
      if (state === 'CONNECTED') {
        console.log("✅ Estado cambiado a CONNECTED, actualizando clientReady...");
        clientReady = true;
        qrCodeData = null;
        estaInicializando = false;
      } else if (state === 'DISCONNECTED' || state === 'CONNECTING') {
        // No actualizar clientReady aquí, esperar al evento 'ready'
        console.log(`⏳ Estado: ${state}, esperando evento 'ready'...`);
      } else if (state === 'UNPAIRED' || state === 'UNPAIRED_IDLE') {
        console.log(`⚠️ Estado: ${state}, puede requerir nuevo QR`);
        clientReady = false;
        qrCodeData = null;
      }
    });

    // Timeout para detectar si la inicialización se queda colgada
    const timeoutInicializacion = setTimeout(() => {
      if (!clientReady && !qrCodeData && estaInicializando) {
        console.warn("⚠️ ========================================");
        console.warn("⚠️ ADVERTENCIA: La inicialización está tomando mucho tiempo");
        console.warn("⚠️ ========================================");
        console.warn("   Esto puede indicar:");
        console.warn("   1. Problema con Puppeteer/Chromium");
        console.warn("   2. Archivos de sesión bloqueados");
        console.warn("   3. Problema de conexión a internet");
        console.warn("   Verifica los logs anteriores para más detalles");
      }
    }, 120000); // 2 minutos

    // Limpiar timeout cuando se complete la inicialización
    const limpiarTimeout = () => {
      clearTimeout(timeoutInicializacion);
    };

    console.log("🚀 Iniciando inicialización del cliente...");
    console.log("   - Timeout configurado: 60 segundos");
    console.log("   - Modo headless: true");
    console.log("   - Esperando eventos: qr, ready, authenticated...");
    
    // Manejar errores de inicialización con reintentos
    client.initialize().catch((error) => {
      console.error("❌ ========================================");
      console.error("❌ ERROR AL INICIALIZAR CLIENTE");
      console.error("❌ ========================================");
      console.error("   Mensaje:", error.message);
      console.error("   Stack:", error.stack);
      clientReady = false;
      qrCodeData = null;
      limpiarTimeout();
      
      // Limpiar cliente completamente
      if (client) {
        try {
          client.destroy().catch(() => {});
        } catch (e) {
          console.log("⚠️ Error al destruir cliente:", e.message);
        }
      }
      client = null;
      estaInicializando = false;
      
      // Reintentar si no hemos alcanzado el máximo
      if (intentosInicializacion < maxIntentosInicializacion) {
        console.log(`⚠️ Reintentando en 5 segundos... (${intentosInicializacion}/${maxIntentosInicializacion})`);
        setTimeout(() => {
          inicializarClienteWhatsApp();
        }, 5000);
      } else {
        console.error("❌ Máximo de intentos de inicialización alcanzado. Deteniendo...");
        console.error("   Último error:", error.message);
        intentosInicializacion = 0;
        estaInicializando = false;
      }
    });
  } catch (error) {
    console.error("❌ Error al crear cliente de WhatsApp:", error.message);
    console.error("   Stack:", error.stack);
    clientReady = false;
    qrCodeData = null;
    
    // Limpiar cliente si existe
    if (client) {
      try {
        client.destroy().catch(() => {});
      } catch (e) {
        console.log("⚠️ Error al destruir cliente en catch:", e.message);
      }
    }
    client = null;
    estaInicializando = false;
    
    // Reintentar si no hemos alcanzado el máximo
    if (intentosInicializacion < maxIntentosInicializacion) {
      setTimeout(() => {
        inicializarClienteWhatsApp();
      }, 5000);
    }
  }
}

// Inicializar cliente de WhatsApp solo si está habilitado
// (La inicialización también se hace en app.listen si está habilitado)
// Esta línea se mantiene para compatibilidad, pero se puede comentar si se desea
// inicializarClienteWhatsApp();

// Endpoint para obtener el QR
app.get("/api/qr", async (req, res) => {
  try {
    // Verificar si WhatsApp está habilitado
    const isDevelopment = process.env.NODE_ENV === 'development' || 
                          process.cwd().includes('htdocs') || 
                          process.cwd().includes('xampp');
    const ENABLE_WHATSAPP = process.env.ENABLE_WHATSAPP === 'true' || process.env.ENABLE_WHATSAPP === undefined;
    const whatsappEnabled = ENABLE_WHATSAPP || !isDevelopment;
    
    if (!whatsappEnabled) {
      return res.json({
        success: false,
        qr: null,
        ready: false,
        message: "WhatsApp está deshabilitado. Establece ENABLE_WHATSAPP=true para habilitarlo",
        disabled: true
      });
    }
    
    // Si el cliente no existe, inicializarlo
    if (!client && !estaInicializando) {
      console.log('🔄 Cliente no existe, inicializando...');
      inicializarClienteWhatsApp();
    }

    if (qrCodeData) {
      const qrImage = await qrcodeLib.toDataURL(qrCodeData, {
        width: 300,
        margin: 2
      });

      res.json({
        success: true,
        qr: qrImage,
        ready: false
      });
    } else if (clientReady) {
      res.json({
        success: true,
        qr: null,
        ready: true,
        message: "WhatsApp ya está conectado"
      });
    } else {
      res.json({
        success: false,
        qr: null,
        ready: false,
        message: "Esperando código QR..."
      });
    }
  } catch (error) {
    res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Endpoint para verificar estado
app.get("/api/status", async (req, res) => {
  try {
    // Verificar si WhatsApp está habilitado
    const isDevelopment = process.env.NODE_ENV === 'development' || 
                          process.cwd().includes('htdocs') || 
                          process.cwd().includes('xampp');
    const ENABLE_WHATSAPP = process.env.ENABLE_WHATSAPP === 'true' || process.env.ENABLE_WHATSAPP === undefined;
    const whatsappEnabled = ENABLE_WHATSAPP || !isDevelopment;
    
    if (!whatsappEnabled) {
      return res.json({
        ready: false,
        hasQR: false,
        clientExists: false,
        disabled: true,
        message: "WhatsApp está deshabilitado. Establece ENABLE_WHATSAPP=true para habilitarlo",
        timestamp: new Date().toISOString()
      });
    }
    
    // Si el cliente no existe, inicializarlo
    if (!client && !estaInicializando) {
      console.log('🔄 Cliente no existe, inicializando desde /status...');
      inicializarClienteWhatsApp();
    }

    // Verificar estado real del cliente si existe
    let estadoReal = null;
    if (client) {
      try {
        estadoReal = await client.getState();
        // Si el estado es CONNECTED pero clientReady es false, actualizarlo
        if (estadoReal === 'CONNECTED' && !clientReady) {
          console.log("⚠️ Estado del cliente es CONNECTED pero clientReady es false, actualizando...");
          clientReady = true;
          qrCodeData = null; // Limpiar QR cuando está conectado
          estaInicializando = false;
        }
        // Si el estado no es CONNECTED pero clientReady es true, actualizarlo
        if (estadoReal !== 'CONNECTED' && clientReady) {
          console.log(`⚠️ Estado del cliente es ${estadoReal} pero clientReady es true, actualizando...`);
          clientReady = false;
        }
      } catch (stateError) {
        // Si hay error al obtener el estado, usar el valor de clientReady
        console.log("⚠️ No se pudo obtener el estado del cliente:", stateError.message);
      }
    }

    // Log para debugging
    console.log("📊 Estado actual del servicio:");
    console.log("   - clientReady:", clientReady);
    console.log("   - qrCodeData:", qrCodeData ? "presente" : "null");
    console.log("   - client existe:", !!client);
    if (estadoReal) {
      console.log("   - estado real del cliente:", estadoReal);
    }
    
    res.json({
      ready: clientReady,
      hasQR: !!qrCodeData,
      clientExists: !!client,
      clientState: estadoReal || null,
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    res.status(500).json({
      ready: false,
      hasQR: false,
      error: error.message
    });
  }
});

// Endpoint para enviar mensajes
app.post("/api/send-message", async (req, res) => {
  try {
    // Verificar si WhatsApp está habilitado
    const isDevelopment = process.env.NODE_ENV === 'development' || 
                          process.cwd().includes('htdocs') || 
                          process.cwd().includes('xampp');
    const ENABLE_WHATSAPP = process.env.ENABLE_WHATSAPP === 'true' || process.env.ENABLE_WHATSAPP === undefined;
    const whatsappEnabled = ENABLE_WHATSAPP || !isDevelopment;
    
    if (!whatsappEnabled) {
      return res.status(503).json({
        success: false,
        error: "WhatsApp está deshabilitado. Para habilitar, establece ENABLE_WHATSAPP=true"
      });
    }

    const { to, message } = req.body;

    console.log("📨 Recibida solicitud de envío:");
    console.log("   - Teléfono recibido:", to);
    console.log("   - Mensaje:", message ? message.substring(0, 50) + "..." : "vacío");
    console.log("   - Cliente listo:", clientReady);
    console.log("   - Cliente existe:", !!client);

    if (!to || !message) {
      console.error("❌ Faltan parámetros: to=" + to + ", message=" + (message ? "presente" : "vacío"));
      return res.status(400).json({ success: false, error: "Faltan parámetros: to y message son requeridos" });
    }

    // Verificar que el cliente existe
    if (!client) {
      console.error("❌ Cliente de WhatsApp no está inicializado");
      return res.status(503).json({
        success: false,
        error: "Cliente de WhatsApp no está inicializado. Reinicia el servicio."
      });
    }

    // Verificar que el cliente esté listo (pero intentar verificar estado real)
    let estadoReal = null;
    try {
      estadoReal = await client.getState();
      console.log("   - Estado del cliente:", estadoReal);
      
      // Si el estado es CONNECTED pero clientReady es false, actualizarlo
      if (estadoReal === 'CONNECTED' && !clientReady) {
        console.log("⚠️ Estado real es CONNECTED pero clientReady es false, actualizando...");
        clientReady = true;
      }
      
      // Si el estado no es CONNECTED, verificar si realmente está desconectado
      if (estadoReal !== 'CONNECTED') {
        console.error("❌ Cliente no está en estado CONNECTED. Estado actual:", estadoReal);
        // Actualizar el flag si el estado no es CONNECTED
        if (estadoReal === 'DISCONNECTED' || estadoReal === 'CONNECTING') {
          clientReady = false;
        }
        return res.status(503).json({
          success: false,
          error: `WhatsApp no está conectado. Estado: ${estadoReal}. Escanea el QR nuevamente.`
        });
      }
    } catch (stateError) {
      console.error("⚠️ Error al verificar estado del cliente:", stateError.message);
      // Si no se puede verificar el estado pero clientReady es true, intentar enviar de todas formas
      if (!clientReady) {
        console.error("❌ WhatsApp no está conectado. Estado: ready=" + clientReady);
        return res.status(503).json({
          success: false,
          error: "WhatsApp no está conectado. Escanea el QR primero. Estado actual: " + (clientReady ? "conectado" : "no conectado")
        });
      }
      // Si clientReady es true pero no se pudo verificar el estado, continuar de todas formas
      console.log("⚠️ No se pudo verificar estado pero clientReady es true, continuando...");
    }

    // Formatear el número correctamente para WhatsApp
    let chatId;
    if (to.includes("@c.us")) {
      chatId = to;
    } else {
      // Asegurarse de que el número solo contenga dígitos
      const numeroLimpio = to.replace(/[^0-9]/g, '');
      
      // Validar que el número tenga al menos 10 dígitos
      if (numeroLimpio.length < 10) {
        console.error("❌ Número de teléfono inválido:", numeroLimpio);
        return res.status(400).json({
          success: false,
          error: "Número de teléfono inválido. Debe tener al menos 10 dígitos."
        });
      }
      
      chatId = `${numeroLimpio}@c.us`;
    }

    console.log("📤 Enviando mensaje a chatId:", chatId);
    
    // Verificar que el cliente tenga el método sendMessage disponible
    if (typeof client.sendMessage !== 'function') {
      console.error("❌ El método sendMessage no está disponible en el cliente");
      return res.status(503).json({
        success: false,
        error: "El cliente de WhatsApp no está completamente inicializado. Método sendMessage no disponible."
      });
    }
    
    // SOLUCIÓN DEFINITIVA: Código limpio y directo
    // Obtener el chat y enviar el mensaje directamente
    // No necesitamos marcar como leído para mensajes automáticos
    try {
      const chat = await client.getChatById(chatId);
      await chat.sendMessage(message, {
        sendSeen: false  // No marcar como leído automáticamente
      });
      
      console.log("✅ Mensaje enviado exitosamente a:", chatId);
      
      return res.json({
        success: true,
        to: chatId,
        message: "Mensaje enviado correctamente"
      });
      
    } catch (error) {
      console.error("❌ Error al enviar mensaje:", error.message);
      
      // Manejar errores específicos
      const errorMsg = error.message || String(error);
      
      if (errorMsg.includes('not found') || errorMsg.includes('404')) {
        return res.status(404).json({
          success: false,
          error: `El número no está registrado en WhatsApp o no es válido. Verifica que el número sea correcto.`
        });
      }
      
      return res.status(500).json({
        success: false,
        error: error.message || "Error al enviar mensaje"
      });
    }
  } catch (error) {
    console.error("❌ Error enviando mensaje:");
    console.error("   - Tipo:", error.constructor ? error.constructor.name : typeof error);
    console.error("   - Mensaje:", error.message || String(error));
    console.error("   - Stack:", error.stack || 'No disponible');
    
    // Determinar el código de estado apropiado
    let statusCode = 500;
    let errorMessage = error.message || String(error) || "Error desconocido al enviar mensaje";
    
    // Mensajes de error comunes de whatsapp-web.js
    if (errorMessage.includes("Timeout") || errorMessage.includes("timeout")) {
      statusCode = 504; // Gateway Timeout
      errorMessage = "El envío tardó demasiado. Verifica tu conexión a internet.";
    } else if (errorMessage.includes("not found") || errorMessage.includes("404") || errorMessage.includes("no está registrado")) {
      statusCode = 404;
      errorMessage = "El número de teléfono no está registrado en WhatsApp o no es válido. Verifica que el número sea correcto.";
    } else if (errorMessage.includes("not connected") || errorMessage.includes("disconnected")) {
      statusCode = 503;
      errorMessage = "WhatsApp no está conectado. Escanea el QR nuevamente.";
    } else if (errorMessage.includes("Evaluation failed") || errorMessage.includes("Protocol error")) {
      statusCode = 503;
      errorMessage = "Error de comunicación con WhatsApp. Intenta reiniciar la sesión.";
    } else if (errorMessage.includes("markedUnread") || errorMessage.includes("Cannot read properties")) {
      // Con sendSeen: false, este error no debería ocurrir, pero lo mantenemos por compatibilidad
      statusCode = 503;
      errorMessage = "Error temporal al enviar mensaje. Intenta nuevamente en unos segundos. Si el problema persiste, verifica que el número sea correcto.";
    }
    
    // Asegurarse de que la respuesta se envíe correctamente
    if (!res.headersSent) {
      res.status(statusCode).json({ 
        success: false, 
        error: errorMessage
      });
    } else {
      console.error("⚠️ No se pudo enviar respuesta de error porque los headers ya fueron enviados");
    }
  }
});

// Endpoint para reiniciar sesión y generar nuevo QR
app.post("/api/restart", async (req, res) => {
  let responseSent = false;
  
  try {
    console.log("🔄 ========================================");
    console.log("🔄 REINICIANDO SESIÓN DE WHATSAPP");
    console.log("🔄 ========================================");
    
    // Guardar referencia al cliente antes de limpiarlo
    const clientToDestroy = client;
    
    // Limpiar estado primero para evitar condiciones de carrera
    qrCodeData = null;
    clientReady = false;
    estaInicializando = false;
    intentosInicializacion = 0;
    client = null; // Establecer null inmediatamente
    
    // Destruir cliente si existe (de forma segura y sin bloquear)
    if (clientToDestroy) {
      console.log("🗑️ Destruyendo cliente de WhatsApp...");
      
      // Intentar logout de forma segura (no crítico si falla, ejecutar en background)
      if (typeof clientToDestroy.logout === 'function') {
        // Ejecutar logout en background sin esperar
        clientToDestroy.logout().then(() => {
          console.log("✅ Cliente desconectado (logout)");
        }).catch((logoutError) => {
          console.log("⚠️ Error al hacer logout (no crítico):", logoutError.message);
        });
      }
      
      // Destruir el cliente de forma segura (no crítico si falla, ejecutar en background)
      if (typeof clientToDestroy.destroy === 'function') {
        // Ejecutar destroy en background sin esperar
        clientToDestroy.destroy().then(() => {
          console.log("✅ Cliente destruido correctamente");
        }).catch((destroyError) => {
          console.log("⚠️ Error al destruir cliente (no crítico):", destroyError.message);
        });
      }
    }
    
    // Limpiar archivos de sesión si es posible
    try {
      const fs = require('fs');
      const path = require('path');
      const sessionPath = path.join(__dirname, '.wwebjs_auth');
      
      if (fs.existsSync(sessionPath)) {
        console.log("🗑️ Eliminando archivos de sesión...");
        
        // Usar método compatible con versiones antiguas de Node.js
        try {
          // Intentar con rmSync primero (Node.js 14.14.0+)
          if (typeof fs.rmSync === 'function') {
            fs.rmSync(sessionPath, { recursive: true, force: true });
            console.log("✅ Archivos de sesión eliminados correctamente (rmSync)");
          } else if (typeof fs.rmdirSync === 'function') {
            // Fallback: usar rmdirSync recursivo (Node.js 12.10.0+)
            try {
              fs.rmdirSync(sessionPath, { recursive: true });
              console.log("✅ Archivos de sesión eliminados correctamente (rmdirSync)");
            } catch (rmdirError) {
              console.log("⚠️ No se pudieron eliminar los archivos de sesión con rmdirSync:", rmdirError.message);
              console.log("   Esto es normal si los archivos están en uso");
              console.log("   Puedes eliminarlos manualmente desde: " + sessionPath);
            }
          } else {
            console.log("⚠️ No se pudo eliminar archivos de sesión: métodos no disponibles");
            console.log("   Puedes eliminarlos manualmente desde: " + sessionPath);
          }
        } catch (rmError) {
          console.log("⚠️ No se pudieron eliminar los archivos de sesión:", rmError.message);
          console.log("   Esto es normal si los archivos están en uso o no existen");
          console.log("   Puedes eliminarlos manualmente desde: " + sessionPath);
        }
      } else {
        console.log("ℹ️ No hay archivos de sesión para eliminar");
      }
    } catch (fsError) {
      console.log("⚠️ Error al acceder al sistema de archivos:", fsError.message);
      console.log("   Continuando sin eliminar archivos de sesión...");
      // No es crítico, continuar de todas formas
    }
    
    // Responder inmediatamente antes de inicializar nuevo cliente
    if (!responseSent && !res.headersSent) {
      responseSent = true;
      res.json({ 
        success: true, 
        message: "Sesión reiniciada correctamente. Se generará un nuevo código QR en breve." 
      });
    }
    
    // Esperar antes de reiniciar
    console.log("⏳ Esperando 5 segundos antes de inicializar nuevo cliente...");
    setTimeout(() => {
      console.log("🔄 Inicializando nuevo cliente...");
      inicializarClienteWhatsApp();
    }, 5000);
  } catch (error) {
    console.error("❌ Error al reiniciar sesión:", error);
    console.error("   Tipo:", error.constructor ? error.constructor.name : typeof error);
    console.error("   Mensaje:", error.message);
    console.error("   Stack:", error.stack);
    
    // Asegurarse de limpiar el estado incluso si hay error
    try {
      qrCodeData = null;
      clientReady = false;
      client = null;
      estaInicializando = false;
      intentosInicializacion = 0;
    } catch (cleanupError) {
      console.error("⚠️ Error al limpiar estado:", cleanupError.message);
    }
    
    // Asegurarse de que la respuesta se envíe solo si los headers no fueron enviados
    if (!responseSent && !res.headersSent) {
      responseSent = true;
      res.status(500).json({ 
        success: false, 
        error: error.message || "Error desconocido al reiniciar sesión",
        message: "El reinicio puede haber fallado parcialmente. Intenta obtener un nuevo QR manualmente."
      });
    } else {
      console.error("⚠️ No se pudo enviar respuesta de error porque los headers ya fueron enviados");
    }
  }
});

// Endpoints adicionales sin prefijo /api/ para compatibilidad (redirigen a /api/)
app.get("/status", (req, res) => {
  // Redirigir a /api/status
  // isProduction ya está definido arriba en CORS
  const protocol = isProduction ? 'https' : 'http';
  const domain = isProduction ? 'rutalan.cloud' : '127.0.0.1';
  fetch(`${protocol}://${domain}:${process.env.PORT || 3000}/api/status`)
    .then(response => response.json())
    .then(data => res.json(data))
    .catch(() => {
      // Si falla, usar el mismo código que /api/status
      const isDevelopment = process.env.NODE_ENV === 'development' || 
                            process.cwd().includes('htdocs') || 
                            process.cwd().includes('xampp');
      const ENABLE_WHATSAPP = process.env.ENABLE_WHATSAPP === 'true' || process.env.ENABLE_WHATSAPP === undefined;
      const whatsappEnabled = ENABLE_WHATSAPP || !isDevelopment;
      
      if (!whatsappEnabled) {
        return res.json({
          ready: false,
          hasQR: false,
          clientExists: false,
          disabled: true,
          message: "WhatsApp está deshabilitado. Establece ENABLE_WHATSAPP=true para habilitarlo",
          timestamp: new Date().toISOString()
        });
      }
      
      res.json({
        ready: clientReady,
        hasQR: !!qrCodeData,
        clientExists: !!client,
        timestamp: new Date().toISOString()
      });
    });
});

app.get("/qr", async (req, res) => {
  // Redirigir a /api/qr usando el mismo código
  const isDevelopment = process.env.NODE_ENV === 'development' || 
                        process.cwd().includes('htdocs') || 
                        process.cwd().includes('xampp');
  const ENABLE_WHATSAPP = process.env.ENABLE_WHATSAPP === 'true' || process.env.ENABLE_WHATSAPP === undefined;
  const whatsappEnabled = ENABLE_WHATSAPP || !isDevelopment;
  
  if (!whatsappEnabled) {
    return res.json({
      success: false,
      qr: null,
      ready: false,
      message: "WhatsApp está deshabilitado. Establece ENABLE_WHATSAPP=true para habilitarlo",
      disabled: true
    });
  }
  
  if (!client && !estaInicializando) {
    console.log('🔄 Cliente no existe, inicializando desde /qr...');
    inicializarClienteWhatsApp();
  }
  
  if (qrCodeData) {
    const qrImage = await qrcodeLib.toDataURL(qrCodeData, {
      width: 300,
      margin: 2
    });
    res.json({
      success: true,
      qr: qrImage,
      ready: false
    });
  } else if (clientReady) {
    res.json({
      success: true,
      qr: null,
      ready: true,
      message: "WhatsApp ya está conectado"
    });
  } else {
    res.json({
      success: false,
      qr: null,
      ready: false,
      message: "Esperando código QR..."
    });
  }
});

// Endpoint para reiniciar servicios de API (reinicio completo)
app.post("/api/restart-service", async (req, res) => {
  let responseSent = false;
  
  try {
    console.log("🔄 ========================================");
    console.log("🔄 REINICIANDO SERVICIOS DE API DE WHATSAPP");
    console.log("🔄 ========================================");
    
    // Guardar referencia al cliente antes de limpiarlo
    const clientToDestroy = client;
    
    // Limpiar estado primero para evitar condiciones de carrera
    qrCodeData = null;
    clientReady = false;
    estaInicializando = false;
    intentosInicializacion = 0;
    client = null; // Establecer null inmediatamente
    
    // Destruir cliente si existe (de forma segura y sin bloquear)
    if (clientToDestroy) {
      console.log("🗑️ Destruyendo cliente de WhatsApp...");
      
      // Intentar logout de forma segura (no crítico si falla, ejecutar en background)
      if (typeof clientToDestroy.logout === 'function') {
        // Ejecutar logout en background sin esperar
        clientToDestroy.logout().then(() => {
          console.log("✅ Cliente desconectado (logout)");
        }).catch((logoutError) => {
          console.log("⚠️ Error al hacer logout (no crítico):", logoutError.message);
        });
      }
      
      // Destruir el cliente de forma segura (no crítico si falla, ejecutar en background)
      if (typeof clientToDestroy.destroy === 'function') {
        // Ejecutar destroy en background sin esperar
        clientToDestroy.destroy().then(() => {
          console.log("✅ Cliente destruido correctamente");
        }).catch((destroyError) => {
          console.log("⚠️ Error al destruir cliente (no crítico):", destroyError.message);
        });
      }
    }
    
    // Limpiar archivos de sesión si es posible
    try {
      const fs = require('fs');
      const path = require('path');
      const sessionPath = path.join(__dirname, '.wwebjs_auth');
      
      if (fs.existsSync(sessionPath)) {
        console.log("🗑️ Eliminando archivos de sesión...");
        
        // Usar método compatible con versiones antiguas de Node.js
        try {
          // Intentar con rmSync primero (Node.js 14.14.0+)
          if (typeof fs.rmSync === 'function') {
            fs.rmSync(sessionPath, { recursive: true, force: true });
            console.log("✅ Archivos de sesión eliminados correctamente (rmSync)");
          } else if (typeof fs.rmdirSync === 'function') {
            // Fallback: usar rmdirSync recursivo (Node.js 12.10.0+)
            try {
              fs.rmdirSync(sessionPath, { recursive: true });
              console.log("✅ Archivos de sesión eliminados correctamente (rmdirSync)");
            } catch (rmdirError) {
              console.log("⚠️ No se pudieron eliminar los archivos de sesión con rmdirSync:", rmdirError.message);
              console.log("   Esto es normal si los archivos están en uso");
              console.log("   Puedes eliminarlos manualmente desde: " + sessionPath);
            }
          } else {
            console.log("⚠️ No se pudo eliminar archivos de sesión: métodos no disponibles");
            console.log("   Puedes eliminarlos manualmente desde: " + sessionPath);
          }
        } catch (rmError) {
          console.log("⚠️ No se pudieron eliminar los archivos de sesión:", rmError.message);
          console.log("   Esto es normal si los archivos están en uso o no existen");
          console.log("   Puedes eliminarlos manualmente desde: " + sessionPath);
        }
      } else {
        console.log("ℹ️ No hay archivos de sesión para eliminar");
      }
    } catch (fsError) {
      console.log("⚠️ Error al acceder al sistema de archivos:", fsError.message);
      console.log("   Continuando sin eliminar archivos de sesión...");
      // No es crítico, continuar de todas formas
    }
    
    // Responder inmediatamente antes de inicializar nuevo cliente
    if (!responseSent && !res.headersSent) {
      responseSent = true;
      res.json({ 
        success: true, 
        message: "Servicios de API reiniciados correctamente. Se generará un nuevo código QR en breve." 
      });
    }
    
    // Esperar antes de reiniciar
    console.log("⏳ Esperando 5 segundos antes de inicializar nuevo cliente...");
    setTimeout(() => {
      console.log("🔄 Inicializando nuevo cliente después del reinicio de servicios...");
      inicializarClienteWhatsApp();
    }, 5000);
  } catch (error) {
    console.error("❌ Error al reiniciar servicios:", error);
    console.error("   Tipo:", error.constructor ? error.constructor.name : typeof error);
    console.error("   Mensaje:", error.message);
    console.error("   Stack:", error.stack);
    
    // Asegurarse de limpiar el estado incluso si hay error
    try {
      qrCodeData = null;
      clientReady = false;
      client = null;
      estaInicializando = false;
      intentosInicializacion = 0;
    } catch (cleanupError) {
      console.error("⚠️ Error al limpiar estado:", cleanupError.message);
    }
    
    // Asegurarse de que la respuesta se envíe solo si los headers no fueron enviados
    if (!responseSent && !res.headersSent) {
      responseSent = true;
      res.status(500).json({ 
        success: false, 
        error: error.message || "Error desconocido al reiniciar servicios",
        message: "El reinicio de servicios puede haber fallado parcialmente. Intenta obtener un nuevo QR manualmente."
      });
    } else {
      console.error("⚠️ No se pudo enviar respuesta de error porque los headers ya fueron enviados");
    }
  }
});

app.post("/restart", async (req, res) => {
  // Redirigir a /api/restart usando el mismo código
  try {
    console.log("🔄 Reiniciando sesión de WhatsApp (endpoint /restart)...");
    
    // Destruir cliente si existe
    if (client) {
      try {
        console.log("🗑️ Destruyendo cliente de WhatsApp...");
        
        // Desconectar el cliente primero
        try {
          await client.logout();
          console.log("✅ Cliente desconectado (logout)");
        } catch (logoutError) {
          console.log("⚠️ Error al hacer logout (puede ser normal):", logoutError.message);
        }
        
        await client.destroy();
        console.log("✅ Cliente destruido correctamente");
      } catch (error) {
        console.error("⚠️ Error al destruir cliente:", error.message);
      }
    }
    
    // Limpiar estado completamente
    qrCodeData = null;
    clientReady = false;
    client = null;
    estaInicializando = false;
    intentosInicializacion = 0;
    
    // Limpiar archivos de sesión si es posible
    const fs = require('fs');
    const path = require('path');
    const sessionPath = path.join(__dirname, '.wwebjs_auth');
    
    try {
      if (fs.existsSync(sessionPath)) {
        console.log("🗑️ Eliminando archivos de sesión...");
        fs.rmSync(sessionPath, { recursive: true, force: true });
        console.log("✅ Archivos de sesión eliminados correctamente");
      }
    } catch (fsError) {
      console.log("⚠️ No se pudieron eliminar los archivos de sesión:", fsError.message);
    }
    
    // Esperar un momento antes de inicializar nuevo cliente
    setTimeout(() => {
      console.log("🔄 Inicializando nuevo cliente...");
      inicializarClienteWhatsApp();
    }, 2000);
    
    res.json({ 
      success: true, 
      message: "Sesión reiniciada correctamente. Se generará un nuevo código QR en breve." 
    });
  } catch (error) {
    console.error("❌ Error al reiniciar sesión:", error);
    console.error("   Tipo:", error.constructor ? error.constructor.name : typeof error);
    console.error("   Mensaje:", error.message);
    console.error("   Stack:", error.stack);
    
    // Asegurarse de limpiar el estado incluso si hay error
    try {
      qrCodeData = null;
      clientReady = false;
      client = null;
      estaInicializando = false;
      intentosInicializacion = 0;
    } catch (cleanupError) {
      console.error("⚠️ Error al limpiar estado:", cleanupError.message);
    }
    
    // Responder con error pero no crítico
    if (!res.headersSent) {
    // Asegurarse de que la respuesta se envíe solo si los headers no fueron enviados
    if (!res.headersSent) {
      res.status(500).json({ 
        success: false, 
        error: error.message || "Error desconocido al reiniciar sesión",
        message: "El reinicio puede haber fallado parcialmente. Intenta obtener un nuevo QR manualmente.",
        details: process.env.NODE_ENV === 'development' ? error.stack : undefined
      });
    } else {
      console.error("⚠️ No se pudo enviar respuesta de error porque los headers ya fueron enviados");
    }
    }
  }
});

// Detectar si estamos en desarrollo (carpeta htdocs) o producción
const ENABLE_WHATSAPP = process.env.ENABLE_WHATSAPP === 'true' || process.env.ENABLE_WHATSAPP === undefined; // Por defecto true si no se especifica
const NODE_ENV = process.env.NODE_ENV || 'development';

// En desarrollo local (htdocs), permitir WhatsApp si está explícitamente habilitado
const isDevelopment = NODE_ENV === 'development' || 
                      process.cwd().includes('htdocs') || 
                      process.cwd().includes('xampp');

// Habilitar WhatsApp si:
// 1. ENABLE_WHATSAPP es explícitamente 'true', O
// 2. No estamos en desarrollo, O
// 3. ENABLE_WHATSAPP no está definido (por defecto habilitado)
const shouldEnableWhatsApp = ENABLE_WHATSAPP || !isDevelopment;

if (isDevelopment && !ENABLE_WHATSAPP) {
  console.log("🔧 Modo desarrollo detectado");
  console.log("💡 Para habilitar WhatsApp en desarrollo, establece: ENABLE_WHATSAPP=true");
  console.log("💡 Ejemplo: ENABLE_WHATSAPP=true node server.js");
} else {
  console.log("✅ WhatsApp habilitado");
}

const PORT = process.env.PORT || 3000;
// Detectar si estamos en producción o desarrollo (ya definido arriba en CORS)
const listenHost = isProduction ? '0.0.0.0' : '127.0.0.1';
const protocol = isProduction ? 'https' : 'http';
const domain = isProduction ? 'rutalan.cloud' : 'localhost';

app.listen(PORT, listenHost, () => {
  console.log(`🚀 API corriendo en ${protocol}://${domain}:${PORT}`);
  console.log(`📡 Escuchando en: ${listenHost}:${PORT}`);
  console.log(`🌍 Entorno: ${isProduction ? 'PRODUCCIÓN' : 'DESARROLLO'}`);
  if (isProduction) {
    console.log(`🌐 Dominios permitidos: rutalan.cloud, www.rutalan.cloud`);
    console.log(`🔒 CORS configurado para producción`);
  }
  
  if (shouldEnableWhatsApp) {
    console.log(`🔄 Iniciando cliente de WhatsApp...`);
    // Inicializar el cliente después de que el servidor esté listo
    setTimeout(() => {
      inicializarClienteWhatsApp();
    }, 1000);
  } else {
    console.log(`⚠️ WhatsApp deshabilitado`);
    console.log(`💡 Para habilitar, establece ENABLE_WHATSAPP=true`);
  }
});

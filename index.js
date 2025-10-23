// index.js
const { Client, LocalAuth } = require("whatsapp-web.js");
const qrcode = require("qrcode");
const express = require("express");
const bodyParser = require("body-parser");
const cors = require("cors");
const path = require("path");

const app = express();

// Middleware
app.use(bodyParser.json());
app.use(express.static(path.join(__dirname, 'public')));

// 🌐 CORS global
app.use(cors({
    origin: '*', // permite cualquier origen (en producción, limitar a tus dominios)
    methods: ['GET','POST','PUT','DELETE','OPTIONS'],
    allowedHeaders: ['Content-Type','Authorization'],
    credentials: true,
    maxAge: 86400
}));

// Variables de estado
let qrCodeData = null; 
let isReady = false;
let isInitializing = false;

// Crear cliente de WhatsApp con sesión persistente
const client = new Client({
    authStrategy: new LocalAuth({ clientId: "rutalan-session" }),
    puppeteer: {
        headless: true,
        args: ["--no-sandbox", "--disable-setuid-sandbox"],
    },
});

// Inicializar cliente
const initializeClient = () => {
    if (!isInitializing) {
        isInitializing = true;
        client.initialize();
    }
};

// Eventos del cliente
client.on("qr", async (qr) => {
    console.log("📲 Nuevo QR generado");
    try {
        qrCodeData = await qrcode.toDataURL(qr);
        isReady = false;
        isInitializing = false;
    } catch (err) {
        console.error("❌ Error al generar QR:", err);
        isInitializing = false;
    }
});

client.on("ready", () => {
    console.log("✅ Cliente de WhatsApp conectado y listo");
    qrCodeData = null;
    isReady = true;
    isInitializing = false;
});

client.on("disconnected", (reason) => {
    console.log("⚠️ Cliente desconectado:", reason);
    isReady = false;
    isInitializing = false;

    if (reason === "LOGOUT") {
        console.log("🚪 El usuario cerró sesión manualmente. Se requiere nuevo escaneo de QR.");
        qrCodeData = null;
    } else {
        console.log("🔄 Intentando reconectar...");
        setTimeout(() => {
            initializeClient();
        }, 5000);
    }
});

client.on("auth_failure", () => {
    console.log("❌ Error de autenticación");
    isReady = false;
    isInitializing = false;
    qrCodeData = null;
});

// Inicializar al iniciar
initializeClient();

// Endpoints

// Obtener QR
app.get("/api/qr", (req, res) => {
    if (qrCodeData) {
        return res.json({ qr: qrCodeData, status: "waiting" });
    } else if (isReady) {
        return res.json({ qr: null, status: "connected", message: "✅ WhatsApp ya está conectado" });
    } else if (isInitializing) {
        return res.json({ qr: null, status: "initializing", message: "⏳ Generando QR, espera un momento..." });
    } else {
        return res.json({ qr: null, status: "disconnected", message: "🔌 Cliente desconectado" });
    }
});

// Obtener estado
app.get("/api/status", (req, res) => {
    res.json({
        isReady,
        isInitializing,
        hasQr: !!qrCodeData,
        status: isReady ? "connected" : (qrCodeData ? "waiting" : (isInitializing ? "initializing" : "disconnected"))
    });
});

// Cerrar sesión
app.post("/api/logout", async (req, res) => {
    try {
        console.log("🚪 Solicitando cierre de sesión...");

        if (!isReady && !client.info) {
            return res.json({ success: true, message: "El cliente ya está desconectado" });
        }

        await client.logout();

        qrCodeData = null;
        isReady = false;
        isInitializing = false;

        console.log("✅ Sesión cerrada exitosamente");
        res.json({ success: true, message: "Sesión cerrada exitosamente" });

    } catch (error) {
        console.error("❌ Error al cerrar sesión:", error);
        res.status(500).json({ success: false, error: error.message });
    }
});

// Forzar nuevo QR
app.post("/api/refresh-qr", async (req, res) => {
    try {
        console.log("🔄 Solicitando nuevo QR...");

        if (isReady) {
            await client.logout();
        }

        if (client.pupPage && !client.pupPage.isClosed()) {
            await client.pupPage.close();
        }

        qrCodeData = null;
        isReady = false;
        isInitializing = false;

        setTimeout(() => {
            initializeClient();
        }, 2000);

        console.log("✅ Solicitando nuevo QR...");
        res.json({ success: true, message: "Solicitando nuevo QR..." });

    } catch (error) {
        console.error("❌ Error al generar nuevo QR:", error);
        res.status(500).json({ success: false, error: error.message });
    }
});

// Enviar mensaje
app.post("/api/send-message", async (req, res) => {
    const { to, message } = req.body;

    if (!to || !message) {
        return res.status(400).json({ success: false, error: "Parámetros inválidos" });
    }

    if (!isReady) {
        return res.status(400).json({ success: false, error: "WhatsApp no está conectado" });
    }

    try {
        await client.sendMessage(to + "@c.us", message);
        res.json({ success: true, to, message });
    } catch (err) {
        console.error("❌ Error al enviar mensaje:", err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// Servir página principal
app.get("/", (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Servidor HTTP
const PORT = 3000;
app.listen(PORT, () => {
    console.log(`🚀 Servidor API corriendo en http://localhost:${PORT}`);
});

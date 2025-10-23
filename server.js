const express = require("express");
const { Client } = require("whatsapp-web.js");
const qrcode = require("qrcode-terminal");

const app = express();
app.use(express.json());

// Inicializar cliente de WhatsApp
const client = new Client();

client.on("qr", (qr) => {
  console.log("📲 Escanea este QR con tu WhatsApp:");
  qrcode.generate(qr, { small: true });
});

client.on("ready", () => {
  console.log("✅ Cliente de WhatsApp listo para enviar mensajes");
});

client.initialize();

// Endpoint para enviar mensajes
app.post("/api/send-message", async (req, res) => {
  const { to, message } = req.body;

  if (!to || !message) {
    return res.status(400).json({ success: false, error: "Faltan parámetros" });
  }

  try {
    const chatId = to.includes("@c.us") ? to : `${to}@c.us`;
    await client.sendMessage(chatId, message);

    res.json({ success: true, to, message });
  } catch (error) {
    console.error("❌ Error enviando mensaje:", error);
    res.status(500).json({ success: false, error: error.message });
  }
});

app.listen(3000, () => {
  console.log("🚀 API corriendo en http://localhost:3000");
});

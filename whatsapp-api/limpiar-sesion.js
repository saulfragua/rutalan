/**
 * Script para limpiar la sesión de WhatsApp bloqueada
 * Ejecutar: node limpiar-sesion.js
 */

const fs = require('fs');
const path = require('path');

const sessionPath = path.join(__dirname, '.wwebjs_auth', 'session-rutalan-whatsapp');

console.log('🧹 Limpiando sesión de WhatsApp...');
console.log('   Ruta:', sessionPath);

try {
  if (fs.existsSync(sessionPath)) {
    // Intentar eliminar archivos individuales primero
    console.log('   Eliminando archivos de sesión...');
    
    function eliminarDirectorio(dir) {
      if (fs.existsSync(dir)) {
        fs.readdirSync(dir).forEach((file) => {
          const curPath = path.join(dir, file);
          try {
            if (fs.lstatSync(curPath).isDirectory()) {
              eliminarDirectorio(curPath);
            } else {
              fs.unlinkSync(curPath);
            }
          } catch (err) {
            console.log(`   ⚠️ No se pudo eliminar: ${curPath} - ${err.message}`);
          }
        });
        try {
          fs.rmdirSync(dir);
        } catch (err) {
          console.log(`   ⚠️ No se pudo eliminar directorio: ${dir} - ${err.message}`);
        }
      }
    }
    
    eliminarDirectorio(sessionPath);
    console.log('✅ Sesión limpiada correctamente');
  } else {
    console.log('ℹ️ No hay sesión para limpiar');
  }
} catch (error) {
  console.error('❌ Error al limpiar sesión:', error.message);
  console.error('');
  console.error('Solución manual:');
  console.error('1. Detén todos los procesos de Node.js');
  console.error('2. Elimina manualmente la carpeta: .wwebjs_auth');
  console.error('3. Reinicia el servidor');
}

import 'zone.js';
import { platformBrowser } from '@angular/platform-browser';
import { AppModule } from './app/app-module';

// Configurar zona horaria de Colombia (GMT-5) para toda la aplicación
// La zona horaria se aplicará automáticamente en los métodos toLocaleString
// con la opción timeZone: 'America/Bogota' en todos los componentes

platformBrowser().bootstrapModule(AppModule, {
  
})
  .catch(err => console.error(err));

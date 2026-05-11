import { NgModule, provideBrowserGlobalErrorListeners, CUSTOM_ELEMENTS_SCHEMA } from '@angular/core';
import { BrowserModule, provideClientHydration, withEventReplay } from '@angular/platform-browser';
import { AppRoutingModule } from './app-routing-module';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpClientModule } from '@angular/common/http';

import { App } from './app';
import { Navbar } from './estructura/navbar/navbar';
import { Sidebar } from './estructura/sidebar/sidebar';
import { Footer } from './estructura/footer/footer';
import { Dashboard } from './modulos/dashboard/dashboard';
import { Clientes } from './modulos/clientes/clientes';
import { Principal } from './estructura/principal';
import { Creditos } from './modulos/creditos/creditos';
import { Caja } from './modulos/caja/caja';
import { Administrador } from './modulos/administrador/administrador';
import { Cobros } from './modulos/cobros/cobros';
import { GestionPago } from './modulos/cobros/gestion-pago';
import { Refinanciar } from './modulos/cobros/refinanciar';
import { Gastos } from './modulos/gastos/gastos';
import { Reportes } from './modulos/reportes/reportes';
import { Login } from './modulos/login/login';
import { Error } from './modulos/error/error';
import { MapaClientes } from './modulos/mapa-clientes/mapa-clientes';
import { Informes } from './modulos/informes/informes';



@NgModule({
  declarations: [
    App,
    Navbar,
    Sidebar,
    Footer,
    Dashboard,
    Clientes,
    Principal,
    Creditos,
    Caja,
    Administrador,
    Cobros,
    GestionPago,
    Refinanciar,
    Gastos,
    Reportes,
    Login,
    Error,
    MapaClientes,
    Informes
    
  ],
  imports: [
    BrowserModule,
    AppRoutingModule,
    FormsModule,
    HttpClientModule
  ],
  schemas: [CUSTOM_ELEMENTS_SCHEMA],
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideClientHydration(withEventReplay())
  ],
  bootstrap: [App]
})
export class AppModule { }

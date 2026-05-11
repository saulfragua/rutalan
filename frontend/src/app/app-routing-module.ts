import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { Principal } from './estructura/principal';
import { Dashboard } from './modulos/dashboard/dashboard';
import { Clientes } from './modulos/clientes/clientes';
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
import { AdminGuard } from './guards/admin.guard';
import { Informes } from './modulos/informes/informes';

const routes: Routes = [
  {
    path: '',
    component: Principal,
    children: [
      { path: 'dashboard', component: Dashboard, canActivate: [AdminGuard] },
      { path: 'clientes', component: Clientes },
      { path: 'creditos', component: Creditos },
      { path: 'cobros', component: Cobros },
      { path: 'gestion-pago', component: GestionPago },
      { path: 'refinanciar', component: Refinanciar },
      { path: 'gastos', component: Gastos },
      { path: 'caja', component: Caja, canActivate: [AdminGuard] },
      { path: 'reportes', component: Reportes },
      { path: 'administrador', component: Administrador, canActivate: [AdminGuard] },
      { path: 'informes', component: Informes, canActivate: [AdminGuard] },
      { path: 'mapa-clientes', component: MapaClientes },
      { path: '', redirectTo: 'clientes', pathMatch: 'full' }
    ]
  },

  { path: 'login', component: Login },

  // ⚠️ SIEMPRE AL FINAL
  { path: '**', component: Error }
];


@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule]
})
export class AppRoutingModule { }

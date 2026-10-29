# Tareas (Tasks) — Ebike Manager

Tareas técnicas asociadas a las historias de usuario.

## EP-01 Visualización del dashboard
- T-01: Crear `DashboardModel` con métodos `getEstadisticasAdmin`, `getIngresosPorEstacion`, etc.
- T-02: Crear `DashboardController::index()` e `apiChart()`.
- T-03: Crear vista `dashboard.php` con KPIs y contenedores de gráficos.
- T-04: Implementar `dashboard.js` con `cargarTodos()` y `Promise.all`.
- T-05: Implementar plugin de etiquetas numéricas en Chart.js.

## EP-02 Filtrado en tiempo real
- T-06: Añadir pills de estado y periodo + inputs cliente/QR en la vista.
- T-07: Implementar `obtenerParams()` y `aplicarFiltros()` con debounce.
- T-08: Pasar filtros a los SPs (`sp_alquiler_buscar_paginado`).
- T-09: Recalcular KPIs filtrados en `getEstadisticasAdmin`.

## EP-03 Exportación de reportes
- T-10: Implementar `capturarImagenChart()` (update('none') + draw()).
- T-11: Enviar imágenes por POST (`generarPDF()`).
- T-12: Aplanar PNG→JPEG sobre blanco con GD en `PdfExport`.
- T-13: Implementar normas APA (márgenes, Times, sangría, `Tw`, figuras).
- T-14: Implementar `ExcelExport` con BOM UTF-8 y separador `;`.

## EP-04 Navegación SPA y responsive
- T-15: Implementar `mostrarSeccion()` y clases `.activa`.
- T-16: Implementar menú acordeón móvil con hamburguesa.
- T-17: Cache-busting de `dashboard.js?v=3`.

## EP-05 Seguridad por roles
- T-18: Crear `sp_validar_rol`.
- T-19: Invocar `sp_validar_rol` como primera línea de cada SP.
- T-20: Probar con rol `HACKER` (debe lanzar SQLSTATE 45000).

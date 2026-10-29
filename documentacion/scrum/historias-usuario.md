# Historias de usuario — Ebike Manager

Formato: **Como** [rol], **quiero** [acción], **para** [beneficio].

## HU-01 — Ver KPIs en vivo
- **Como** administrador,
- **quiero** ver 6 KPIs (total bicicletas, disponibles, alquileres activos,
  usuarios, total alquileres, ingresos),
- **para** conocer el estado del negocio de un vistazo.
- **Criterios de aceptación:**
  - Se muestran 6 tarjetas KPI.
  - Los valores provienen de `sp_reporte_estadisticas_admin`.
  - Se actualizan al aplicar filtros.

## HU-02 — Ver gráficos
- **Como** administrador,
- **quiero** ver 5 gráficos (barras, histograma, línea, dona, radar) con
  etiquetas numéricas,
- **para** analizar ingresos, duraciones, evolución y salud de la flota.
- **Criterios de aceptación:**
  - Los 5 gráficos se renderizan con Chart.js.
  - Cada gráfico muestra etiquetas numéricas sobre los datos.

## HU-03 — Ver tabla de alquileres
- **Como** administrador,
- **quiero** ver la lista de alquileres con badges de color por estado,
- **para** consultar los datos crudos.
- **Criterios de aceptación:**
  - Tabla con paginación de 12 filas.
  - Badges de color por estado (ACTIVO, FINALIZADO, etc.).

## HU-04 — Filtrar por estado/cliente/QR/periodo
- **Como** administrador,
- **quiero** filtrar por estado del alquiler, cliente (LIKE), código QR y
  periodo (HOY/SEMANA/MES),
- **para** analizar subconjuntos de datos.
- **Criterios de aceptación:**
  - Los filtros aplican a KPIs, gráficos, tabla y exportaciones.
  - Búsqueda por texto con debounce de 450 ms.

## HU-05 — Filtros en vivo (SPA)
- **Como** administrador,
- **quiero** que los filtros se apliquen sin recargar la página,
- **para** una experiencia fluida.
- **Criterios de aceptación:**
  - Uso de `fetch` + `Promise.all`.
  - Navegación por secciones sin recarga.

## HU-06 — Exportar a PDF
- **Como** administrador,
- **quiero** exportar un PDF ejecutivo con normas APA que incluya las imágenes
  de los gráficos filtrados,
- **para** presentar reportes profesionales.
- **Criterios de aceptación:**
  - Captura de los 5 canvas a Base64.
  - PDF con márgenes APA, Times New Roman, figuras numeradas y `Nota.`.

## HU-07 — Exportar a Excel (CSV)
- **Como** administrador,
- **quiero** exportar los alquileres filtrados a CSV (separador `;`, BOM UTF-8),
- **para** abrirlos en Excel en español.

## HU-08 — Navegación SPA
- **Como** usuario,
- **quiero** navegar entre Resumen / Gráficas / Alquileres sin recargar,
- **para** una experiencia rápida.

## HU-09 — Diseño responsive
- **Como** usuario móvil,
- **quiero** un menú acordeón con botón hamburguesa,
- **para** usar el dashboard en pantallas pequeñas.

## HU-10 — Validación de rol en BD
- **Como** sistema,
- **quiero** validar el rol del ejecutor dentro del SP (`sp_validar_rol`),
- **para** impedir acceso a roles no autorizados (ej. `HACKER`).

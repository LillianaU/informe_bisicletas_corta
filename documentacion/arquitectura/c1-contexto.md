# C1 — Diagrama de contexto

Muestra el sistema en su entorno: quién lo usa y con qué sistemas externos
interactúa.

```mermaid
flowchart LR
    Admin["👤 Administrador / Supervisor<br/>(navegador web)"]
    Sys["🚲 Ebike Manager<br/>Dashboard de alquileres"]
    DB["🗄️ MySQL / MariaDB<br/>ebike_management"]
    FS["📁 Sistema de archivos<br/>(vendor/fpdf, assets)"]

    Admin -->|HTTP (filtros, exportación)| Sys
    Sys -->|PDO / Stored Procedures| DB
    Sys -->|Lee FPDF, CSS, JS| FS
    Sys -->|Descarga PDF/CSV| Admin
```

## Descripción
- **Actor principal:** Administrador/Supervisor que opera el dashboard.
- **Sistema:** Ebike Manager (PHP 8 + MySQL).
- **Dependencias externas:** base de datos MySQL y archivos locales (FPDF,
  Bootstrap, Chart.js, SweetAlert2).

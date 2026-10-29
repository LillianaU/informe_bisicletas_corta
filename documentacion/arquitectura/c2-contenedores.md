# C2 — Diagrama de contenedores

Contenedores (aplicaciones/procesos) que conforman el sistema.

```mermaid
flowchart TB
    Browser["🌐 Navegador (SPA)<br/>dashboard.php + dashboard.js<br/>Chart.js, Bootstrap, SweetAlert2"]
    Apache["⚙️ Apache + PHP 8<br/>public/index.php (Front Controller)"]
    MySQL["🗄️ MySQL / MariaDB<br/>ebike_management"]
    FS["📁 Archivos estáticos<br/>public/assets, vendor/fpdf"]

    Browser -->|HTTP GET/POST (JSON, Base64)| Apache
    Apache -->|PDO (CALL sp_...)| MySQL
    Apache -->|Lee assets / FPDF| FS
    Apache -->|HTML / JSON / PDF / CSV| Browser
```

## Contenedores
| Contenedor | Tecnología | Responsabilidad |
|---|---|---|
| Navegador (SPA) | HTML/CSS/JS + Chart.js | UI, filtros, gráficos, captura de canvas |
| Apache + PHP 8 | PHP (sin framework) | Front controller, controlador, modelo, exports |
| MySQL/MariaDB | SQL + Stored Procedures | Datos y lógica de consulta (SPs) |
| Archivos estáticos | CSS/JS/PDF lib | Librerías locales y FPDF |

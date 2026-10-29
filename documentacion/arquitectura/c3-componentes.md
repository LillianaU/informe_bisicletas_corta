# C3 — Diagrama de componentes

Componentes internos del contenedor PHP.

```mermaid
flowchart LR
    subgraph Web["Capa Web"]
        V["Views/dashboard.php<br/>(SPA)"]
        JS["public/assets/js/dashboard.js"]
    end
    subgraph App["Capa Aplicación (src/)"]
        FC["public/index.php<br/>(Front Controller)"]
        C["Controllers/DashboardController"]
        M["Models/DashboardModel"]
        DB["Database/Database<br/>(Singleton PDO)"]
        PDF["Export/PdfExport"]
        XLS["Export/ExcelExport"]
    end
    subgraph Data["Capa Datos"]
        SP["MySQL Stored Procedures"]
    end

    V --> JS
    JS -->|fetch JSON| FC
    FC --> C
    C --> M
    C --> PDF
    C --> XLS
    M --> DB
    DB --> SP
    PDF -->|FPDF| FS2["vendor/fpdf/fpdf.php"]
```

## Componentes
- **Front Controller** (`public/index.php`): router por `?accion=`.
- **DashboardController**: coordina vista, API JSON y exports.
- **DashboardModel**: envuelve los SPs y normaliza datos.
- **Database**: Singleton PDO.
- **PdfExport / ExcelExport**: generación de reportes.
- **Views/dashboard.php + dashboard.js**: SPA del navegador.
- **Stored Procedures**: lógica de consulta y seguridad por rol.

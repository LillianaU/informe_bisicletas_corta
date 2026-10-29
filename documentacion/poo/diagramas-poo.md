# Diagramas POO — Ebike Manager

## Diagrama de clases (POO) — Mermaid

```mermaid
classDiagram
    class Database {
        -static PDO instancia
        +static obtenerInstancia() PDO
    }
    class DashboardModel {
        -PDO pdo
        +getEstadisticasAdmin(rol, estado, cliente, qr, periodo)
        +getIngresosPorEstacion(...)
        +getDuracionAlquileres(...)
        +getAlquileresPorPeriodo(...)
        +getAlquileresPorEstado(...)
        +getIndicadoresRadar(rol)
        +getAlquileres(...)
        #callSpSingleRow(sp, params)
        #callSpAllRows(sp, params)
    }
    class DashboardController {
        -DashboardModel model
        -PdfExport pdf
        -ExcelExport excel
        +index()
        +apiChart(tipo)
        +exportarPdf()
        +exportarExcel()
        -render(vista, datos)
    }
    class PdfExport {
        +generarReporteEjecutivo(stats, estado, periodo, cliente, qr, chartData, imagens, descargar)
        +Header()
        -portada(...)
        -resumenEjecutivo(stats)
        -seccionFiguras(archivos)
        -notaApa(texto)
    }
    class ExcelExport {
        -array rows
        +create(alquileres)
        +Output(nombreArchivo, formato)
    }
    class FPDF {
        <<librería externa>>
    }

    DashboardController *-- DashboardModel
    DashboardController *-- PdfExport
    DashboardController *-- ExcelExport
    DashboardModel ..> Database : usa Singleton
    PdfExport --|> FPDF : hereda
```

## Principios POO aplicados
- **Encapsulación:** propiedades privadas (`$model`, `$rows`, `$instancia`).
- **Herencia:** `PdfExport extends \FPDF`.
- **Singleton:** `Database::obtenerInstancia()` garantiza una única conexión.
- **Composición:** `DashboardController` compone Modelo + Exportadores.
- **Abstracción:** el modelo oculta los SPs detrás de métodos con nombres de
  negocio (`getEstadisticasAdmin`, etc.).
- **Polimorfismo:** `Output()` de ExcelExport soporta distintos formatos (CSV).

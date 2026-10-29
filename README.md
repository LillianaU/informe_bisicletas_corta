# 🚲 Ebike Manager — Dashboard de Alquileres de Bicicletas Eléctricas

> **Proyecto de curso completo**: aplicación web con PHP 8 + MySQL que muestra un dashboard SPA (Single Page Application) con gráficos Chart.js, filtros en vivo, tabla de datos y exportación a **PDF con estética APA** y **Excel (CSV)**. Todo el código está comentado en español, explicando qué hace cada bloque.

| Tecnología | Uso |
|---|---|
| PHP 8.3 (PDO) | Lógica de servidor, sin framework, sin Composer |
| MySQL / MariaDB | Datos + **procedimientos almacenados** (toda la lógica de consulta vive en la BD) |
| Bootstrap 5.3 (local) | Layout responsivo, grid, modales |
| Chart.js 4 (local) | Los 5 gráficos del dashboard |
| SweetAlert2 (local) | Alertas modernas |
| FPDF (implementación propia) | Generación del PDF ejecutivo |

---

## 📑 Índice del curso

1. [¿Qué hace este software?](#1-qué-hace-este-software)
2. [Diccionario de términos](#2-diccionario-de-términos)
3. [Arquitectura y patrones de diseño](#3-arquitectura-y-patrones-de-diseño)
4. [Requisitos e instalación](#4-requisitos-e-instalación)
5. [Estructura de archivos](#5-estructura-de-archivos)
6. [Base de datos y procedimientos almacenados](#6-base-de-datos-y-procedimientos-almacenados)
7. [Librerías y para qué se usan](#7-librerías-y-para-qué-se-usan)
8. [Tutorial paso a paso: cómo construir este software](#8-tutorial-paso-a-paso)
9. [El flujo completo de una exportación PDF](#9-el-flujo-completo-de-una-exportación-pdf)
10. [Depuración: lecciones aprendidas](#10-depuración-lecciones-aprendidas)
11. [Tour del código documentado](#11-tour-del-código-documentado)

---

## 1. ¿Qué hace este software?

Un panel de control para una empresa de alquiler de bicicletas eléctricas:

- **6 KPIs en vivo**: total de bicicletas, disponibles, alquileres activos, usuarios, total de alquileres e ingresos (EUR).
- **5 gráficos**: barras (ingresos por estación), histograma (duraciones), polígono/línea (evolución mensual), dona (composición por estado) y radar (salud de la flota). Todos con **etiquetas numéricas visibles**.
- **Filtros en tiempo real** por estado del alquiler, cliente (LIKE), código QR y periodo (HOY / SEMANA / MES) — aplican a KPIs, gráficos, tabla y exportaciones.
- **SPA**: la página nunca se recarga; el menú del sidebar navega entre secciones (Resumen / Gráficas / Alquileres) y los datos viajan por `fetch` en JSON.
- **Responsivo**: en pantallas pequeñas el sidebar de filtros se pliega en un menú tipo **acordeón** con botón hamburguesa.
- **Exportación PDF profesional** con normas APA (márgenes de 2,54 cm, Times New Roman, figuras numeradas con "Nota.", texto justificado) que **incluye las imágenes de los gráficos tal y como se ven en pantalla**.
- **Exportación Excel (CSV)** con los alquileres filtrados, lista para abrir en Excel en español.

---

## 2. Diccionario de términos

| Término | Explicación simple |
|---|---|
| **Alquiler** | Registro de que un usuario tomó una bici en una estación (fecha_inicio) y la devolvió (fecha_fin, si ya terminó). |
| **Flota** | El conjunto de todas las bicicletas. |
| **KPI** | *Key Performance Indicator*: número clave que resume la salud del negocio (ej: ingresos totales). |
| **Dashboard** | Panel visual con KPIs y gráficos para tomar decisiones de un vistazo. |
| **SPA (Single Page Application)** | Aplicación de una sola página: la interfaz cambia sin recargar el navegador; los datos llegan por peticiones asíncronas. |
| **MVC** | Patrón Model-View-Controller: separa datos (Modelo), presentación (Vista) y coordinación (Controlador). |
| **Front Controller** | Un único punto de entrada (index.php) que decide a qué parte del sistema enviar cada petición. |
| **Router** | Mecanismo que asocia una URL/parámetro con una acción del controlador (`?accion=api`). |
| **Namespace** | "Apellido" de una clase para evitar colisiones de nombres: `App\Models\DashboardModel`. |
| **PSR-4** | Estándar de PHP que mapea namespaces a rutas de archivo: `App\Models\X` → `src/Models/X.php`. |
| **Autoloader** | Código que carga las clases automáticamente cuando se usan, sin `require` manual. |
| **PDO** | Capa de acceso a bases de datos de PHP, con consultas preparadas (seguras contra inyección SQL). |
| **Consulta preparada** | Consulta con marcadores `?` donde los valores viajan por separado; evita inyección SQL. |
| **Stored Procedure (SP)** | Función guardada DENTRO de MySQL que encapsula SQL complejo y se llama con `CALL nombre(params)`. |
| **Result set** | Cada "tabla" de filas que devuelve una consulta. Un SP puede devolver varios. |
| **JSON** | Formato de texto para intercambiar datos: `{"success": true, "data": [...]}`. |
| **API / Endpoint** | URL que devuelve datos en JSON en vez de HTML. Aquí: `?accion=api&tipo=barras`. |
| **fetch** | Función del navegador para hacer peticiones HTTP desde JavaScript sin recargar la página. |
| **Promise / async-await** | Formas de escribir código asíncrono que espera respuestas sin bloquear. |
| **Canvas** | Lienzo HTML donde se puede dibujar con JavaScript; Chart.js dibuja los gráficos ahí. |
| **Chart.js** | Librería que convierte datos JSON en gráficos interactivos. |
| **Dataset** | Serie de datos de un gráfico (etiquetas + valores). |
| **Plugin de Chart.js** | Función que se engancha al ciclo de dibujo para añadir extras (nuestro plugin pinta las etiquetas numéricas). |
| **Base64 / DataURL** | Texto que representa una imagen completa (`data:image/png;base64,...`); así viajan los gráficos al servidor. |
| **toDataURL / toBase64Image** | Métodos que convierten el contenido del canvas a una cadena Base64. |
| **requestAnimationFrame (rAF)** | Timer del navegador para animaciones; si la ventana está oculta, se pausa (¡importante en bugs!). |
| **Debounce** | Retrasar una función hasta que el usuario deje de escribir (ej: 450 ms), para no bombardear al servidor. |
| **XSS** | Ataque que inyecta HTML/JS malicioso; se previene usando `textContent` en vez de `innerHTML` con datos del usuario. |
| **Cache-busting** | Forzar que el navegador descargue de nuevo un archivo cambiando su URL (`dashboard.js?v=3`). |
| **FPDF** | Clase PHP que construye archivos PDF "a mano": escribe texto, líneas, curvas e imágenes con operadores PDF. |
| **Operador PDF** | Instrucción del formato PDF: `BT/ET` (inicio/fin de texto), `Tj` (pintar texto), `Tw` (espaciado entre palabras → justificado), `cm` (transformar/posicionar), `Do` (dibujar imagen), `q/Q` (guardar/restaurar estado). |
| **XObject / Stream / xref** | Objeto imagen, flujo de bytes y tabla de posiciones dentro de un archivo PDF. |
| **Bezier** | Curva definida por puntos de control; con 4 arcos se aproxima un círculo (logo de la bicicleta). |
| **PNG RGBA / canal alfa** | PNG con transparencia; al convertirlo a JPEG hay que aplanar el fondo (si no, sale negro). |
| **GD** | Extensión de PHP para procesar imágenes (la usamos para aplanar PNG→JPEG). |
| **Normas APA** | Estilo académico: márgenes de 2,54 cm, Times 12, sangría de 1,27 cm, figuras numeradas con "Nota.". |
| **Sangría francesa** | Formato donde la primera línea va al margen y las siguientes-indentadas (usado en las notas del PDF). |
| **CSV** | Texto separado por punto y coma que Excel abre como tabla. |
| **BOM UTF-8** | Marca de bytes al inicio de un archivo; si aparece antes de `<?php` rompe los headers de PHP. |
| **Singleton** | Patrón que garantiza UNA sola instancia (aquí, una sola conexión a la BD). |
| **CLI** | Ejecutar PHP desde la terminal (`php script.php`), útil para probar sin navegador. |

---

## 3. Arquitectura y patrones de diseño

### Capas del sistema

```
┌──────────────────────────────────────────────────────────────┐
│ NAVEGADOR (SPA)                                              │
│  dashboard.php (HTML+CSS Bootstrap) + dashboard.js           │
│  · Secciones: Resumen / Gráficas / Alquileres (sin recarga)  │
│  · Filtros → fetch JSON → repinta Chart.js / tabla / KPIs    │
└───────────────────────┬──────────────────────────────────────┘
                        │ HTTP (GET/POST + JSON)
┌───────────────────────▼──────────────────────────────────────┐
│ FRONT CONTROLLER — public/index.php                           │
│  Router simple: ?accion=dashboard | api | exportar-pdf |      │
│  exportar-excel                                              │
└───────────────────────┬──────────────────────────────────────┘
                        │
┌───────────────────────▼──────────────────────────────────────┐
│ CONTROLADOR — DashboardController                              │
│  · index()        → carga la vista                             │
│  · apiChart(tipo) → JSON desde el Modelo                       │
│  · exportarPdf()  → estadísticas filtradas + imágenes → PDF    │
│  · exportarExcel()→ filas filtradas → CSV                      │
└───────────────────────┬──────────────────────────────────────┘
                        │
┌───────────────────────▼──────────────────────────────────────┐
│ MODELO — DashboardModel                                       │
│  Ejecuta procedimientos almacenados vía PDO y normaliza datos  │
└───────────────────────┬──────────────────────────────────────┘
                        │ PDO ( prepared statements )
┌───────────────────────▼──────────────────────────────────────┐
│ MySQL — base ebike_management                                 │
│  Tablas: roles, usuarios, bicicletas, estaciones, alquileres   │
│  SPs: sp_validar_rol, sp_alquiler_buscar_paginado,             │
│       sp_reporte_estadisticas_admin, sp_histograma_duraciones, │
│       sp_indicadores_sistema                                   │
└──────────────────────────────────────────────────────────────┘
```

### Patrones aplicados y dónde

| Patrón | Dónde | Por qué |
|---|---|---|
| **Front Controller** | `public/index.php` | Una sola entrada; el parámetro `accion` decide el destino. |
| **MVC** | `src/Controllers`, `src/Models`, `src/Views` | Separación de responsabilidades: fácil de mantener y de explicar. |
| **Singleton** | `src/Database/Database.php::obtenerInstancia()` | Una única conexión PDO compartida por todo el request. |
| **PSR-4 + Autoloader** | `autoload.php` | Las clases se cargan solas: `App\Models\X` → `src/Models/X.php`. |
| **API JSON interna** | `apiChart()` | El frontend solo consume datos; el mismo endpoint sirve a la tabla, KPIs y gráficos. |
| **Filtrado server-side** | SPs + `getEstadisticasAdmin(...$filtros)` | La BD hace el trabajo pesado; PHP y JS solo presentan. |
| **SPA por secciones** | `mostrarSeccion()` en `dashboard.js` | Navegación instantánea sin recargar (clase `.activa`). |
| **Captura de canvas → POST Base64** | `generarPDF()` en la vista | El PDF incluye los gráficos EXACTOS que ve el usuario, ya filtrados. |

---

## 4. Requisitos e instalación

### Requisitos

- [Laragon Full](https://laragon.org) (incluye Apache, MySQL y PHP ≥ 8.1) — también sirve XAMPP/WAMP manual.
- Extensión **GD** de PHP activada (en Laragon viene activada; comprobar con `php -m | findstr gd`).
- Navegador moderno (Chrome, Edge, Firefox).

### Instalación paso a paso

1. **Copiar el proyecto** a la carpeta web:
   ```
   C:\laragon\www\ebike_dashboard
   ```

2. **Crear la base de datos**: abrir phpMyAdmin (`http://localhost/phpmyadmin`) → pestaña *Importar* → seleccionar `ebike_management.sql` (contiene tablas + datos de ejemplo) → Ejecutar.

3. **Instalar los procedimientos almacenados**: importar `sql/stored_procedures.sql` de la misma forma (o pegar su contenido en la pestaña *SQL*). Crea:
   - `sp_validar_rol`
   - `sp_alquiler_buscar_paginado`
   - `sp_reporte_estadisticas_admin`
   - `sp_histograma_duraciones`
   - `sp_indicadores_sistema`

4. **Configurar la conexión** si tu MySQL usa otra clave (por defecto root, sin clave):
   ```php
   // config/database.php
   return [
       'host'   => '127.0.0.1',
       'dbname' => 'ebike_management',
       'user'   => 'root',
       'pass'   => '',
       'charset'=> 'utf8mb4',
   ];
   ```

5. **Abrir el dashboard**: <http://localhost/ebike_dashboard/public/>

> No hay login: el rol se toma de la sesión con valor por defecto `ADMIN`
> (`$_SESSION['rol_ejecutor'] ?? 'ADMIN'`), ideal para un entorno de clase.

### Comprobaciones rápidas

```bash
# ¿PHP funciona y tiene GD?
php -v
php -m | findstr gd

# ¿Los archivos PHP no tienen errores de sintaxis?
php -l public\index.php
php -l src\Models\DashboardModel.php
php -l src\Export\PdfExport.php
```

---

## 5. Estructura de archivos

```
ebike_dashboard/
├── autoload.php                  # Autoloader PSR-4: App\ → src/
├── ebike_management.sql          # Dump completo de la BD (tablas + datos)
├── README.md                     # Este documento
├── config/
│   └── database.php              # Credenciales MySQL (array PHP)
├── public/                        # Único directorio expuesto al navegador
│   ├── index.php                 # FRONT CONTROLLER (router por ?accion=)
│   └── assets/
│       ├── css/bootstrap.min.css  # Bootstrap 5.3 (local)
│       └── js/
│           ├── bootstrap.bundle.min.js  # Modales y componentes BS
│           ├── chart.umd.min.js         # Chart.js 4
│           ├── sweetalert2.min.js       # Alertas
│           └── dashboard.js             # Nuestra lógica SPA (filtros+charts)
├── sql/
│   └── stored_procedures.sql     # Fuente de los 5 SPs
├── src/
│   ├── Controllers/
│   │   └── DashboardController.php  # Coordina: vista, API JSON, exports
│   ├── Database/
│   │   └── Database.php           # Singleton PDO
│   ├── Export/
│   │   ├── ExcelExport.php       # CSV con ; y BOM UTF-8
│   │   └── PdfExport.php         # Reporte ejecutivo APA (FPDF)
│   ├── Models/
│   │   └── DashboardModel.php   # Llama a los SPs y normaliza datos
│   └── Views/
│       └── dashboard.php         # Interfaz SPA (HTML + CSS + JS de exportación)
└── vendor/
    └── fpdf/
        └── fpdf.php              # FPDF documentado (texto, imágenes, elipses...)
```

---

## 6. Base de datos y procedimientos almacenados

### Tablas (simplificadas)

| Tabla | Qué guarda | Campos clave |
|---|---|---|
| `roles` | Perfiles de usuario | `codigo_rol` (ADMIN, SUPERVISOR...) |
| `usuarios` | Clientes | `id_usuario`, `nombre` |
| `bicicletas` | La flota | `codigo_qr`, `nivel_bateria`, `estado` |
| `estaciones` | Puntos de alquiler | `nombre`, `capacidad` |
| `alquileres` | Cada uso de una bici | `fecha_inicio`, `fecha_fin`, `costo_total`, `estado` |

### Procedimientos almacenados y dónde se usa cada uno

La base `ebike_management` contiene **31 procedimientos almacenados**. El
módulo dashboard utiliza 5 de ellos (los analíticos); el resto pertenece al
sistema de gestión completo (CRUD de usuarios, bicicletas, estaciones y el
ciclo de vida de los alquileres) y queda disponible para futuros módulos.

**A. Los 5 SPs que usa el dashboard — cadena completa de llamada:**

| SP | Llamado por (Modelo) | Endpoint (URL) | Función JS | Se ve en |
|---|---|---|---|---|
| `sp_validar_rol` | Todos los SPs lo invocan como 1ª línea | — | — | Seguridad: rechaza roles no autorizados (`SIGNAL SQLSTATE '45000'`) |
| `sp_reporte_estadisticas_admin` | `getEstadisticasAdmin()` | `?accion=api&tipo=estadisticas` | `actualizarKPIs()` | Las 6 tarjetas KPI + Resumen Ejecutivo del PDF |
| `sp_alquiler_buscar_paginado` | `getAlquileres()` | `?accion=api&tipo=barras`, `poligono`, `sectores`, `alquileres` · `exportar-excel` · `exportar-pdf` (estadísticas filtradas) | `renderTablaAlquileres()`, `renderGraficoBarras()`... | Tabla SPA, gráficos de barras/línea/dona, Excel, KPIs filtrados del PDF |
| `sp_histograma_duraciones` | `getDuracionAlquileres()` | `?accion=api&tipo=histograma` | `renderGraficoHistograma()` | Histograma de duraciones |
| `sp_indicadores_sistema` | `getIndicadoresRadar()` | `?accion=api&tipo=radar` | `renderGraficoRadar()` | Radar: batería 81,7 % · disponibilidad 48 % · ocupación 28 % · pagos 88,2 % · rotación 84 % |

**B. SPs del sistema de gestión disponibles (no usados por el dashboard):**

| Grupo | Procedimientos |
|---|---|
| Alquileres (ciclo de vida) | `sp_registrar_alquiler`, `sp_finalizar_alquiler`, `sp_alquiler_obtener_todos`, `sp_alquiler_historial_cliente`, `sp_alquiler_activos_por_cliente`, `sp_reporte_alquileres_detallado`, `sp_reporte_cantidad_alquileres`, `sp_reporte_cliente_historial`, `sp_reporte_estado_alquileres`, `sp_reporte_estacion` |
| Bicicletas | `sp_bicicleta_crear`, `sp_bicicleta_actualizar`, `sp_bicicleta_cambiar_estado`, `sp_bicicleta_listar_paginado`, `sp_bicicleta_obtener_todas`, `sp_bicicleta_obtener_disponibles` |
| Estaciones | `sp_estacion_crear`, `sp_estacion_actualizar`, `sp_estacion_obtener_todas` |
| Usuarios | `sp_usuario_crear`, `sp_usuario_actualizar`, `sp_usuario_estado`, `sp_usuario_obtener_todos`, `sp_usuario_obtener_por_id`, `sp_evaluar_cliente_frecuente` |
| Mantenimiento | `sp_agregar_repuesto_mantenimiento` |

**C. Prueba en vivo de que la app pasa por los SPs** (script CLI):

```
$ php demo_sps.php

=== SPs instalados ===   (SHOW PROCEDURE STATUS WHERE Db='ebike_management')
  31 procedimientos (listados arriba)

=== Llamada real desde el modelo ===
getEstadisticasAdmin -> sp_reporte_estadisticas_admin:
  25 bicicletas · 12 disponibles · 6 activos · 15 usuarios · 27 alquileres · 258,25 EUR
getIndicadoresRadar -> sp_indicadores_sistema:
  [81.7, 48, 28, 88.2, 84]
getDuracionAlquileres -> sp_histograma_duraciones:
  0-30 min: 3 · 30-60 min: 4 · 1-2 h: 7 · 2-4 h: 6
getAlquileres('FINALIZADO') -> sp_alquiler_buscar_paginado:
  fila real: {"cliente":"David Ruiz","estado_alquiler":"FINALIZADO", ...}

=== El SP valida el rol DENTRO de MySQL ===
CALL sp_reporte_estadisticas_admin('HACKER')
  → SQLSTATE[45000]: 1644 Acceso denegado: el rol no tiene permiso
```

> **Por qué esto importa**: si los datos se calcularan con SQL suelto en PHP,
> el rol `HACKER` habría obtenido los KPIs igualmente. Como el filtro de
> seguridad vive en el SP (primera línea: `CALL sp_validar_rol(...)`), cualquier
> cliente que intente saltarse la aplicación topa con el muro de la propia BD.

**Idea clave del diseño**: los filtros de estado/cliente/QR se resuelven en el SP
(cláusulas `WHERE (p_estado = '' OR a.estado = p_estado)`), mientras que el filtro
de **periodo** (HOY/SEMANA/MES) se aplica en PHP sobre `fecha_inicio`, porque es
más fácil de explicar y de mantener en un curso.

Ejemplos de uso directo:

```sql
CALL sp_reporte_estadisticas_admin('ADMIN');
CALL sp_alquiler_buscar_paginado(10, 0, 'FINALIZADO', '', '', 'ADMIN');
CALL sp_indicadores_sistema('ADMIN');
```

**Idea clave del diseño**: los filtros de estado/cliente/QR se resuelven en el SP
(cláusulas `WHERE (p_estado = '' OR a.estado = p_estado)`), mientras que el filtro
de **periodo** (HOY/SEMANA/MES) se aplica en PHP sobre `fecha_inicio`, porque es
más fácil de explicar y de mantener en un curso.

---

## 7. Librerías y para qué se usan

| Librería | Local | Qué aporta aquí |
|---|---|---|
| **Bootstrap 5.3** (CSS + JS bundle) | ✅ | Grid responsivo de KPIs y gráficos, modal de confirmación del PDF, utilidades de espaciado. |
| **Chart.js 4** | ✅ | Barras, histograma, línea/área, dona y radar + tooltips. |
| **SweetAlert2** | ✅ | Alertas de error amables cuando falla un fetch. |
| **FPDF** (implementación propia documentada) | ✅ | Genera el PDF escribiendo el formato a mano: fuentes Type1 estándar (`Times-Bold`...), imágenes JPEG con `/DCTDecode`, elipses con curvas Bézier y texto justificado con `Tw`. |

**Por qué librerías locales y no CDN**: la clase puede dictarse sin Internet y el
proyecto funciona offline; además evita sorpresas por cambios de versión en CDN.

### 7.1 Instalación paso a paso de cada librería (modo local/offline)

Todas las librerías se guardan en `public/assets/` para que el proyecto funcione
sin conexión. Para reproducir esta instalación desde cero:

**A. Bootstrap 5.3 (CSS + JS bundle)**
1. Descargar desde https://getbootstrap.com/docs/5.3/getting-started/download/
   (o desde el CDN: `bootstrap.min.css` y `bootstrap.bundle.min.js`).
2. Copiar a:
   - `public/assets/css/bootstrap.min.css`
   - `public/assets/js/bootstrap.bundle.min.js`
3. En `src/Views/dashboard.php` enlazar con `<link rel="stylesheet" href="assets/css/bootstrap.min.css">`
   y `<script src="assets/js/bootstrap.bundle.min.js"></script>`.

**B. Chart.js 4**
1. Descargar `chart.umd.min.js` desde https://www.chartjs.org/docs/latest/getting-started/installation.html
   (o el CDN `https://cdn.jsdelivr.net/npm/chart.js`).
2. Copiar a `public/assets/js/chart.umd.min.js`.
3. En la vista: `<script src="assets/js/chart.umd.min.js"></script>`.

**C. SweetAlert2**
1. Descargar `sweetalert2.min.js` desde https://sweetalert2.github.io/#download
   (o el CDN `https://cdn.jsdelivr.net/npm/sweetalert2`).
2. Copiar a `public/assets/js/sweetalert2.min.js`.
3. En la vista: `<script src="assets/js/sweetalert2.min.js"></script>`.

**D. FPDF (implementación propia)**
1. Este proyecto **no usa Composer**: la clase FPDF está incluida como un solo
   archivo en `vendor/fpdf/fpdf.php` (versión documentada).
2. Si partes de cero, descarga FPDF desde http://www.fpdf.org/ y coloca
   `fpdf.php` en `vendor/fpdf/fpdf.php`.
3. En `autoload.php` no se registra (no tiene namespace `App\`); se incluye
   manualmente donde se use (`require_once __DIR__ . '/../vendor/fpdf/fpdf.php'`).

**E. Extensión GD de PHP (para aplanar PNG→JPEG en el PDF)**
1. En Laragon/XAMPP viene activada. Verificar con:
   ```
   php -m | findstr gd
   ```
2. Si no aparece, editar `php.ini` y descomentar `extension=gd` (o `gd2`),
   reiniciar Apache y volver a verificar.

**F. MySQL/MariaDB**
1. Crear la base `ebike_management`.
2. Importar `ebike_management.sql` (tablas + datos).
3. Importar `sql/stored_procedures.sql` (los 5 SPs del dashboard).

---

## 8. Tutorial paso a paso

> Cada paso corresponde a archivos reales del proyecto. Puedes seguirlo
> construyendo desde cero.

### Paso 0 — Crear la estructura de carpetas

```
ebike_dashboard/
├── public/  ├── config/  ├── sql/  ├── src/{Controllers,Models,Views,Database,Export}
└── vendor/fpdf/
```

`public/` es lo único que Apache sirve; el resto queda fuera del alcance del
navegador por seguridad.

### Paso 1 — Autoloader PSR-4 (`autoload.php`)

PHP necesita saber dónde viven las clases. En vez de escribir `require` en cada
archivo, registramos una función que traduce namespaces a rutas:

```php
spl_autoload_register(function ($class) {
    $prefix = 'App\\';                    // todas nuestras clases empiezan por App\
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;

    $relativa = substr($class, strlen($prefix));        // Models\DashboardModel
    $archivo  = __DIR__ . '/src/' . str_replace('\\', '/', $relativa) . '.php';
    if (file_exists($archivo)) require $archivo;         // src/Models/DashboardModel.php
});
```

**Qué aprendes**: namespaces, `spl_autoload_register` y el estándar PSR-4.

### Paso 2 — Conexión única a MySQL (patrón Singleton)

`config/database.php` solo devuelve un array con credenciales.
`src/Database/Database.php` garantiza UNA conexión PDO por request:

```php
public static function obtenerInstancia(): \PDO
{
    if (self::$instancia === null) {
        $config = require __DIR__ . '/../../config/database.php';
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        self::$instancia = new \PDO($dsn, $config['user'], $config['pass'], [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION, // errores como excepciones
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,       // filas como arrays con nombre
            \PDO::ATTR_EMULATE_PREPARES   => false,                   // preparación real
        ]);
    }
    return self::$instancia;
}
```

**Qué aprendes**: por qué una sola conexión (eficiencia), DSN, excepciones y modos de fetch.

### Paso 3 — La base de datos y sus SPs

1. Crea las tablas (ver dump).
2. Escribe los SPs con `DELIMITER $$` para que MySQL acepte los `;` internos:

```sql
DELIMITER $$
CREATE PROCEDURE sp_reporte_estadisticas_admin(IN p_rol_ejecutor VARCHAR(30))
BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');  -- seguridad primero
    SELECT
        (SELECT COUNT(*) FROM bicicletas)                                  AS total_bicicletas,
        (SELECT COUNT(*) FROM bicicletas WHERE estado = 'DISPONIBLE')      AS bicicletas_disponibles,
        (SELECT COUNT(*) FROM alquileres WHERE estado = 'ACTIVO')          AS alquileres_activos,
        (SELECT COUNT(*) FROM usuarios)                                    AS total_usuarios,
        (SELECT COUNT(*) FROM alquileres)                                  AS total_alquileres,
        (SELECT COALESCE(SUM(costo_total),0) FROM alquileres
                 WHERE estado = 'FINALIZADO')                              AS ingresos_totales;
END$$
DELIMITER ;
```

El SP de búsqueda admite filtros opcionales con el truco `(:valor = '' OR ...)`:

```sql
WHERE (p_estado IS NULL OR p_estado = '' OR a.estado = p_estado)
  AND (p_cliente_nombre = '' OR u.nombre LIKE CONCAT('%', p_cliente_nombre, '%'))
  AND (p_bicicleta_qr   = '' OR b.codigo_qr LIKE CONCAT('%', p_bicicleta_qr, '%'))
```

**Qué aprendes**: `DELIMITER`, `CALL`, múltiples result sets, subconsultas escalares, `LIKE` con comodines y seguridad basada en roles.

### Paso 4 — El Modelo (`src/Models/DashboardModel.php`)

Dos ayudantes ejecutan cualquier SP y leen sus result sets:

```php
// Devuelve la primera fila del primer result set (para KPIs/indicadores)
protected function callSpSingleRow(string $sp, array $params): array

// Devuelve TODAS las filas del último result set (para listados)
protected function callSpAllRows(string $sp, array $params): array
```

Cada método público corresponde a un gráfico o reporte. Ejemplo del radar,
que delega el cálculo al SP:

```php
public function getIndicadoresRadar(string $rolEjecutor): array
{
    $fila = $this->callSpSingleRow('sp_indicadores_sistema', [$rolEjecutor]);
    return [
        'labels' => ['Bateria Promedio (%)', 'Disponibilidad de Flota (%)', /*...*/],
        'data'   => [(float)$fila['bateria_promedio'], /*...*/],
    ];
}
```

Y las **estadísticas filtradas**, que recomputan KPIs con el mismo conjunto
filtrado que los gráficos (para que el resumen del PDF coincida con la pantalla):

```php
public function getEstadisticasAdmin(string $rol, string $estado = '',
    string $cliente = '', string $qr = '', string $periodo = 'TODOS'): array
{
    $stats = /* ...KPIs globales del SP... */;

    // Sin filtros → datos globales. Con filtros → recalcular sobre el subset
    if ($estado === '' && $cliente === '' && $qr === '' && $periodo === 'TODOS') {
        return $stats;
    }
    $alquileres = $this->getAlquileres($rol, $estado, $cliente, $qr, 5000, 0, $periodo);
    foreach ($alquileres as $a) {
        // +1 alquiler; suma costo si FINALIZADO; +1 activo si ACTIVO
    }
    return $stats;
}
```

**Qué aprendes**: encapsular acceso a datos, separar "global" de "filtrado" y mantener una única fuente de verdad.

### Paso 5 — Front Controller y API (`public/index.php`, Controlador)

El router es un `switch` legible:

```php
$accion = $_GET['accion'] ?? 'dashboard';
switch ($accion) {
    case 'dashboard':      $controller->index();         break;
    case 'api':             $controller->apiChart($_GET['tipo'] ?? ''); break;
    case 'exportar-pdf':    $controller->exportarPdf();   break;
    case 'exportar-excel':  $controller->exportarExcel(); break;
}
```

`apiChart()` convierte cualquier método del modelo en JSON:

```php
case 'estadisticas':
    $datos = $this->model->getEstadisticasAdmin($rol, $estado, $cliente, $qr, $periodo);
    break;
// ...
echo json_encode(['success' => true, 'data' => $datos]);
```

**Qué aprendes**: separar API de vista, cabeceras JSON y contratos de respuesta (`success/data`).

### Paso 6 — La vista SPA (`src/Views/dashboard.php`)

Estructura: **sidebar** (navegación + filtros) + **contenido** con secciones:

```html
<div class="app-layout">
  <aside class="sidebar" id="sidebarFiltros">
      <div class="brand">🚲 Ebike<span class="brand-accent">Manager</span></div>
      <!-- Menú SPA -->
      <nav id="navSpa">
          <button data-seccion="seccionResumen">🏠 Resumen</button>
          <button data-seccion="seccionGraficas">📊 Gráficas</button>
          <button data-seccion="seccionAlquileres">📋 Alquileres</button>
      </nav>
      <!-- Filtros: pills de estado/periodo + inputs cliente/qr -->
  </aside>
  <main>
      <section id="seccionResumen" class="seccion-spa activa"> KPIs + radar </section>
      <section id="seccionGraficas" class="seccion-spa"> 4 gráficos </section>
      <section id="seccionAlquileres" class="seccion-spa"> tabla </section>
  </main>
</div>
```

La paleta vive en variables CSS (documentadas en la propia vista):

```css
:root {
    --bg-sidebar: #3B4A26;  /* verde olivo profundo */
    --accent-mint: #53D397; --accent-gold: #D4AF37;
    --accent-blue: #2A75D3; --accent-red: #E63946;
    --card-bg: #F8FAF9; --border-radius: 12px;
}
```

El **menú acordeón** móvil: el sidebar se pliega con `max-height:0` y se
despliega al pulsar el botón hamburguesa (`☰/✕`) con transición suave.

**Qué aprendes**: flexbox para el layout, variables CSS, accesibilidad (`aria-expanded`), diseño responsive móvil-primero.

### Paso 7 — `dashboard.js`: datos → gráficos

El corazón es `cargarTodos()`, que pide TODOS los datos en paralelo y repinta:

```js
async function cargarTodos() {
    const params = obtenerParams();   // lee pills e inputs → query string
    const [barras, histograma, poligono, sectores, radar, stats, alquileres] =
        await Promise.all([
            fetch(`${apiBase}barras&${params}`).then(r => r.json()),
            // ... 7 endpoints en paralelo
        ]);
    renderGraficoBarras(barras); renderGraficoHistograma(histograma); // ...
    actualizarKPIs(stats.data);
    renderTablaAlquileres(alquileres);
}
```

Cada gráfico define colores de la paleta. Ejemplo: la estación con más
ingresos se pinta en **dorado**:

```js
const maximo  = Math.max(...d.data, 0);
const colores = d.data.map(v => v === maximo ? PALETA.dorado : PALETA.olivo);
```

**Etiquetas numéricas** con un plugin propio (se dibujan dentro del canvas,
por eso aparecen también en el PDF):

```js
function crearPluginEtiquetas({ formato }) {
    return {
        id: 'etiquetasDatos',
        afterDatasetsDraw(chart) {          // se ejecuta tras dibujar los datos
            const meta = chart.getDatasetMeta(0);
            meta.data.forEach((el, i) => {
                const v = chart.data.datasets[0].data[i];
                ctx.fillText(formato(v), el.x, el.y - 7); // encima de cada barra
            });
        }
    };
}
```

**Qué aprendes**: `async/await`, `Promise.all`, el ciclo de vida de plugins de Chart.js y por qué el orden de dibujo importa.

### Paso 8 — Filtros en vivo (y el bug que enseña este paso)

Flujo correcto: `pills/inputs → aplicarFiltros() → cargarTodos()`.

> **Lección real del proyecto**: `aplicarFiltros()` llamaba a `actualizarChips()`,
> una función que NO existía → `ReferenceError` → `cargarTodos()` nunca se
> ejecutaba → "los filtros no funcionan". **Moraleja**: cuando una cadena de
> funciones falla en silencio, verifica con la consola del navegador y
> protege los pasos decorativos con `try/catch`:

```js
function aplicarFiltros() {
    try { actualizarChips(); } catch (e) { console.warn(e); } // decorativo: no bloquea
    cargarTodos();                                            // esencial: siempre corre
}
```

También añadimos **debounce** a la búsqueda por texto (450 ms) para no hacer
una petición por cada tecla.

### Paso 9 — Tabla de alquileres (vista de datos crudos)

Endpoint nuevo en el controlador:

```php
case 'alquileres':
    $datos = $this->model->getAlquileres($rol, $estado, $cliente, $qr, 1000, 0, $periodo);
    break;
```

El frontend renderiza las filas con **badges de color por estado**
(ACTIVO=menta, RESERVADO=azul, FINALIZADO=olivo, PENDIENTE_PAGO=dorado,
CANCELADO=rojo) usando `textContent` (anti-XSS) y paginación simple de 12 filas.

### Paso 10 — Exportación Excel (CSV nativo)

Sin librerías: `fputcsv()` con separador `;` (Excel en español) y **BOM UTF-8**
al inicio para que Excel respete acentos:

```php
echo "\xEF\xBB\xBF";                       // BOM: Excel entiende UTF-8
fputcsv($salida, $fila, ';');              // ; porque en ES la coma es decimal
```

### Paso 11 — Exportación PDF con FPDF (el plato fuerte)

**11.1 Capturar los gráficos del navegador.** Antes de capturar, forzamos el
repintado síncrono (los charts animados o con la pestaña oculta pueden estar
"vacíos" porque `requestAnimationFrame` se pausa):

```js
function capturarImagenChart(chart) {
    chart.options.animation = false;
    chart.update('none');     // recalcula al estado final
    chart.draw();             // pinta AHORA, sin esperar rAF
    return chart.toBase64Image();   // PNG en Base64
}
```

**11.2 Enviar al servidor.** `fetch` POST con JSON: `{imagenes, estado, cliente, qr, periodo}`.

**11.3 Aplanar PNG transparente sobre blanco.** Los canvas de Chart.js tienen
fondo transparente; un PNG RGBA convertido a JPEG "aplana" el alfa a NEGRO.
Solución con GD:

```php
$src = imagecreatefromstring($png);
$dst = imagecreatetruecolor($w, $h);
imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // fondo blanco
imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);                      // mezcla el alfa
imagejpeg($dst, null, 90);                 // JPEG → se incrusta con /DCTDecode
```

**11.4 Normas APA.**

| Norma | Implementación |
|---|---|
| Márgenes 2,54 cm | `$lMargin = $rMargin = $tMargin = $bMargin = 25.4;` |
| Times New Roman 12 | `SetFont('Times', '', 12)` + nombres de fuente estándar (`Times-Bold`) |
| Sangría 1,27 cm | Primera línea del párrafo en `SetX(lMargin + 12.7)` |
| Justificado homogéneo | Operador `Tw`: `espacioExtra = anchoUtil - anchoTexto`, repartido entre palabras (métricas AFM reales de Times para el cálculo) |
| Figuras APA | `Figura N` (negrita) + título en cursiva ARRIBA; `Nota.` con sangría francesa DEBAJO |
| Número de página | Esquina superior derecha en cada `Header()` |
| Layout | Pág. 1: resumen + Figura 1 juntos; después máximo 2 figuras por página |

**11.5 Logo vectorial.** La bicicleta se dibuja con 2 elipses (4 arcos Bézier
cada una) + líneas; por eso escala sin perder calidad.

### Paso 12 — Pruebas sin navegador (CLI)

Crear un script que use las clases reales con datos de prueba y verificar:

```bash
php test_paleta_pdf.php   # genera el PDF y analiza sus bytes
```

Verificaciones útiles: `php -l` (sintaxis), extraer streams del PDF y buscar
textos clave, comprobar que las imágenes no tienen fondo negro decodificándolas
con GD, y validar la tabla `xref`. Es TDD ligero para formato de archivos.

---

## 9. El flujo completo de una exportación PDF

```
[Usuario] pulsa "PDF ejecutivo"
   │
   ├─ mostrarModalConfirmarPDF()   → tabla de registros + filtros a aplicar
   ├─ [Aceptar] generarPDF()
   │     ├─ leerFiltros()           (pills/inputs ocultos)
   │     ├─ capturarImagenChart()  × 5  (repinta y convierte canvas→Base64)
   │     └─ POST ?accion=exportar-pdf  {imagenes, filtros}
   │
   ├─ [Servidor] exportarPdf()
   │     ├─ getEstadisticasAdmin($rol, $estado, $cliente, $qr, $periodo)
   │     │     └─ SP sp_alquiler_buscar_paginado → KPIs filtrados
   │     └─ PdfExport->generarReporteEjecutivo(...)
   │           ├─ decodifica Base64 → PNG temporal
   │           ├─ portada: logo Bézier + marca + filtros (márgenes APA)
   │           ├─ Resumen Ejecutivo: cifras REALES + texto justificado (Tw)
   │           ├─ Figuras 1..5: título APA + imagen (aplanada blanca) + Nota.
   │           └─ Output('D') → bytes del PDF
   │
   └─ [Navegador] recibe blob → descarga reporte_ejecutivo_YYYY-MM-DD.pdf
```

---

## 10. Depuración: lecciones aprendidas

Problemas reales encontrados durante el desarrollo y cómo se resolvieron:

| # | Síntoma | Causa raíz | Solución |
|---|---|---|---|
| 1 | "Los filtros no aplican" | Se llamaba a `actualizarChips()` que no existía → excepción antes de `cargarTodos()` | Implementar la función + `try/catch` protector |
| 2 | PDF con gráficos negros | PNG con canal alfa → GD lo aplasta a negro al hacer JPEG | Componer sobre fondo blanco antes de `imagejpeg` |
| 3 | PDF con celdas de tabla negras | `SetFillColor()` no emitía el operador `rg` al PDF (el default de PDF es negro) | Emitir el color en el momento del `SetFillColor` y re-emitirlo en cada `AddPage` |
| 4 | Colores "lavados" | `$b = $g` sobrescribía el componente azul | Manejo correcto de los 3 componentes RGB |
| 5 | Imágenes cortadas al final de página | Salto de página se evaluaba DESPUÉS de dibujar | Calcular alto de imagen y saltar ANTES |
| 6 | "PDF dañado" en visores estrictos | Tabla xref desplazada 9 bytes (cabecera no contada) | Sumar la longitud de la cabecera al construir xref |
| 7 | Charts vacíos al capturar | `requestAnimationFrame` pausado (ventana oculta) o animación a medias | `update('none')` + `draw()` síncronos antes de `toBase64Image` |
| 8 | Namespace PHP fallaba tras editar con PowerShell | El cmdlet escribió BOM UTF-8 antes de `<?php` | Escribir sin BOM; verificar con un script |
| 9 | El JS nuevo no cargaba | Caché del navegador servía `dashboard.js` viejo | Cache-busting: `dashboard.js?v=3` |
| 10 | Texto del PDF mal en algunos visores | Nombres de fuente no estándar (`HelveticaB`) | Mapa canónico: `Times-Bold`, `Helvetica-Oblique`... |
| 11 | Párrafos desbordados | `MultiCell` no ajustaba líneas | Ajuste manual palabra a palabra con `GetStringWidth` (métricas reales) |
| 12 | PDF decía 27 alquileres con filtro puesto | `getEstadisticasAdmin` ignoraba los filtros | Firmas con parámetros de filtro + recálculo sobre el subset |
| 13 | Excel ignoraba el periodo | `getAlquileres` no lo recibía | Parámetro `$periodo` + filtro por `fecha_inicio` en PHP |

**Herramientas de depuración usadas**:
- Consola del navegador (`console.warn`, pestaña Network para ver los fetch).
- `php -l` para sintaxis y scripts CLI para probar SPs/PDF sin navegador.
- Análisis de bytes del PDF con expresiones regulares (buscar operadores `Tj`, `Tw`, `Do`).
- Interceptación temporal de `fetch` en la página para ver el cuerpo exacto del POST.

---

## 11. Tour del código documentado

Cada archivo lleva comentarios de bloque en español. Ruta de lectura sugerida:

1. **`public/index.php`** (30 líneas) — el mapa del sistema.
2. **`autoload.php`** — 20 líneas que eliminan todos los `require`.
3. **`config/database.php` + `src/Database/Database.php`** — la conexión.
4. **`sql/stored_procedures.sql`** — la "lógica de negocio" en SQL.
5. **`src/Models/DashboardModel.php`** — cómo se envuelven los SPs; busca
   `callSpSingleRow`, `callSpAllRows` y el filtro de periodo.
6. **`src/Controllers/DashboardController.php`** — contratos JSON y exports.
7. **`public/assets/js/dashboard.js`** — SPA: `cargarTodos`, `mostrarSeccion`,
   `renderTablaAlquileres`, `crearPluginEtiquetas` y los 5 `renderGrafico*`.
8. **`src/Views/dashboard.php`** — layout, paleta CSS, pills, modal,
   acordeón y `generarPDF()` (captura + descarga).
9. **`src/Export/PdfExport.php`** — APA de principio a fin:
   `portada`, `parrafoApa`, `lineaJustificada` (operador `Tw`), `seccionFiguras`
   (Figura 1 junto al resumen; máx. 2 por página), `notaApa`, `Header()` (nº de página).
10. **`vendor/fpdf/fpdf.php`** — FPDF comentado: `Cell`, `Image` (PNG→JPEG),
    `Ellipse` (Bézier), `_putimages`, `_buildpdf` (xref), `_baseFontName`.
11. **`src/Export/ExcelExport.php`** — CSV con BOM y `;`.

---

## Créditos

Proyecto didáctico de curso — construido paso a paso con PHP, MySQL, Bootstrap,
Chart.js y FPDF. Todos los datos mostrados provienen de la base de datos
`ebike_management` a través de procedimientos almacenados.

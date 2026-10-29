<!DOCTYPE html>
<!-- Declaracion del tipo de documento: HTML5 -->
<html lang="es">
<!-- Idioma del documento: espanol -->
<head>
    <!-- Codificacion de caracteres UTF-8 para acentos y simbolos -->
    <meta charset="UTF-8">
    <!-- Viewport responsivo: ancho del dispositivo, escala inicial 1 -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Titulo de la pestana del navegador -->
    <title>Ebike Manager - Dashboard</title>

    <!-- Bootstrap 5 (local): framework CSS para layout y componentes -->
    <link href="/ebike_dashboard/public/assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js (local): libreria para renderizar graficos en canvas -->
    <script src="/ebike_dashboard/public/assets/js/chart.umd.min.js"></script>

    <!-- SweetAlert2 (local): alertas/modales elegantes (reservado para uso) -->
    <script src="/ebike_dashboard/public/assets/js/sweetalert2.min.js"></script>

    <!-- ============================================================
         GUIA DE ESTILOS - Paleta "Limpia, Organica y Tecnologica"
         Define variables CSS (colores, sombras, radios) y estilos globales.
         ============================================================ -->
    <style>
        /* Variables CSS globales: paleta de colores y medidas de layout */
        :root {
            /* Colores principales de la paleta */
            --bg-dashboard: #FFFFFF;        /* Fondo principal del dashboard */
            --bg-sidebar:   #3B4A26;        /* Verde Olivo Profundo (sidebar) */
            --accent-mint:  #53D397;        /* Verde Menta (acento principal) */
            --accent-gold:  #D4AF37;        /* Dorado (destacados) */
            --accent-blue:  #2A75D3;        /* Azul Electrico (periodo/botones) */
            --accent-red:   #E63946;        /* Rojo Alerta (cancelados) */
            --olive:        #556B2F;        /* Verde Olivo (titulos KPI) */
            --text-dark:    #1F2937;        /* Texto principal oscuro */
            --text-soft:    #6B7280;        /* Texto secundario suave */
            --card-bg:      #F8FAF9;        /* Fondo de tarjetas KPI */

            /* Layout y sombras reutilizables */
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);  /* Sombra suave de tarjetas */
            --border-radius: 12px;                            /* Radio de borde estandar */
        }

        /* Estilos base del body: fondo, color de texto y tipografia */
        body {
            background: var(--bg-dashboard);   /* Fondo blanco del dashboard */
            color: var(--text-dark);            /* Color de texto oscuro */
            font-family: "Segoe UI", system-ui, sans-serif; /* Fuente del sistema */
        }

        /* ========================================================
           LAYOUT GENERAL: Sidebar + Contenido central
           Contenedor flex que coloca sidebar a la izquierda y main a la derecha.
           ======================================================== */
        .app-layout { display: flex; min-height: 100vh; }  /* Flex horizontal, altura minima viewport */

        /* ========================================================
           1. SIDEBAR IZQUIERDO - Barra de filtros (Verde Olivo)
           Barra lateral fija con filtros globales de estado, busqueda y periodo.
           ======================================================== */
        .sidebar {
            width: 290px;               /* Ancho fijo del sidebar */
            flex-shrink: 0;             /* No se encoge en flex */
            background: linear-gradient(180deg, var(--bg-sidebar) 0%, #2E3A1E 100%); /* Degradado verde */
            color: #E9F0E2;             /* Color de texto claro sobre verde */
            padding: 1.75rem 1.25rem;   /* Espaciado interno */
            position: sticky;           /* Se mantiene visible al hacer scroll */
            top: 0;                     /* Pegado arriba */
            height: 100vh;              /* Altura completa del viewport */
            overflow-y: auto;           /* Scroll vertical si el contenido es alto */
        }

        /* Marca: Ebike Manager (logo/titulo en el sidebar) */
        .brand {
            font-size: 1.45rem;         /* Tamano de fuente grande */
            font-weight: 700;           /* Negrita */
            letter-spacing: 0.5px;      /* Espaciado entre letras */
            color: #FFFFFF;             /* Blanco */
        }
        .brand .brand-accent { color: var(--accent-mint); } /* "Manager" en verde menta */
        .brand .brand-gold   { color: var(--accent-gold); } /* Acento dorado (no usado actualmente) */
        .brand-sub {
            font-size: 0.75rem;         /* Texto pequeno */
            color: #B9C7A9;             /* Verde claro suave */
            margin-bottom: 1.75rem;     /* Separacion inferior */
        }

        /* Titulos de seccion del sidebar (Estado, Busqueda, Periodo, Exportar) */
        .section-title {
            font-size: 0.7rem;          /* Muy pequeno */
            font-weight: 700;           /* Negrita */
            text-transform: uppercase;  /* Mayusculas */
            letter-spacing: 1.5px;      /* Espaciado amplio */
            color: var(--accent-mint);  /* Verde menta */
            margin: 1.6rem 0 0.6rem;    /* Margen superior e inferior */
        }

        /* Pills de Estado con punto de color por estado (botones redondeados) */
        .pill-estado {
            display: flex;              /* Flex para alinear punto + texto */
            align-items: center;        /* Centrado vertical */
            gap: 0.55rem;               /* Espacio entre punto y texto */
            width: 100%;                /* Ocupa todo el ancho */
            background: rgba(255, 255, 255, 0.06); /* Fondo translucido */
            border: 1px solid rgba(255, 255, 255, 0.12); /* Borde translucido */
            color: #E9F0E2;             /* Texto claro */
            border-radius: 999px;       /* Forma de pill redondeada */
            padding: 0.42rem 0.85rem;   /* Espaciado interno */
            font-size: 0.83rem;         /* Tamano pequeno */
            text-align: left;           /* Texto a la izquierda */
            transition: all 0.15s ease; /* Transicion suave en hover/active */
        }
        .pill-estado:hover { background: rgba(255, 255, 255, 0.14); } /* Hover mas claro */
        .pill-estado.active {
            background: var(--accent-mint); /* Fondo verde menta cuando activo */
            border-color: var(--accent-mint);
            color: #14361F;             /* Texto verde oscuro */
            font-weight: 700;           /* Negrita */
        }
        /* Punto de color indicador de estado */
        .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; } /* Circulo base */
        .dot-reservado  { background: var(--accent-blue); }   /* Azul: reservado */
        .dot-activo     { background: var(--accent-mint); }  /* Menta: activo */
        .dot-finalizado { background: var(--olive); }         /* Olivo: finalizado */
        .dot-cancelado  { background: var(--accent-red); }    /* Rojo: cancelado */
        .dot-pendiente  { background: var(--accent-gold); }   /* Dorado: pendiente pago */
        .dot-todos      { background: #CFD8C4; }              /* Gris claro: todos */

        /* Busqueda dinamica: inputs de Cliente y QR */
        .search-input {
            width: 100%;                /* Ancho completo */
            background: rgba(255, 255, 255, 0.08); /* Fondo translucido */
            border: 1px solid rgba(255, 255, 255, 0.18); /* Borde translucido */
            border-radius: var(--border-radius); /* Radio estandar */
            color: #FFFFFF;             /* Texto blanco */
            padding: 0.5rem 0.8rem;     /* Espaciado interno */
            font-size: 0.85rem;         /* Tamano pequeno */
            margin-bottom: 0.6rem;      /* Separacion inferior */
        }
        .search-input::placeholder { color: #A7B698; } /* Color del placeholder */
        .search-input:focus {
            background: rgba(255, 255, 255, 0.14); /* Fondo mas claro al enfocar */
            border-color: var(--accent-mint);        /* Borde menta */
            color: #fff;                            /* Texto blanco */
            box-shadow: none;                       /* Sin sombra de foco */
            outline: none;                          /* Sin outline por defecto */
        }

        /* Pills de Periodo (Azul y Blanco): TODOS/HOY/SEMANA/MES */
        .pills-periodo { display: flex; gap: 0.4rem; } /* Flex con espacio entre pills */
        .pill-periodo {
            flex: 1;                    /* Crecen por igual */
            background: transparent;    /* Fondo transparente */
            border: 1.5px solid var(--accent-blue); /* Borde azul */
            color: #CFE0FA;             /* Texto azul claro */
            border-radius: 999px;       /* Forma pill */
            font-size: 0.72rem;         /* Muy pequeno */
            font-weight: 600;           /* Semi-negrita */
            padding: 0.32rem 0.2rem;    /* Espaciado compacto */
            transition: all 0.15s ease; /* Transicion suave */
        }
        .pill-periodo:hover { background: rgba(42, 117, 211, 0.25); } /* Hover azul translucido */
        .pill-periodo.active {
            background: var(--accent-blue); /* Fondo azul cuando activo */
            color: #FFFFFF;             /* Texto blanco */
        }

        /* Botones de exportacion (PDF y Excel) */
        .btn-export {
            width: 100%;                /* Ancho completo */
            border-radius: var(--border-radius); /* Radio estandar */
            font-weight: 700;           /* Negrita */
            font-size: 0.9rem;          /* Tamano medio */
            padding: 0.6rem;            /* Espaciado interno */
            border: none;               /* Sin borde */
            transition: transform 0.12s ease, box-shadow 0.12s ease; /* Transicion */
        }
        .btn-export:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.25); } /* Elevacion al hover */
        .btn-export-pdf { background: var(--accent-gold); color: #2B2103; } /* PDF: dorado */
        .btn-export-xls { background: var(--accent-mint); color: #14361F; margin-top: 0.5rem; display: block; text-align: center; text-decoration: none; } /* Excel: menta */

        /* ========================================================
           2. ENCABEZADO SUPERIOR + KPIs (Tarjetas elevated)
           Estilos del contenido principal y tarjetas de indicadores.
           ======================================================== */
        .main-content { flex: 1; padding: 1.5rem 2rem 3rem; min-width: 0; } /* Area principal flexible */

        /* Encabezado del dashboard (titulo + subtitulo + chips) */
        .dash-header { margin-bottom: 1.5rem; } /* Separacion inferior */
        .dash-header h1 {
            font-weight: 800;           /* Extra negrita */
            font-size: 1.7rem;          /* Tamano grande */
            color: var(--olive);        /* Verde olivo */
            margin-bottom: 0.15rem;     /* Poco espacio inferior */
        }
        .dash-header p { color: var(--text-soft); font-size: 0.88rem; margin: 0; } /* Subtitulo suave */

        /* Tarjeta KPI generica: contenedor de cada indicador */
        .kpi-card {
            background: var(--card-bg); /* Fondo claro */
            border-radius: var(--border-radius); /* Radio estandar */
            box-shadow: var(--card-shadow); /* Sombra suave */
            border: 1px solid #EDF2EE;  /* Borde claro */
            padding: 1rem 0.9rem;       /* Espaciado interno */
            text-align: center;         /* Centrado */
            height: 100%;               /* Altura completa de la columna */
            position: relative;         /* Para la barra superior ::before */
            overflow: hidden;           /* Oculta desbordamiento */
        }
        .kpi-card::before {
            content: "";                /* Barra de color superior */
            position: absolute;
            top: 0; left: 0; right: 0;  /* Ocupa todo el ancho superior */
            height: 4px;                /* Altura de la barra */
        }
        .kpi-icon { font-size: 1.35rem; } /* Icono emoji */
        .kpi-value { font-size: 1.5rem; font-weight: 800; line-height: 1.1; margin-top: 0.15rem; } /* Valor numerico */
        .kpi-label { font-size: 0.72rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: 0.5px; margin: 0; } /* Etiqueta */

        /* Acentos por indicador (spec): color de texto y barra superior */
        .kpi-olive  { color: var(--olive); }                 /* Texto olivo */
        .kpi-olive::before  { background: var(--olive); }    /* Barra olivo */
        .kpi-olive.gold-border { border-color: var(--accent-gold); box-shadow: inset 0 0 0 1px var(--accent-gold); } /* Borde dorado especial */
        .kpi-mint   { color: #1E7A52; }                      /* Texto verde oscuro */
        .kpi-mint::before   { background: var(--accent-mint); } /* Barra menta */
        .kpi-blue   { color: var(--accent-blue); }           /* Texto azul */
        .kpi-blue::before   { background: var(--accent-blue); } /* Barra azul */
        .kpi-gold   { color: #A8842A; }                      /* Texto dorado oscuro */
        .kpi-gold::before   { background: var(--accent-gold); } /* Barra dorado */
        .kpi-gold-text .kpi-value { color: var(--accent-gold); } /* Valor en dorado (ingresos) */

        /* Boton hamburguesa: solo visible en pantallas pequenas */
        .btn-menu {
            display: none;              /* Oculto por defecto (desktop) */
            align-items: center;        /* Centrado vertical */
            gap: 0.5rem;                /* Espacio icono-texto */
            background: var(--bg-sidebar); /* Fondo verde olivo */
            color: #FFFFFF;             /* Texto blanco */
            border: none;               /* Sin borde */
            border-radius: var(--border-radius); /* Radio estandar */
            font-size: 0.95rem;         /* Tamano medio */
            font-weight: 700;           /* Negrita */
            padding: 0.55rem 1.1rem;    /* Espaciado interno */
            margin-bottom: 0.75rem;     /* Separacion inferior */
            cursor: pointer;            /* Cursor de mano */
            box-shadow: var(--card-shadow); /* Sombra */
        }
        .btn-menu .icono { font-size: 1.15rem; line-height: 1; } /* Icono hamburguesa */

        /* ========================================================
           3. ZONA PRINCIPAL - Bento Grid de graficos
           Estilos de tarjetas de graficos y contenedores canvas.
           ======================================================== */
        .chart-card {
            background: #FFFFFF;        /* Fondo blanco */
            border-radius: var(--border-radius); /* Radio estandar */
            box-shadow: var(--card-shadow); /* Sombra suave */
            border: 1px solid #EDF2EE;  /* Borde claro */
            padding: 1.15rem 1.25rem 1rem; /* Espaciado interno */
            height: 100%;               /* Altura completa de columna */
        }
        .chart-card .chart-title {
            font-weight: 700;           /* Negrita */
            font-size: 0.98rem;         /* Tamano medio */
            color: var(--text-dark);    /* Texto oscuro */
            margin: 0;                  /* Sin margen */
        }
        .chart-card .chart-subtitle {
            font-size: 0.78rem;         /* Pequeno */
            color: var(--text-soft);     /* Texto suave */
            margin: 0.1rem 0 0.25rem;   /* Margenes compactos */
        }
        .chart-box { position: relative; height: 300px; }      /* Contenedor canvas estandar (300px) */
        .chart-box-tall { position: relative; height: 340px; } /* Contenedor canvas alto (radar) */

        /* Chips de filtros activos (badges en el encabezado) */
        #chipsFiltros .badge { font-weight: 600; border-radius: 999px; padding: 0.45em 0.9em; } /* Badge redondeado */

        /* Responsive: en pantallas pequenas el sidebar se convierte en
           un menu ACORDEON que se despliega con el boton hamburguesa */
        @media (max-width: 991px) {
            .app-layout { flex-direction: column; } /* Apila verticalmente */
            .btn-menu { display: inline-flex; }     /* Muestra boton hamburguesa */
            .sidebar {
                width: 100%;            /* Ancho completo */
                height: auto;           /* Altura automatica */
                position: static;       /* Posicionamiento normal */
                max-height: 0;          /* Colapsado por defecto */
                overflow: hidden;       /* Oculta contenido colapsado */
                padding-top: 0;         /* Sin padding superior */
                padding-bottom: 0;      /* Sin padding inferior */
                transition: max-height 0.4s ease, padding 0.4s ease; /* Animacion suave */
            }
            .sidebar.abierto {
                max-height: 1200px;     /* Espacio suficiente para todo el contenido */
                padding-top: 1.75rem;   /* Restaura padding superior */
                padding-bottom: 1.5rem;  /* Restaura padding inferior */
            }
            .main-content { padding: 1.25rem; } /* Padding reducido en movil */
        }
    </style>
</head>
<!-- Fin del head: recursos y estilos cargados -->
<body>
<!-- Inicio del body: contenido visible de la pagina -->

<!-- Contenedor principal del layout: sidebar + contenido central -->
<div class="app-layout">

    <!-- ============================================================
         1. SIDEBAR IZQUIERDO - Navegacion y filtros globales
         En resoluciones pequenas se despliega como menu acordeon.
         ============================================================ -->
    <!-- Aside lateral: filtros globales del dashboard -->
    <aside class="sidebar" id="sidebarFiltros">
        <!-- Marca/logo del dashboard -->
        <div class="brand">🚲 Ebike<span class="brand-accent">Manager</span></div>
        <!-- Subtitulo descriptivo -->
        <p class="brand-sub">Panel de control de flota electrica</p>

        <!-- Formulario de filtros: contiene pills, inputs y botones de exportacion -->
        <form id="formFiltros">
            <!-- Estado del alquiler: pills de seleccion de estado -->
            <div class="section-title">Estado</div>
            <!-- Contenedor de pills de estado (se sincronizan con input oculto) -->
            <div id="pillsEstado" class="d-grid gap-2">
                <!-- Pill TODOS: sin filtro de estado -->
                <button type="button" class="pill-estado active" data-valor="">
                    <span class="dot dot-todos"></span>TODOS
                </button>
                <!-- Pill RESERVADO -->
                <button type="button" class="pill-estado" data-valor="RESERVADO">
                    <span class="dot dot-reservado"></span>RESERVADO
                </button>
                <!-- Pill ACTIVO -->
                <button type="button" class="pill-estado" data-valor="ACTIVO">
                    <span class="dot dot-activo"></span>ACTIVO
                </button>
                <!-- Pill FINALIZADO -->
                <button type="button" class="pill-estado" data-valor="FINALIZADO">
                    <span class="dot dot-finalizado"></span>FINALIZADO
                </button>
                <!-- Pill CANCELADO -->
                <button type="button" class="pill-estado" data-valor="CANCELADO">
                    <span class="dot dot-cancelado"></span>CANCELADO
                </button>
                <!-- Pill PENDIENTE_PAGO -->
                <button type="button" class="pill-estado" data-valor="PENDIENTE_PAGO">
                    <span class="dot dot-pendiente"></span>PENDIENTE_PAGO
                </button>
            </div>

            <!-- Busqueda dinamica: por cliente y codigo QR -->
            <div class="section-title">Búsqueda dinámica</div>
            <!-- Input de busqueda por nombre de cliente -->
            <input type="text" id="filtroCliente" class="search-input" placeholder="🔍 Cliente por nombre" autocomplete="off">
            <!-- Input de busqueda por codigo QR de la bici -->
            <input type="text" id="filtroQr" class="search-input" placeholder="📱 Código QR de la bici" autocomplete="off">

            <!-- Periodo: pills de seleccion de rango temporal -->
            <div class="section-title">Período</div>
            <!-- Contenedor de pills de periodo -->
            <div id="pillsPeriodo" class="pills-periodo">
                <!-- Pill TODOS: sin filtro de periodo -->
                <button type="button" class="pill-periodo active" data-valor="">TODOS</button>
                <!-- Pill HOY -->
                <button type="button" class="pill-periodo" data-valor="HOY">HOY</button>
                <!-- Pill SEMANA -->
                <button type="button" class="pill-periodo" data-valor="SEMANA">SEMANA</button>
                <!-- Pill MES -->
                <button type="button" class="pill-periodo" data-valor="MES">MES</button>
            </div>

            <!-- Exportacion de reportes: botones PDF y Excel -->
            <div class="section-title">Exportar reporte</div>
            <!-- Boton que abre el modal de confirmacion PDF -->
            <button type="button" class="btn-export btn-export-pdf" id="btnExportarPDF" title="Reporte PDF con graficas y resumen ejecutivo">
                📄 PDF ejecutivo
            </button>
            <!-- Enlace de descarga directa de Excel (CSV) -->
            <a href="#" class="btn-export btn-export-xls" id="btnExportarExcel" target="_blank" title="Descargar Excel (CSV)">
                📊 Excel (CSV)
            </a>

            <!-- Inputs ocultos: mantienen el valor seleccionado de las pills.
                 Todo el JS existente lee estos valores, asi no hay que reescribirlo. -->
            <!-- Input oculto para el estado seleccionado -->
            <input type="hidden" id="filtroEstado" value="">
            <!-- Input oculto para el periodo seleccionado -->
            <input type="hidden" id="filtroPeriodo" value="">
        </form>
    </aside>

    <!-- ============================================================
         2. CONTENIDO CENTRAL: KPIs + Tablero de graficos
         Area principal con indicadores y graficos.
         ============================================================ -->
    <!-- Main content: KPIs + graficos -->
    <main class="main-content">

        <!-- Encabezado del dashboard -->
        <header class="dash-header">
            <!-- Boton hamburguesa: despliega el menu de filtros (solo movil/tablet) -->
            <button type="button" class="btn-menu" id="btnMenuSidebar" aria-expanded="false" aria-controls="sidebarFiltros">
                <span class="icono" id="iconoMenuSidebar">☰</span>
                <span id="textoMenuSidebar">Filtros</span>
            </button>
            <!-- Titulo principal -->
            <h1>Dashboard Ebike</h1>
            <!-- Subtitulo descriptivo -->
            <p>Datos en vivo desde la base de datos mediante procedimientos almacenados</p>
            <!-- Contenedor de chips de filtros activos -->
            <div id="chipsFiltros" class="mt-2"></div>
        </header>

        <!-- 6 Tarjetas KPI (indicadores rapidos) -->
        <section class="row g-3 mb-4" aria-label="Indicadores rapidos">
            <!-- KPI: Total Bicicletas -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card kpi-olive gold-border">
                    <div class="kpi-icon">🚲</div>
                    <div class="kpi-value" id="kpiBicicletas">0</div>
                    <p class="kpi-label">Total Bicicletas</p>
                </div>
            </div>
            <!-- KPI: Disponibles -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card kpi-mint">
                    <div class="kpi-icon">🟢</div>
                    <div class="kpi-value" id="kpiDisponibles">0</div>
                    <p class="kpi-label">Disponibles</p>
                </div>
            </div>
            <!-- KPI: Alquileres Activos -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card kpi-blue">
                    <div class="kpi-icon">⚡</div>
                    <div class="kpi-value" id="kpiActivos">0</div>
                    <p class="kpi-label">Alquileres Activos</p>
                </div>
            </div>
            <!-- KPI: Total Usuarios -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card kpi-gold">
                    <div class="kpi-icon">👥</div>
                    <div class="kpi-value" id="kpiUsuarios">0</div>
                    <p class="kpi-label">Total Usuarios</p>
                </div>
            </div>
            <!-- KPI: Total Alquileres -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card kpi-olive">
                    <div class="kpi-icon">📋</div>
                    <div class="kpi-value" id="kpiAlquileres">0</div>
                    <p class="kpi-label">Total Alquileres</p>
                </div>
            </div>
            <!-- KPI: Ingresos Totales (EUR) -->
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card kpi-gold kpi-gold-text">
                    <div class="kpi-icon">💰</div>
                    <div class="kpi-value" id="kpiIngresos">0.00</div>
                    <p class="kpi-label">Ingresos Totales (EUR)</p>
                </div>
            </div>
        </section>

        <!-- =======================================================
             BLOQUE A: Barras de ingresos + Histograma de duraciones
             ======================================================= -->
        <!-- Seccion Bloque A: ingresos por estacion + duraciones -->
        <section class="row g-4 mb-4" aria-label="Bloque A - ingresos y duraciones">
            <!-- Columna: Ingresos por Estacion -->
            <div class="col-lg-6">
                <div class="chart-card">
                    <h2 class="chart-title">📊 Ingresos por Estación</h2>
                    <p class="chart-subtitle">Barras en verde olivo; la estacion de mayor volumen se destaca en dorado</p>
                    <div class="chart-box"><canvas id="graficoBarras"></canvas></div>
                </div>
            </div>
            <!-- Columna: Duraciones de Alquileres -->
            <div class="col-lg-6">
                <div class="chart-card">
                    <h2 class="chart-title">📈 Duraciones de Alquileres</h2>
                    <p class="chart-subtitle">Distribucion por rangos de tiempo (degradado menta a azul)</p>
                    <div class="chart-box"><canvas id="graficoHistograma"></canvas></div>
                </div>
            </div>
        </section>

        <!-- =======================================================
             BLOQUE B: Poligono de evolucion + Dona por estado
             ======================================================= -->
        <!-- Seccion Bloque B: alquileres por periodo + por estado -->
        <section class="row g-4 mb-4" aria-label="Bloque B - evolucion y estados">
            <!-- Columna: Alquileres por Periodo -->
            <div class="col-lg-7">
                <div class="chart-card">
                    <h2 class="chart-title">📐 Alquileres por Período</h2>
                    <p class="chart-subtitle">Tendencia mensual con linea azul y area en verde menta</p>
                    <div class="chart-box"><canvas id="graficoPoligono"></canvas></div>
                </div>
            </div>
            <!-- Columna: Alquileres por Estado -->
            <div class="col-lg-5">
                <div class="chart-card">
                    <h2 class="chart-title">🥧 Alquileres por Estado</h2>
                    <p class="chart-subtitle">Dona centralizada con leyenda lateral</p>
                    <div class="chart-box"><canvas id="graficoSectores"></canvas></div>
                </div>
            </div>
        </section>

        <!-- =======================================================
             BLOQUE C: Radar de indicadores del sistema
             ======================================================= -->
        <!-- Seccion Bloque C: indicadores del sistema (radar) -->
        <section class="row g-4" aria-label="Bloque C - radar del sistema">
            <div class="col-12">
                <div class="chart-card">
                    <h2 class="chart-title">🕸️ Indicadores del Sistema</h2>
                    <p class="chart-subtitle">Salud general de la flota: bateria, disponibilidad, ocupacion, pagos y rotacion (SP sp_indicadores_sistema)</p>
                    <div class="chart-box-tall"><canvas id="graficoRadar"></canvas></div>
                </div>
            </div>
        </section>

    </main>
</div>

<!-- ============================================================
     MODAL DE CONFIRMACION PDF (resumen antes de exportar)
     Modal de Bootstrap que muestra resumen y filtros antes de generar PDF.
     ============================================================ -->
<!-- Modal de confirmacion de exportacion PDF -->
<div class="modal fade" id="modalConfirmarPDF" tabindex="-1" aria-labelledby="modalPDFLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Encabezado del modal -->
            <div class="modal-header" style="background: var(--bg-sidebar); color: #fff;">
                <h5 class="modal-title" id="modalPDFLabel">📄 Exportar Reporte PDF</h5>
                <!-- Boton de cierre -->
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <!-- Cuerpo del modal -->
            <div class="modal-body">
                <!-- Alerta informativa -->
                <div class="alert alert-info mb-3">
                    <strong>📋 Resumen del reporte a exportar:</strong>
                </div>
                <!-- Tabla responsive de resumen -->
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead>
                            <tr class="table-light">
                                <th style="width:40%">Gráfico</th>
                                <th class="text-center" style="width:20%">Registros</th>
                                <th class="text-center" style="width:20%">Estado</th>
                            </tr>
                        </thead>
                        <!-- Cuerpo de la tabla: se llena con JavaScript -->
                        <tbody id="tablaResumenPDF"><!-- Se llena con JavaScript --></tbody>
                    </table>
                </div>
                <!-- Alerta de filtros aplicados -->
                <div class="alert alert-warning mt-3">
                    <strong>📋 Filtros aplicados:</strong>
                    <div id="resumenFiltrosPDF" class="mt-2 small">--</div>
                </div>
            </div>
            <!-- Pie del modal con botones -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarPDF">Sí, Generar PDF</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap bundle (local): incluye Popper y componentes JS (modal, etc.) -->
<script src="/ebike_dashboard/public/assets/js/bootstrap.bundle.min.js"></script>
<!-- dashboard.js (local): logica de carga de datos y renderizado de graficos -->
<script src="/ebike_dashboard/public/assets/js/dashboard.js?v=3"></script>
<!-- Script inline: pills, menu responsivo, exportacion Excel/PDF -->
<script>
    // ============================================================
    // 1) PILLS DEL SIDEBAR -> sincronizan los inputs ocultos
    //    y disparan 'change' para que el dashboard filtre en vivo.
    // Funcion que activa el comportamiento de las pills (estado/periodo).
    // ============================================================
    // Funcion: activa las pills de un contenedor sincronizando un input oculto
    function activarPills(idContenedor, idInput) {
        // Obtiene el contenedor de pills por id
        const cont = document.getElementById(idContenedor);
        // Obtiene el input oculto asociado por id
        const input = document.getElementById(idInput);

        // Listener de click en el contenedor (delegacion de eventos)
        cont.addEventListener('click', function (e) {
            // Busca el boton con data-valor mas cercano al click
            const btn = e.target.closest('button[data-valor]');
            // Si no se hizo click en una pill, sale
            if (!btn) return;

            // Marcar la pill activa: quita 'active' de todas las pills
            cont.querySelectorAll('button').forEach(b => b.classList.remove('active'));
            // Anade 'active' a la pill clickeada
            btn.classList.add('active');

            // Sincronizar el input oculto con el valor de la pill
            input.value = btn.dataset.valor;
            // Dispara evento 'change' para que dashboard.js refiltre
            input.dispatchEvent(new Event('change'));
        });
    }
    // Activa las pills de Estado
    activarPills('pillsEstado', 'filtroEstado');
    // Activa las pills de Periodo
    activarPills('pillsPeriodo', 'filtroPeriodo');

    // ============================================================
    // 1b) MENU RESPONSIVO TIPO ACORDEON (pantallas pequenas)
    //     El boton hamburguesa despliega/pliega el sidebar de
    //     filtros con una animacion suave de altura.
    // ============================================================
    // Referencia al boton hamburguesa
    const btnMenu = document.getElementById('btnMenuSidebar');
    // Referencia al sidebar de filtros
    const sidebar = document.getElementById('sidebarFiltros');

    // Listener de click: alterna la clase 'abierto' y actualiza icono/texto/aria
    btnMenu.addEventListener('click', function () {
        // Alterna la clase 'abierto' y devuelve el nuevo estado
        const abierto = sidebar.classList.toggle('abierto');
        // Actualiza aria-expanded para accesibilidad
        btnMenu.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        // Cambia el icono segun el estado (✕ o ☰)
        document.getElementById('iconoMenuSidebar').textContent = abierto ? '✕' : '☰';
        // Cambia el texto del boton segun el estado
        document.getElementById('textoMenuSidebar').textContent = abierto ? 'Cerrar filtros' : 'Filtros';
    });

    // ============================================================
    // 2) URL DE EXPORTACION EXCEL con los filtros actuales
    // Construye la URL de descarga de Excel (CSV) con los filtros activos.
    // ============================================================
    // Funcion: actualiza el href del enlace de Excel con los filtros actuales
    function updateExportUrls() {
        // Crea un objeto URLSearchParams para construir la query string
        const params = new URLSearchParams();
        // Lee el estado seleccionado
        const estado  = document.getElementById('filtroEstado').value;
        // Lee el cliente ingresado
        const cliente = document.getElementById('filtroCliente').value;
        // Lee el QR ingresado
        const qr      = document.getElementById('filtroQr').value;
        // Lee el periodo seleccionado
        const periodo = document.getElementById('filtroPeriodo').value;

        // Anade 'estado' si tiene valor
        if (estado)  params.append('estado', estado);
        // Anade 'cliente' si tiene valor
        if (cliente) params.append('cliente', cliente);
        // Anade 'qr' si tiene valor
        if (qr)      params.append('qr', qr);
        // Anade 'periodo' si tiene valor
        if (periodo) params.append('periodo', periodo);

        // Convierte los params a string de query
        const qs = params.toString();
        // Actualiza el href del enlace de Excel con la accion y filtros
        document.getElementById('btnExportarExcel').href = '?accion=exportar-excel' + (qs ? '&' + qs : '');
    }

    // Actualiza la URL al escribir en el input de cliente
    document.getElementById('filtroCliente').addEventListener('input', updateExportUrls);
    // Actualiza la URL al escribir en el input de QR
    document.getElementById('filtroQr').addEventListener('input', updateExportUrls);
    // Actualiza la URL al cambiar el estado
    document.getElementById('filtroEstado').addEventListener('change', updateExportUrls);
    // Actualiza la URL al cambiar el periodo
    document.getElementById('filtroPeriodo').addEventListener('change', updateExportUrls);
    // Ejecuta una vez al cargar para inicializar la URL
    updateExportUrls();

    // ============================================================
    // 3) FLUJO DE EXPORTACION PDF: modal de confirmacion -> POST
    // Abre el modal de confirmacion y, al confirmar, genera el PDF.
    // ============================================================
    // Listener del boton PDF: evita default y muestra el modal
    document.getElementById('btnExportarPDF').addEventListener('click', function (e) {
        e.preventDefault();                 // Evita comportamiento por defecto
        mostrarModalConfirmarPDF();        // Muestra el modal de confirmacion
    });

    // Funcion: lee los filtros actuales y devuelve un objeto
    function leerFiltros() {
        return {
            estado:  document.getElementById('filtroEstado').value,   // Estado seleccionado
            cliente: document.getElementById('filtroCliente').value,   // Cliente ingresado
            qr:      document.getElementById('filtroQr').value,        // QR ingresado
            periodo: document.getElementById('filtroPeriodo').value     // Periodo seleccionado
        };
    }

    // Funcion: muestra el modal de confirmacion con resumen y filtros
    function mostrarModalConfirmarPDF() {
        // Desestructura los filtros actuales
        const { estado, cliente, qr, periodo } = leerFiltros();

        // Resumen textual de filtros
        const filtrosTexto = [];
        // Anade estado si existe
        if (estado)  filtrosTexto.push(`Estado: ${estado}`);
        // Anade cliente si existe
        if (cliente) filtrosTexto.push(`Cliente: ${cliente}`);
        // Anade QR si existe
        if (qr)      filtrosTexto.push(`QR: ${qr}`);
        // Anade periodo si existe
        if (periodo) filtrosTexto.push(`Periodo: ${periodo}`);
        // Muestra los filtros en el modal (o 'Todos' si no hay)
        document.getElementById('resumenFiltrosPDF').innerHTML =
            filtrosTexto.length ? filtrosTexto.join(' | ') : 'Todos';

        // Registros por grafico: cuenta data points de cada chart
        const conteos = {
            barras:     window.graficoBarras     ? window.graficoBarras.data.datasets[0].data.length : 0,     // Ingresos
            histograma: window.graficoHistograma ? window.graficoHistograma.data.datasets[0].data.length : 0, // Duraciones
            poligono:  window.graficoPoligono  ? window.graficoPoligono.data.datasets[0].data.length : 0,   // Por periodo
            sectores:  window.graficoSectores  ? window.graficoSectores.data.datasets[0].data.length : 0,   // Por estado
            radar:     window.graficoRadar     ? window.graficoRadar.data.datasets[0].data.length : 0       // Indicadores
        };

        // Renderiza la tabla de resumen con los conteos
        document.getElementById('tablaResumenPDF').innerHTML = `
            <tr><td><strong>Ingresos por Estación</strong></td><td class="text-center">${conteos.barras}</td><td class="text-center">${conteos.barras > 0 ? '✅' : '⚪'}</td></tr>
            <tr><td><strong>Duraciones de Alquileres</strong></td><td class="text-center">${conteos.histograma}</td><td class="text-center">${conteos.histograma > 0 ? '✅' : '⚪'}</td></tr>
            <tr><td><strong>Alquileres por Mes</strong></td><td class="text-center">${conteos.poligono}</td><td class="text-center">${conteos.poligono > 0 ? '✅' : '⚪'}</td></tr>
            <tr><td><strong>Alquileres por Estado</strong></td><td class="text-center">${conteos.sectores}</td><td class="text-center">${conteos.sectores > 0 ? '✅' : '⚪'}</td></tr>
            <tr><td><strong>Indicadores del Sistema</strong></td><td class="text-center">${conteos.radar}</td><td class="text-center">${conteos.radar > 0 ? '✅' : '⚪'}</td></tr>
            <tr class="table-info"><td><strong>TOTAL REGISTROS</strong></td><td class="text-center fw-bold">${Object.values(conteos).reduce((a, b) => a + b, 0)}</td><td></td></tr>
        `;

        // Crea la instancia del modal de Bootstrap
        const modal = new bootstrap.Modal(document.getElementById('modalConfirmarPDF'));
        // Muestra el modal
        modal.show();
        // Al confirmar, oculta el modal y genera el PDF
        document.getElementById('btnConfirmarPDF').onclick = function () {
            modal.hide();      // Oculta el modal
            generarPDF();      // Ejecuta la generacion del PDF
        };
    }

    // Repinta el chart sincronamente antes de capturarlo: garantiza el
    // frame final aunque haya animacion en curso o la ventana este oculta.
    // Funcion: captura una imagen (base64) de un chart Chart.js
    function capturarImagenChart(chart) {
        try {
            chart.options.animation = false; // Desactiva animacion para captura inmediata
            chart.update('none');            // Actualiza sin animacion
            chart.draw();                    // Redibuja sincronamente
        } catch (e) { /* se captura el estado actual */ } // Ignora errores de captura
        // Devuelve la imagen en base64
        return chart.toBase64Image();
    }

    // Funcion: genera y descarga el PDF ejecutivo con las graficas filtradas
    function generarPDF() {
        // Referencia al boton de exportar PDF
        const btn = document.getElementById('btnExportarPDF');
        // Guarda el texto original del boton
        const originalText = btn.innerHTML;
        // Cambia el texto a "Generando..."
        btn.innerHTML = '⏳ Generando...';
        // Deshabilita el boton mientras se genera
        btn.disabled = true;

        // Lee los filtros actuales
        const { estado, cliente, qr, periodo } = leerFiltros();

        // Capturar las 5 graficas tal y como se ven ahora (ya filtradas)
        const imagenes = {};
        // Captura grafico de barras si existe
        if (window.graficoBarras)     imagenes.barras     = capturarImagenChart(window.graficoBarras);
        // Captura histograma si existe
        if (window.graficoHistograma) imagenes.histograma = capturarImagenChart(window.graficoHistograma);
        // Captura poligono si existe
        if (window.graficoPoligono)   imagenes.poligono   = capturarImagenChart(window.graficoPoligono);
        // Captura sectores (dona) si existe
        if (window.graficoSectores)   imagenes.sectores   = capturarImagenChart(window.graficoSectores);
        // Captura radar si existe
        if (window.graficoRadar)      imagenes.radar      = capturarImagenChart(window.graficoRadar);

        // El servidor recalcula las estadisticas con estos mismos filtros
        // POST con imagenes (base64) y filtros al endpoint de exportacion PDF
        fetch('?accion=exportar-pdf', {
            method: 'POST',                          // Metodo POST
            headers: { 'Content-Type': 'application/json' }, // Content-Type JSON
            body: JSON.stringify({ imagenes, estado, cliente, qr, periodo }) // Cuerpo JSON
        })
        .then(response => {
            // Si la respuesta es OK, convierte a blob
            if (response.ok) return response.blob();
            // Si no, lanza error
            throw new Error('Error generando PDF');
        })
        .then(blob => {
            // Crea una URL de objeto para el blob
            const url = window.URL.createObjectURL(blob);
            // Crea un enlace temporal para descarga
            const a = document.createElement('a');
            a.href = url;                            // Asigna la URL
            // Nombre del archivo con fecha/hora
            a.download = 'reporte_ejecutivo_' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '_') + '.pdf';
            // Anade el enlace al DOM
            document.body.appendChild(a);
            // Dispara el click para descargar
            a.click();
            // Elimina el enlace del DOM
            document.body.removeChild(a);
            // Libera la URL del objeto
            window.URL.revokeObjectURL(url);
        })
        .catch(err => {
            // Registra el error en consola
            console.error('Error:', err);
            // Muestra alerta al usuario
            alert('Error generando el PDF');
        })
        .finally(() => {
            // Restaura el texto original del boton
            btn.innerHTML = originalText;
            // Rehabilita el boton
            btn.disabled = false;
        });
    }
</script>
<!-- Fin del script inline -->
</body>
<!-- Fin del body -->
</html>
<!-- Fin del documento HTML -->
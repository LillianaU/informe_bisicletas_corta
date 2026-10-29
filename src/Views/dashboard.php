<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ebike Manager - Dashboard</title>

    <!-- Bootstrap 5 (local) -->
    <link href="/ebike_dashboard/public/assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js (local) -->
    <script src="/ebike_dashboard/public/assets/js/chart.umd.min.js"></script>

    <!-- SweetAlert2 (local) -->
    <script src="/ebike_dashboard/public/assets/js/sweetalert2.min.js"></script>

    <!-- ============================================================
         GUIA DE ESTILOS - Paleta "Limpia, Organica y Tecnologica"
         ============================================================ -->
    <style>
        :root {
            /* Colores */
            --bg-dashboard: #FFFFFF;
            --bg-sidebar:   #3B4A26;   /* Verde Olivo Profundo */
            --accent-mint:  #53D397;   /* Verde Menta */
            --accent-gold:  #D4AF37;   /* Dorado */
            --accent-blue:  #2A75D3;   /* Azul Electrico */
            --accent-red:   #E63946;   /* Rojo Alerta */
            --olive:        #556B2F;   /* Verde Olivo */
            --text-dark:    #1F2937;
            --text-soft:    #6B7280;
            --card-bg:      #F8FAF9;

            /* Layout y sombras */
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            --border-radius: 12px;
        }

        body {
            background: var(--bg-dashboard);
            color: var(--text-dark);
            font-family: "Segoe UI", system-ui, sans-serif;
        }

        /* ========================================================
           LAYOUT GENERAL: Sidebar + Contenido central
           ======================================================== */
        .app-layout { display: flex; min-height: 100vh; }

        /* ========================================================
           1. SIDEBAR IZQUIERDO - Barra de filtros (Verde Olivo)
           ======================================================== */
        .sidebar {
            width: 290px;
            flex-shrink: 0;
            background: linear-gradient(180deg, var(--bg-sidebar) 0%, #2E3A1E 100%);
            color: #E9F0E2;
            padding: 1.75rem 1.25rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        /* Marca: Ebike Manager */
        .brand {
            font-size: 1.45rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #FFFFFF;
        }
        .brand .brand-accent { color: var(--accent-mint); }
        .brand .brand-gold   { color: var(--accent-gold); }
        .brand-sub {
            font-size: 0.75rem;
            color: #B9C7A9;
            margin-bottom: 1.75rem;
        }

        /* Titulos de seccion del sidebar */
        .section-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--accent-mint);
            margin: 1.6rem 0 0.6rem;
        }

        /* Pills de Estado con punto de color por estado */
        .pill-estado {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            width: 100%;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #E9F0E2;
            border-radius: 999px;
            padding: 0.42rem 0.85rem;
            font-size: 0.83rem;
            text-align: left;
            transition: all 0.15s ease;
        }
        .pill-estado:hover { background: rgba(255, 255, 255, 0.14); }
        .pill-estado.active {
            background: var(--accent-mint);
            border-color: var(--accent-mint);
            color: #14361F;
            font-weight: 700;
        }
        .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .dot-reservado  { background: var(--accent-blue); }
        .dot-activo     { background: var(--accent-mint); }
        .dot-finalizado { background: var(--olive); }
        .dot-cancelado  { background: var(--accent-red); }
        .dot-pendiente  { background: var(--accent-gold); }
        .dot-todos      { background: #CFD8C4; }

        /* Busqueda dinamica: Cliente y QR */
        .search-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: var(--border-radius);
            color: #FFFFFF;
            padding: 0.5rem 0.8rem;
            font-size: 0.85rem;
            margin-bottom: 0.6rem;
        }
        .search-input::placeholder { color: #A7B698; }
        .search-input:focus {
            background: rgba(255, 255, 255, 0.14);
            border-color: var(--accent-mint);
            color: #fff;
            box-shadow: none;
            outline: none;
        }

        /* Pills de Periodo (Azul y Blanco) */
        .pills-periodo { display: flex; gap: 0.4rem; }
        .pill-periodo {
            flex: 1;
            background: transparent;
            border: 1.5px solid var(--accent-blue);
            color: #CFE0FA;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.32rem 0.2rem;
            transition: all 0.15s ease;
        }
        .pill-periodo:hover { background: rgba(42, 117, 211, 0.25); }
        .pill-periodo.active {
            background: var(--accent-blue);
            color: #FFFFFF;
        }

        /* Botones de exportacion */
        .btn-export {
            width: 100%;
            border-radius: var(--border-radius);
            font-weight: 700;
            font-size: 0.9rem;
            padding: 0.6rem;
            border: none;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }
        .btn-export:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.25); }
        .btn-export-pdf { background: var(--accent-gold); color: #2B2103; }
        .btn-export-xls { background: var(--accent-mint); color: #14361F; margin-top: 0.5rem; display: block; text-align: center; text-decoration: none; }

        /* ========================================================
           2. ENCABEZADO SUPERIOR + KPIs (Tarjetas elevated)
           ======================================================== */
        .main-content { flex: 1; padding: 1.5rem 2rem 3rem; min-width: 0; }

        .dash-header { margin-bottom: 1.5rem; }
        .dash-header h1 {
            font-weight: 800;
            font-size: 1.7rem;
            color: var(--olive);
            margin-bottom: 0.15rem;
        }
        .dash-header p { color: var(--text-soft); font-size: 0.88rem; margin: 0; }

        /* Tarjeta KPI generica */
        .kpi-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid #EDF2EE;
            padding: 1rem 0.9rem;
            text-align: center;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .kpi-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
        }
        .kpi-icon { font-size: 1.35rem; }
        .kpi-value { font-size: 1.5rem; font-weight: 800; line-height: 1.1; margin-top: 0.15rem; }
        .kpi-label { font-size: 0.72rem; color: var(--text-soft); text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }

        /* Acentos por indicador (spec) */
        .kpi-olive  { color: var(--olive); }
        .kpi-olive::before  { background: var(--olive); }
        .kpi-olive.gold-border { border-color: var(--accent-gold); box-shadow: inset 0 0 0 1px var(--accent-gold); }
        .kpi-mint   { color: #1E7A52; }
        .kpi-mint::before   { background: var(--accent-mint); }
        .kpi-blue   { color: var(--accent-blue); }
        .kpi-blue::before   { background: var(--accent-blue); }
        .kpi-gold   { color: #A8842A; }
        .kpi-gold::before   { background: var(--accent-gold); }
        .kpi-gold-text .kpi-value { color: var(--accent-gold); }

        /* Boton hamburguesa: solo visible en pantallas pequenas */
        .btn-menu {
            display: none;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-sidebar);
            color: #FFFFFF;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            font-weight: 700;
            padding: 0.55rem 1.1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            box-shadow: var(--card-shadow);
        }
        .btn-menu .icono { font-size: 1.15rem; line-height: 1; }

        /* ========================================================
           3. ZONA PRINCIPAL - Bento Grid de graficos
           ======================================================== */
        .chart-card {
            background: #FFFFFF;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid #EDF2EE;
            padding: 1.15rem 1.25rem 1rem;
            height: 100%;
        }
        .chart-card .chart-title {
            font-weight: 700;
            font-size: 0.98rem;
            color: var(--text-dark);
            margin: 0;
        }
        .chart-card .chart-subtitle {
            font-size: 0.78rem;
            color: var(--text-soft);
            margin: 0.1rem 0 0.25rem;
        }
        .chart-box { position: relative; height: 300px; }
        .chart-box-tall { position: relative; height: 340px; }

        /* Chips de filtros activos */
        #chipsFiltros .badge { font-weight: 600; border-radius: 999px; padding: 0.45em 0.9em; }

        /* Responsive: en pantallas pequenas el sidebar se convierte en
           un menu ACORDEON que se despliega con el boton hamburguesa */
        @media (max-width: 991px) {
            .app-layout { flex-direction: column; }

            .btn-menu { display: inline-flex; }

            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                max-height: 0;
                overflow: hidden;
                padding-top: 0;
                padding-bottom: 0;
                transition: max-height 0.4s ease, padding 0.4s ease;
            }
            .sidebar.abierto {
                max-height: 1200px;   /* espacio suficiente para todo el contenido */
                padding-top: 1.75rem;
                padding-bottom: 1.5rem;
            }

            .main-content { padding: 1.25rem; }
        }
    </style>
</head>
<body>

<div class="app-layout">

    <!-- ============================================================
         1. SIDEBAR IZQUIERDO - Navegacion y filtros globales
         En resoluciones pequenas se despliega como menu acordeon.
         ============================================================ -->
    <aside class="sidebar" id="sidebarFiltros">
        <div class="brand">🚲 Ebike<span class="brand-accent">Manager</span></div>
        <p class="brand-sub">Panel de control de flota electrica</p>

        <form id="formFiltros">
            <!-- Estado del alquiler -->
            <div class="section-title">Estado</div>
            <div id="pillsEstado" class="d-grid gap-2">
                <button type="button" class="pill-estado active" data-valor="">
                    <span class="dot dot-todos"></span>TODOS
                </button>
                <button type="button" class="pill-estado" data-valor="RESERVADO">
                    <span class="dot dot-reservado"></span>RESERVADO
                </button>
                <button type="button" class="pill-estado" data-valor="ACTIVO">
                    <span class="dot dot-activo"></span>ACTIVO
                </button>
                <button type="button" class="pill-estado" data-valor="FINALIZADO">
                    <span class="dot dot-finalizado"></span>FINALIZADO
                </button>
                <button type="button" class="pill-estado" data-valor="CANCELADO">
                    <span class="dot dot-cancelado"></span>CANCELADO
                </button>
                <button type="button" class="pill-estado" data-valor="PENDIENTE_PAGO">
                    <span class="dot dot-pendiente"></span>PENDIENTE_PAGO
                </button>
            </div>

            <!-- Busqueda dinamica -->
            <div class="section-title">Búsqueda dinámica</div>
            <input type="text" id="filtroCliente" class="search-input" placeholder="🔍 Cliente por nombre" autocomplete="off">
            <input type="text" id="filtroQr" class="search-input" placeholder="📱 Código QR de la bici" autocomplete="off">

            <!-- Periodo -->
            <div class="section-title">Período</div>
            <div id="pillsPeriodo" class="pills-periodo">
                <button type="button" class="pill-periodo active" data-valor="">TODOS</button>
                <button type="button" class="pill-periodo" data-valor="HOY">HOY</button>
                <button type="button" class="pill-periodo" data-valor="SEMANA">SEMANA</button>
                <button type="button" class="pill-periodo" data-valor="MES">MES</button>
            </div>

            <!-- Exportacion de reportes -->
            <div class="section-title">Exportar reporte</div>
            <button type="button" class="btn-export btn-export-pdf" id="btnExportarPDF" title="Reporte PDF con graficas y resumen ejecutivo">
                📄 PDF ejecutivo
            </button>
            <a href="#" class="btn-export btn-export-xls" id="btnExportarExcel" target="_blank" title="Descargar Excel (CSV)">
                📊 Excel (CSV)
            </a>

            <!-- Inputs ocultos: mantienen el valor seleccionado de las pills.
                 Todo el JS existente lee estos valores, asi no hay que reescribirlo. -->
            <input type="hidden" id="filtroEstado" value="">
            <input type="hidden" id="filtroPeriodo" value="">
        </form>
    </aside>

    <!-- ============================================================
         2. CONTENIDO CENTRAL: KPIs + Tablero de graficos
         ============================================================ -->
    <main class="main-content">

        <header class="dash-header">
            <!-- Boton hamburguesa: despliega el menu de filtros (solo movil/tablet) -->
            <button type="button" class="btn-menu" id="btnMenuSidebar" aria-expanded="false" aria-controls="sidebarFiltros">
                <span class="icono" id="iconoMenuSidebar">☰</span>
                <span id="textoMenuSidebar">Filtros</span>
            </button>
            <h1>Dashboard Ebike</h1>
            <p>Datos en vivo desde la base de datos mediante procedimientos almacenados</p>
            <div id="chipsFiltros" class="mt-2"></div>
        </header>

        <!-- 6 Tarjetas KPI (indicadores rapidos) -->
        <section class="row g-3 mb-4" aria-label="Indicadores rapidos">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card kpi-olive gold-border">
                    <div class="kpi-icon">🚲</div>
                    <div class="kpi-value" id="kpiBicicletas">0</div>
                    <p class="kpi-label">Total Bicicletas</p>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card kpi-mint">
                    <div class="kpi-icon">🟢</div>
                    <div class="kpi-value" id="kpiDisponibles">0</div>
                    <p class="kpi-label">Disponibles</p>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card kpi-blue">
                    <div class="kpi-icon">⚡</div>
                    <div class="kpi-value" id="kpiActivos">0</div>
                    <p class="kpi-label">Alquileres Activos</p>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card kpi-gold">
                    <div class="kpi-icon">👥</div>
                    <div class="kpi-value" id="kpiUsuarios">0</div>
                    <p class="kpi-label">Total Usuarios</p>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="kpi-card kpi-olive">
                    <div class="kpi-icon">📋</div>
                    <div class="kpi-value" id="kpiAlquileres">0</div>
                    <p class="kpi-label">Total Alquileres</p>
                </div>
            </div>
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
        <section class="row g-4 mb-4" aria-label="Bloque A - ingresos y duraciones">
            <div class="col-lg-6">
                <div class="chart-card">
                    <h2 class="chart-title">📊 Ingresos por Estación</h2>
                    <p class="chart-subtitle">Barras en verde olivo; la estacion de mayor volumen se destaca en dorado</p>
                    <div class="chart-box"><canvas id="graficoBarras"></canvas></div>
                </div>
            </div>
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
        <section class="row g-4 mb-4" aria-label="Bloque B - evolucion y estados">
            <div class="col-lg-7">
                <div class="chart-card">
                    <h2 class="chart-title">📐 Alquileres por Período</h2>
                    <p class="chart-subtitle">Tendencia mensual con linea azul y area en verde menta</p>
                    <div class="chart-box"><canvas id="graficoPoligono"></canvas></div>
                </div>
            </div>
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
     ============================================================ -->
<div class="modal fade" id="modalConfirmarPDF" tabindex="-1" aria-labelledby="modalPDFLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--bg-sidebar); color: #fff;">
                <h5 class="modal-title" id="modalPDFLabel">📄 Exportar Reporte PDF</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <strong>📋 Resumen del reporte a exportar:</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead>
                            <tr class="table-light">
                                <th style="width:40%">Gráfico</th>
                                <th class="text-center" style="width:20%">Registros</th>
                                <th class="text-center" style="width:20%">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tablaResumenPDF"><!-- Se llena con JavaScript --></tbody>
                    </table>
                </div>
                <div class="alert alert-warning mt-3">
                    <strong>📋 Filtros aplicados:</strong>
                    <div id="resumenFiltrosPDF" class="mt-2 small">--</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarPDF">Sí, Generar PDF</button>
            </div>
        </div>
    </div>
</div>

<script src="/ebike_dashboard/public/assets/js/bootstrap.bundle.min.js"></script>
<script src="/ebike_dashboard/public/assets/js/dashboard.js?v=3"></script>
<script>
    // ============================================================
    // 1) PILLS DEL SIDEBAR -> sincronizan los inputs ocultos
    //    y disparan 'change' para que el dashboard filtre en vivo.
    // ============================================================
    function activarPills(idContenedor, idInput) {
        const cont = document.getElementById(idContenedor);
        const input = document.getElementById(idInput);

        cont.addEventListener('click', function (e) {
            const btn = e.target.closest('button[data-valor]');
            if (!btn) return;

            // Marcar la pill activa
            cont.querySelectorAll('button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Sincronizar el input oculto y notificar a los listeners
            input.value = btn.dataset.valor;
            input.dispatchEvent(new Event('change'));
        });
    }
    activarPills('pillsEstado', 'filtroEstado');
    activarPills('pillsPeriodo', 'filtroPeriodo');

    // ============================================================
    // 1b) MENU RESPONSIVO TIPO ACORDEON (pantallas pequenas)
    //     El boton hamburguesa despliega/pliega el sidebar de
    //     filtros con una animacion suave de altura.
    // ============================================================
    const btnMenu = document.getElementById('btnMenuSidebar');
    const sidebar = document.getElementById('sidebarFiltros');

    btnMenu.addEventListener('click', function () {
        const abierto = sidebar.classList.toggle('abierto');
        btnMenu.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        document.getElementById('iconoMenuSidebar').textContent = abierto ? '✕' : '☰';
        document.getElementById('textoMenuSidebar').textContent = abierto ? 'Cerrar filtros' : 'Filtros';
    });

    // ============================================================
    // 2) URL DE EXPORTACION EXCEL con los filtros actuales
    // ============================================================
    function updateExportUrls() {
        const params = new URLSearchParams();
        const estado  = document.getElementById('filtroEstado').value;
        const cliente = document.getElementById('filtroCliente').value;
        const qr      = document.getElementById('filtroQr').value;
        const periodo = document.getElementById('filtroPeriodo').value;

        if (estado)  params.append('estado', estado);
        if (cliente) params.append('cliente', cliente);
        if (qr)      params.append('qr', qr);
        if (periodo) params.append('periodo', periodo);

        const qs = params.toString();
        document.getElementById('btnExportarExcel').href = '?accion=exportar-excel' + (qs ? '&' + qs : '');
    }

    document.getElementById('filtroCliente').addEventListener('input', updateExportUrls);
    document.getElementById('filtroQr').addEventListener('input', updateExportUrls);
    document.getElementById('filtroEstado').addEventListener('change', updateExportUrls);
    document.getElementById('filtroPeriodo').addEventListener('change', updateExportUrls);
    updateExportUrls();

    // ============================================================
    // 3) FLUJO DE EXPORTACION PDF: modal de confirmacion -> POST
    // ============================================================
    document.getElementById('btnExportarPDF').addEventListener('click', function (e) {
        e.preventDefault();
        mostrarModalConfirmarPDF();
    });

    function leerFiltros() {
        return {
            estado:  document.getElementById('filtroEstado').value,
            cliente: document.getElementById('filtroCliente').value,
            qr:      document.getElementById('filtroQr').value,
            periodo: document.getElementById('filtroPeriodo').value
        };
    }

    function mostrarModalConfirmarPDF() {
        const { estado, cliente, qr, periodo } = leerFiltros();

        // Resumen textual de filtros
        const filtrosTexto = [];
        if (estado)  filtrosTexto.push(`Estado: ${estado}`);
        if (cliente) filtrosTexto.push(`Cliente: ${cliente}`);
        if (qr)      filtrosTexto.push(`QR: ${qr}`);
        if (periodo) filtrosTexto.push(`Periodo: ${periodo}`);
        document.getElementById('resumenFiltrosPDF').innerHTML =
            filtrosTexto.length ? filtrosTexto.join(' | ') : 'Todos';

        // Registros por grafico
        const conteos = {
            barras:     window.graficoBarras     ? window.graficoBarras.data.datasets[0].data.length : 0,
            histograma: window.graficoHistograma ? window.graficoHistograma.data.datasets[0].data.length : 0,
            poligono:  window.graficoPoligono  ? window.graficoPoligono.data.datasets[0].data.length : 0,
            sectores:  window.graficoSectores  ? window.graficoSectores.data.datasets[0].data.length : 0,
            radar:     window.graficoRadar     ? window.graficoRadar.data.datasets[0].data.length : 0
        };

        document.getElementById('tablaResumenPDF').innerHTML = `
            <tr><td><strong>Ingresos por Estación</strong></td><td class="text-center">${conteos.barras}</td><td class="text-center">${conteos.barras > 0 ? '✅' : '⚪'}</td></tr>
            <tr><td><strong>Duraciones de Alquileres</strong></td><td class="text-center">${conteos.histograma}</td><td class="text-center">${conteos.histograma > 0 ? '✅' : '⚪'}</td></tr>
            <tr><td><strong>Alquileres por Mes</strong></td><td class="text-center">${conteos.poligono}</td><td class="text-center">${conteos.poligono > 0 ? '✅' : '⚪'}</td></tr>
            <tr><td><strong>Alquileres por Estado</strong></td><td class="text-center">${conteos.sectores}</td><td class="text-center">${conteos.sectores > 0 ? '✅' : '⚪'}</td></tr>
            <tr><td><strong>Indicadores del Sistema</strong></td><td class="text-center">${conteos.radar}</td><td class="text-center">${conteos.radar > 0 ? '✅' : '⚪'}</td></tr>
            <tr class="table-info"><td><strong>TOTAL REGISTROS</strong></td><td class="text-center fw-bold">${Object.values(conteos).reduce((a, b) => a + b, 0)}</td><td></td></tr>
        `;

        const modal = new bootstrap.Modal(document.getElementById('modalConfirmarPDF'));
        modal.show();
        document.getElementById('btnConfirmarPDF').onclick = function () {
            modal.hide();
            generarPDF();
        };
    }

    // Repinta el chart sincronamente antes de capturarlo: garantiza el
    // frame final aunque haya animacion en curso o la ventana este oculta.
    function capturarImagenChart(chart) {
        try {
            chart.options.animation = false;
            chart.update('none');
            chart.draw();
        } catch (e) { /* se captura el estado actual */ }
        return chart.toBase64Image();
    }

    function generarPDF() {
        const btn = document.getElementById('btnExportarPDF');
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ Generando...';
        btn.disabled = true;

        const { estado, cliente, qr, periodo } = leerFiltros();

        // Capturar las 5 graficas tal y como se ven ahora (ya filtradas)
        const imagenes = {};
        if (window.graficoBarras)     imagenes.barras     = capturarImagenChart(window.graficoBarras);
        if (window.graficoHistograma) imagenes.histograma = capturarImagenChart(window.graficoHistograma);
        if (window.graficoPoligono)   imagenes.poligono   = capturarImagenChart(window.graficoPoligono);
        if (window.graficoSectores)   imagenes.sectores   = capturarImagenChart(window.graficoSectores);
        if (window.graficoRadar)      imagenes.radar      = capturarImagenChart(window.graficoRadar);

        // El servidor recalcula las estadisticas con estos mismos filtros
        fetch('?accion=exportar-pdf', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ imagenes, estado, cliente, qr, periodo })
        })
        .then(response => {
            if (response.ok) return response.blob();
            throw new Error('Error generando PDF');
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'reporte_ejecutivo_' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '_') + '.pdf';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error generando el PDF');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
</script>
</body>
</html>

// ============================================================
// DASHBOARD EBIKE MANAGER - Logica de filtros, KPIs y graficos
// ============================================================
// Paleta:      Olivo #556B2F | Menta #53D397 | Dorado #D4AF37
//              Azul #2A75D3  | Rojo #E63946
// Datos:       API PHP -> procedimientos almacenados MySQL
// ============================================================

let graficoBarras = null;
let graficoHistograma = null;
let graficoPoligono = null;
let graficoSectores = null;
let graficoRadar = null;

// Referencias publicas para la captura de imagenes del PDF
window.graficoBarras = null;
window.graficoHistograma = null;
window.graficoPoligono = null;
window.graficoSectores = null;
window.graficoRadar = null;

let apiBase = '?accion=api&tipo=';

// ---------- PALETA DE COLORES (guia de estilos) ----------
const PALETA = {
    olivo:   '#556B2F',
    menta:   '#53D397',
    dorado:  '#D4AF37',
    azul:    '#2A75D3',
    rojo:    '#E63946',
    gris:    '#9CA3AF',
    texto:   '#1F2937',
    suave:   '#6B7280',
    rejilla: '#E5EBE4',
};

// Color asociado a cada estado de alquiler (para la dona)
const COLOR_ESTADO = {
    ACTIVO:         PALETA.menta,
    RESERVADO:      PALETA.azul,
    FINALIZADO:     PALETA.olivo,
    PENDIENTE_PAGO: PALETA.dorado,
    CANCELADO:      PALETA.rojo,
};
const colorEstado = (estado) => COLOR_ESTADO[estado] || PALETA.gris;

// Interpola un color entre menta y azul segun la posicion (i/n)
function degradadoMentaAzul(i, n) {
    const desde = [83, 211, 151];   // #53D397
    const hasta = [42, 117, 211];   // #2A75D3
    const t = n <= 1 ? 0 : i / (n - 1);
    const c = desde.map((v, k) => Math.round(v + (hasta[k] - v) * t));
    return 'rgba(' + c.join(',') + ', 0.85)';
}

// ---------- ETIQUETAS NUMERICAS SOBRE LOS GRAFICOS ----------
// Plugin de Chart.js que dibuja el valor de cada dato. Al ser parte del
// canvas, las etiquetas quedan incluidas en las imagenes capturadas
// para el PDF (toBase64Image) y visibles en el dashboard.
function crearPluginEtiquetas(opciones = {}) {
    const {
        formato    = (v) => String(v),   // como se formatea cada valor
        offset     = 7,                   // distancia respecto a barra/punto
        color      = PALETA.texto,
        omitirCero = false,               // no dibujar etiqueta para valor 0
        fuente     = 'bold 11px "Segoe UI", sans-serif',
    } = opciones;

    return {
        id: 'etiquetasDatos',
        afterDatasetsDraw(chart) {
            const meta = chart.getDatasetMeta(0);
            if (!meta || !meta.data.length) return;

            const { ctx } = chart;
            ctx.save();
            ctx.font = fuente;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = color;

            meta.data.forEach((el, i) => {
                const v = chart.data.datasets[0].data[i];
                if (v === null || v === undefined) return;
                if (omitirCero && !v) return;

                const tipo = chart.config.type;
                let x = el.x, y = el.y;

                if (tipo === 'bar') {
                    y = el.y - offset;                       // sobre la barra
                } else if (tipo === 'line') {
                    y = el.y - offset - 2;                   // sobre el punto
                } else if (tipo === 'doughnut' || tipo === 'pie') {
                    const ang = (el.startAngle + el.endAngle) / 2;   // fuera del segmento
                    const radio = el.outerRadius + 15;
                    x = el.x + Math.cos(ang) * radio;
                    y = el.y + Math.sin(ang) * radio;
                } else if (tipo === 'radar') {
                    y = el.y - 13;                           // sobre el vertice
                }
                ctx.fillText(formato(v, i, chart), x, y);
            });
            ctx.restore();
        }
    };
}

// Estilo global de Chart.js acorde a la paleta
Chart.defaults.font.family = '"Segoe UI", system-ui, sans-serif';
Chart.defaults.color = PALETA.suave;

// ---------- CICLO DE VIDA ----------

document.addEventListener('DOMContentLoaded', function () {
    actualizarChips();          // estado inicial de los chips
    cargarTodos();

    // Envio manual del formulario (boton "Aplicar filtros" / Enter)
    document.getElementById('formFiltros').addEventListener('submit', function (e) {
        e.preventDefault();
        aplicarFiltros();
    });

    // Filtro en tiempo real al cambiar estado o periodo (pills del sidebar)
    document.getElementById('filtroEstado').addEventListener('change', aplicarFiltros);
    document.getElementById('filtroPeriodo').addEventListener('change', aplicarFiltros);

    // Busqueda dinamica con pequeño retardo mientras se escribe
    const refiltrarConRetardo = debounce(aplicarFiltros, 450);
    document.getElementById('filtroCliente').addEventListener('input', refiltrarConRetardo);
    document.getElementById('filtroQr').addEventListener('input', refiltrarConRetardo);
});

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// ---------- FILTROS ----------

function obtenerParams() {
    const p = new URLSearchParams();
    const estado  = document.getElementById('filtroEstado').value;
    const cliente = document.getElementById('filtroCliente').value;
    const qr      = document.getElementById('filtroQr').value;
    const periodo = document.getElementById('filtroPeriodo').value;
    if (estado)  p.set('estado', estado);
    if (cliente) p.set('cliente', cliente);
    if (qr)      p.set('qr', qr);
    if (periodo) p.set('periodo', periodo);
    return p.toString();
}

function aplicarFiltros() {
    // Los chips son decorativos: si fallan, los graficos se recargan igual
    try { actualizarChips(); } catch (e) { console.warn('actualizarChips:', e); }
    cargarTodos();
}

// Muestra los filtros activos como chips bajo el encabezado
function actualizarChips() {
    const cont = document.getElementById('chipsFiltros');
    if (!cont) return;

    const defs = [
        ['Estado',   (document.getElementById('filtroEstado')  || {}).value],
        ['Cliente',  (document.getElementById('filtroCliente') || {}).value],
        ['QR',       (document.getElementById('filtroQr')      || {}).value],
        ['Periodo',  (document.getElementById('filtroPeriodo') || {}).value],
    ];
    const activos = defs.filter((par) => par[1] && par[1] !== 'TODOS');

    cont.innerHTML = '';
    if (!activos.length) {
        const b = document.createElement('span');
        b.className = 'badge bg-secondary';
        b.textContent = 'Sin filtros - mostrando todos los datos';
        cont.appendChild(b);
        return;
    }
    activos.forEach(([k, v]) => {
        const b = document.createElement('span');
        b.className = 'badge me-1';
        b.style.background = PALETA.olivo;
        b.style.color = '#fff';
        b.textContent = k + ': ' + v;   // textContent: seguro frente a XSS
        cont.appendChild(b);
    });
}

// ---------- CARGA DE DATOS (API -> SP MySQL) ----------

async function cargarTodos() {
    try {
        const params = obtenerParams();
        const [barras, histograma, poligono, sectores, radar, stats] = await Promise.all([
            fetch(`${apiBase}barras&${params}`).then(r => r.json()),
            fetch(`${apiBase}histograma&${params}`).then(r => r.json()),
            fetch(`${apiBase}poligono&${params}`).then(r => r.json()),
            fetch(`${apiBase}sectores&${params}`).then(r => r.json()),
            fetch(`${apiBase}radar`).then(r => r.json()),                       // indicadores globales (SP)
            fetch(`${apiBase}estadisticas&${params}`).then(r => r.json()),      // KPIs filtrados
        ]);

        renderGraficoBarras(barras);
        renderGraficoHistograma(histograma);
        renderGraficoPoligono(poligono);
        renderGraficoSectores(sectores);
        renderGraficoRadar(radar);

        if (stats && stats.success && stats.data) {
            actualizarKPIs(stats.data);
        }
    } catch (e) {
        console.error('Error cargando datos:', e);
        Swal.fire({ icon: 'error', title: 'Error cargando datos', text: e.message });
    }
}

function actualizarKPIs(stats) {
    document.getElementById('kpiBicicletas').textContent  = (stats.total_bicicletas ?? 0).toLocaleString();
    document.getElementById('kpiDisponibles').textContent = (stats.bicicletas_disponibles ?? 0).toLocaleString();
    document.getElementById('kpiActivos').textContent     = (stats.alquileres_activos ?? 0).toLocaleString();
    document.getElementById('kpiUsuarios').textContent    = (stats.total_usuarios ?? 0).toLocaleString();
    document.getElementById('kpiAlquileres').textContent  = (stats.total_alquileres ?? 0).toLocaleString();
    document.getElementById('kpiIngresos').textContent     = (stats.ingresos_totales ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function mostrarResumen(id, texto) {
    const el = document.getElementById(id);
    if (el) el.textContent = texto;
}

// ============================================================
// RENDERS DE GRAFICOS (Chart.js con la paleta corporativa)
// ============================================================

// BLOQUE A1 - Barras: ingresos por estacion.
// Barras en verde olivo; la estacion de mayor volumen se destaca en dorado.
function renderGraficoBarras(data) {
    if (!data || !data.success) return;
    const d = data.data;

    const maximo = Math.max(...d.data, 0);
    const colores = d.data.map((v) => (v === maximo && maximo > 0 ? PALETA.dorado : PALETA.olivo));

    if (graficoBarras) graficoBarras.destroy();
    graficoBarras = new Chart(document.getElementById('graficoBarras'), {
        type: 'bar',
        data: {
            labels: d.labels,
            datasets: [{
                label: 'Ingresos totales (EUR)',
                data: d.data,
                backgroundColor: colores,
                borderRadius: 8,
                maxBarThickness: 64,
            }],
        },
        plugins: [
            // Etiqueta con el importe en EUR sobre cada barra
            crearPluginEtiquetas({
                formato: (v) => '€ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 }),
            }),
        ],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 18 } },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => 'EUR ' + Number(ctx.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 2 }),
                    },
                },
            },
            scales: {
                y: { beginAtZero: true, grid: { color: PALETA.rejilla }, ticks: { callback: (v) => '€ ' + v.toLocaleString() } },
                x: { grid: { display: false } },
            },
        },
    });
    window.graficoBarras = graficoBarras;
    mostrarResumen('resumenBarras', 'Total: € ' + d.data.reduce((a, b) => a + b, 0).toLocaleString());
}

// BLOQUE A2 - Histograma: duraciones por rango (degradado menta -> azul)
function renderGraficoHistograma(data) {
    if (!data || !data.success) return;
    const d = data.data;
    const n = d.data.length;

    if (graficoHistograma) graficoHistograma.destroy();
    graficoHistograma = new Chart(document.getElementById('graficoHistograma'), {
        type: 'bar',
        data: {
            labels: d.labels,
            datasets: [{
                label: 'Cantidad de alquileres',
                data: d.data,
                backgroundColor: d.data.map((_, i) => degradadoMentaAzul(i, n)),
                borderRadius: 8,
                maxBarThickness: 72,
            }],
        },
        plugins: [
            // Cantidad de alquileres sobre cada barra
            crearPluginEtiquetas({ formato: (v) => String(v) }),
        ],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 18 } },
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: PALETA.rejilla }, ticks: { stepSize: 1 } },
                x: { grid: { display: false } },
            },
        },
    });
    window.graficoHistograma = graficoHistograma;
    mostrarResumen('resumenHist', 'Total: ' + d.data.reduce((a, b) => a + b, 0));
}

// BLOQUE B1 - Poligono: evolucion mensual (linea azul, area menta suave)
function renderGraficoPoligono(data) {
    if (!data || !data.success) return;
    const d = data.data;

    if (graficoPoligono) graficoPoligono.destroy();
    graficoPoligono = new Chart(document.getElementById('graficoPoligono'), {
        type: 'line',
        data: {
            labels: d.labels,
            datasets: [{
                label: 'Alquileres',
                data: d.data,
                borderColor: PALETA.azul,
                backgroundColor: 'rgba(83, 211, 151, 0.22)',   // area en verde menta
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,                                  // linea suavizada
                pointBackgroundColor: PALETA.dorado,           // vertices dorados
                pointBorderColor: '#FFFFFF',
                pointRadius: 5,
                pointHoverRadius: 7,
            }],
        },
        plugins: [
            // Cantidad de alquileres sobre cada punto de la linea
            crearPluginEtiquetas({ formato: (v) => String(v) }),
        ],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 18 } },
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: PALETA.rejilla }, ticks: { stepSize: 1 } },
                x: { grid: { display: false } },
            },
        },
    });
    window.graficoPoligono = graficoPoligono;
    mostrarResumen('resumenPol', 'Total: ' + d.data.reduce((a, b) => a + b, 0));
}

// BLOQUE B2 - Sectores: dona por estado con el color de cada estado
function renderGraficoSectores(data) {
    if (!data || !data.success) return;
    const d = data.data;

    if (graficoSectores) graficoSectores.destroy();
    graficoSectores = new Chart(document.getElementById('graficoSectores'), {
        type: 'doughnut',
        data: {
            labels: d.labels,
            datasets: [{
                label: 'Alquileres',
                data: d.data,
                backgroundColor: d.labels.map((estado) => colorEstado(estado)),
                borderColor: '#FFFFFF',
                borderWidth: 2,
            }],
        },
        plugins: [
            // "cantidad (porcentaje%)" fuera de cada segmento
            crearPluginEtiquetas({
                formato: (v, i, chart) => {
                    const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                    const pct = total > 0 ? Math.round((v / total) * 100) : 0;
                    return `${v} (${pct}%)`;
                },
                omitirCero: true,
                color: PALETA.texto,
            }),
        ],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: 18 },
            cutout: '58%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 14, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                            return ` ${label}: ${value} (${pct}%)`;
                        },
                    },
                },
            },
        },
    });
    window.graficoSectores = graficoSectores;
    mostrarResumen('resumenSect', 'Total: ' + d.data.reduce((a, b) => a + b, 0));
}

// BLOQUE C - Radar: salud de la flota (SP sp_indicadores_sistema).
// Malla olivo, area menta al 70%, trazado perimetral azul y vertices
// dorados; cualquier eje en riesgo (< 40%) se resalta en rojo.
function renderGraficoRadar(data) {
    if (!data || !data.success) return;
    const d = data.data;
    const UMBRAL_RIESGO = 40;

    if (graficoRadar) graficoRadar.destroy();
    graficoRadar = new Chart(document.getElementById('graficoRadar'), {
        type: 'radar',
        data: {
            labels: d.labels,
            datasets: [{
                label: 'Indicadores del Sistema',
                data: d.data,
                borderColor: PALETA.azul,
                backgroundColor: 'rgba(83, 211, 151, 0.70)',   // cobertura en verde menta
                borderWidth: 2.5,
                pointBackgroundColor: function (ctx) {
                    const v = ctx.dataset.data[ctx.dataIndex];
                    return (typeof v === 'number' && v < UMBRAL_RIESGO) ? PALETA.rojo : PALETA.dorado;
                },
                pointBorderColor: '#FFFFFF',
                pointRadius: 5,
                pointHoverRadius: 7,
            }],
        },
        plugins: [
            // Porcentaje del indicador junto a cada vertice del radar
            crearPluginEtiquetas({
                formato: (v) => Number(v).toFixed(1) + '%',
                color: PALETA.olivo,
            }),
        ],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: 16 },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => ' ' + ctx.label + ': ' + ctx.parsed.r + '%' } },
            },
            scales: {
                r: {
                    beginAtZero: true,
                    suggestedMax: 100,
                    grid: { color: 'rgba(85, 107, 47, 0.35)' },        // malla en verde olivo tenue
                    angleLines: { color: 'rgba(85, 107, 47, 0.35)' },
                    ticks: { stepSize: 20, backdropColor: 'transparent', callback: (v) => v + '%' },
                    pointLabels: { font: { size: 12, weight: 'bold' }, color: PALETA.texto },
                },
            },
        },
    });
    window.graficoRadar = graficoRadar;
    mostrarResumen('resumenRadar', 'Promedio: ' + (d.data.reduce((a, b) => a + b, 0) / d.data.length).toFixed(1) + '%');
}

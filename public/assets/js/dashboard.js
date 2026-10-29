// ============================================================
// DASHBOARD EBIKE MANAGER - Logica de filtros, KPIs y graficos
// ============================================================
// Paleta:      Olivo #556B2F | Menta #53D397 | Dorado #D4AF37
//              Azul #2A75D3  | Rojo #E63946
// Datos:       API PHP -> procedimientos almacenados MySQL
// ============================================================
// Archivo principal del dashboard: gestiona el ciclo de vida de la pagina,
// los filtros del sidebar, la carga de datos desde la API PHP (que consulta
// procedimientos almacenados de MySQL), el renderizado de KPIs y de cinco
// graficos Chart.js, y la captura de imagenes para el PDF.
// ============================================================

// Variables globales que almacenan las instancias de los graficos Chart.js.
// Se inicializan en null y se asignan al renderizar; se reutilizan para
// destruir el grafico previo antes de crear uno nuevo (evita fugas de memoria).
let graficoBarras = null;       // Grafico de barras: ingresos por estacion.
let graficoHistograma = null;   // Histograma: cantidad de alquileres por rango de duracion.
let graficoPoligono = null;     // Poligono (linea): evolucion mensual de alquileres.
let graficoSectores = null;     // Dona: distribucion de alquileres por estado.
let graficoRadar = null;        // Radar: indicadores de salud de la flota.

// Referencias publicas expuestas en window para que el modulo de captura
// de imagenes del PDF pueda acceder a las instancias de los graficos.
window.graficoBarras = null;
window.graficoHistograma = null;
window.graficoPoligono = null;
window.graficoSectores = null;
window.graficoRadar = null;

// Prefijo base de la API PHP: se concatena con el tipo de endpoint y los params.
let apiBase = '?accion=api&tipo=';

// ---------- PALETA DE COLORES (guia de estilos) ----------
// Objeto con los colores corporativos reutilizados en graficos, chips y UI.
const PALETA = {
    olivo:   '#556B2F',   // Verde olivo: color principal de barras/series.
    menta:   '#53D397',   // Verde menta: areas, coberturas y estados activos.
    dorado:  '#D4AF37',   // Dorado: destacados (maximo, vertices).
    azul:    '#2A75D3',   // Azul: lineas, estados reservados.
    rojo:    '#E63946',   // Rojo: riesgo/cancelado.
    gris:    '#9CA3AF',   // Gris: fallback para estados desconocidos.
    texto:   '#1F2937',   // Texto principal sobre graficos.
    suave:   '#6B7280',   // Texto secundario (Chart.js defaults).
    rejilla: '#E5EBE4',   // Color de la rejilla de ejes.
};

// Color asociado a cada estado de alquiler (usado por la dona de sectores).
const COLOR_ESTADO = {
    ACTIVO:         PALETA.menta,    // Alquiler en curso.
    RESERVADO:      PALETA.azul,     // Reservado pero no iniciado.
    FINALIZADO:     PALETA.olivo,    // Alquiler completado.
    PENDIENTE_PAGO: PALETA.dorado,   // Pendiente de cobro.
    CANCELADO:      PALETA.rojo,     // Cancelado.
};
// Funcion auxiliar: devuelve el color de un estado o gris si no esta mapeado.
const colorEstado = (estado) => COLOR_ESTADO[estado] || PALETA.gris;

// Interpola un color entre menta y azul segun la posicion (i/n) para el histograma.
function degradadoMentaAzul(i, n) {
    const desde = [83, 211, 151];   // Componentes RGB de #53D397 (menta).
    const hasta = [42, 117, 211];   // Componentes RGB de #2A75D3 (azul).
    const t = n <= 1 ? 0 : i / (n - 1);          // Factor de interpolacion 0..1.
    const c = desde.map((v, k) => Math.round(v + (hasta[k] - v) * t)); // RGB interpolado.
    return 'rgba(' + c.join(',') + ', 0.85)';     // Color con 85% de opacidad.
}

// ---------- ETIQUETAS NUMERICAS SOBRE LOS GRAFICOS ----------
// Plugin de Chart.js que dibuja el valor de cada dato. Al formar parte del
// canvas, las etiquetas quedan incluidas en las imagenes capturadas para el
// PDF (toBase64Image) y son visibles en el dashboard.
function crearPluginEtiquetas(opciones = {}) {
    // Desestructuracion con valores por defecto para las opciones del plugin.
    const {
        formato    = (v) => String(v),   // Como se formatea cada valor.
        offset     = 7,                   // Distancia respecto a barra/punto.
        color      = PALETA.texto,        // Color del texto de la etiqueta.
        omitirCero = false,               // Si true, no dibuja etiqueta para valor 0.
        fuente     = 'bold 11px "Segoe UI", sans-serif', // Tipografia de la etiqueta.
    } = opciones;

    // Objeto plugin compatible con la API de plugins de Chart.js.
    return {
        id: 'etiquetasDatos',             // Identificador unico del plugin.
        afterDatasetsDraw(chart) {        // Se ejecuta tras dibujar los datasets.
            const meta = chart.getDatasetMeta(0); // Metadatos del primer dataset.
            if (!meta || !meta.data.length) return; // Sin datos, no dibujar.

            const { ctx } = chart;        // Contexto 2D del canvas.
            ctx.save();                   // Guarda estado del contexto.
            ctx.font = fuente;            // Aplica la fuente configurada.
            ctx.textAlign = 'center';     // Texto centrado horizontalmente.
            ctx.textBaseline = 'middle';  // Texto centrado verticalmente.
            ctx.fillStyle = color;        // Color del texto.

            // Recorre cada elemento (barra/punto/segmento) del dataset.
            meta.data.forEach((el, i) => {
                const v = chart.data.datasets[0].data[i]; // Valor del dato i.
                if (v === null || v === undefined) return; // Ignora nulos/indefinidos.
                if (omitirCero && !v) return;              // Ignora ceros si se pidio.

                const tipo = chart.config.type; // Tipo de grafico (bar, line, ...).
                let x = el.x, y = el.y;        // Posicion base del elemento.

                // Ajusta la posicion de la etiqueta segun el tipo de grafico.
                if (tipo === 'bar') {
                    y = el.y - offset;                       // Sobre la barra.
                } else if (tipo === 'line') {
                    y = el.y - offset - 2;                   // Sobre el punto.
                } else if (tipo === 'doughnut' || tipo === 'pie') {
                    const ang = (el.startAngle + el.endAngle) / 2; // Angulo central del segmento.
                    const radio = el.outerRadius + 15;              // Radio exterior + margen.
                    x = el.x + Math.cos(ang) * radio;              // Coordenada X fuera del segmento.
                    y = el.y + Math.sin(ang) * radio;              // Coordenada Y fuera del segmento.
                } else if (tipo === 'radar') {
                    y = el.y - 13;                           // Sobre el vertice del radar.
                }
                // Dibuja el texto formateado en la posicion calculada.
                ctx.fillText(formato(v, i, chart), x, y);
            });
            ctx.restore();                // Restaura estado del contexto.
        }
    };
}

// Estilo global de Chart.js acorde a la paleta corporativa.
Chart.defaults.font.family = '"Segoe UI", system-ui, sans-serif'; // Fuente global.
Chart.defaults.color = PALETA.suave;                              // Color de texto global.

// ---------- CICLO DE VIDA ----------
// Al cargar el DOM se inicializan chips, se cargan todos los datos y se
// registran los listeners de filtros y busqueda.
document.addEventListener('DOMContentLoaded', function () {
    actualizarChips();          // Estado inicial de los chips de filtros.
    cargarTodos();              // Primera carga de todos los datos/graficos.

    // Envio manual del formulario (boton "Aplicar filtros" o Enter).
    document.getElementById('formFiltros').addEventListener('submit', function (e) {
        e.preventDefault();     // Evita recarga de pagina.
        aplicarFiltros();       // Aplica filtros y recarga datos.
    });

    // Filtro en tiempo real al cambiar estado o periodo (pills del sidebar).
    document.getElementById('filtroEstado').addEventListener('change', aplicarFiltros);
    document.getElementById('filtroPeriodo').addEventListener('change', aplicarFiltros);

    // Busqueda dinamica con pequeño retardo mientras se escribe (debounce).
    const refiltrarConRetardo = debounce(aplicarFiltros, 450); // 450 ms de espera.
    document.getElementById('filtroCliente').addEventListener('input', refiltrarConRetardo);
    document.getElementById('filtroQr').addEventListener('input', refiltrarConRetardo);
});

// Crea una funcion con retardo: ejecuta fn solo tras ms milisegundos sin llamadas.
function debounce(fn, ms) {
    let t;                          // Temporizador interno.
    return (...args) => {           // Funcion envuelta.
        clearTimeout(t);            // Cancela temporizador previo.
        t = setTimeout(() => fn(...args), ms); // Programa nueva ejecucion.
    };
}

// ---------- FILTROS ----------

// Construye la cadena de query params a partir de los inputs del sidebar.
function obtenerParams() {
    const p = new URLSearchParams();               // Contenedor de parametros.
    const estado  = document.getElementById('filtroEstado').value;   // Estado seleccionado.
    const cliente = document.getElementById('filtroCliente').value;  // Texto de cliente.
    const qr      = document.getElementById('filtroQr').value;       // Texto de QR.
    const periodo = document.getElementById('filtroPeriodo').value;   // Periodo seleccionado.
    if (estado)  p.set('estado', estado);   // Solo si hay valor.
    if (cliente) p.set('cliente', cliente);
    if (qr)      p.set('qr', qr);
    if (periodo) p.set('periodo', periodo);
    return p.toString();                   // Devuelve "k=v&...".
}

// Aplica los filtros actuales: actualiza chips y recarga todos los datos.
function aplicarFiltros() {
    // Los chips son decorativos: si fallan, los graficos se recargan igual.
    try { actualizarChips(); } catch (e) { console.warn('actualizarChips:', e); }
    cargarTodos();             // Recarga datos con los nuevos filtros.
}

// Muestra los filtros activos como chips bajo el encabezado.
function actualizarChips() {
    const cont = document.getElementById('chipsFiltros'); // Contenedor de chips.
    if (!cont) return;                                   // Si no existe, salir.

    // Definicion de pares [etiqueta, valor] para cada filtro.
    const defs = [
        ['Estado',   (document.getElementById('filtroEstado')  || {}).value],
        ['Cliente',  (document.getElementById('filtroCliente') || {}).value],
        ['QR',       (document.getElementById('filtroQr')      || {}).value],
        ['Periodo',  (document.getElementById('filtroPeriodo') || {}).value],
    ];
    // Filtra los activos: con valor y distinto de 'TODOS'.
    const activos = defs.filter((par) => par[1] && par[1] !== 'TODOS');

    cont.innerHTML = '';       // Limpia chips previos.
    if (!activos.length) {
        // Sin filtros activos: muestra badge informativo.
        const b = document.createElement('span');
        b.className = 'badge bg-secondary';
        b.textContent = 'Sin filtros - mostrando todos los datos';
        cont.appendChild(b);
        return;
    }
    // Crea un badge por cada filtro activo.
    activos.forEach(([k, v]) => {
        const b = document.createElement('span');
        b.className = 'badge me-1';
        b.style.background = PALETA.olivo;   // Color olivo corporativo.
        b.style.color = '#fff';
        b.textContent = k + ': ' + v;        // textContent: seguro frente a XSS.
        cont.appendChild(b);
    });
}

// ---------- CARGA DE DATOS (API -> SP MySQL) ----------

// Carga en paralelo todos los endpoints y renderiza graficos + KPIs.
async function cargarTodos() {
    try {
        const params = obtenerParams(); // Parametros de filtrado actuales.
        // Peticiones paralelas a la API PHP (cada una consulta un SP).
        const [barras, histograma, poligono, sectores, radar, stats] = await Promise.all([
            fetch(`${apiBase}barras&${params}`).then(r => r.json()),       // Ingresos por estacion.
            fetch(`${apiBase}histograma&${params}`).then(r => r.json()),  // Duraciones por rango.
            fetch(`${apiBase}poligono&${params}`).then(r => r.json()),     // Evolucion mensual.
            fetch(`${apiBase}sectores&${params}`).then(r => r.json()),     // Distribucion por estado.
            fetch(`${apiBase}radar`).then(r => r.json()),                  // Indicadores globales (SP).
            fetch(`${apiBase}estadisticas&${params}`).then(r => r.json()), // KPIs filtrados.
        ]);

        // Renderiza cada grafico con su dataset.
        renderGraficoBarras(barras);
        renderGraficoHistograma(histograma);
        renderGraficoPoligono(poligono);
        renderGraficoSectores(sectores);
        renderGraficoRadar(radar);

        // Actualiza KPIs si la respuesta es valida.
        if (stats && stats.success && stats.data) {
            actualizarKPIs(stats.data);
        }
    } catch (e) {
        console.error('Error cargando datos:', e); // Log del error.
        Swal.fire({ icon: 'error', title: 'Error cargando datos', text: e.message }); // Alerta al usuario.
    }
}

// Actualiza las tarjetas KPI con los valores del endpoint de estadisticas.
function actualizarKPIs(stats) {
    document.getElementById('kpiBicicletas').textContent  = (stats.total_bicicletas ?? 0).toLocaleString();
    document.getElementById('kpiDisponibles').textContent = (stats.bicicletas_disponibles ?? 0).toLocaleString();
    document.getElementById('kpiActivos').textContent     = (stats.alquileres_activos ?? 0).toLocaleString();
    document.getElementById('kpiUsuarios').textContent    = (stats.total_usuarios ?? 0).toLocaleString();
    document.getElementById('kpiAlquileres').textContent  = (stats.total_alquileres ?? 0).toLocaleString();
    document.getElementById('kpiIngresos').textContent     = (stats.ingresos_totales ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Actualiza el texto de resumen de un grafico si existe el elemento.
function mostrarResumen(id, texto) {
    const el = document.getElementById(id);
    if (el) el.textContent = texto; // Asigna el texto resumen.
}

// ============================================================
// RENDERS DE GRAFICOS (Chart.js con la paleta corporativa)
// ============================================================

// BLOQUE A1 - Barras: ingresos por estacion.
// Barras en verde olivo; la estacion de mayor volumen se destaca en dorado.
function renderGraficoBarras(data) {
    if (!data || !data.success) return; // Valida respuesta de la API.
    const d = data.data;                // Datos del grafico.

    const maximo = Math.max(...d.data, 0); // Valor maximo de ingresos.
    // Colores: dorado para el maximo, olivo para el resto.
    const colores = d.data.map((v) => (v === maximo && maximo > 0 ? PALETA.dorado : PALETA.olivo));

    if (graficoBarras) graficoBarras.destroy(); // Destruye grafico previo.
    graficoBarras = new Chart(document.getElementById('graficoBarras'), {
        type: 'bar',                   // Tipo barra.
        data: {
            labels: d.labels,          // Etiquetas del eje X (estaciones).
            datasets: [{
                label: 'Ingresos totales (EUR)',
                data: d.data,          // Valores de ingresos.
                backgroundColor: colores, // Color por barra.
                borderRadius: 8,       // Esquinas redondeadas.
                maxBarThickness: 64,   // Grosor maximo de barra.
            }],
        },
        plugins: [
            // Etiqueta con el importe en EUR sobre cada barra.
            crearPluginEtiquetas({
                formato: (v) => '€ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 }),
            }),
        ],
        options: {
            responsive: true,           // Adaptable al contenedor.
            maintainAspectRatio: false, // No mantiene aspecto fijo.
            layout: { padding: { top: 18 } }, // Padding superior para etiquetas.
            plugins: {
                legend: { display: false }, // Oculta leyenda.
                tooltip: {
                    callbacks: {
                        // Formato del tooltip al pasar el raton.
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
    window.graficoBarras = graficoBarras; // Expone instancia globalmente.
    // Muestra el total de ingresos en el resumen.
    mostrarResumen('resumenBarras', 'Total: € ' + d.data.reduce((a, b) => a + b, 0).toLocaleString());
}

// BLOQUE A2 - Histograma: duraciones por rango (degradado menta -> azul).
function renderGraficoHistograma(data) {
    if (!data || !data.success) return; // Valida respuesta.
    const d = data.data;                // Datos.
    const n = d.data.length;            // Numero de rangos.

    if (graficoHistograma) graficoHistograma.destroy(); // Destruye previo.
    graficoHistograma = new Chart(document.getElementById('graficoHistograma'), {
        type: 'bar',                    // Tipo barra (histograma).
        data: {
            labels: d.labels,           // Rangos de duracion.
            datasets: [{
                label: 'Cantidad de alquileres',
                data: d.data,           // Cantidad por rango.
                backgroundColor: d.data.map((_, i) => degradadoMentaAzul(i, n)), // Degradado.
                borderRadius: 8,
                maxBarThickness: 72,
            }],
        },
        plugins: [
            // Cantidad de alquileres sobre cada barra.
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
    window.graficoHistograma = graficoHistograma; // Expone instancia.
    mostrarResumen('resumenHist', 'Total: ' + d.data.reduce((a, b) => a + b, 0)); // Total alquileres.
}

// BLOQUE B1 - Poligono: evolucion mensual (linea azul, area menta suave).
function renderGraficoPoligono(data) {
    if (!data || !data.success) return; // Valida respuesta.
    const d = data.data;                // Datos.

    if (graficoPoligono) graficoPoligono.destroy(); // Destruye previo.
    graficoPoligono = new Chart(document.getElementById('graficoPoligono'), {
        type: 'line',                   // Tipo linea (poligono).
        data: {
            labels: d.labels,           // Meses.
            datasets: [{
                label: 'Alquileres',
                data: d.data,           // Cantidad por mes.
                borderColor: PALETA.azul, // Linea azul.
                backgroundColor: 'rgba(83, 211, 151, 0.22)',   // Area en verde menta.
                borderWidth: 2.5,
                fill: true,             // Rellena area bajo la linea.
                tension: 0.4,           // Linea suavizada.
                pointBackgroundColor: PALETA.dorado, // Vertices dorados.
                pointBorderColor: '#FFFFFF',
                pointRadius: 5,
                pointHoverRadius: 7,
            }],
        },
        plugins: [
            // Cantidad de alquileres sobre cada punto de la linea.
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
    window.graficoPoligono = graficoPoligono; // Expone instancia.
    mostrarResumen('resumenPol', 'Total: ' + d.data.reduce((a, b) => a + b, 0)); // Total alquileres.
}

// BLOQUE B2 - Sectores: dona por estado con el color de cada estado.
function renderGraficoSectores(data) {
    if (!data || !data.success) return; // Valida respuesta.
    const d = data.data;                // Datos.

    if (graficoSectores) graficoSectores.destroy(); // Destruye previo.
    graficoSectores = new Chart(document.getElementById('graficoSectores'), {
        type: 'doughnut',               // Tipo dona.
        data: {
            labels: d.labels,           // Estados.
            datasets: [{
                label: 'Alquileres',
                data: d.data,           // Cantidad por estado.
                backgroundColor: d.labels.map((estado) => colorEstado(estado)), // Color por estado.
                borderColor: '#FFFFFF',
                borderWidth: 2,
            }],
        },
        plugins: [
            // "cantidad (porcentaje%)" fuera de cada segmento.
            crearPluginEtiquetas({
                formato: (v, i, chart) => {
                    const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0); // Total.
                    const pct = total > 0 ? Math.round((v / total) * 100) : 0;            // Porcentaje.
                    return `${v} (${pct}%)`; // Texto de etiqueta.
                },
                omitirCero: true,       // No etiqueta segmentos con 0.
                color: PALETA.texto,
            }),
        ],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: 18 },
            cutout: '58%',             // Agujero central del 58%.
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 14, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const label = context.label || '';                // Nombre del estado.
                            const value = context.parsed || 0;               // Cantidad.
                            const total = context.dataset.data.reduce((a, b) => a + b, 0); // Total.
                            const pct = total > 0 ? Math.round((value / total) * 100) : 0; // Porcentaje.
                            return ` ${label}: ${value} (${pct}%)`; // Texto del tooltip.
                        },
                    },
                },
            },
        },
    });
    window.graficoSectores = graficoSectores; // Expone instancia.
    mostrarResumen('resumenSect', 'Total: ' + d.data.reduce((a, b) => a + b, 0)); // Total alquileres.
}

// BLOQUE C - Radar: salud de la flota (SP sp_indicadores_sistema).
// Malla olivo, area menta al 70%, trazado perimetral azul y vertices
// dorados; cualquier eje en riesgo (< 40%) se resalta en rojo.
function renderGraficoRadar(data) {
    if (!data || !data.success) return; // Valida respuesta.
    const d = data.data;                // Datos.
    const UMBRAL_RIESGO = 40;          // Umbral de riesgo en porcentaje.

    if (graficoRadar) graficoRadar.destroy(); // Destruye previo.
    graficoRadar = new Chart(document.getElementById('graficoRadar'), {
        type: 'radar',                  // Tipo radar.
        data: {
            labels: d.labels,           // Ejes/indicadores.
            datasets: [{
                label: 'Indicadores del Sistema',
                data: d.data,           // Valores porcentuales.
                borderColor: PALETA.azul, // Trazado perimetral azul.
                backgroundColor: 'rgba(83, 211, 151, 0.70)',   // Cobertura menta al 70%.
                borderWidth: 2.5,
                pointBackgroundColor: function (ctx) {
                    const v = ctx.dataset.data[ctx.dataIndex]; // Valor del vertice.
                    // Rojo si esta en riesgo (< 40%), dorado si no.
                    return (typeof v === 'number' && v < UMBRAL_RIESGO) ? PALETA.rojo : PALETA.dorado;
                },
                pointBorderColor: '#FFFFFF',
                pointRadius: 5,
                pointHoverRadius: 7,
            }],
        },
        plugins: [
            // Porcentaje del indicador junto a cada vertice del radar.
            crearPluginEtiquetas({
                formato: (v) => Number(v).toFixed(1) + '%', // Formato con 1 decimal.
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
                    beginAtZero: true,       // Eje radial desde 0.
                    suggestedMax: 100,       // Maximo sugerido 100%.
                    grid: { color: 'rgba(85, 107, 47, 0.35)' },        // Malla olivo tenue.
                    angleLines: { color: 'rgba(85, 107, 47, 0.35)' },  // Lineas angulares olivo.
                    ticks: { stepSize: 20, backdropColor: 'transparent', callback: (v) => v + '%' },
                    pointLabels: { font: { size: 12, weight: 'bold' }, color: PALETA.texto },
                },
            },
        },
    });
    window.graficoRadar = graficoRadar; // Expone instancia.
    // Promedio de indicadores en el resumen.
    mostrarResumen('resumenRadar', 'Promedio: ' + (d.data.reduce((a, b) => a + b, 0) / d.data.length).toFixed(1) + '%');
}

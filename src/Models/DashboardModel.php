<?php
// Etiqueta de apertura del lenguaje PHP
// ============================================================
// MODELO DE DATOS DEL DASHBOARD (PDO) - CORREGIDO
// ============================================================
// Declaración del espacio de nombres al que pertenece esta clase
namespace App\Models;

// Importación de la clase Database, usada para obtener la conexión PDO
use App\Database\Database;

// Definición de la clase DashboardModel, encargada de obtener datos del dashboard
class DashboardModel
{
    // Propiedad privada tipada que almacena la conexión PDO a la base de datos
    private \PDO $db;

    // Constructor de la clase: inicializa la conexión a la base de datos
    public function __construct()
    {
        // Bloque try para capturar posibles errores de conexión
        try {
            // Obtiene la instancia singleton de la base de datos y la asigna a $this->db
            $this->db = Database::obtenerInstancia();
        } catch (\Throwable $e) {
            // Registra el mensaje de error de conexión en el log del servidor
            error_log("DashboardModel: Error de conexión BD: " . $e->getMessage());
            // Lanza una excepción de tiempo de ejecución para notificar el fallo
            throw new \RuntimeException("No se pudo conectar a la base de datos");
        }
    }

    // Método privado que ejecuta un procedimiento almacenado y retorna la primera fila (para stats)
    private function callSpSingleRow(string $sp, array $params): array
    {
        // Bloque try para capturar errores durante la ejecución del procedimiento
        try {
            // Construye la cadena de placeholders (?, ?, ...) según la cantidad de parámetros
            $placeholders = str_repeat('?,', count($params) - 1) . '?';
            // Prepara la sentencia SQL CALL con los placeholders generados
            $stmt = $this->db->prepare("CALL $sp($placeholders)");
            // Ejecuta la sentencia pasando los parámetros
            $stmt->execute($params);
            // Obtiene la primera fila del resultado
            $row = $stmt->fetch();
            // Consume los rowsets adicionales que pueda devolver el procedimiento
            while ($stmt->nextRowset()) {
                // Descarta las filas de cada rowset adicional
                $stmt->fetchAll();
            }
            // Cierra el cursor del statement
            $stmt->closeCursor();
            // Retorna la fila obtenida o un arreglo vacío si no hay datos
            return $row ?: [];
        } catch (\Throwable $e) {
            // Registra el error de ejecución del procedimiento en el log
            error_log("callSpSingleRow [$sp] error: " . $e->getMessage());
            // Lanza una excepción de tiempo de ejecución indicando el procedimiento fallido
            throw new \RuntimeException("Error ejecutando procedimiento: $sp");
        }
    }

    // Método privado que ejecuta un procedimiento almacenado y retorna todas las filas (segundo result set para sp_alquiler_buscar_paginado)
    private function callSpAllRows(string $sp, array $params): array
    {
        // Bloque try para capturar errores durante la ejecución del procedimiento
        try {
            // Construye la cadena de placeholders (?, ?, ...) según la cantidad de parámetros
            $placeholders = str_repeat('?,', count($params) - 1) . '?';
            // Prepara la sentencia SQL CALL con los placeholders generados
            $stmt = $this->db->prepare("CALL $sp($placeholders)");
            // Ejecuta la sentencia pasando los parámetros
            $stmt->execute($params);

            // Obtiene todas las filas del primer result set
            $firstRows = $stmt->fetchAll();
            // Inicializa el arreglo que contendrá las filas de datos del segundo result set
            $dataRows = [];

            // Si existe un segundo result set, obtiene sus filas
            if ($stmt->nextRowset()) {
                // Almacena las filas del segundo result set
                $dataRows = $stmt->fetchAll();
            }

            // Consume cualquier rowset adicional restante
            while ($stmt->nextRowset()) {
                // Descarta las filas de cada rowset adicional
                $stmt->fetchAll();
            }
            // Cierra el cursor del statement
            $stmt->closeCursor();

            // Retorna las filas de datos si existen, de lo contrario las del primer result set
            return $dataRows ?: $firstRows;
        } catch (\Throwable $e) {
            // Registra el error de ejecución del procedimiento en el log
            error_log("callSpAllRows [$sp] error: " . $e->getMessage());
            // Lanza una excepción de tiempo de ejecución indicando el procedimiento fallido
            throw new \RuntimeException("Error ejecutando procedimiento: $sp");
        }
    }

    // Estadisticas generales para el grafico radial / reporte
    // Acepta filtros opcionales (estado, cliente, qr, periodo). Las metricas
    // de flota/usuarios son globales; las de alquileres se recalculan filtradas.
    public function getEstadisticasAdmin(
        string $rolEjecutor,
        string $estado = '',
        string $cliente = '',
        string $qr = '',
        string $periodo = 'TODOS'
    ): array {
        // Llama al SP de estadísticas administrativas pasando el rol ejecutor
        $fila = $this->callSpSingleRow('sp_reporte_estadisticas_admin', [$rolEjecutor]);

        // Construye el arreglo de estadísticas con valores por defecto a partir de la fila obtenida
        $stats = [
            'total_bicicletas'      => (int)($fila['total_bicicletas'] ?? 0),
            'bicicletas_disponibles'=> (int)($fila['bicicletas_disponibles'] ?? 0),
            'alquileres_activos'    => (int)($fila['alquileres_activos'] ?? 0),
            'total_usuarios'        => (int)($fila['total_usuarios'] ?? 0),
            'total_alquileres'      => (int)($fila['total_alquileres'] ?? 0),
            'ingresos_totales'      => (float)($fila['ingresos_totales'] ?? 0),
        ];

        // Determina si se está filtrando por periodo distinto a TODOS o vacío
        $filtraPeriodo = ($periodo !== 'TODOS' && $periodo !== '');
        // Si no hay filtros aplicados, devuelve las estadísticas globales del SP
        if ($estado === '' && $cliente === '' && $qr === '' && !$filtraPeriodo) {
            // Retorna las estadísticas sin recalcular
            return $stats;
        }

        // Con filtros: recalcular las metricas dependientes de alquileres
        // usando el mismo conjunto filtrado que los graficos
        // Obtiene los alquileres filtrados (hasta MAX_REGISTROS_GRAFICOS) para recalcular métricas
        $alquileres = $this->getAlquileres($rolEjecutor, $estado, $cliente, $qr, self::MAX_REGISTROS_GRAFICOS, 0);

        // Inicializa contadores para totales, ingresos, activos y fechas de referencia
        $total = 0;
        $ingresos = 0.0;
        $activos = 0;
        $hoy    = strtotime('today');
        $semana = strtotime('-7 days');
        $mesIni = strtotime('first day of this month');

        // Recorre cada alquiler para recalcular las métricas filtradas
        foreach ($alquileres as $a) {
            // Si se filtra por periodo, valida la fecha de inicio del alquiler
            if ($filtraPeriodo) {
                // Omite alquileres sin fecha de inicio
                if (empty($a['fecha_inicio'])) continue;
                // Convierte la fecha de inicio a timestamp
                $fecha = strtotime($a['fecha_inicio']);
                // Filtra por día actual si el periodo es HOY
                if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
                // Filtra por los últimos 7 días si el periodo es SEMANA
                if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
                // Filtra por el mes actual si el periodo es MES
                if ($periodo === 'MES'     && $fecha < $mesIni) continue;
            }
            // Incrementa el total de alquileres válidos
            $total++;
            // Normaliza el estado del alquiler a mayúsculas
            $est = strtoupper((string)($a['estado_alquiler'] ?? ''));
            // Incrementa el contador de activos si el estado es ACTIVO
            if ($est === 'ACTIVO') $activos++;
            // Mismo criterio que el SP global: ingresos de alquileres FINALIZADOS
            if ($est === 'FINALIZADO') $ingresos += (float)($a['costo_total'] ?? 0);
        }

        // Asigna las métricas recalculadas al arreglo de estadísticas
        $stats['total_alquileres']   = $total;
        $stats['ingresos_totales']   = $ingresos;
        $stats['alquileres_activos'] = $activos;

        // Retorna las estadísticas recalculadas con filtros
        return $stats;
    }

    // Obtiene los alquileres usando el procedimiento almacenado.
    // El filtro de periodo (HOY/SEMANA/MES) se aplica en PHP sobre fecha_inicio.
    public function getAlquileres(
        string $rolEjecutor,
        string $estado,
        string $cliente = '',
        string $qr = '',
        int $limite = self::MAX_REGISTROS_GRAFICOS,
        int $offset = 0,
        string $periodo = 'TODOS'
    ): array {
        // Llama al SP de búsqueda paginada de alquileres con los parámetros indicados
        $alquileres = $this->callSpAllRows('sp_alquiler_buscar_paginado', [
            $limite, $offset, $estado, $cliente, $qr, $rolEjecutor
        ]);

        // Si el periodo es TODOS o vacío, devuelve los alquileres sin filtrar
        if ($periodo === 'TODOS' || $periodo === '') {
            // Retorna directamente los alquileres obtenidos
            return $alquileres;
        }

        // Fechas de referencia para el filtrado por periodo
        $hoy    = strtotime('today');
        $semana = strtotime('-7 days');
        $mesIni = strtotime('first day of this month');
        // Arreglo para almacenar los alquileres filtrados
        $filtrados = [];
        // Recorre cada alquiler para aplicar el filtro de periodo
        foreach ($alquileres as $a) {
            // Omite alquileres sin fecha de inicio
            if (empty($a['fecha_inicio'])) continue;
            // Convierte la fecha de inicio a timestamp
            $fecha = strtotime($a['fecha_inicio']);
            // Filtra por día actual si el periodo es HOY
            if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
            // Filtra por los últimos 7 días si el periodo es SEMANA
            if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
            // Filtra por el mes actual si el periodo es MES
            if ($periodo === 'MES'     && $fecha < $mesIni) continue;
            // Agrega el alquiler filtrado al resultado
            $filtrados[] = $a;
        }
        // Retorna los alquileres filtrados por periodo
        return $filtrados;
    }

    // Constante para límite máximo de registros en gráficos
    private const MAX_REGISTROS_GRAFICOS = 5000;

    // ============================================================
    // DATOS PARA LOS GRAFICOS
    // ============================================================

    // Grafico de barras: ingresos por estacion
    public function getIngresosPorEstacion(string $rolEjecutor, string $estado, string $cliente = '', string $qr = '', string $periodo = 'TODOS'): array
    {
        // Obtiene los alquileres hasta el máximo de registros para gráficos
        $alquileres = $this->getAlquileres($rolEjecutor, $estado, $cliente, $qr, self::MAX_REGISTROS_GRAFICOS, 0);

        // Filtrar por periodo si aplica
        if ($periodo !== 'TODOS' && $periodo !== '') {
            // Arreglo temporal para los alquileres filtrados
            $filtrados = [];
            // Recorre cada alquiler para aplicar el filtro de periodo
            foreach ($alquileres as $a) {
                // Omite alquileres sin fecha de inicio
                if (empty($a['fecha_inicio'])) continue;
                // Convierte la fecha de inicio a timestamp
                $fecha = strtotime($a['fecha_inicio']);
                // Fechas de referencia para el filtrado
                $hoy    = strtotime('today');
                $semana = strtotime('-7 days');
                $mesIni = strtotime('first day of this month');
                // Filtra por día actual si el periodo es HOY
                if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
                // Filtra por los últimos 7 días si el periodo es SEMANA
                if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
                // Filtra por el mes actual si el periodo es MES
                if ($periodo === 'MES'     && $fecha < $mesIni) continue;
                // Agrega el alquiler filtrado al resultado
                $filtrados[] = $a;
            }
            // Reemplaza los alquileres por los filtrados
            $alquileres = $filtrados;
        }

        // Arreglo asociativo para acumular ingresos por estación de origen
        $ingresos = [];
        // Recorre cada alquiler para acumular ingresos por estación
        foreach ($alquileres as $a) {
            // Obtiene el nombre de la estación de origen o 'Otra' por defecto
            $nombre = $a['estacion_origen'] ?? 'Otra';
            // Inicializa el acumulador de la estación si no existe
            if (!isset($ingresos[$nombre])) {
                $ingresos[$nombre] = 0;
            }
            // Suma el costo total del alquiler a la estación correspondiente
            $ingresos[$nombre] += (float)($a['costo_total'] ?? 0);
        }

        // Ordenar por ingreso descendente
        arsort($ingresos);

        // Retorna las etiquetas (estaciones) y los datos (ingresos) para el gráfico
        return [
            'labels' => array_keys($ingresos),
            'data'   => array_values($ingresos),
        ];
    }

// Grafico de histograma: duraciones de alquileres en minutos
    public function getDuracionAlquileres(
        string $rolEjecutor, string $estado, string $cliente, string $qr, string $periodo = 'TODOS'
    ): array {
        // Usa el SP dedicado sp_histograma_duraciones
        $rows = $this->callSpAllRows('sp_histograma_duraciones', [
            $estado, $cliente, $qr, $rolEjecutor
        ]);

        // Si hay filtro de periodo, re-calcular en PHP
        if ($periodo !== 'TODOS' && $periodo !== '') {
            // Obtiene los alquileres para recalcular duraciones con filtro de periodo
            $alquileres = $this->getAlquileres($rolEjecutor, $estado, $cliente, $qr, self::MAX_REGISTROS_GRAFICOS, 0);
            // Arreglo temporal para los alquileres filtrados
            $filtrados = [];
            // Recorre cada alquiler para aplicar el filtro de periodo
            foreach ($alquileres as $a) {
                // Omite alquileres sin fecha de inicio o sin fecha de fin
                if (empty($a['fecha_inicio']) || empty($a['fecha_fin'])) continue;
                // Convierte la fecha de inicio a timestamp
                $fecha = strtotime($a['fecha_inicio']);
                // Fechas de referencia para el filtrado
                $hoy    = strtotime('today');
                $semana = strtotime('-7 days');
                $mesIni = strtotime('first day of this month');
                // Filtra por día actual si el periodo es HOY
                if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
                // Filtra por los últimos 7 días si el periodo es SEMANA
                if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
                // Filtra por el mes actual si el periodo es MES
                if ($periodo === 'MES'     && $fecha < $mesIni) continue;
                // Agrega el alquiler filtrado al resultado
                $filtrados[] = $a;
            }
            // Re-calcular buckets
            // Arreglo para acumular duraciones por rango (bucket)
            $duraciones = [];
            // Recorre cada alquiler filtrado para calcular su duración en minutos
            foreach ($filtrados as $a) {
                // Convierte la fecha de inicio a timestamp
                $inicio = strtotime($a['fecha_inicio']);
                // Convierte la fecha de fin a timestamp
                $fin    = strtotime($a['fecha_fin']);
                // Calcula la duración en minutos redondeada
                $minutos = (int)round(($fin - $inicio) / 60);
                // Clasifica la duración en el bucket correspondiente
                if ($minutos <= 30)      $bucket = '0 - 30 min';
                elseif ($minutos <= 60)  $bucket = '30 - 60 min';
                elseif ($minutos <= 120) $bucket = '1 - 2 hrs';
                elseif ($minutos <= 240) $bucket = '2 - 4 hrs';
                else                     $bucket = '> 4 hrs';
                // Incrementa el contador del bucket correspondiente
                $duraciones[$bucket] = ($duraciones[$bucket] ?? 0) + 1;
            }
            // Orden de los buckets para el gráfico
            $orden = ['0 - 30 min'=>1,'30 - 60 min'=>2,'1 - 2 hrs'=>3,'2 - 4 hrs'=>4,'> 4 hrs'=>5];
            // Ordena los buckets según el orden definido
            uksort($duraciones, fn($a,$b) => ($orden[$a]??99) <=> ($orden[$b]??99));
            // Obtiene las etiquetas (buckets) ordenadas
            $labels = array_keys($duraciones);
            // Obtiene los datos (cantidades) ordenados
            $data   = array_values($duraciones);
        } else {
            // Inicializa las etiquetas del gráfico
            $labels = [];
            // Inicializa los datos del gráfico
            $data   = [];
            // Recorre las filas devueltas por el SP para armar etiquetas y datos
            foreach ($rows as $r) {
                // Agrega el rango como etiqueta
                $labels[] = $r['rango'];
                // Agrega la cantidad como dato
                $data[]   = (int)$r['cantidad'];
            }
        }

        // Retorna las etiquetas y datos del histograma de duraciones
        return ['labels' => $labels, 'data' => $data];
    }

    // Grafico de poligono: alquileres por mes
    public function getAlquileresPorPeriodo(string $rolEjecutor, string $periodo, string $estado = '', string $cliente = '', string $qr = ''): array
    {
        // Obtiene los alquileres hasta el máximo de registros para gráficos
        $alquileres = $this->getAlquileres($rolEjecutor, $estado, $cliente, $qr, self::MAX_REGISTROS_GRAFICOS, 0);

        // Arreglo para acumular alquileres por mes (clave YYYY-MM)
        $porMes = [];

        // Recorre cada alquiler para contar por mes de inicio
        foreach ($alquileres as $a) {
            // Omite alquileres sin fecha de inicio
            if (empty($a['fecha_inicio'])) continue;
            // Genera la clave de mes (YYYY-MM) a partir de la fecha de inicio
            $mesKey = date('Y-m', strtotime($a['fecha_inicio']));
            // Inicializa el contador del mes si no existe
            if (!isset($porMes[$mesKey])) {
                $porMes[$mesKey] = 0;
            }
            // Incrementa el contador del mes
            $porMes[$mesKey]++;
        }

        // Ordena los meses cronológicamente
        ksort($porMes);

        // Obtiene las etiquetas (meses) ordenadas
        $labels = array_keys($porMes);
        // Obtiene los datos (cantidades) ordenados
        $datos  = array_values($porMes);

        // Si el periodo es HOY / SEMANA / MES, filtrar
        if ($periodo !== 'TODOS') {
            // Arreglo temporal para los meses filtrados
            $filtrados = [];
            // Recorre cada alquiler para aplicar el filtro de periodo y contar por mes
            foreach ($alquileres as $a) {
                // Omite alquileres sin fecha de inicio
                if (empty($a['fecha_inicio'])) continue;
                
                // Convierte la fecha de inicio a timestamp
                $fecha = strtotime($a['fecha_inicio']);
                // Fechas de referencia para el filtrado
                $hoy    = strtotime('today');
                $semana = strtotime('-7 days');
                $mesIni = strtotime('first day of this month');

                // Filtra por día actual si el periodo es HOY
                if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
                // Filtra por los últimos 7 días si el periodo es SEMANA
                if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
                // Filtra por el mes actual si el periodo es MES
                if ($periodo === 'MES'     && $fecha < $mesIni) continue;

                // Genera la clave de mes (YYYY-MM) a partir de la fecha filtrada
                $mesKey = date('Y-m', $fecha);
                // Inicializa el contador del mes si no existe
                if (!isset($filtrados[$mesKey])) $filtrados[$mesKey] = 0;
                // Incrementa el contador del mes
                $filtrados[$mesKey]++;
            }
            // Reemplaza las etiquetas por los meses filtrados
            $labels = array_keys($filtrados);
            // Reemplaza los datos por las cantidades filtradas
            $datos  = array_values($filtrados);
        }

        // Retorna las etiquetas (meses formateados) y los datos (cantidades)
        return [
            'labels' => array_map(fn($m) => date('F', strtotime($m . '-01')), $labels),
            'data'   => $datos,
        ];
    }

    // Grafico de sectores: alquileres por estado
    public function getAlquileresPorEstado(string $rolEjecutor, string $periodo, string $estado = '', string $cliente = '', string $qr = ''): array
    {
        // Obtiene los alquileres hasta el máximo de registros para gráficos
        $alquileres = $this->getAlquileres($rolEjecutor, $estado, $cliente, $qr, self::MAX_REGISTROS_GRAFICOS, 0);

        // Arreglo para acumular alquileres por estado
        $porEstado = [];

        // Recorre cada alquiler para contar por estado
        foreach ($alquileres as $a) {
            // Obtiene el estado del alquiler o 'DESCONOCIDO' por defecto
            $estado = $a['estado_alquiler'] ?? 'DESCONOCIDO';
            // Inicializa el contador del estado si no existe
            if (!isset($porEstado[$estado])) {
                $porEstado[$estado] = 0;
            }
            // Incrementa el contador del estado
            $porEstado[$estado]++;
        }

        // Aplicar filtro de periodo
        if ($periodo !== 'TODOS') {
            // Arreglo temporal para los estados filtrados
            $filtrados = [];
            // Recorre cada alquiler para aplicar el filtro de periodo y contar por estado
            foreach ($alquileres as $a) {
                // Omite alquileres sin fecha de inicio
                if (empty($a['fecha_inicio'])) continue;
                
                // Convierte la fecha de inicio a timestamp
                $fecha = strtotime($a['fecha_inicio']);
                // Fechas de referencia para el filtrado
                $hoy    = strtotime('today');
                $semana = strtotime('-7 days');
                $mesIni = strtotime('first day of this month');

                // Filtra por día actual si el periodo es HOY
                if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
                // Filtra por los últimos 7 días si el periodo es SEMANA
                if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
                // Filtra por el mes actual si el periodo es MES
                if ($periodo === 'MES'     && $fecha < $mesIni) continue;

                // Obtiene el estado del alquiler filtrado o 'DESCONOCIDO' por defecto
                $estado = $a['estado_alquiler'] ?? 'DESCONOCIDO';
                // Inicializa el contador del estado si no existe
                if (!isset($filtrados[$estado])) $filtrados[$estado] = 0;
                // Incrementa el contador del estado filtrado
                $filtrados[$estado]++;
            }
            // Reemplaza el arreglo por estado por los filtrados
            $porEstado = $filtrados;
        }

        // Ordenar por cantidad descendente
        arsort($porEstado);

        // Retorna las etiquetas (estados) y los datos (cantidades)
        return [
            'labels' => array_keys($porEstado),
            'data'   => array_values($porEstado),
        ];
    }

    // Grafico radial: indicadores del sistema calculados en SQL (0-100)
    // Fuente: procedimiento almacenado sp_indicadores_sistema
    public function getIndicadoresRadar(string $rolEjecutor): array
    {
        // Llama al SP de indicadores del sistema pasando el rol ejecutor
        $fila = $this->callSpSingleRow('sp_indicadores_sistema', [$rolEjecutor]);

        // Retorna las etiquetas de indicadores y sus valores (0-100)
        return [
            'labels' => [
                'Bateria Promedio (%)',
                'Disponibilidad de Flota (%)',
                'Ocupacion de Flota (%)',
                'Pagos Exitosos (%)',
                'Rotacion de Uso (%)',
            ],
            'data' => [
                (float)($fila['bateria_promedio'] ?? 0),
                (float)($fila['disponibilidad'] ?? 0),
                (float)($fila['ocupacion'] ?? 0),
                (float)($fila['pagos_exitosos'] ?? 0),
                (float)($fila['rotacion_uso'] ?? 0),
            ],
        ];
    }
}

<?php
// ============================================================
// MODELO DE DATOS DEL DASHBOARD (PDO) - CORREGIDO
// ============================================================

namespace App\Models;

use App\Database\Database;

class DashboardModel
{
    private \PDO $db;

    public function __construct()
    {
        try {
            $this->db = Database::obtenerInstancia();
        } catch (\Throwable $e) {
            error_log("DashboardModel: Error de conexión BD: " . $e->getMessage());
            throw new \RuntimeException("No se pudo conectar a la base de datos");
        }
    }

    // Ejecuta un stored procedure y retorna la primera fila (para stats)
    private function callSpSingleRow(string $sp, array $params): array
    {
        try {
            $placeholders = str_repeat('?,', count($params) - 1) . '?';
            $stmt = $this->db->prepare("CALL $sp($placeholders)");
            $stmt->execute($params);
            $row = $stmt->fetch();
            while ($stmt->nextRowset()) {
                $stmt->fetchAll();
            }
            $stmt->closeCursor();
            return $row ?: [];
        } catch (\Throwable $e) {
            error_log("callSpSingleRow [$sp] error: " . $e->getMessage());
            throw new \RuntimeException("Error ejecutando procedimiento: $sp");
        }
    }

    // Ejecuta un stored procedure y retorna todas las filas (segundo result set para sp_alquiler_buscar_paginado)
    private function callSpAllRows(string $sp, array $params): array
    {
        try {
            $placeholders = str_repeat('?,', count($params) - 1) . '?';
            $stmt = $this->db->prepare("CALL $sp($placeholders)");
            $stmt->execute($params);

            $firstRows = $stmt->fetchAll();
            $dataRows = [];

            if ($stmt->nextRowset()) {
                $dataRows = $stmt->fetchAll();
            }

            while ($stmt->nextRowset()) {
                $stmt->fetchAll();
            }
            $stmt->closeCursor();

            return $dataRows ?: $firstRows;
        } catch (\Throwable $e) {
            error_log("callSpAllRows [$sp] error: " . $e->getMessage());
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
        $fila = $this->callSpSingleRow('sp_reporte_estadisticas_admin', [$rolEjecutor]);

        $stats = [
            'total_bicicletas'      => (int)($fila['total_bicicletas'] ?? 0),
            'bicicletas_disponibles'=> (int)($fila['bicicletas_disponibles'] ?? 0),
            'alquileres_activos'    => (int)($fila['alquileres_activos'] ?? 0),
            'total_usuarios'        => (int)($fila['total_usuarios'] ?? 0),
            'total_alquileres'      => (int)($fila['total_alquileres'] ?? 0),
            'ingresos_totales'      => (float)($fila['ingresos_totales'] ?? 0),
        ];

        // Sin filtros: devolver las estadisticas globales del SP
        $filtraPeriodo = ($periodo !== 'TODOS' && $periodo !== '');
        if ($estado === '' && $cliente === '' && $qr === '' && !$filtraPeriodo) {
            return $stats;
        }

        // Con filtros: recalcular las metricas dependientes de alquileres
        // usando el mismo conjunto filtrado que los graficos
        $alquileres = $this->getAlquileres($rolEjecutor, $estado, $cliente, $qr, self::MAX_REGISTROS_GRAFICOS, 0);

        $total = 0;
        $ingresos = 0.0;
        $activos = 0;
        $hoy    = strtotime('today');
        $semana = strtotime('-7 days');
        $mesIni = strtotime('first day of this month');

        foreach ($alquileres as $a) {
            if ($filtraPeriodo) {
                if (empty($a['fecha_inicio'])) continue;
                $fecha = strtotime($a['fecha_inicio']);
                if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
                if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
                if ($periodo === 'MES'     && $fecha < $mesIni) continue;
            }
            $total++;
            $est = strtoupper((string)($a['estado_alquiler'] ?? ''));
            if ($est === 'ACTIVO') $activos++;
            // Mismo criterio que el SP global: ingresos de alquileres FINALIZADOS
            if ($est === 'FINALIZADO') $ingresos += (float)($a['costo_total'] ?? 0);
        }

        $stats['total_alquileres']   = $total;
        $stats['ingresos_totales']   = $ingresos;
        $stats['alquileres_activos'] = $activos;

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
        $alquileres = $this->callSpAllRows('sp_alquiler_buscar_paginado', [
            $limite, $offset, $estado, $cliente, $qr, $rolEjecutor
        ]);

        if ($periodo === 'TODOS' || $periodo === '') {
            return $alquileres;
        }

        $hoy    = strtotime('today');
        $semana = strtotime('-7 days');
        $mesIni = strtotime('first day of this month');
        $filtrados = [];
        foreach ($alquileres as $a) {
            if (empty($a['fecha_inicio'])) continue;
            $fecha = strtotime($a['fecha_inicio']);
            if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
            if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
            if ($periodo === 'MES'     && $fecha < $mesIni) continue;
            $filtrados[] = $a;
        }
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
        $alquileres = $this->getAlquileres($rolEjecutor, $estado, $cliente, $qr, self::MAX_REGISTROS_GRAFICOS, 0);

        // Filtrar por periodo si aplica
        if ($periodo !== 'TODOS' && $periodo !== '') {
            $filtrados = [];
            foreach ($alquileres as $a) {
                if (empty($a['fecha_inicio'])) continue;
                $fecha = strtotime($a['fecha_inicio']);
                $hoy    = strtotime('today');
                $semana = strtotime('-7 days');
                $mesIni = strtotime('first day of this month');
                if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
                if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
                if ($periodo === 'MES'     && $fecha < $mesIni) continue;
                $filtrados[] = $a;
            }
            $alquileres = $filtrados;
        }

        $ingresos = [];
        foreach ($alquileres as $a) {
            $nombre = $a['estacion_origen'] ?? 'Otra';
            if (!isset($ingresos[$nombre])) {
                $ingresos[$nombre] = 0;
            }
            $ingresos[$nombre] += (float)($a['costo_total'] ?? 0);
        }

        // Ordenar por ingreso descendente
        arsort($ingresos);

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
            $alquileres = $this->getAlquileres($rolEjecutor, $estado, $cliente, $qr, self::MAX_REGISTROS_GRAFICOS, 0);
            $filtrados = [];
            foreach ($alquileres as $a) {
                if (empty($a['fecha_inicio']) || empty($a['fecha_fin'])) continue;
                $fecha = strtotime($a['fecha_inicio']);
                $hoy    = strtotime('today');
                $semana = strtotime('-7 days');
                $mesIni = strtotime('first day of this month');
                if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
                if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
                if ($periodo === 'MES'     && $fecha < $mesIni) continue;
                $filtrados[] = $a;
            }
            // Re-calcular buckets
            $duraciones = [];
            foreach ($filtrados as $a) {
                $inicio = strtotime($a['fecha_inicio']);
                $fin    = strtotime($a['fecha_fin']);
                $minutos = (int)round(($fin - $inicio) / 60);
                if ($minutos <= 30)      $bucket = '0 - 30 min';
                elseif ($minutos <= 60)  $bucket = '30 - 60 min';
                elseif ($minutos <= 120) $bucket = '1 - 2 hrs';
                elseif ($minutos <= 240) $bucket = '2 - 4 hrs';
                else                     $bucket = '> 4 hrs';
                $duraciones[$bucket] = ($duraciones[$bucket] ?? 0) + 1;
            }
            $orden = ['0 - 30 min'=>1,'30 - 60 min'=>2,'1 - 2 hrs'=>3,'2 - 4 hrs'=>4,'> 4 hrs'=>5];
            uksort($duraciones, fn($a,$b) => ($orden[$a]??99) <=> ($orden[$b]??99));
            $labels = array_keys($duraciones);
            $data   = array_values($duraciones);
        } else {
            $labels = [];
            $data   = [];
            foreach ($rows as $r) {
                $labels[] = $r['rango'];
                $data[]   = (int)$r['cantidad'];
            }
        }

        return ['labels' => $labels, 'data' => $data];
    }

    // Grafico de poligono: alquileres por mes
    public function getAlquileresPorPeriodo(string $rolEjecutor, string $periodo, string $estado = '', string $cliente = '', string $qr = ''): array
    {
        $alquileres = $this->getAlquileres($rolEjecutor, $estado, $cliente, $qr, self::MAX_REGISTROS_GRAFICOS, 0);

        $porMes = [];

        foreach ($alquileres as $a) {
            if (empty($a['fecha_inicio'])) continue;
            $mesKey = date('Y-m', strtotime($a['fecha_inicio']));
            if (!isset($porMes[$mesKey])) {
                $porMes[$mesKey] = 0;
            }
            $porMes[$mesKey]++;
        }

        ksort($porMes);

        $labels = array_keys($porMes);
        $datos  = array_values($porMes);

        // Si el periodo es HOY / SEMANA / MES, filtrar
        if ($periodo !== 'TODOS') {
            $filtrados = [];
            foreach ($alquileres as $a) {
                if (empty($a['fecha_inicio'])) continue;
                
                $fecha = strtotime($a['fecha_inicio']);
                $hoy    = strtotime('today');
                $semana = strtotime('-7 days');
                $mesIni = strtotime('first day of this month');

                if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
                if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
                if ($periodo === 'MES'     && $fecha < $mesIni) continue;

                $mesKey = date('Y-m', $fecha);
                if (!isset($filtrados[$mesKey])) $filtrados[$mesKey] = 0;
                $filtrados[$mesKey]++;
            }
            $labels = array_keys($filtrados);
            $datos  = array_values($filtrados);
        }

        return [
            'labels' => array_map(fn($m) => date('F', strtotime($m . '-01')), $labels),
            'data'   => $datos,
        ];
    }

    // Grafico de sectores: alquileres por estado
    public function getAlquileresPorEstado(string $rolEjecutor, string $periodo, string $estado = '', string $cliente = '', string $qr = ''): array
    {
        $alquileres = $this->getAlquileres($rolEjecutor, $estado, $cliente, $qr, self::MAX_REGISTROS_GRAFICOS, 0);

        $porEstado = [];

        foreach ($alquileres as $a) {
            $estado = $a['estado_alquiler'] ?? 'DESCONOCIDO';
            if (!isset($porEstado[$estado])) {
                $porEstado[$estado] = 0;
            }
            $porEstado[$estado]++;
        }

        // Aplicar filtro de periodo
        if ($periodo !== 'TODOS') {
            $filtrados = [];
            foreach ($alquileres as $a) {
                if (empty($a['fecha_inicio'])) continue;
                
                $fecha = strtotime($a['fecha_inicio']);
                $hoy    = strtotime('today');
                $semana = strtotime('-7 days');
                $mesIni = strtotime('first day of this month');

                if ($periodo === 'HOY'     && date('Y-m-d', $fecha) !== date('Y-m-d', $hoy)) continue;
                if ($periodo === 'SEMANA'  && $fecha < $semana) continue;
                if ($periodo === 'MES'     && $fecha < $mesIni) continue;

                $estado = $a['estado_alquiler'] ?? 'DESCONOCIDO';
                if (!isset($filtrados[$estado])) $filtrados[$estado] = 0;
                $filtrados[$estado]++;
            }
            $porEstado = $filtrados;
        }

        // Ordenar por cantidad descendente
        arsort($porEstado);

        return [
            'labels' => array_keys($porEstado),
            'data'   => array_values($porEstado),
        ];
    }

    // Grafico radial: indicadores del sistema calculados en SQL (0-100)
    // Fuente: procedimiento almacenado sp_indicadores_sistema
    public function getIndicadoresRadar(string $rolEjecutor): array
    {
        $fila = $this->callSpSingleRow('sp_indicadores_sistema', [$rolEjecutor]);

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
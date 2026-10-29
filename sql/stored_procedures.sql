-- =====================================================================
-- PROCEDIMIENTOS ALMACENADOS - EBike Management
-- Ejecutar DESPUÉS de crear las tablas y insertar datos
-- Tablas requeridas: roles, usuarios, alquileres, bicicletas, estaciones, transacciones, metodos_pago
-- =====================================================================
-- Selecciona la base de datos de trabajo para que todos los objetos se creen en ella
USE ebike_management;

-- Cambia el delimitador de fin de sentencia a $$ para permitir bloques con punto y coma interno
DELIMITER $$

-- ---------------------------------------------------------------
-- 1. VALIDACIÓN DE ROLES
-- Procedimiento auxiliar que verifica si el rol ejecutor está dentro de la lista de roles permitidos
-- ---------------------------------------------------------------
-- Elimina el procedimiento si ya existe para permitir recrearlo sin errores
DROP PROCEDURE IF EXISTS sp_validar_rol$$
-- Define el procedimiento que recibe el rol ejecutor y la lista de roles permitidos (separados por coma)
CREATE PROCEDURE sp_validar_rol(
    IN p_rol_ejecutor VARCHAR(30), -- Rol del usuario que intenta ejecutar la operación
    IN p_roles_permitidos VARCHAR(255) -- Lista de roles autorizados, separados por coma
)
BEGIN
    -- Si el rol ejecutor no está en la lista permitida, se lanza un error de acceso denegado
    IF FIND_IN_SET(p_rol_ejecutor, p_roles_permitidos) = 0 THEN
        -- Señal SQLSTATE 45000 con mensaje de error para abortar la operación
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Acceso denegado: el rol no tiene permiso para esta operacion';
    END IF;
END$$

-- ---------------------------------------------------------------
-- 2. REGISTRAR ALQUILER NUEVO
-- Inserta un nuevo alquiler en estado ACTIVO y devuelve su ID generado
-- ---------------------------------------------------------------
-- Elimina el procedimiento si ya existe para permitir recrearlo sin errores
DROP PROCEDURE IF EXISTS sp_registrar_alquiler$$
-- Define el procedimiento con parámetros de entrada de usuario, bicicleta, estación y costo, y salida del ID
CREATE PROCEDURE sp_registrar_alquiler(
    IN p_id_usuario BIGINT UNSIGNED, -- Identificador del usuario que alquila
    IN p_id_bicicleta BIGINT UNSIGNED, -- Identificador de la bicicleta alquilada
    IN p_estacion_orig INT UNSIGNED, -- Estación de origen del alquiler
    IN p_costo_base DECIMAL(12,2), -- Costo base del alquiler
    OUT p_id_alquiler BIGINT UNSIGNED -- ID del alquiler recién creado (salida)
)
BEGIN
    -- Declara manejador de excepciones: ante cualquier error hace rollback y relanza el error
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    -- Inicia transacción para asegurar atomicidad del insert
    START TRANSACTION;
        -- Inserta el registro del alquiler con estado ACTIVO y UUID generado automáticamente
        INSERT INTO alquileres (uuid_alquiler, id_usuario, id_bicicleta, estacion_orig, costo_base, estado)
        VALUES (UUID(), p_id_usuario, p_id_bicicleta, p_estacion_orig, p_costo_base, 'ACTIVO');
        -- Obtiene el ID autoincremental generado por el insert y lo asigna al parámetro de salida
        SET p_id_alquiler = LAST_INSERT_ID();
    -- Confirma la transacción
    COMMIT;
END$$

-- ---------------------------------------------------------------
-- 3. FINALIZAR ALQUILER + CREAR TRANSACCIÓN
-- Marca un alquiler como FINALIZADO y, si se indica método de pago, crea la transacción correspondiente
-- ---------------------------------------------------------------
-- Elimina el procedimiento si ya existe para permitir recrearlo sin errores
DROP PROCEDURE IF EXISTS sp_finalizar_alquiler$$
-- Define el procedimiento con ID de alquiler, estación destino, método de pago y referencia de pago
CREATE PROCEDURE sp_finalizar_alquiler(
    IN p_id_alquiler BIGINT UNSIGNED, -- ID del alquiler a finalizar
    IN p_estacion_dest INT UNSIGNED, -- Estación de destino donde se devuelve la bicicleta
    IN p_id_metodo_pago BIGINT UNSIGNED, -- Método de pago (NULL si no se cobra en este paso)
    IN p_referencia_pago VARCHAR(150) -- Referencia externa del pago
)
BEGIN
    -- Variable local para almacenar el monto total del alquiler
    DECLARE v_monto DECIMAL(12,2);
    -- Declara manejador de excepciones: ante cualquier error hace rollback y relanza el error
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    -- Inicia transacción para asegurar atomicidad de la finalización y el pago
    START TRANSACTION;
        -- Actualiza el alquiler: registra estación destino, fecha fin y estado FINALIZADO
        UPDATE alquileres
        SET estacion_dest = p_estacion_dest, fecha_fin = NOW(), estado = 'FINALIZADO'
        WHERE id_alquiler = p_id_alquiler;

        -- Si se proporcionó un método de pago, se crea la transacción asociada
        IF p_id_metodo_pago IS NOT NULL THEN
            -- Obtiene el costo total calculado del alquiler para usarlo como monto
            SELECT costo_total INTO v_monto FROM alquileres WHERE id_alquiler = p_id_alquiler;
            -- Inserta la transacción con estado APROBADO y fecha de proceso actual
            INSERT INTO transacciones (id_alquiler, id_metodo_pago, monto, estado_pago, referencia_ext, fecha_proceso)
            VALUES (p_id_alquiler, p_id_metodo_pago, v_monto, 'APROBADO', p_referencia_pago, NOW());
        END IF;
    -- Confirma la transacción
    COMMIT;
END$$

-- ---------------------------------------------------------------
-- 4. BUSCAR ALQUILERES PAGINADO (CON FILTROS) - PRINCIPAL PARA GRÁFICAS
-- Devuelve conteo total y datos paginados de alquileres con filtros opcionales
-- ---------------------------------------------------------------
-- Elimina el procedimiento si ya existe para permitir recrearlo sin errores
DROP PROCEDURE IF EXISTS sp_alquiler_buscar_paginado$$
-- Define el procedimiento con límites de paginación, filtros y rol ejecutor
CREATE PROCEDURE sp_alquiler_buscar_paginado(
    IN p_limite INT UNSIGNED, -- Cantidad máxima de registros a devolver
    IN p_offset INT UNSIGNED, -- Desplazamiento para paginación
    IN p_estado VARCHAR(30), -- Filtro por estado del alquiler (vacío o NULL = todos)
    IN p_cliente_nombre VARCHAR(150), -- Filtro por nombre del cliente (búsqueda parcial)
    IN p_bicicleta_qr VARCHAR(150), -- Filtro por código QR de bicicleta (búsqueda parcial)
    IN p_rol_ejecutor VARCHAR(30) -- Rol del usuario que ejecuta la consulta
)
BEGIN
    -- Valida que el rol ejecutor tenga permiso ADMIN o SUPERVISOR
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    -- Result set 1: conteo total de registros que cumplen los filtros
    SELECT COUNT(*) AS total
    FROM alquileres a
    JOIN usuarios u ON u.id_usuario = a.id_usuario -- Relaciona alquiler con su usuario
    JOIN bicicletas b ON b.id_bicicleta = a.id_bicicleta -- Relaciona alquiler con su bicicleta
    JOIN estaciones eo ON eo.id_estacion = a.estacion_orig -- Estación de origen
    LEFT JOIN estaciones ed ON ed.id_estacion = a.estacion_dest -- Estación de destino (puede ser NULL)
    WHERE (p_estado IS NULL OR p_estado = '' OR a.estado = p_estado) -- Filtro de estado opcional
      AND (p_cliente_nombre IS NULL OR p_cliente_nombre = '' OR u.nombre LIKE CONCAT('%', p_cliente_nombre, '%')) -- Filtro de cliente opcional
      AND (p_bicicleta_qr IS NULL OR p_bicicleta_qr = '' OR b.codigo_qr LIKE CONCAT('%', p_bicicleta_qr, '%')); -- Filtro de QR opcional

    -- Result set 2: datos paginados con los mismos filtros
    SELECT 
        a.id_alquiler, a.uuid_alquiler, a.fecha_inicio, a.fecha_fin,
        a.costo_total, a.estado AS estado_alquiler,
        u.nombre AS cliente, b.codigo_qr, b.nivel_bateria,
        eo.nombre AS estacion_origen, ed.nombre AS estacion_destino
    FROM alquileres a
    JOIN usuarios u ON u.id_usuario = a.id_usuario -- Relaciona alquiler con su usuario
    JOIN bicicletas b ON b.id_bicicleta = a.id_bicicleta -- Relaciona alquiler con su bicicleta
    JOIN estaciones eo ON eo.id_estacion = a.estacion_orig -- Estación de origen
    LEFT JOIN estaciones ed ON ed.id_estacion = a.estacion_dest -- Estación de destino (puede ser NULL)
    WHERE (p_estado IS NULL OR p_estado = '' OR a.estado = p_estado) -- Filtro de estado opcional
      AND (p_cliente_nombre IS NULL OR p_cliente_nombre = '' OR u.nombre LIKE CONCAT('%', p_cliente_nombre, '%')) -- Filtro de cliente opcional
      AND (p_bicicleta_qr IS NULL OR p_bicicleta_qr = '' OR b.codigo_qr LIKE CONCAT('%', p_bicicleta_qr, '%')) -- Filtro de QR opcional
    ORDER BY a.fecha_inicio DESC -- Ordena por fecha de inicio descendente
    LIMIT p_limite OFFSET p_offset; -- Aplica paginación
END$$

-- ---------------------------------------------------------------
-- 5. REPORTE ESTADÍSTICAS ADMIN (PARA RADAR + RESUMEN EJECUTIVO)
-- Devuelve métricas agregadas de la flota, alquileres y usuarios
-- ---------------------------------------------------------------
-- Elimina el procedimiento si ya existe para permitir recrearlo sin errores
DROP PROCEDURE IF EXISTS sp_reporte_estadisticas_admin$$
-- Define el procedimiento que recibe el rol ejecutor
CREATE PROCEDURE sp_reporte_estadisticas_admin(IN p_rol_ejecutor VARCHAR(30))
BEGIN
    -- Valida que el rol ejecutor tenga permiso ADMIN o SUPERVISOR
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    -- Selecciona métricas agregadas en una sola fila
    SELECT
        (SELECT COUNT(*) FROM bicicletas) AS total_bicicletas, -- Total de bicicletas registradas
        (SELECT COUNT(*) FROM bicicletas WHERE estado = 'DISPONIBLE') AS bicicletas_disponibles, -- Bicicletas disponibles
        (SELECT COUNT(*) FROM alquileres WHERE estado = 'ACTIVO') AS alquileres_activos, -- Alquileres en curso
        (SELECT COUNT(*) FROM usuarios) AS total_usuarios, -- Total de usuarios
        (SELECT COUNT(*) FROM alquileres) AS total_alquileres, -- Total de alquileres históricos
        (SELECT COALESCE(SUM(costo_total),0) FROM alquileres WHERE estado = 'FINALIZADO') AS ingresos_totales; -- Ingresos de alquileres finalizados
END$$

-- ---------------------------------------------------------------
-- 7. HISTOGRAMA DE DURACIONES (NUEVO - DEDICADO)
-- Agrupa alquileres finalizados por rango de duración en minutos
-- ---------------------------------------------------------------
-- Elimina el procedimiento si ya existe para permitir recrearlo sin errores
DROP PROCEDURE IF EXISTS sp_histograma_duraciones$$
-- Define el procedimiento con filtros y rol ejecutor
CREATE PROCEDURE sp_histograma_duraciones(
    IN p_estado VARCHAR(30), -- Filtro por estado del alquiler (vacío o NULL = todos)
    IN p_cliente_nombre VARCHAR(150), -- Filtro por nombre del cliente (búsqueda parcial)
    IN p_bicicleta_qr VARCHAR(150), -- Filtro por código QR de bicicleta (búsqueda parcial)
    IN p_rol_ejecutor VARCHAR(30) -- Rol del usuario que ejecuta la consulta
)
BEGIN
    -- Valida que el rol ejecutor tenga permiso ADMIN o SUPERVISOR
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    -- Selecciona rango de duración y cantidad de alquileres por rango
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(MINUTE, a.fecha_inicio, a.fecha_fin) <= 30   THEN '0 - 30 min' -- Hasta 30 minutos
            WHEN TIMESTAMPDIFF(MINUTE, a.fecha_inicio, a.fecha_fin) <= 60   THEN '30 - 60 min' -- Entre 31 y 60 minutos
            WHEN TIMESTAMPDIFF(MINUTE, a.fecha_inicio, a.fecha_fin) <= 120  THEN '1 - 2 hrs' -- Entre 61 y 120 minutos
            WHEN TIMESTAMPDIFF(MINUTE, a.fecha_inicio, a.fecha_fin) <= 240   THEN '2 - 4 hrs' -- Entre 121 y 240 minutos
            ELSE '> 4 hrs' -- Más de 240 minutos
        END AS rango,
        COUNT(*) AS cantidad
    FROM alquileres a
    JOIN usuarios u ON u.id_usuario = a.id_usuario -- Relaciona alquiler con su usuario
    JOIN bicicletas b ON b.id_bicicleta = a.id_bicicleta -- Relaciona alquiler con su bicicleta
    JOIN estaciones eo ON eo.id_estacion = a.estacion_orig -- Estación de origen
    LEFT JOIN estaciones ed ON ed.id_estacion = a.estacion_dest -- Estación de destino (puede ser NULL)
    WHERE a.fecha_inicio IS NOT NULL  -- Solo alquileres con fecha de inicio
      AND a.fecha_fin IS NOT NULL  -- Solo alquileres con fecha de fin
      AND (p_estado IS NULL OR p_estado = '' OR a.estado = p_estado) -- Filtro de estado opcional
      AND (p_cliente_nombre IS NULL OR p_cliente_nombre = '' OR u.nombre LIKE CONCAT('%', p_cliente_nombre, '%')) -- Filtro de cliente opcional
      AND (p_bicicleta_qr IS NULL OR p_bicicleta_qr = '' OR b.codigo_qr LIKE CONCAT('%', p_bicicleta_qr, '%')) -- Filtro de QR opcional
    GROUP BY rango -- Agrupa por rango de duración
    ORDER BY 
        CASE rango
            WHEN '0 - 30 min' THEN 1 -- Orden del primer rango
            WHEN '30 - 60 min' THEN 2 -- Orden del segundo rango
            WHEN '1 - 2 hrs' THEN 3 -- Orden del tercer rango
            WHEN '2 - 4 hrs' THEN 4 -- Orden del cuarto rango
            ELSE 5 -- Orden del último rango
        END;
END$$

-- ---------------------------------------------------------------
-- 8. INDICADORES DEL SISTEMA (RADAR) - salud de la flota
--    Todos los valores se calculan en SQL y se expresan en 0-100
-- Devuelve indicadores clave de salud de la flota en porcentajes
-- ---------------------------------------------------------------
-- Elimina el procedimiento si ya existe para permitir recrearlo sin errores
DROP PROCEDURE IF EXISTS sp_indicadores_sistema$$
-- Define el procedimiento que recibe el rol ejecutor
CREATE PROCEDURE sp_indicadores_sistema(IN p_rol_ejecutor VARCHAR(30))
BEGIN
    -- Valida que el rol ejecutor tenga permiso ADMIN o SUPERVISOR
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    -- Selecciona los indicadores calculados en SQL, todos expresados en 0-100
    SELECT
        -- 1) Bateria promedio de la flota (%)
        ROUND((SELECT COALESCE(AVG(nivel_bateria), 0) FROM bicicletas), 1) AS bateria_promedio, -- Promedio de batería de todas las bicicletas
        -- 2) Disponibilidad de flota (%)
        ROUND((SELECT COUNT(*) FROM bicicletas WHERE estado = 'DISPONIBLE') * 100.0
            / GREATEST((SELECT COUNT(*) FROM bicicletas), 1), 1) AS disponibilidad, -- Porcentaje de bicicletas disponibles
        -- 3) Ocupacion de flota (%): bicicletas alquiladas ahora mismo
        ROUND((SELECT COUNT(*) FROM bicicletas WHERE estado = 'ALQUILADA') * 100.0
            / GREATEST((SELECT COUNT(*) FROM bicicletas), 1), 1) AS ocupacion, -- Porcentaje de bicicletas alquiladas
        -- 4) Pagos exitosos (%): finalizados vs finalizados + pendientes de pago
        ROUND((SELECT COUNT(*) FROM alquileres WHERE estado = 'FINALIZADO') * 100.0
            / GREATEST((SELECT COUNT(*) FROM alquileres
                         WHERE estado IN ('FINALIZADO', 'PENDIENTE_PAGO')), 1), 1) AS pagos_exitosos, -- Porcentaje de pagos exitosos
        -- 5) Rotacion de uso (%): bicicletas que tuvieron al menos un alquiler
        ROUND((SELECT COUNT(DISTINCT id_bicicleta) FROM alquileres) * 100.0
            / GREATEST((SELECT COUNT(*) FROM bicicletas), 1), 1) AS rotacion_uso; -- Porcentaje de bicicletas con al menos un alquiler
END$$

-- Restaura el delimitador estándar de sentencias
DELIMITER ;

-- =====================================================================
-- EJEMPLOS DE USO / PRUEBAS RÁPIDAS
-- =====================================================================

-- Ver estadísticas (rol ADMIN)
-- CALL sp_reporte_estadisticas_admin('ADMIN');

-- Buscar alquileres (primeros 10, todos los estados)
-- CALL sp_alquiler_buscar_paginado(10, 0, '', '', '', 'ADMIN');

-- Ver histograma de duraciones
-- CALL sp_histograma_duraciones('', '', '', 'ADMIN');

-- Registrar alquiler de prueba
-- SET @nuevo_id = 0;
-- CALL sp_registrar_alquiler(7, 1, 1, 15.00, @nuevo_id);
-- SELECT @nuevo_id;

-- Finalizar alquiler (cambia el ID por uno real)
-- CALL sp_finalizar_alquiler(1, 2, 1, 'TEST-REF-001');
-- =====================================================================
-- PROCEDIMIENTOS ALMACENADOS - EBike Management
-- Ejecutar DESPUÉS de crear las tablas y insertar datos
-- Tablas requeridas: roles, usuarios, alquileres, bicicletas, estaciones, transacciones, metodos_pago
-- =====================================================================

USE ebike_management;

DELIMITER $$

-- ---------------------------------------------------------------
-- 1. VALIDACIÓN DE ROLES
-- ---------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_validar_rol$$
CREATE PROCEDURE sp_validar_rol(
    IN p_rol_ejecutor VARCHAR(30),
    IN p_roles_permitidos VARCHAR(255)
)
BEGIN
    IF FIND_IN_SET(p_rol_ejecutor, p_roles_permitidos) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Acceso denegado: el rol no tiene permiso para esta operacion';
    END IF;
END$$

-- ---------------------------------------------------------------
-- 2. REGISTRAR ALQUILER NUEVO
-- ---------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_registrar_alquiler$$
CREATE PROCEDURE sp_registrar_alquiler(
    IN p_id_usuario BIGINT UNSIGNED,
    IN p_id_bicicleta BIGINT UNSIGNED,
    IN p_estacion_orig INT UNSIGNED,
    IN p_costo_base DECIMAL(12,2),
    OUT p_id_alquiler BIGINT UNSIGNED
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    START TRANSACTION;
        INSERT INTO alquileres (uuid_alquiler, id_usuario, id_bicicleta, estacion_orig, costo_base, estado)
        VALUES (UUID(), p_id_usuario, p_id_bicicleta, p_estacion_orig, p_costo_base, 'ACTIVO');
        SET p_id_alquiler = LAST_INSERT_ID();
    COMMIT;
END$$

-- ---------------------------------------------------------------
-- 3. FINALIZAR ALQUILER + CREAR TRANSACCIÓN
-- ---------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_finalizar_alquiler$$
CREATE PROCEDURE sp_finalizar_alquiler(
    IN p_id_alquiler BIGINT UNSIGNED,
    IN p_estacion_dest INT UNSIGNED,
    IN p_id_metodo_pago BIGINT UNSIGNED,
    IN p_referencia_pago VARCHAR(150)
)
BEGIN
    DECLARE v_monto DECIMAL(12,2);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    START TRANSACTION;
        UPDATE alquileres
        SET estacion_dest = p_estacion_dest, fecha_fin = NOW(), estado = 'FINALIZADO'
        WHERE id_alquiler = p_id_alquiler;

        IF p_id_metodo_pago IS NOT NULL THEN
            SELECT costo_total INTO v_monto FROM alquileres WHERE id_alquiler = p_id_alquiler;
            INSERT INTO transacciones (id_alquiler, id_metodo_pago, monto, estado_pago, referencia_ext, fecha_proceso)
            VALUES (p_id_alquiler, p_id_metodo_pago, v_monto, 'APROBADO', p_referencia_pago, NOW());
        END IF;
    COMMIT;
END$$

-- ---------------------------------------------------------------
-- 4. BUSCAR ALQUILERES PAGINADO (CON FILTROS) - PRINCIPAL PARA GRÁFICAS
-- ---------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_alquiler_buscar_paginado$$
CREATE PROCEDURE sp_alquiler_buscar_paginado(
    IN p_limite INT UNSIGNED,
    IN p_offset INT UNSIGNED,
    IN p_estado VARCHAR(30),
    IN p_cliente_nombre VARCHAR(150),
    IN p_bicicleta_qr VARCHAR(150),
    IN p_rol_ejecutor VARCHAR(30)
)
BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    -- Result set 1: conteo total
    SELECT COUNT(*) AS total
    FROM alquileres a
    JOIN usuarios u ON u.id_usuario = a.id_usuario
    JOIN bicicletas b ON b.id_bicicleta = a.id_bicicleta
    JOIN estaciones eo ON eo.id_estacion = a.estacion_orig
    LEFT JOIN estaciones ed ON ed.id_estacion = a.estacion_dest
    WHERE (p_estado IS NULL OR p_estado = '' OR a.estado = p_estado)
      AND (p_cliente_nombre IS NULL OR p_cliente_nombre = '' OR u.nombre LIKE CONCAT('%', p_cliente_nombre, '%'))
      AND (p_bicicleta_qr IS NULL OR p_bicicleta_qr = '' OR b.codigo_qr LIKE CONCAT('%', p_bicicleta_qr, '%'));

    -- Result set 2: datos paginados
    SELECT 
        a.id_alquiler, a.uuid_alquiler, a.fecha_inicio, a.fecha_fin,
        a.costo_total, a.estado AS estado_alquiler,
        u.nombre AS cliente, b.codigo_qr, b.nivel_bateria,
        eo.nombre AS estacion_origen, ed.nombre AS estacion_destino
    FROM alquileres a
    JOIN usuarios u ON u.id_usuario = a.id_usuario
    JOIN bicicletas b ON b.id_bicicleta = a.id_bicicleta
    JOIN estaciones eo ON eo.id_estacion = a.estacion_orig
    LEFT JOIN estaciones ed ON ed.id_estacion = a.estacion_dest
    WHERE (p_estado IS NULL OR p_estado = '' OR a.estado = p_estado)
      AND (p_cliente_nombre IS NULL OR p_cliente_nombre = '' OR u.nombre LIKE CONCAT('%', p_cliente_nombre, '%'))
      AND (p_bicicleta_qr IS NULL OR p_bicicleta_qr = '' OR b.codigo_qr LIKE CONCAT('%', p_bicicleta_qr, '%'))
    ORDER BY a.fecha_inicio DESC
    LIMIT p_limite OFFSET p_offset;
END$$

-- ---------------------------------------------------------------
-- 5. REPORTE ESTADÍSTICAS ADMIN (PARA RADAR + RESUMEN EJECUTIVO)
-- ---------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_reporte_estadisticas_admin$$
CREATE PROCEDURE sp_reporte_estadisticas_admin(IN p_rol_ejecutor VARCHAR(30))
BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    SELECT
        (SELECT COUNT(*) FROM bicicletas) AS total_bicicletas,
        (SELECT COUNT(*) FROM bicicletas WHERE estado = 'DISPONIBLE') AS bicicletas_disponibles,
        (SELECT COUNT(*) FROM alquileres WHERE estado = 'ACTIVO') AS alquileres_activos,
        (SELECT COUNT(*) FROM usuarios) AS total_usuarios,
        (SELECT COUNT(*) FROM alquileres) AS total_alquileres,
        (SELECT COALESCE(SUM(costo_total),0) FROM alquileres WHERE estado = 'FINALIZADO') AS ingresos_totales;
END$$

-- ---------------------------------------------------------------
-- 7. HISTOGRAMA DE DURACIONES (NUEVO - DEDICADO)
-- ---------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_histograma_duraciones$$
CREATE PROCEDURE sp_histograma_duraciones(
    IN p_estado VARCHAR(30),
    IN p_cliente_nombre VARCHAR(150),
    IN p_bicicleta_qr VARCHAR(150),
    IN p_rol_ejecutor VARCHAR(30)
)
BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(MINUTE, a.fecha_inicio, a.fecha_fin) <= 30   THEN '0 - 30 min'
            WHEN TIMESTAMPDIFF(MINUTE, a.fecha_inicio, a.fecha_fin) <= 60   THEN '30 - 60 min'
            WHEN TIMESTAMPDIFF(MINUTE, a.fecha_inicio, a.fecha_fin) <= 120  THEN '1 - 2 hrs'
            WHEN TIMESTAMPDIFF(MINUTE, a.fecha_inicio, a.fecha_fin) <= 240   THEN '2 - 4 hrs'
            ELSE '> 4 hrs'
        END AS rango,
        COUNT(*) AS cantidad
    FROM alquileres a
    JOIN usuarios u ON u.id_usuario = a.id_usuario
    JOIN bicicletas b ON b.id_bicicleta = a.id_bicicleta
    JOIN estaciones eo ON eo.id_estacion = a.estacion_orig
    LEFT JOIN estaciones ed ON ed.id_estacion = a.estacion_dest
    WHERE a.fecha_inicio IS NOT NULL 
      AND a.fecha_fin IS NOT NULL 
      AND (p_estado IS NULL OR p_estado = '' OR a.estado = p_estado)
      AND (p_cliente_nombre IS NULL OR p_cliente_nombre = '' OR u.nombre LIKE CONCAT('%', p_cliente_nombre, '%'))
      AND (p_bicicleta_qr IS NULL OR p_bicicleta_qr = '' OR b.codigo_qr LIKE CONCAT('%', p_bicicleta_qr, '%'))
    GROUP BY rango
    ORDER BY 
        CASE rango
            WHEN '0 - 30 min' THEN 1
            WHEN '30 - 60 min' THEN 2
            WHEN '1 - 2 hrs' THEN 3
            WHEN '2 - 4 hrs' THEN 4
            ELSE 5
        END;
END$$

-- ---------------------------------------------------------------
-- 8. INDICADORES DEL SISTEMA (RADAR) - salud de la flota
--    Todos los valores se calculan en SQL y se expresan en 0-100
-- ---------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_indicadores_sistema$$
CREATE PROCEDURE sp_indicadores_sistema(IN p_rol_ejecutor VARCHAR(30))
BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    SELECT
        -- 1) Bateria promedio de la flota (%)
        ROUND((SELECT COALESCE(AVG(nivel_bateria), 0) FROM bicicletas), 1) AS bateria_promedio,
        -- 2) Disponibilidad de flota (%)
        ROUND((SELECT COUNT(*) FROM bicicletas WHERE estado = 'DISPONIBLE') * 100.0
            / GREATEST((SELECT COUNT(*) FROM bicicletas), 1), 1) AS disponibilidad,
        -- 3) Ocupacion de flota (%): bicicletas alquiladas ahora mismo
        ROUND((SELECT COUNT(*) FROM bicicletas WHERE estado = 'ALQUILADA') * 100.0
            / GREATEST((SELECT COUNT(*) FROM bicicletas), 1), 1) AS ocupacion,
        -- 4) Pagos exitosos (%): finalizados vs finalizados + pendientes de pago
        ROUND((SELECT COUNT(*) FROM alquileres WHERE estado = 'FINALIZADO') * 100.0
            / GREATEST((SELECT COUNT(*) FROM alquileres
                         WHERE estado IN ('FINALIZADO', 'PENDIENTE_PAGO')), 1), 1) AS pagos_exitosos,
        -- 5) Rotacion de uso (%): bicicletas que tuvieron al menos un alquiler
        ROUND((SELECT COUNT(DISTINCT id_bicicleta) FROM alquileres) * 100.0
            / GREATEST((SELECT COUNT(*) FROM bicicletas), 1), 1) AS rotacion_uso;
END$$

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
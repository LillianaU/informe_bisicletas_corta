-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 23-10-2026 a las 22:37:23
-- Versión del servidor: 8.4.3
-- Versión de PHP: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `ebike_management`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_agregar_repuesto_mantenimiento` (IN `p_id_manten` BIGINT UNSIGNED, IN `p_id_repuesto` BIGINT UNSIGNED, IN `p_cantidad` INT UNSIGNED)   BEGIN
    DECLARE v_stock INT UNSIGNED;
    DECLARE v_precio DECIMAL(12,2);
    DECLARE v_subtotal DECIMAL(12,2);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    START TRANSACTION;
        SELECT cantidad_disponible, precio_actual INTO v_stock, v_precio
        FROM inventario_costos WHERE id_repuesto = p_id_repuesto FOR UPDATE;

        IF v_stock < p_cantidad THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente de repuesto';
        END IF;

        SET v_subtotal = ROUND(v_precio * p_cantidad, 2);

        INSERT INTO mantenimiento_repuestos (id_manten, id_repuesto, cantidad, precio_unitario, subtotal)
        VALUES (p_id_manten, p_id_repuesto, p_cantidad, v_precio, v_subtotal);

        UPDATE inventario_costos SET cantidad_disponible = cantidad_disponible - p_cantidad WHERE id_repuesto = p_id_repuesto;
        UPDATE mantenimientos SET costo = costo + v_subtotal WHERE id_manten = p_id_manten;
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_alquiler_activos_por_cliente` (IN `p_id_usuario` BIGINT UNSIGNED, IN `p_id_ejecutor` BIGINT UNSIGNED, IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    IF p_rol_ejecutor = 'CLIENTE' AND p_id_usuario <> p_id_ejecutor THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No puede consultar los alquileres de otro usuario';
    END IF;

    SELECT a.id_alquiler, a.uuid_alquiler, a.fecha_inicio, a.fecha_fin,
           a.costo_base, a.descuento, a.costo_total, a.estado AS estado_alquiler,
           TIMESTAMPDIFF(MINUTE, a.fecha_inicio, COALESCE(a.fecha_fin, NOW())) AS duracion_minutos,
           b.id_bicicleta, b.codigo_qr, b.modelo, b.nivel_bateria,
           eo.nombre AS estacion_origen, ed.nombre AS estacion_destino
    FROM alquileres a
    JOIN bicicletas b ON b.id_bicicleta = a.id_bicicleta
    JOIN estaciones eo ON eo.id_estacion = a.estacion_orig
    LEFT JOIN estaciones ed ON ed.id_estacion = a.estacion_dest
    WHERE a.id_usuario = p_id_usuario
      AND a.estado IN ('ACTIVO', 'RESERVADO')
    ORDER BY a.fecha_inicio DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_alquiler_buscar_paginado` (IN `p_limite` INT UNSIGNED, IN `p_offset` INT UNSIGNED, IN `p_estado` VARCHAR(30), IN `p_cliente_nombre` VARCHAR(150), IN `p_bicicleta_qr` VARCHAR(150), IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_alquiler_historial_cliente` (IN `p_id_usuario` BIGINT UNSIGNED, IN `p_id_ejecutor` BIGINT UNSIGNED, IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    IF p_rol_ejecutor = 'CLIENTE' AND p_id_usuario <> p_id_ejecutor THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No puede consultar el historial de otro usuario';
    END IF;
    SELECT a.id_alquiler, a.uuid_alquiler, a.fecha_inicio, a.fecha_fin, a.costo_total, a.estado AS estado_alquiler,
           b.codigo_qr, b.modelo, eo.nombre AS estacion_origen, ed.nombre AS estacion_destino
    FROM alquileres a
    JOIN bicicletas b ON b.id_bicicleta = a.id_bicicleta
    JOIN estaciones eo ON eo.id_estacion = a.estacion_orig
    LEFT JOIN estaciones ed ON ed.id_estacion = a.estacion_dest
    WHERE a.id_usuario = p_id_usuario
    ORDER BY a.fecha_inicio DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_alquiler_obtener_todos` (IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR,OPERADOR');

    SELECT a.id_alquiler, a.uuid_alquiler, a.fecha_inicio, a.fecha_fin,
           a.costo_base, a.descuento, a.costo_total, a.estado AS estado_alquiler,
           TIMESTAMPDIFF(MINUTE, a.fecha_inicio, COALESCE(a.fecha_fin, NOW())) AS duracion_minutos,
           u.id_usuario, u.nombre AS cliente, u.email AS cliente_email,
           b.id_bicicleta, b.codigo_qr, b.modelo, b.nivel_bateria,
           eo.id_estacion, eo.nombre AS estacion_origen,
           ed.id_estacion AS id_estacion_destino, ed.nombre AS estacion_destino
    FROM alquileres a
    JOIN usuarios u ON u.id_usuario = a.id_usuario
    JOIN bicicletas b ON b.id_bicicleta = a.id_bicicleta
    JOIN estaciones eo ON eo.id_estacion = a.estacion_orig
    LEFT JOIN estaciones ed ON ed.id_estacion = a.estacion_dest
    ORDER BY a.fecha_inicio DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bicicleta_actualizar` (IN `p_id_bicicleta` BIGINT UNSIGNED, IN `p_estacion_act` INT UNSIGNED, IN `p_modelo` VARCHAR(100), IN `p_nivel_bateria` DECIMAL(5,2), IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,OPERADOR');

    START TRANSACTION;
        UPDATE bicicletas
        SET estacion_act = p_estacion_act, modelo = p_modelo, nivel_bateria = p_nivel_bateria
        WHERE id_bicicleta = p_id_bicicleta;
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bicicleta_cambiar_estado` (IN `p_id_bicicleta` BIGINT UNSIGNED, IN `p_estado` VARCHAR(30), IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    DECLARE v_estado_actual VARCHAR(30);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,OPERADOR');

    IF p_estado NOT IN ('DISPONIBLE','MANTENIMIENTO','FUERA_SERVICIO','CARGANDO') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Estado de bicicleta no vÃ¡lido';
    END IF;

    START TRANSACTION;
        SELECT estado INTO v_estado_actual FROM bicicletas WHERE id_bicicleta = p_id_bicicleta FOR UPDATE;

        IF v_estado_actual = 'ALQUILADA' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede cambiar el estado de una bicicleta alquilada';
        END IF;

        UPDATE bicicletas SET estado = p_estado WHERE id_bicicleta = p_id_bicicleta;
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bicicleta_crear` (IN `p_estacion_act` INT UNSIGNED, IN `p_codigo_qr` VARCHAR(150), IN `p_modelo` VARCHAR(100), IN `p_num_serie` VARCHAR(100), IN `p_rol_ejecutor` VARCHAR(30), OUT `p_id_bicicleta` BIGINT UNSIGNED)   BEGIN
    DECLARE v_existe INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,OPERADOR');

    SELECT COUNT(*) INTO v_existe FROM bicicletas
    WHERE codigo_qr = p_codigo_qr OR num_serie = p_num_serie;
    IF v_existe > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe una bicicleta con ese cÃ³digo QR o nÃºmero de serie';
    END IF;

    START TRANSACTION;
        INSERT INTO bicicletas (estacion_act, codigo_qr, modelo, num_serie, nivel_bateria, estado, kilometraje)
        VALUES (p_estacion_act, p_codigo_qr, p_modelo, p_num_serie, 100, 'DISPONIBLE', 0);
        SET p_id_bicicleta = LAST_INSERT_ID();
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bicicleta_listar_paginado` (IN `p_limite` INT UNSIGNED, IN `p_offset` INT UNSIGNED, IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,OPERADOR,SUPERVISOR,TECNICO');
    SELECT COUNT(*) AS total FROM bicicletas;
    SELECT b.id_bicicleta, b.estacion_act, b.codigo_qr, b.modelo, b.num_serie, b.nivel_bateria, b.estado, b.kilometraje, e.nombre AS estacion_nombre
    FROM bicicletas b
    LEFT JOIN estaciones e ON e.id_estacion = b.estacion_act
    ORDER BY b.codigo_qr
    LIMIT p_limite OFFSET p_offset;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bicicleta_obtener_disponibles` (IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR,OPERADOR,CLIENTE,TECNICO');

    SELECT 
        b.id_bicicleta, b.codigo_qr, b.modelo, b.nivel_bateria, b.estado, b.kilometraje, b.estacion_act,
        e.nombre AS estacion_nombre
    FROM bicicletas b
    LEFT JOIN estaciones e ON e.id_estacion = b.estacion_act
    WHERE b.estado = 'DISPONIBLE' AND b.nivel_bateria >= 20
    ORDER BY b.codigo_qr;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bicicleta_obtener_todas` (IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,OPERADOR,SUPERVISOR,TECNICO');

    SELECT b.id_bicicleta, b.estacion_act, b.codigo_qr, b.modelo, b.num_serie,
           b.nivel_bateria, b.estado, b.kilometraje,
           e.nombre AS estacion_nombre
    FROM bicicletas b
    LEFT JOIN estaciones e ON e.id_estacion = b.estacion_act
    ORDER BY b.codigo_qr;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_estacion_actualizar` (IN `p_id_estacion` INT UNSIGNED, IN `p_nombre` VARCHAR(100), IN `p_direccion` VARCHAR(255), IN `p_capacidad` INT UNSIGNED, IN `p_estado` VARCHAR(30), IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN');

    IF p_estado NOT IN ('ACTIVA','INACTIVA','MANTENIMIENTO','FUERA_SERVICIO') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Estado de estaciÃ³n no vÃ¡lido';
    END IF;

    START TRANSACTION;
        UPDATE estaciones
        SET nombre = p_nombre, direccion = p_direccion, capacidad = p_capacidad, estado = p_estado
        WHERE id_estacion = p_id_estacion;
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_estacion_crear` (IN `p_codigo` VARCHAR(30), IN `p_nombre` VARCHAR(100), IN `p_direccion` VARCHAR(255), IN `p_capacidad` INT UNSIGNED, IN `p_rol_ejecutor` VARCHAR(30), OUT `p_id_estacion` INT UNSIGNED)   BEGIN
    DECLARE v_existe INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN');

    SELECT COUNT(*) INTO v_existe FROM estaciones WHERE codigo = p_codigo;
    IF v_existe > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe una estaciÃ³n con ese cÃ³digo';
    END IF;

    START TRANSACTION;
        INSERT INTO estaciones (codigo, nombre, direccion, capacidad, energia_disp, estado)
        VALUES (p_codigo, p_nombre, p_direccion, p_capacidad, 0, 'ACTIVA');
        SET p_id_estacion = LAST_INSERT_ID();
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_estacion_obtener_todas` (IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,OPERADOR,SUPERVISOR,TECNICO,CLIENTE');

    SELECT e.id_estacion, e.codigo, e.nombre, e.direccion, e.capacidad,
           e.energia_disp, e.estado,
           (SELECT COUNT(*) FROM bicicletas b WHERE b.estacion_act = e.id_estacion) AS bicicletas_actuales
    FROM estaciones e
    ORDER BY e.nombre;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_evaluar_cliente_frecuente` (IN `p_id_usuario` BIGINT UNSIGNED)   BEGIN
    DECLARE v_cantidad INT DEFAULT 0;

    SELECT COUNT(*) INTO v_cantidad
    FROM alquileres
    WHERE id_usuario = p_id_usuario AND estado = 'FINALIZADO' AND fecha_fin >= NOW() - INTERVAL 30 DAY;

    UPDATE usuarios
    SET cantidad_alquileres = v_cantidad,
        es_cliente_frecuente = CASE WHEN v_cantidad >= 5 THEN TRUE ELSE FALSE END,
        fecha_ultima_evaluacion = NOW()
    WHERE id_usuario = p_id_usuario;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_finalizar_alquiler` (IN `p_id_alquiler` BIGINT UNSIGNED, IN `p_estacion_dest` INT UNSIGNED, IN `p_id_metodo_pago` BIGINT UNSIGNED, IN `p_referencia_pago` VARCHAR(150))   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_histograma_duraciones` (IN `p_estado` VARCHAR(30), IN `p_cliente_nombre` VARCHAR(150), IN `p_bicicleta_qr` VARCHAR(150), IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_alquiler` (IN `p_id_usuario` BIGINT UNSIGNED, IN `p_id_bicicleta` BIGINT UNSIGNED, IN `p_estacion_orig` INT UNSIGNED, IN `p_costo_base` DECIMAL(12,2), OUT `p_id_alquiler` BIGINT UNSIGNED)   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    START TRANSACTION;
        INSERT INTO alquileres (uuid_alquiler, id_usuario, id_bicicleta, estacion_orig, costo_base, estado)
        VALUES (UUID(), p_id_usuario, p_id_bicicleta, p_estacion_orig, p_costo_base, 'ACTIVO');
        SET p_id_alquiler = LAST_INSERT_ID();
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reporte_alquileres_detallado` (IN `p_limite` INT UNSIGNED, IN `p_offset` INT UNSIGNED, IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    SELECT COUNT(*) AS total FROM alquileres;

    SELECT a.id_alquiler, a.uuid_alquiler, a.fecha_inicio, a.fecha_fin,
           a.costo_base, a.costo_total, a.estado AS estado_alquiler,
           TIMESTAMPDIFF(MINUTE, a.fecha_inicio, COALESCE(a.fecha_fin, NOW())) AS duracion_minutos,
           u.nombre AS cliente,
           b.codigo_qr, b.nivel_bateria,
           eo.nombre AS estacion_origen,
           ed.nombre AS estacion_destino
    FROM alquileres a
    JOIN usuarios u ON u.id_usuario = a.id_usuario
    JOIN bicicletas b ON b.id_bicicleta = a.id_bicicleta
    JOIN estaciones eo ON eo.id_estacion = a.estacion_orig
    LEFT JOIN estaciones ed ON ed.id_estacion = a.estacion_dest
    ORDER BY a.fecha_inicio DESC
    LIMIT p_limite OFFSET p_offset;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reporte_cantidad_alquileres` (IN `p_periodo` VARCHAR(20))   BEGIN
    IF p_periodo = 'DIARIO' THEN
        SELECT DATE_FORMAT(fecha_inicio, '%Y-%m-%d') AS etiqueta, COUNT(*) AS total
        FROM alquileres
        GROUP BY DATE_FORMAT(fecha_inicio, '%Y-%m-%d')
        ORDER BY etiqueta ASC LIMIT 10;
    ELSEIF p_periodo = 'SEMANAL' THEN
        SELECT CONCAT('Semana ', WEEK(fecha_inicio)) AS etiqueta, COUNT(*) AS total
        FROM alquileres
        GROUP BY WEEK(fecha_inicio)
        ORDER BY etiqueta ASC LIMIT 8;
    ELSE
        SELECT DATE_FORMAT(fecha_inicio, '%Y-%m') AS etiqueta, COUNT(*) AS total
        FROM alquileres
        GROUP BY DATE_FORMAT(fecha_inicio, '%Y-%m')
        ORDER BY etiqueta ASC LIMIT 12;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reporte_cliente_historial` (IN `p_id_usuario` BIGINT UNSIGNED, IN `p_id_ejecutor` BIGINT UNSIGNED, IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    IF p_rol_ejecutor IN ('ADMIN','SUPERVISOR','OPERADOR') THEN
        -- roles internos: pueden ver el de cualquiera
        SELECT 1;
    ELSEIF p_id_usuario <> p_id_ejecutor THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No puede consultar el historial de otro usuario';
    END IF;

    SELECT a.id_alquiler, a.uuid_alquiler, a.fecha_inicio, a.fecha_fin,
           a.costo_total, a.estado AS estado_alquiler,
           b.codigo_qr, b.modelo,
           eo.nombre AS estacion_origen,
           ed.nombre AS estacion_destino
    FROM alquileres a
    JOIN bicicletas b ON b.id_bicicleta = a.id_bicicleta
    JOIN estaciones eo ON eo.id_estacion = a.estacion_orig
    LEFT JOIN estaciones ed ON ed.id_estacion = a.estacion_dest
    WHERE a.id_usuario = p_id_usuario
    ORDER BY a.fecha_inicio DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reporte_estacion` (IN `p_id_estacion` INT UNSIGNED)   BEGIN
    SELECT 
        e.nombre AS estacion,
        e.capacidad,
        COUNT(b.id_bicicleta) AS bicicletas_actuales,
        SUM(CASE WHEN b.estado = 'DISPONIBLE' THEN 1 ELSE 0 END) AS disponibles,
        SUM(CASE WHEN b.estado = 'CARGANDO' THEN 1 ELSE 0 END) AS cargando,
        e.energia_disp AS energia_disponible_kwh
    FROM estaciones e
    LEFT JOIN bicicletas b ON e.id_estacion = b.estacion_act
    WHERE e.id_estacion = p_id_estacion
    GROUP BY e.id_estacion;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reporte_estadisticas_admin` (IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    SELECT
        (SELECT COUNT(*) FROM bicicletas) AS total_bicicletas,
        (SELECT COUNT(*) FROM bicicletas WHERE estado = 'DISPONIBLE') AS bicicletas_disponibles,
        (SELECT COUNT(*) FROM alquileres WHERE estado = 'ACTIVO') AS alquileres_activos,
        (SELECT COUNT(*) FROM usuarios) AS total_usuarios,
        (SELECT COUNT(*) FROM alquileres) AS total_alquileres,
        (SELECT COALESCE(SUM(costo_total),0) FROM alquileres WHERE estado = 'FINALIZADO') AS ingresos_totales;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reporte_estado_alquileres` (IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    SELECT estado AS etiqueta, COUNT(*) AS total
    FROM alquileres
    GROUP BY estado
    ORDER BY total DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuario_actualizar` (IN `p_id_usuario` BIGINT UNSIGNED, IN `p_nombre` VARCHAR(150), IN `p_email` VARCHAR(150), IN `p_telefono` VARCHAR(30), IN `p_rol_id` INT UNSIGNED, IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN');

    START TRANSACTION;
        UPDATE usuarios
        SET nombre = p_nombre, email = p_email, telefono = p_telefono, rol_id = p_rol_id
        WHERE id_usuario = p_id_usuario;
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuario_crear` (IN `p_nombre` VARCHAR(150), IN `p_email` VARCHAR(150), IN `p_password_hash` VARCHAR(255), IN `p_telefono` VARCHAR(30), IN `p_rol_id` INT UNSIGNED, IN `p_rol_ejecutor` VARCHAR(30), OUT `p_id_usuario` BIGINT UNSIGNED)   BEGIN
    DECLARE v_existe INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN');

    SELECT COUNT(*) INTO v_existe FROM usuarios WHERE email = p_email;
    IF v_existe > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El correo ya estÃ¡ registrado';
    END IF;

    START TRANSACTION;
        INSERT INTO usuarios (uuid, nombre, email, password_hash, telefono, rol_id, estado)
        VALUES (UUID(), p_nombre, p_email, p_password_hash, p_telefono, p_rol_id, 'ACTIVO');
        SET p_id_usuario = LAST_INSERT_ID();
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuario_estado` (IN `p_id_usuario` BIGINT UNSIGNED, IN `p_estado` VARCHAR(20), IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN');

    IF p_estado NOT IN ('ACTIVO','INACTIVO','BLOQUEADO') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Estado de usuario no vÃ¡lido';
    END IF;

    START TRANSACTION;
        UPDATE usuarios SET estado = p_estado WHERE id_usuario = p_id_usuario;
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuario_obtener_por_id` (IN `p_id_usuario` BIGINT UNSIGNED, IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    SELECT u.id_usuario, u.uuid, u.nombre, u.email, u.telefono, u.estado,
           u.es_cliente_frecuente, u.cantidad_alquileres, u.fecha_registro,
           u.rol_id, r.codigo_rol AS rol
    FROM usuarios u
    JOIN roles r ON r.id_rol = u.rol_id
    WHERE u.id_usuario = p_id_usuario;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usuario_obtener_todos` (IN `p_rol_ejecutor` VARCHAR(30))   BEGIN
    CALL sp_validar_rol(p_rol_ejecutor, 'ADMIN,SUPERVISOR');

    SELECT u.id_usuario, u.uuid, u.nombre, u.email, u.telefono, u.estado,
           u.es_cliente_frecuente, u.cantidad_alquileres, u.fecha_registro,
           r.codigo_rol AS rol, r.nombre_rol
    FROM usuarios u
    JOIN roles r ON r.id_rol = u.rol_id
    ORDER BY u.nombre ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_validar_rol` (IN `p_rol_ejecutor` VARCHAR(30), IN `p_roles_permitidos` VARCHAR(255))   BEGIN
    IF FIND_IN_SET(p_rol_ejecutor, p_roles_permitidos) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Acceso denegado: el rol no tiene permiso para esta operacion';
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas_energia`
--

CREATE TABLE `alertas_energia` (
  `id_alerta` bigint UNSIGNED NOT NULL,
  `id_bateria` bigint UNSIGNED NOT NULL,
  `tipo_alerta` enum('BATERIA_BAJA','SOBRETEMPERATURA','BATERIA_DAÃ‘ADA','SOBRECARGA','MANTENIMIENTO','OTRA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel_prioridad` enum('BAJA','MEDIA','ALTA','CRITICA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MEDIA',
  `fecha_alerta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado_alerta` enum('PENDIENTE','ATENDIDA','CERRADA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `alertas_energia`
--

INSERT INTO `alertas_energia` (`id_alerta`, `id_bateria`, `tipo_alerta`, `descripcion`, `nivel_prioridad`, `fecha_alerta`, `estado_alerta`) VALUES
(1, 4, 'BATERIA_BAJA', 'Batería bici 4 al 15%', 'ALTA', '2026-10-22 17:51:54', 'PENDIENTE'),
(2, 12, 'BATERIA_BAJA', 'Batería bici 12 al 22%', 'MEDIA', '2026-10-22 17:51:54', 'PENDIENTE'),
(3, 23, 'BATERIA_DAÃ‘ADA', 'Batería bici 23 al 0%', 'CRITICA', '2026-10-22 17:51:54', 'PENDIENTE'),
(4, 24, 'SOBRETEMPERATURA', 'Batería bici 24 a 30°C', 'ALTA', '2026-10-22 17:51:54', 'PENDIENTE'),
(5, 8, 'OTRA', 'Batería bici 8 no completa', 'BAJA', '2026-10-22 17:51:54', 'ATENDIDA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alquileres`
--

CREATE TABLE `alquileres` (
  `id_alquiler` bigint UNSIGNED NOT NULL,
  `uuid_alquiler` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_usuario` bigint UNSIGNED NOT NULL,
  `id_bicicleta` bigint UNSIGNED NOT NULL,
  `estacion_orig` int UNSIGNED NOT NULL,
  `estacion_dest` int UNSIGNED DEFAULT NULL,
  `fecha_inicio` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_fin` datetime DEFAULT NULL,
  `costo_base` decimal(12,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(12,2) NOT NULL DEFAULT '0.00',
  `porcentaje_descuento` decimal(5,2) NOT NULL DEFAULT '0.00',
  `costo_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estado` enum('RESERVADO','ACTIVO','FINALIZADO','CANCELADO','PENDIENTE_PAGO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RESERVADO'
) ;

--
-- Volcado de datos para la tabla `alquileres`
--

INSERT INTO `alquileres` (`id_alquiler`, `uuid_alquiler`, `id_usuario`, `id_bicicleta`, `estacion_orig`, `estacion_dest`, `fecha_inicio`, `fecha_fin`, `costo_base`, `descuento`, `porcentaje_descuento`, `costo_total`, `estado`) VALUES
(1, '2d7d68b2-ce6b-11f1-9fb7-b44506137da9', 7, 1, 1, 2, '2025-01-05 10:00:00', '2025-01-05 12:00:00', 15.00, 1.50, 10.00, 13.50, 'FINALIZADO'),
(2, '2d7e3dec-ce6b-11f1-9fb7-b44506137da9', 8, 2, 2, 3, '2025-01-08 14:00:00', '2025-01-08 15:00:00', 18.50, 1.85, 10.00, 16.65, 'FINALIZADO'),
(3, '2d7f6674-ce6b-11f1-9fb7-b44506137da9', 9, 3, 3, 1, '2025-01-10 09:00:00', '2025-01-10 13:00:00', 12.00, 0.00, 0.00, 12.00, 'FINALIZADO'),
(4, '2d8030e0-ce6b-11f1-9fb7-b44506137da9', 10, 4, 1, 4, '2025-01-12 16:00:00', '2025-01-12 19:00:00', 20.00, 0.00, 0.00, 20.00, 'FINALIZADO'),
(5, '2d813310-ce6b-11f1-9fb7-b44506137da9', 7, 5, 4, 5, '2025-01-15 11:00:00', '2025-01-15 12:00:00', 22.00, 2.20, 10.00, 19.80, 'FINALIZADO'),
(6, '2d820ce1-ce6b-11f1-9fb7-b44506137da9', 11, 6, 5, 2, '2025-01-18 10:00:00', '2025-01-18 12:00:00', 16.50, 0.00, 0.00, 16.50, 'FINALIZADO'),
(7, '2d82d734-ce6b-11f1-9fb7-b44506137da9', 8, 7, 2, 1, '2025-01-20 14:00:00', '2025-01-20 18:00:00', 14.00, 1.40, 10.00, 12.60, 'FINALIZADO'),
(8, '2d839a4b-ce6b-11f1-9fb7-b44506137da9', 12, 8, 4, 3, '2025-01-22 09:00:00', '2025-01-22 12:00:00', 19.00, 0.00, 0.00, 19.00, 'FINALIZADO'),
(9, '2d844e54-ce6b-11f1-9fb7-b44506137da9', 9, 9, 1, 5, '2025-01-24 16:00:00', '2025-01-24 17:00:00', 25.00, 0.00, 0.00, 25.00, 'FINALIZADO'),
(10, '2d8509e4-ce6b-11f1-9fb7-b44506137da9', 10, 10, 5, 4, '2025-01-26 11:00:00', '2025-01-26 13:00:00', 17.50, 0.00, 0.00, 17.50, 'FINALIZADO'),
(11, '2d85d441-ce6b-11f1-9fb7-b44506137da9', 7, 11, 2, 1, '2025-01-27 10:00:00', '2025-01-27 14:00:00', 13.00, 1.30, 10.00, 11.70, 'FINALIZADO'),
(12, '2d86af3f-ce6b-11f1-9fb7-b44506137da9', 11, 12, 3, 2, '2025-01-28 15:00:00', '2025-01-28 18:00:00', 21.00, 0.00, 0.00, 21.00, 'FINALIZADO'),
(13, '2d878481-ce6b-11f1-9fb7-b44506137da9', 7, 13, 2, 3, '2026-10-22 18:51:54', '2026-10-22 20:51:54', 18.00, 1.80, 10.00, 16.20, 'FINALIZADO'),
(14, '2d8861eb-ce6b-11f1-9fb7-b44506137da9', 8, 14, 4, 5, '2026-10-22 19:51:54', '2026-10-22 21:51:54', 22.00, 2.20, 10.00, 19.80, 'FINALIZADO'),
(15, '2d894bfc-ce6b-11f1-9fb7-b44506137da9', 9, 15, 5, 1, '2026-10-22 20:51:54', '2026-10-22 22:21:54', 17.00, 0.00, 0.00, 17.00, 'FINALIZADO'),
(16, '2d8a4e3d-ce6b-11f1-9fb7-b44506137da9', 8, 16, 4, NULL, '2026-10-22 17:51:54', NULL, 15.00, 1.50, 10.00, 13.50, 'ACTIVO'),
(17, '2d8b5832-ce6b-11f1-9fb7-b44506137da9', 9, 17, 1, NULL, '2026-10-22 19:51:54', NULL, 18.00, 0.00, 0.00, 18.00, 'ACTIVO'),
(18, '2d8c4d0a-ce6b-11f1-9fb7-b44506137da9', 12, 18, 5, NULL, '2026-10-22 20:51:54', NULL, 12.50, 0.00, 0.00, 12.50, 'ACTIVO'),
(19, '2d8d4c1c-ce6b-11f1-9fb7-b44506137da9', 10, 19, 2, NULL, '2026-10-22 21:51:54', NULL, 20.00, 0.00, 0.00, 20.00, 'ACTIVO'),
(20, '2d8e54ea-ce6b-11f1-9fb7-b44506137da9', 7, 20, 3, NULL, '2026-10-22 22:21:54', NULL, 22.00, 2.20, 10.00, 19.80, 'ACTIVO'),
(21, '2d8f723d-ce6b-11f1-9fb7-b44506137da9', 13, 1, 1, NULL, '2026-10-23 00:51:54', NULL, 15.00, 0.00, 0.00, 15.00, 'RESERVADO'),
(22, '2d908217-ce6b-11f1-9fb7-b44506137da9', 14, 2, 4, NULL, '2026-10-23 02:51:54', NULL, 18.00, 0.00, 0.00, 18.00, 'RESERVADO'),
(23, '2d91a050-ce6b-11f1-9fb7-b44506137da9', 11, 3, 2, NULL, '2026-10-17 22:51:54', '2026-10-17 22:51:54', 12.00, 0.00, 0.00, 12.00, 'CANCELADO'),
(24, '2d929631-ce6b-11f1-9fb7-b44506137da9', 12, 4, 3, NULL, '2026-10-19 22:51:54', '2026-10-19 22:51:54', 20.00, 0.00, 0.00, 20.00, 'CANCELADO'),
(25, '2d9380aa-ce6b-11f1-9fb7-b44506137da9', 13, 6, 5, 4, '2026-10-21 22:51:54', '2026-10-21 23:51:54', 16.00, 0.00, 0.00, 16.00, 'PENDIENTE_PAGO'),
(26, '2d94686b-ce6b-11f1-9fb7-b44506137da9', 14, 7, 1, 2, '2026-10-22 10:51:54', '2026-10-22 12:51:54', 14.50, 0.00, 0.00, 14.50, 'PENDIENTE_PAGO'),
(27, 'b872b366-cec3-11f1-94d0-b44506137da9', 1, 25, 2, 7, '2026-10-23 04:25:43', '2026-10-23 04:25:05', 2300.00, 0.00, 0.00, 2300.00, 'ACTIVO');

--
-- Disparadores `alquileres`
--
DELIMITER $$
CREATE TRIGGER `trg_alquiler_cliente_frecuente` BEFORE INSERT ON `alquileres` FOR EACH ROW BEGIN
    DECLARE v_cliente_frecuente BOOLEAN DEFAULT FALSE;
    SELECT es_cliente_frecuente INTO v_cliente_frecuente FROM usuarios WHERE id_usuario = NEW.id_usuario;

    IF v_cliente_frecuente = TRUE THEN
        SET NEW.porcentaje_descuento = 10;
        SET NEW.descuento = ROUND(NEW.costo_base * 0.10, 2);
        SET NEW.costo_total = ROUND(NEW.costo_base - NEW.descuento, 2);
    ELSE
        SET NEW.porcentaje_descuento = 0;
        SET NEW.descuento = 0;
        SET NEW.costo_total = NEW.costo_base;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_alquiler_uuid` BEFORE INSERT ON `alquileres` FOR EACH ROW BEGIN
    IF NEW.uuid_alquiler IS NULL OR NEW.uuid_alquiler = '' THEN
        SET NEW.uuid_alquiler = UUID();
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_bicicleta_alquilada` AFTER INSERT ON `alquileres` FOR EACH ROW BEGIN
    IF NEW.estado IN ('RESERVADO', 'ACTIVO') THEN
        UPDATE bicicletas SET estado = 'ALQUILADA' WHERE id_bicicleta = NEW.id_bicicleta;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_finalizar_alquiler` AFTER UPDATE ON `alquileres` FOR EACH ROW BEGIN
    IF NEW.estado = 'FINALIZADO' AND OLD.estado <> 'FINALIZADO' THEN
        UPDATE bicicletas SET estado = 'DISPONIBLE', estacion_act = NEW.estacion_dest WHERE id_bicicleta = NEW.id_bicicleta;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_validar_bicicleta_alquiler` BEFORE INSERT ON `alquileres` FOR EACH ROW BEGIN
    DECLARE v_estado VARCHAR(30);
    DECLARE v_bateria DECIMAL(5,2);

    SELECT estado, nivel_bateria INTO v_estado, v_bateria FROM bicicletas WHERE id_bicicleta = NEW.id_bicicleta;

    IF v_estado <> 'DISPONIBLE' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bicicleta no estÃ¡ disponible para alquiler';
    END IF;

    IF v_bateria < 20 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bicicleta tiene un nivel de baterÃ­a inferior al 20%';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `baterias`
--

CREATE TABLE `baterias` (
  `id_bateria` bigint UNSIGNED NOT NULL,
  `codigo_bateria` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacidad_wh` decimal(10,2) NOT NULL,
  `nivel_carga` decimal(5,2) NOT NULL DEFAULT '100.00',
  `temperatura` decimal(6,2) DEFAULT NULL,
  `estado` enum('DISPONIBLE','INSTALADA','CARGANDO','BAJA','DAÃ‘ADA','FUERA_SERVICIO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DISPONIBLE',
  `fecha_instalacion` date DEFAULT NULL,
  `vida_util` int UNSIGNED DEFAULT NULL,
  `id_bicicleta` bigint UNSIGNED DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `baterias`
--

INSERT INTO `baterias` (`id_bateria`, `codigo_bateria`, `capacidad_wh`, `nivel_carga`, `temperatura`, `estado`, `fecha_instalacion`, `vida_util`, `id_bicicleta`) VALUES
(1, 'BAT-1', 500.00, 85.00, 22.00, 'INSTALADA', '2026-04-25', 800, 1),
(2, 'BAT-2', 500.00, 92.00, 21.00, 'INSTALADA', '2026-05-25', 800, 2),
(3, 'BAT-3', 750.00, 65.00, 24.00, 'INSTALADA', '2026-04-05', 600, 3),
(4, 'BAT-4', 450.00, 15.00, 28.00, 'BAJA', '2025-12-26', 1000, 4),
(5, 'BAT-5', 500.00, 78.00, 23.00, 'INSTALADA', '2026-06-24', 800, 5),
(6, 'BAT-6', 500.00, 88.00, 22.00, 'INSTALADA', '2026-07-24', 800, 6),
(7, 'BAT-7', 750.00, 55.00, 25.00, 'INSTALADA', '2026-04-25', 600, 7),
(8, 'BAT-8', 450.00, 30.00, 26.00, 'CARGANDO', '2026-02-14', 1000, 8),
(9, 'BAT-9', 500.00, 5.00, 20.00, 'CARGANDO', '2026-08-23', 800, 9),
(10, 'BAT-10', 500.00, 95.00, 21.00, 'INSTALADA', '2026-09-22', 800, 10),
(11, 'BAT-11', 750.00, 55.00, 24.00, 'INSTALADA', '2026-03-16', 600, 11),
(12, 'BAT-12', 500.00, 22.00, 29.00, 'BAJA', '2026-01-15', 800, 12),
(13, 'BAT-13', 500.00, 90.00, 22.00, 'INSTALADA', '2026-09-07', 800, 13),
(14, 'BAT-14', 450.00, 80.00, 23.00, 'INSTALADA', '2026-04-05', 1000, 14),
(15, 'BAT-15', 750.00, 70.00, 24.00, 'INSTALADA', '2026-05-15', 600, 15),
(16, 'BAT-16', 500.00, 60.00, 25.00, 'INSTALADA', '2026-07-14', 800, 16),
(17, 'BAT-17', 500.00, 12.00, 27.00, 'CARGANDO', '2026-08-03', 800, 17),
(18, 'BAT-18', 500.00, 85.00, 22.00, 'INSTALADA', '2026-09-02', 800, 18),
(19, 'BAT-19', 450.00, 75.00, 23.00, 'INSTALADA', '2026-01-25', 1000, 19),
(20, 'BAT-20', 750.00, 95.00, 21.00, 'INSTALADA', '2026-10-02', 600, 20),
(21, 'BAT-21', 500.00, 40.00, 26.00, 'INSTALADA', '2026-02-24', 800, 21),
(22, 'BAT-22', 450.00, 50.00, 25.00, 'INSTALADA', '2026-03-26', 1000, 22),
(23, 'BAT-23', 500.00, 0.00, 18.00, 'DAÃ‘ADA', '2025-09-17', 800, 23),
(24, 'BAT-24', 750.00, 10.00, 30.00, 'FUERA_SERVICIO', '2025-11-06', 600, 24);

--
-- Disparadores `baterias`
--
DELIMITER $$
CREATE TRIGGER `trg_alerta_bateria_baja` AFTER UPDATE ON `baterias` FOR EACH ROW BEGIN
    IF NEW.nivel_carga <= 20 AND OLD.nivel_carga > 20 THEN
        INSERT INTO alertas_energia (id_bateria, tipo_alerta, descripcion, nivel_prioridad, estado_alerta)
        VALUES (NEW.id_bateria, 'BATERIA_BAJA', 'BaterÃ­a en nivel crÃ­tico (<= 20%)', 'ALTA', 'PENDIENTE');
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_alerta_temperatura` AFTER UPDATE ON `baterias` FOR EACH ROW BEGIN
    IF NEW.temperatura >= 45 AND (OLD.temperatura IS NULL OR OLD.temperatura < 45) THEN
        INSERT INTO alertas_energia (id_bateria, tipo_alerta, descripcion, nivel_prioridad, estado_alerta)
        VALUES (NEW.id_bateria, 'SOBRETEMPERATURA', 'Temperatura superior a 45Â°C', 'CRITICA', 'PENDIENTE');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bicicletas`
--

CREATE TABLE `bicicletas` (
  `id_bicicleta` bigint UNSIGNED NOT NULL,
  `estacion_act` int UNSIGNED DEFAULT NULL,
  `codigo_qr` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `num_serie` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel_bateria` decimal(5,2) NOT NULL DEFAULT '100.00',
  `estado` enum('DISPONIBLE','ALQUILADA','MANTENIMIENTO','FUERA_SERVICIO','CARGANDO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DISPONIBLE',
  `kilometraje` decimal(10,2) NOT NULL DEFAULT '0.00'
) ;

--
-- Volcado de datos para la tabla `bicicletas`
--

INSERT INTO `bicicletas` (`id_bicicleta`, `estacion_act`, `codigo_qr`, `modelo`, `num_serie`, `nivel_bateria`, `estado`, `kilometraje`) VALUES
(1, 1, 'QR-EST001-001', 'Ebike Urban Pro', 'SN-URB-001', 85.50, 'ALQUILADA', 1250.30),
(2, 1, 'QR-EST001-002', 'Ebike Urban Pro', 'SN-URB-002', 92.00, 'ALQUILADA', 980.15),
(3, 1, 'QR-EST001-003', 'Ebike Cargo', 'SN-CAR-001', 85.00, 'DISPONIBLE', 2100.50),
(4, 1, 'QR-EST001-004', 'Ebike Sport', 'SN-SPT-001', 15.00, 'DISPONIBLE', 3200.00),
(5, 1, 'QR-EST001-005', 'Ebike Urban Pro', 'SN-URB-003', 85.00, 'DISPONIBLE', 1560.80),
(6, 2, 'QR-EST002-001', 'Ebike Urban Pro', 'SN-URB-004', 88.00, 'DISPONIBLE', 1100.25),
(7, 2, 'QR-EST002-002', 'Ebike Cargo', 'SN-CAR-002', 85.00, 'DISPONIBLE', 1890.40),
(8, 2, 'QR-EST002-003', 'Ebike Sport', 'SN-SPT-002', 85.00, 'DISPONIBLE', 2500.00),
(9, 2, 'QR-EST002-004', 'Ebike Urban Pro', 'SN-URB-005', 85.00, 'DISPONIBLE', 950.60),
(10, 3, 'QR-EST003-001', 'Ebike Urban Pro', 'SN-URB-006', 95.00, 'DISPONIBLE', 800.00),
(11, 3, 'QR-EST003-002', 'Ebike Cargo', 'SN-CAR-003', 85.00, 'DISPONIBLE', 2200.10),
(12, 3, 'QR-EST003-003', 'Ebike Urban Pro', 'SN-URB-007', 22.00, 'DISPONIBLE', 1750.30),
(13, 4, 'QR-EST004-001', 'Ebike Urban Pro', 'SN-URB-008', 90.00, 'DISPONIBLE', 1300.00),
(14, 4, 'QR-EST004-002', 'Ebike Sport', 'SN-SPT-003', 85.00, 'MANTENIMIENTO', 2800.50),
(15, 4, 'QR-EST004-003', 'Ebike Cargo', 'SN-CAR-004', 85.00, 'DISPONIBLE', 1950.20),
(16, 4, 'QR-EST004-004', 'Ebike Urban Pro', 'SN-URB-009', 85.00, 'ALQUILADA', 1600.00),
(17, 4, 'QR-EST004-005', 'Ebike Urban Pro', 'SN-URB-010', 85.00, 'MANTENIMIENTO', 1050.80),
(18, 5, 'QR-EST005-001', 'Ebike Urban Pro', 'SN-URB-011', 85.00, 'ALQUILADA', 1400.00),
(19, 5, 'QR-EST005-002', 'Ebike Sport', 'SN-SPT-004', 85.00, 'ALQUILADA', 3100.00),
(20, 5, 'QR-EST005-003', 'Ebike Cargo', 'SN-CAR-005', 95.00, 'ALQUILADA', 850.50),
(21, 5, 'QR-EST005-004', 'Ebike Urban Pro', 'SN-URB-012', 85.00, 'CARGANDO', 2300.70),
(22, 5, 'QR-EST005-005', 'Ebike Sport', 'SN-SPT-005', 85.00, 'MANTENIMIENTO', 2900.30),
(23, NULL, 'QR-TALLER-001', 'Ebike Urban Pro', 'SN-URB-013', 85.00, 'FUERA_SERVICIO', 4500.00),
(24, NULL, 'QR-TALLER-002', 'Ebike Cargo', 'SN-CAR-006', 85.00, 'MANTENIMIENTO', 3800.00),
(25, 1, 'swt-rt67', '20025', 'cv456', 100.00, 'ALQUILADA', 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleados`
--

CREATE TABLE `empleados` (
  `id_empleado` bigint UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `fecha_ingreso` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `empleados`
--

INSERT INTO `empleados` (`id_empleado`, `uuid`, `nombre`, `email`, `telefono`, `cargo`, `estado`, `fecha_ingreso`) VALUES
(1, '2d9dfa23-ce6b-11f1-9fb7-b44506137da9', 'Roberto Mecánico', 'roberto@ebike.com', '611223344', 'Mecánico Senior', 'ACTIVO', '2026-10-22 17:51:54'),
(2, '2d9e027d-ce6b-11f1-9fb7-b44506137da9', 'Sandra Eléctrica', 'sandra@ebike.com', '622334455', 'Especialista Eléctrica', 'ACTIVO', '2026-10-22 17:51:54'),
(3, '2d9e062f-ce6b-11f1-9fb7-b44506137da9', 'Carlos Junior', 'carlos.jr@ebike.com', '633445566', 'Mecánico Junior', 'ACTIVO', '2026-10-22 17:51:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estaciones`
--

CREATE TABLE `estaciones` (
  `id_estacion` int UNSIGNED NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `coordenadas` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacidad` int UNSIGNED NOT NULL,
  `energia_disp` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estado` enum('ACTIVA','INACTIVA','MANTENIMIENTO','FUERA_SERVICIO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA'
) ;

--
-- Volcado de datos para la tabla `estaciones`
--

INSERT INTO `estaciones` (`id_estacion`, `codigo`, `nombre`, `direccion`, `coordenadas`, `capacidad`, `energia_disp`, `estado`) VALUES
(1, 'EST001', 'Estación Centro', 'Plaza Mayor, 1', '40.4168,-3.7038', 20, 1500.00, 'ACTIVA'),
(2, 'EST002', 'Estación Universidad', 'Av. Universidad, 45', '40.4234,-3.7025', 15, 1200.50, 'ACTIVA'),
(3, 'EST003', 'Estación Hospital', 'C/ Salud, 12', '40.4112,-3.6987', 10, 800.75, 'ACTIVA'),
(4, 'EST004', 'Estación Parque', 'Paseo del Parque, 8', '40.4189,-3.7102', 25, 2000.00, 'ACTIVA'),
(5, 'EST005', 'Estación Tren', 'Av. Estación, 1', '40.4085,-3.6921', 30, 2500.25, 'ACTIVA'),
(6, 'EST006', 'Estación Norte (Mantenimiento)', 'C/ Norte, 50', '40.4350,-3.6800', 10, 0.00, 'MANTENIMIENTO'),
(7, 'EST007', 'Estación Sur (Inactiva)', 'C/ Sur, 25', '40.3950,-3.7200', 8, 0.00, 'INACTIVA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_carga`
--

CREATE TABLE `historial_carga` (
  `id_historial` bigint UNSIGNED NOT NULL,
  `id_bateria` bigint UNSIGNED NOT NULL,
  `id_estacion` int UNSIGNED NOT NULL,
  `porcentaje_inicial` decimal(5,2) NOT NULL,
  `porcentaje_final` decimal(5,2) NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `tiempo_carga` int UNSIGNED DEFAULT NULL COMMENT 'Tiempo en minutos',
  `energia_consumida` decimal(10,3) DEFAULT NULL COMMENT 'Energia en kWh'
) ;

--
-- Volcado de datos para la tabla `historial_carga`
--

INSERT INTO `historial_carga` (`id_historial`, `id_bateria`, `id_estacion`, `porcentaje_inicial`, `porcentaje_final`, `fecha_inicio`, `fecha_fin`, `tiempo_carga`, `energia_consumida`) VALUES
(1, 1, 1, 15.00, 85.50, '2026-10-20 17:51:54', '2026-10-21 17:51:54', 60, 0.350),
(2, 4, 1, 5.00, 15.00, '2026-10-21 17:51:54', '2026-10-22 05:51:54', 30, 0.045),
(3, 8, 2, 10.00, 30.00, '2026-10-22 05:51:54', '2026-10-22 07:51:54', 120, 0.135),
(4, 9, 2, 0.00, 5.00, '2026-10-22 11:51:54', '2026-10-22 12:51:54', 60, 0.025),
(5, 10, 3, 20.00, 95.00, '2026-10-19 17:51:54', '2026-10-20 17:51:54', 120, 0.375),
(6, 17, 4, 8.00, 12.00, '2026-10-22 13:51:54', '2026-10-22 14:51:54', 60, 0.020),
(7, 20, 5, 35.00, 95.00, '2026-10-21 17:51:54', '2026-10-22 05:51:54', 60, 0.300);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario_costos`
--

CREATE TABLE `inventario_costos` (
  `id_repuesto` bigint UNSIGNED NOT NULL,
  `nombre_rep` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad_disponible` int UNSIGNED NOT NULL DEFAULT '0',
  `precio_actual` decimal(12,2) NOT NULL DEFAULT '0.00',
  `costo_repuestos` decimal(12,2) NOT NULL DEFAULT '0.00',
  `proveedor` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `costo_mano_obra` decimal(12,2) NOT NULL DEFAULT '0.00',
  `costo_total` decimal(12,2) NOT NULL DEFAULT '0.00'
) ;

--
-- Volcado de datos para la tabla `inventario_costos`
--

INSERT INTO `inventario_costos` (`id_repuesto`, `nombre_rep`, `cantidad_disponible`, `precio_actual`, `costo_repuestos`, `proveedor`, `costo_mano_obra`, `costo_total`) VALUES
(1, 'Pastillas freno (par)', 50, 12.00, 8.50, 'BikeParts SL', 15.00, 23.50),
(2, 'Cadena 114 eslabones', 30, 18.00, 12.00, 'ChainCo', 10.00, 22.00),
(3, 'Cámara 27.5\"', 40, 8.00, 4.50, 'TireWorld', 8.00, 12.50),
(4, 'Cubierta 27.5\" x 2.35', 25, 35.00, 22.00, 'TireWorld', 10.00, 32.00),
(5, 'Cambio trasero Shimano Deore', 10, 85.00, 65.00, 'Shimano Iberia', 25.00, 90.00),
(6, 'Manetas freno hidráulico', 15, 45.00, 30.00, 'BrakeTech', 20.00, 50.00),
(7, 'Display LCD Ebike', 8, 120.00, 85.00, 'EbikeDisplays', 30.00, 115.00),
(8, 'Controlador motor 250W', 5, 150.00, 110.00, 'MotorControl', 40.00, 150.00),
(9, 'Batería 500Wh repuesto', 6, 450.00, 350.00, 'BatteryPro', 50.00, 400.00),
(10, 'Batería 750Wh repuesto', 4, 650.00, 500.00, 'BatteryPro', 50.00, 550.00),
(11, 'Sensor torque', 12, 35.00, 22.00, 'SensorTech', 15.00, 37.00),
(12, 'Luz delantera LED', 20, 25.00, 15.00, 'LightBike', 5.00, 20.00),
(13, 'Luz trasera LED', 20, 18.00, 10.00, 'LightBike', 5.00, 15.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_auditoria`
--

CREATE TABLE `logs_auditoria` (
  `id_log` bigint UNSIGNED NOT NULL,
  `id_manten` bigint UNSIGNED NOT NULL,
  `estado_anterior` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_nuevo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_empleado` bigint UNSIGNED DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `logs_auditoria`
--

INSERT INTO `logs_auditoria` (`id_log`, `id_manten`, `estado_anterior`, `estado_nuevo`, `id_empleado`, `fecha_hora`) VALUES
(1, 1, 'PROGRAMADO', 'EN_REVISION', 1, '2026-10-12 17:51:54'),
(2, 1, 'EN_REVISION', 'EN_REPARACION', 1, '2026-10-13 17:51:54'),
(3, 1, 'EN_REPARACION', 'FINALIZADO', 1, '2026-10-14 17:51:54'),
(4, 2, 'PROGRAMADO', 'EN_REVISION', 2, '2026-10-17 17:51:54'),
(5, 2, 'EN_REVISION', 'EN_REPARACION', 2, '2026-10-18 17:51:54'),
(6, 2, 'EN_REPARACION', 'FINALIZADO', 2, '2026-10-19 17:51:54'),
(7, 3, 'PROGRAMADO', 'EN_REVISION', 1, '2026-10-20 17:51:54'),
(8, 3, 'EN_REVISION', 'EN_REPARACION', 1, '2026-10-21 17:51:54'),
(9, 4, 'PROGRAMADO', 'EN_REVISION', 3, '2026-10-21 17:51:54'),
(10, 5, 'PROGRAMADO', 'EN_REVISION', 1, '2026-10-22 05:51:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mantenimientos`
--

CREATE TABLE `mantenimientos` (
  `id_manten` bigint UNSIGNED NOT NULL,
  `id_bicicleta` bigint UNSIGNED NOT NULL,
  `tipo_manten` enum('PREVENTIVO','CORRECTIVO','PREDICTIVO','EMERGENCIA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `diagnostico` text COLLATE utf8mb4_unicode_ci,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `tecnico` bigint UNSIGNED DEFAULT NULL,
  `fecha_ingreso` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_salida` datetime DEFAULT NULL,
  `costo` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estado` enum('PROGRAMADO','EN_REVISION','EN_REPARACION','FINALIZADO','CANCELADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PROGRAMADO'
) ;

--
-- Volcado de datos para la tabla `mantenimientos`
--

INSERT INTO `mantenimientos` (`id_manten`, `id_bicicleta`, `tipo_manten`, `diagnostico`, `descripcion`, `tecnico`, `fecha_ingreso`, `fecha_salida`, `costo`, `estado`) VALUES
(1, 4, 'CORRECTIVO', 'Fallo en motor', 'Ruido extraño al pedalear', 1, '2026-10-12 17:51:54', '2026-10-14 17:51:54', 85.00, 'FINALIZADO'),
(2, 13, 'PREVENTIVO', 'Revisión 3000km', 'Cambio pastillas, ajuste cambios', 2, '2026-10-17 17:51:54', '2026-10-19 17:51:54', 45.00, 'FINALIZADO'),
(3, 14, 'PREDICTIVO', 'Batería degradada', 'Capacidad batería al 65%', 1, '2026-10-20 17:51:54', NULL, 0.00, 'EN_REPARACION'),
(4, 17, 'EMERGENCIA', 'Pinchazo rueda', 'Sustitución cámara', 3, '2026-10-21 17:51:54', NULL, 35.00, 'EN_REVISION'),
(5, 22, 'CORRECTIVO', 'Error display', 'Pantalla no enciende', 1, '2026-10-22 05:51:54', NULL, 0.00, 'PROGRAMADO');

--
-- Disparadores `mantenimientos`
--
DELIMITER $$
CREATE TRIGGER `trg_auditoria_mantenimiento` AFTER UPDATE ON `mantenimientos` FOR EACH ROW BEGIN
    IF OLD.estado <> NEW.estado THEN
        INSERT INTO logs_auditoria (id_manten, estado_anterior, estado_nuevo, id_empleado, fecha_hora)
        VALUES (NEW.id_manten, OLD.estado, NEW.estado, NEW.tecnico, NOW());
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_bicicleta_mantenimiento` AFTER INSERT ON `mantenimientos` FOR EACH ROW BEGIN
    IF NEW.estado IN ('PROGRAMADO', 'EN_REVISION', 'EN_REPARACION') THEN
        UPDATE bicicletas SET estado = 'MANTENIMIENTO' WHERE id_bicicleta = NEW.id_bicicleta;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mantenimiento_repuestos`
--

CREATE TABLE `mantenimiento_repuestos` (
  `id_mantenimiento_repuesto` bigint UNSIGNED NOT NULL,
  `id_manten` bigint UNSIGNED NOT NULL,
  `id_repuesto` bigint UNSIGNED NOT NULL,
  `cantidad` int UNSIGNED NOT NULL DEFAULT '1',
  `precio_unitario` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metodos_pago`
--

CREATE TABLE `metodos_pago` (
  `id_metodo` bigint UNSIGNED NOT NULL,
  `id_usuario` bigint UNSIGNED NOT NULL,
  `tipo` enum('EFECTIVO','TARJETA_CREDITO','TARJETA_DEBITO','PSE','NEQUI','DAVIPLATA','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `metodos_pago`
--

INSERT INTO `metodos_pago` (`id_metodo`, `id_usuario`, `tipo`, `activo`, `fecha_creacion`) VALUES
(1, 7, 'TARJETA_CREDITO', 1, '2026-10-22 17:51:54'),
(2, 8, 'NEQUI', 1, '2026-10-22 17:51:54'),
(3, 9, 'PSE', 1, '2026-10-22 17:51:54'),
(4, 10, 'TARJETA_DEBITO', 1, '2026-10-22 17:51:54'),
(5, 11, 'DAVIPLATA', 1, '2026-10-22 17:51:54'),
(6, 12, 'OTRO', 1, '2026-10-22 17:51:54'),
(7, 13, 'EFECTIVO', 1, '2026-10-22 17:51:54'),
(8, 14, 'TARJETA_CREDITO', 1, '2026-10-22 17:51:54');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registro_energia`
--

CREATE TABLE `registro_energia` (
  `id_registro` bigint UNSIGNED NOT NULL,
  `id_estacion` int UNSIGNED NOT NULL,
  `cantidad_kwh` decimal(12,3) NOT NULL,
  `costo_kwh` decimal(12,4) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_costo` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estado_registro` enum('PENDIENTE','PAGADO','ANULADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `descripcion` text COLLATE utf8mb4_unicode_ci
) ;

--
-- Volcado de datos para la tabla `registro_energia`
--

INSERT INTO `registro_energia` (`id_registro`, `id_estacion`, `cantidad_kwh`, `costo_kwh`, `fecha_registro`, `total_costo`, `estado_registro`, `descripcion`) VALUES
(1, 1, 50.500, 0.1500, '2026-10-22 17:51:54', 7.58, 'PAGADO', 'Factura enero - Estación Centro'),
(2, 2, 42.200, 0.1500, '2026-10-22 17:51:54', 6.33, 'PAGADO', 'Factura enero - Estación Universidad'),
(3, 3, 28.750, 0.1500, '2026-10-22 17:51:54', 4.31, 'PAGADO', 'Factura enero - Estación Hospital'),
(4, 4, 65.000, 0.1500, '2026-10-22 17:51:54', 9.75, 'PAGADO', 'Factura enero - Estación Parque'),
(5, 5, 78.300, 0.1500, '2026-10-22 17:51:54', 11.75, 'PENDIENTE', 'Factura enero - Estación Tren'),
(6, 1, 48.100, 0.1550, '2026-10-22 17:51:54', 7.46, 'PENDIENTE', 'Factura febrero - Estación Centro'),
(7, 2, 40.500, 0.1550, '2026-10-22 17:51:54', 6.28, 'PENDIENTE', 'Factura febrero - Estación Universidad');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int UNSIGNED NOT NULL,
  `codigo_rol` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_rol` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permisos` json DEFAULT NULL,
  `nivel_acceso` tinyint UNSIGNED NOT NULL DEFAULT '1'
) ;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `codigo_rol`, `nombre_rol`, `permisos`, `nivel_acceso`) VALUES
(1, 'ADMIN', 'Administrador', '{\"all\": true}', 10),
(2, 'SUPERVISOR', 'Supervisor', '{\"read\": true, \"write\": true, \"reports\": true}', 8),
(3, 'OPERADOR', 'Operador', '{\"read\": true, \"write\": true}', 5),
(4, 'TECNICO', 'Técnico', '{\"read\": true, \"maintenance\": true}', 4),
(5, 'CLIENTE', 'Cliente', '{\"read\": true, \"rent\": true}', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transacciones`
--

CREATE TABLE `transacciones` (
  `id_transaccion` bigint UNSIGNED NOT NULL,
  `id_alquiler` bigint UNSIGNED NOT NULL,
  `id_metodo_pago` bigint UNSIGNED NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `estado_pago` enum('PENDIENTE','APROBADO','RECHAZADO','ANULADO','REEMBOLSADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `referencia_ext` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_proceso` datetime DEFAULT NULL,
  `imagen_verificar` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `transacciones`
--

INSERT INTO `transacciones` (`id_transaccion`, `id_alquiler`, `id_metodo_pago`, `monto`, `estado_pago`, `referencia_ext`, `fecha_proceso`, `imagen_verificar`) VALUES
(1, 1, 1, 15.00, 'APROBADO', 'TXN-001', '2025-01-05 12:00:00', NULL),
(2, 2, 2, 17.00, 'APROBADO', 'TXN-002', '2025-01-08 15:00:00', NULL),
(3, 3, 3, 12.00, 'APROBADO', 'TXN-003', '2025-01-10 13:00:00', NULL),
(4, 4, 1, 18.00, 'APROBADO', 'TXN-004', '2025-01-12 19:00:00', NULL),
(5, 5, 2, 22.00, 'APROBADO', 'TXN-005', '2025-01-15 12:00:00', NULL),
(6, 6, 3, 16.50, 'APROBADO', 'TXN-006', '2025-01-18 12:00:00', NULL),
(7, 7, 1, 12.60, 'APROBADO', 'TXN-007', '2025-01-20 18:00:00', NULL),
(8, 8, 2, 19.00, 'APROBADO', 'TXN-008', '2025-01-22 12:00:00', NULL),
(9, 9, 3, 22.50, 'APROBADO', 'TXN-009', '2025-01-24 17:00:00', NULL),
(10, 10, 1, 17.50, 'APROBADO', 'TXN-010', '2025-01-26 13:00:00', NULL),
(11, 11, 2, 13.00, 'APROBADO', 'TXN-011', '2025-01-27 14:00:00', NULL),
(12, 12, 3, 18.90, 'APROBADO', 'TXN-012', '2025-01-28 18:00:00', NULL),
(13, 13, 1, 16.20, 'APROBADO', 'TXN-013', '2026-10-22 15:51:54', NULL),
(14, 14, 2, 22.00, 'APROBADO', 'TXN-014', '2026-10-22 16:51:54', NULL),
(15, 15, 3, 17.00, 'APROBADO', 'TXN-015', '2026-10-22 17:21:54', NULL),
(16, 16, 1, 16.00, 'PENDIENTE', 'TXN-016', NULL, 'comprobante_1.jpg'),
(17, 17, 2, 14.50, 'PENDIENTE', 'TXN-017', NULL, 'comprobante_2.jpg');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` bigint UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO','BLOQUEADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `fecha_registro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `es_cliente_frecuente` tinyint(1) NOT NULL DEFAULT '0',
  `cantidad_alquileres` int UNSIGNED NOT NULL DEFAULT '0',
  `fecha_ultima_evaluacion` datetime DEFAULT NULL,
  `rol_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `uuid`, `nombre`, `email`, `password_hash`, `telefono`, `estado`, `fecha_registro`, `es_cliente_frecuente`, `cantidad_alquileres`, `fecha_ultima_evaluacion`, `rol_id`) VALUES
(1, '2d7815dd-ce6b-11f1-9fb7-b44506137da9', 'Carlos Admin', 'admin@ebike.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '600111111', 'ACTIVO', '2026-10-22 17:51:54', 0, 0, NULL, 1),
(2, '2d781fbf-ce6b-11f1-9fb7-b44506137da9', 'María Supervisora', 'supervisor@ebike.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '600222222', 'ACTIVO', '2026-10-22 17:51:54', 0, 0, NULL, 2),
(3, '2d7825d4-ce6b-11f1-9fb7-b44506137da9', 'Juan Operador', 'operador@ebike.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '600333333', 'ACTIVO', '2026-10-22 17:51:54', 0, 0, NULL, 3),
(4, '2d782a9b-ce6b-11f1-9fb7-b44506137da9', 'Ana Operadora', 'operador2@ebike.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '600444444', 'ACTIVO', '2026-10-22 17:51:54', 0, 0, NULL, 3),
(5, '2d782ea2-ce6b-11f1-9fb7-b44506137da9', 'Pedro Técnico', 'tecnico@ebike.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '600555555', 'ACTIVO', '2026-10-22 17:51:54', 0, 0, NULL, 4),
(6, '2d783270-ce6b-11f1-9fb7-b44506137da9', 'Laura García', 'laura@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '611111111', 'ACTIVO', '2026-10-22 17:51:54', 1, 15, NULL, 5),
(7, '2d783654-ce6b-11f1-9fb7-b44506137da9', 'Miguel López', 'miguel@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '622222222', 'ACTIVO', '2026-10-22 17:51:54', 1, 8, NULL, 5),
(8, '2d783a54-ce6b-11f1-9fb7-b44506137da9', 'Sofía Martín', 'sofia@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '633333333', 'ACTIVO', '2026-10-22 17:51:54', 1, 12, NULL, 5),
(9, '2d783e31-ce6b-11f1-9fb7-b44506137da9', 'David Ruiz', 'david@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '644444444', 'ACTIVO', '2026-10-22 17:51:54', 0, 3, NULL, 5),
(10, '2d78421c-ce6b-11f1-9fb7-b44506137da9', 'Elena Torres', 'elena@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '655555555', 'ACTIVO', '2026-10-22 17:51:54', 0, 5, NULL, 5),
(11, '2d784963-ce6b-11f1-9fb7-b44506137da9', 'Pablo Jiménez', 'pablo@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '666666666', 'ACTIVO', '2026-10-22 17:51:54', 0, 2, NULL, 5),
(12, '2d784ecf-ce6b-11f1-9fb7-b44506137da9', 'Carmen Serrano', 'carmen@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '677777777', 'ACTIVO', '2026-10-22 17:51:54', 0, 1, NULL, 5),
(13, '2d7852d3-ce6b-11f1-9fb7-b44506137da9', 'Jorge Moreno', 'jorge@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '688888888', 'ACTIVO', '2026-10-22 17:51:54', 0, 0, NULL, 5),
(14, '2d7854d0-ce6b-11f1-9fb7-b44506137da9', 'Lucía Molina', 'lucia@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '699999999', 'ACTIVO', '2026-10-22 17:51:54', 0, 0, NULL, 5),
(15, '8c02a604-cebc-11f1-94d0-b44506137da9', 'alejandra martinez', 'martinez@gmail.com', '12345', '23456712', 'ACTIVO', '2026-10-23 03:34:22', 0, 0, NULL, 5);

--
-- Disparadores `usuarios`
--
DELIMITER $$
CREATE TRIGGER `trg_usuarios_uuid` BEFORE INSERT ON `usuarios` FOR EACH ROW BEGIN
    IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
        SET NEW.uuid = UUID();
    END IF;
END
$$
DELIMITER ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alertas_energia`
--
ALTER TABLE `alertas_energia`
  ADD PRIMARY KEY (`id_alerta`),
  ADD KEY `idx_alertas_bateria` (`id_bateria`),
  ADD KEY `idx_alertas_estado` (`estado_alerta`),
  ADD KEY `idx_alertas_prioridad` (`nivel_prioridad`);

--
-- Indices de la tabla `alquileres`
--
ALTER TABLE `alquileres`
  ADD PRIMARY KEY (`id_alquiler`),
  ADD UNIQUE KEY `uuid_alquiler` (`uuid_alquiler`),
  ADD KEY `fk_alquiler_estacion_origen` (`estacion_orig`),
  ADD KEY `fk_alquiler_estacion_destino` (`estacion_dest`),
  ADD KEY `idx_alquiler_usuario` (`id_usuario`),
  ADD KEY `idx_alquiler_bicicleta` (`id_bicicleta`),
  ADD KEY `idx_alquiler_estado` (`estado`),
  ADD KEY `idx_alquiler_fecha_inicio` (`fecha_inicio`),
  ADD KEY `idx_alquiler_fecha_fin` (`fecha_fin`);

--
-- Indices de la tabla `baterias`
--
ALTER TABLE `baterias`
  ADD PRIMARY KEY (`id_bateria`),
  ADD UNIQUE KEY `codigo_bateria` (`codigo_bateria`),
  ADD KEY `idx_baterias_bicicleta` (`id_bicicleta`),
  ADD KEY `idx_baterias_estado` (`estado`);

--
-- Indices de la tabla `bicicletas`
--
ALTER TABLE `bicicletas`
  ADD PRIMARY KEY (`id_bicicleta`),
  ADD UNIQUE KEY `codigo_qr` (`codigo_qr`),
  ADD UNIQUE KEY `num_serie` (`num_serie`),
  ADD KEY `idx_bicicletas_estacion` (`estacion_act`),
  ADD KEY `idx_bicicletas_estado` (`estado`),
  ADD KEY `idx_bicicletas_bateria` (`nivel_bateria`);

--
-- Indices de la tabla `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`id_empleado`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_empleados_estado` (`estado`);

--
-- Indices de la tabla `estaciones`
--
ALTER TABLE `estaciones`
  ADD PRIMARY KEY (`id_estacion`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_estaciones_estado` (`estado`);

--
-- Indices de la tabla `historial_carga`
--
ALTER TABLE `historial_carga`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `idx_historial_bateria` (`id_bateria`),
  ADD KEY `idx_historial_estacion` (`id_estacion`),
  ADD KEY `idx_historial_fecha` (`fecha_inicio`);

--
-- Indices de la tabla `inventario_costos`
--
ALTER TABLE `inventario_costos`
  ADD PRIMARY KEY (`id_repuesto`);

--
-- Indices de la tabla `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_logs_mantenimiento` (`id_manten`),
  ADD KEY `idx_logs_empleado` (`id_empleado`),
  ADD KEY `idx_logs_fecha` (`fecha_hora`);

--
-- Indices de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD PRIMARY KEY (`id_manten`),
  ADD KEY `idx_mantenimiento_bicicleta` (`id_bicicleta`),
  ADD KEY `idx_mantenimiento_estado` (`estado`),
  ADD KEY `idx_mantenimiento_tecnico` (`tecnico`),
  ADD KEY `idx_mantenimiento_fecha` (`fecha_ingreso`);

--
-- Indices de la tabla `mantenimiento_repuestos`
--
ALTER TABLE `mantenimiento_repuestos`
  ADD PRIMARY KEY (`id_mantenimiento_repuesto`),
  ADD UNIQUE KEY `id_manten` (`id_manten`,`id_repuesto`),
  ADD KEY `fk_mr_repuesto` (`id_repuesto`);

--
-- Indices de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  ADD PRIMARY KEY (`id_metodo`),
  ADD KEY `idx_metodos_usuario` (`id_usuario`),
  ADD KEY `idx_metodos_activo` (`activo`);

--
-- Indices de la tabla `registro_energia`
--
ALTER TABLE `registro_energia`
  ADD PRIMARY KEY (`id_registro`),
  ADD KEY `idx_energia_estacion` (`id_estacion`),
  ADD KEY `idx_energia_fecha` (`fecha_registro`),
  ADD KEY `idx_energia_estado` (`estado_registro`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `codigo_rol` (`codigo_rol`);

--
-- Indices de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD PRIMARY KEY (`id_transaccion`),
  ADD KEY `idx_transacciones_alquiler` (`id_alquiler`),
  ADD KEY `idx_transacciones_metodo` (`id_metodo_pago`),
  ADD KEY `idx_transacciones_estado` (`estado_pago`),
  ADD KEY `idx_transacciones_fecha` (`fecha_proceso`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_usuarios_rol` (`rol_id`),
  ADD KEY `idx_usuarios_estado` (`estado`),
  ADD KEY `idx_usuarios_frecuente` (`es_cliente_frecuente`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alertas_energia`
--
ALTER TABLE `alertas_energia`
  MODIFY `id_alerta` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `alquileres`
--
ALTER TABLE `alquileres`
  MODIFY `id_alquiler` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `baterias`
--
ALTER TABLE `baterias`
  MODIFY `id_bateria` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `bicicletas`
--
ALTER TABLE `bicicletas`
  MODIFY `id_bicicleta` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id_empleado` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `estaciones`
--
ALTER TABLE `estaciones`
  MODIFY `id_estacion` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_carga`
--
ALTER TABLE `historial_carga`
  MODIFY `id_historial` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inventario_costos`
--
ALTER TABLE `inventario_costos`
  MODIFY `id_repuesto` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  MODIFY `id_log` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  MODIFY `id_manten` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mantenimiento_repuestos`
--
ALTER TABLE `mantenimiento_repuestos`
  MODIFY `id_mantenimiento_repuesto` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  MODIFY `id_metodo` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `registro_energia`
--
ALTER TABLE `registro_energia`
  MODIFY `id_registro` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  MODIFY `id_transaccion` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alertas_energia`
--
ALTER TABLE `alertas_energia`
  ADD CONSTRAINT `fk_alerta_bateria` FOREIGN KEY (`id_bateria`) REFERENCES `baterias` (`id_bateria`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `alquileres`
--
ALTER TABLE `alquileres`
  ADD CONSTRAINT `fk_alquiler_bicicleta` FOREIGN KEY (`id_bicicleta`) REFERENCES `bicicletas` (`id_bicicleta`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alquiler_estacion_destino` FOREIGN KEY (`estacion_dest`) REFERENCES `estaciones` (`id_estacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alquiler_estacion_origen` FOREIGN KEY (`estacion_orig`) REFERENCES `estaciones` (`id_estacion`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alquiler_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Filtros para la tabla `baterias`
--
ALTER TABLE `baterias`
  ADD CONSTRAINT `fk_bateria_bicicleta` FOREIGN KEY (`id_bicicleta`) REFERENCES `bicicletas` (`id_bicicleta`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `bicicletas`
--
ALTER TABLE `bicicletas`
  ADD CONSTRAINT `fk_bicicletas_estaciones` FOREIGN KEY (`estacion_act`) REFERENCES `estaciones` (`id_estacion`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_carga`
--
ALTER TABLE `historial_carga`
  ADD CONSTRAINT `fk_historial_bateria` FOREIGN KEY (`id_bateria`) REFERENCES `baterias` (`id_bateria`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historial_estacion` FOREIGN KEY (`id_estacion`) REFERENCES `estaciones` (`id_estacion`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Filtros para la tabla `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  ADD CONSTRAINT `fk_log_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_log_mantenimiento` FOREIGN KEY (`id_manten`) REFERENCES `mantenimientos` (`id_manten`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD CONSTRAINT `fk_mantenimiento_bicicleta` FOREIGN KEY (`id_bicicleta`) REFERENCES `bicicletas` (`id_bicicleta`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mantenimiento_tecnico` FOREIGN KEY (`tecnico`) REFERENCES `empleados` (`id_empleado`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `mantenimiento_repuestos`
--
ALTER TABLE `mantenimiento_repuestos`
  ADD CONSTRAINT `fk_mr_mantenimiento` FOREIGN KEY (`id_manten`) REFERENCES `mantenimientos` (`id_manten`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mr_repuesto` FOREIGN KEY (`id_repuesto`) REFERENCES `inventario_costos` (`id_repuesto`) ON DELETE RESTRICT;

--
-- Filtros para la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  ADD CONSTRAINT `fk_metodo_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `registro_energia`
--
ALTER TABLE `registro_energia`
  ADD CONSTRAINT `fk_registro_estacion` FOREIGN KEY (`id_estacion`) REFERENCES `estaciones` (`id_estacion`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Filtros para la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD CONSTRAINT `fk_transaccion_alquiler` FOREIGN KEY (`id_alquiler`) REFERENCES `alquileres` (`id_alquiler`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transaccion_metodo` FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodos_pago` (`id_metodo`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id_rol`) ON DELETE RESTRICT ON UPDATE CASCADE;

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`root`@`localhost` EVENT `ev_actualizar_clientes_frecuentes` ON SCHEDULE EVERY 1 DAY STARTS '2026-10-16 17:12:18' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    UPDATE usuarios u
    LEFT JOIN (
        SELECT id_usuario, COUNT(*) AS cantidad
        FROM alquileres
        WHERE estado = 'FINALIZADO' AND fecha_fin >= NOW() - INTERVAL 30 DAY
        GROUP BY id_usuario
    ) a ON u.id_usuario = a.id_usuario
    SET
        u.cantidad_alquileres = COALESCE(a.cantidad, 0),
        u.es_cliente_frecuente = CASE WHEN COALESCE(a.cantidad, 0) >= 5 THEN TRUE ELSE FALSE END,
        u.fecha_ultima_evaluacion = NOW();
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

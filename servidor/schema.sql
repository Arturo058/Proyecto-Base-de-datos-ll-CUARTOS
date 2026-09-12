-- ==============================================================================
-- Sistema de Información - Renta de Cuartos (v2)
-- Login estricto, sesión única por usuario, paneles Admin / Inquilino.
-- ==============================================================================

-- Fuerza la codificación de esta sesión de importación a utf8mb4 para que los
-- acentos y caracteres especiales del propio archivo se guarden correctamente
-- (sin esto, algunos clientes asumen latin1 y los acentos quedan corruptos).

SET NAMES utf8mb4;

USE renta_cuartos_db;

-- ------------------------------------------------------------------------------
-- Tabla: usuarios
-- rol determina el panel (admin | inquilino).
-- session_token implementa "sesión única": al iniciar sesión en un nuevo
-- dispositivo se genera un token nuevo; la sesión anterior deja de ser válida
-- porque su token ya no coincide con el guardado en esta columna.
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    rol             ENUM('admin', 'inquilino') NOT NULL DEFAULT 'inquilino',
    session_token   VARCHAR(64)  NULL,
    creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_usuarios_username UNIQUE (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin de prueba -> usuario: admin_cuartos | contraseña: Admin123!
INSERT INTO usuarios (username, password_hash, rol)
VALUES ('admin_cuartos', '$2b$12$rIbJZ7PrGGWDNQVDUyTHW.6vdErp.4lI7dYZmQYhaP4D/vFv2ZyAe', 'admin')
ON DUPLICATE KEY UPDATE username = username;

-- Inquilino de prueba -> usuario: inquilino_101 | contraseña: Inquilino123!
INSERT INTO usuarios (username, password_hash, rol)
VALUES ('inquilino_101', '$2b$12$/m1kOxzWT4QwX/bq3r7we.IYiqVX3xcgJ/8ERIqy7bRJL/ZVCtcyS', 'inquilino')
ON DUPLICATE KEY UPDATE username = username;

-- ------------------------------------------------------------------------------
-- Tabla: cuartos
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cuartos (
    cuarto_id       INT AUTO_INCREMENT PRIMARY KEY,
    numero_cuarto   VARCHAR(20)     NOT NULL,
    precio_mensual  DECIMAL(10,2)   NOT NULL,
    estado          VARCHAR(20)     NOT NULL DEFAULT 'Disponible',
    creado_en       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_numero_cuarto UNIQUE (numero_cuarto),
    CONSTRAINT chk_precio_mensual_positivo CHECK (precio_mensual > 0),
    CONSTRAINT chk_estado_valido CHECK (estado IN ('Disponible', 'Ocupado', 'Mantenimiento'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cuartos (numero_cuarto, precio_mensual, estado) VALUES
    ('101', 3500.00, 'Disponible'),
    ('102', 4200.00, 'Ocupado'),
    ('103', 3800.00, 'Mantenimiento')
ON DUPLICATE KEY UPDATE numero_cuarto = numero_cuarto;

-- ------------------------------------------------------------------------------
-- Tabla: inquilinos
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inquilinos (
    inquilino_id            INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id              INT          NOT NULL,
    cuarto_id               INT          NOT NULL,
    nombre_completo         VARCHAR(120) NOT NULL,
    telefono                VARCHAR(20)  NULL,
    correo                  VARCHAR(120) NULL,
    personas                INT          NOT NULL DEFAULT 1,
    fecha_inicio_contrato   DATE         NOT NULL,
    fecha_fin_contrato      DATE         NULL,
    activo                  TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_inquilino_usuario UNIQUE (usuario_id),
    CONSTRAINT chk_personas_positivo CHECK (personas > 0),
    CONSTRAINT fk_inquilino_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_inquilino_cuarto  FOREIGN KEY (cuarto_id)  REFERENCES cuartos(cuarto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO inquilinos (usuario_id, cuarto_id, nombre_completo, telefono, correo, personas, fecha_inicio_contrato, activo)
SELECT u.id, c.cuarto_id, 'Juan Pérez López', '2721234567', 'juan.perez@correo.com', 2, '2026-03-10', 1
FROM usuarios u, cuartos c
WHERE u.username = 'inquilino_101' AND c.numero_cuarto = '102'
ON DUPLICATE KEY UPDATE nombre_completo = nombre_completo;

-- ------------------------------------------------------------------------------
-- Tabla: reportes_mantenimiento
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reportes_mantenimiento (
    reporte_id            INT AUTO_INCREMENT PRIMARY KEY,
    inquilino_id          INT           NOT NULL,
    cuarto_id             INT           NOT NULL,
    titulo                VARCHAR(150)  NOT NULL,
    descripcion           VARCHAR(500)  NOT NULL,
    prioridad             ENUM('Baja', 'Media', 'Alta') NOT NULL DEFAULT 'Media',
    estado                ENUM('Pendiente', 'En Proceso', 'Resuelto', 'Cancelado') NOT NULL DEFAULT 'Pendiente',
    fecha_creacion         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reporte_inquilino FOREIGN KEY (inquilino_id) REFERENCES inquilinos(inquilino_id) ON DELETE CASCADE,
    CONSTRAINT fk_reporte_cuarto    FOREIGN KEY (cuarto_id)    REFERENCES cuartos(cuarto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO reportes_mantenimiento (inquilino_id, cuarto_id, titulo, descripcion, prioridad, estado)
SELECT i.inquilino_id, i.cuarto_id, 'Fuga en el lavabo', 'La llave del lavabo gotea constantemente desde hace una semana.', 'Media', 'Pendiente'
FROM inquilinos i JOIN usuarios u ON u.id = i.usuario_id
WHERE u.username = 'inquilino_101'
LIMIT 1;

-- ------------------------------------------------------------------------------
-- Tabla: llamadas_atencion
-- motivo lo redacta el Admin; descargo lo redacta el inquilino como apelación.
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llamadas_atencion (
    llamada_id      INT AUTO_INCREMENT PRIMARY KEY,
    inquilino_id    INT NOT NULL,
    motivo          VARCHAR(500) NOT NULL,
    descargo        VARCHAR(500) NULL,
    estado          ENUM('Aplicada', 'En Revision') NOT NULL DEFAULT 'Aplicada',
    fecha_creacion  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_descargo  TIMESTAMP NULL,
    CONSTRAINT fk_llamada_inquilino FOREIGN KEY (inquilino_id) REFERENCES inquilinos(inquilino_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO llamadas_atencion (inquilino_id, motivo, estado)
SELECT i.inquilino_id, 'Ruido excesivo reportado por vecinos después de las 11pm.', 'Aplicada'
FROM inquilinos i JOIN usuarios u ON u.id = i.usuario_id
WHERE u.username = 'inquilino_101'
LIMIT 1;

-- ------------------------------------------------------------------------------
-- Seguridad: Principio de Menor Privilegio
-- ------------------------------------------------------------------------------
CREATE USER IF NOT EXISTS 'web_user'@'%' IDENTIFIED BY 'CuartosSeguros2026!';
GRANT SELECT, INSERT, UPDATE, DELETE ON renta_cuartos_db.* TO 'web_user'@'%';
FLUSH PRIVILEGES;

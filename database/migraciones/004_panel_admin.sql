-- 004: Panel de administración (/admin): usuarios, intentos de acceso y bitácora de cambios
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS administradores (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario         VARCHAR(40)  NOT NULL,
  nombre          VARCHAR(80)  NOT NULL DEFAULT '',
  hash            VARCHAR(255) NOT NULL,
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  ultimo_acceso   DATETIME     NULL,
  creado          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Para frenar ataques de fuerza bruta al inicio de sesión
CREATE TABLE IF NOT EXISTS intentos_login (
  id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip       VARCHAR(45)  NOT NULL,
  usuario  VARCHAR(40)  NOT NULL,
  exito    TINYINT(1)   NOT NULL DEFAULT 0,
  fecha    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_intentos_ip (ip, fecha),
  KEY idx_intentos_usuario (usuario, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quién cambió qué y cuándo
CREATE TABLE IF NOT EXISTS bitacora (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id  INT UNSIGNED NULL,
  usuario   VARCHAR(40)  NOT NULL DEFAULT '',
  accion    VARCHAR(40)  NOT NULL,
  detalle   TEXT         NOT NULL,
  fecha     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bitacora_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

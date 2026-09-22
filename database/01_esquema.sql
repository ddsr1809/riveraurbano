-- =====================================================================
-- riveraurbano.com — Esquema de base de datos (MariaDB 10.6+)
-- Textos del sitio en varios idiomas + configuración de contacto
-- =====================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS rivera
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rivera;

-- Idiomas disponibles en el sitio
CREATE TABLE IF NOT EXISTS idiomas (
  codigo       CHAR(2)      NOT NULL,              -- es, en
  nombre       VARCHAR(40)  NOT NULL,              -- Español, English
  locale       VARCHAR(10)  NOT NULL,              -- es_MX, en_US (para Open Graph)
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  por_defecto  TINYINT(1)   NOT NULL DEFAULT 0,
  orden        TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Todos los textos del sitio. Una fila por clave e idioma.
-- Las llaves {entre_llaves} se sustituyen al mostrar el texto (ej. {lote}).
CREATE TABLE IF NOT EXISTS textos (
  clave        VARCHAR(100) NOT NULL,              -- ej. hero.titulo
  idioma       CHAR(2)      NOT NULL,
  valor        TEXT         NOT NULL,
  actualizado  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clave, idioma),
  KEY idx_textos_idioma (idioma),
  CONSTRAINT fk_textos_idioma FOREIGN KEY (idioma) REFERENCES idiomas (codigo)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos que no dependen del idioma: teléfono, correo, video, mapa...
CREATE TABLE IF NOT EXISTS configuracion (
  clave        VARCHAR(60)  NOT NULL,
  valor        VARCHAR(500) NOT NULL DEFAULT '',
  descripcion  VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista útil para revisar qué textos faltan por traducir
CREATE OR REPLACE VIEW textos_sin_traducir AS
SELECT t.clave, i.codigo AS idioma_faltante
FROM (SELECT DISTINCT clave FROM textos) t
CROSS JOIN idiomas i
LEFT JOIN textos x ON x.clave = t.clave AND x.idioma = i.codigo
WHERE i.activo = 1 AND x.clave IS NULL;

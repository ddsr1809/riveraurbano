-- 005: Registro de visitas (quién entra, de dónde y si es persona o bot)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS visitas (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha             DATETIME     NOT NULL,
  token             CHAR(32)     NOT NULL,              -- enlaza la visita con las señales del navegador
  visitante         CHAR(16)     NOT NULL,              -- huella diaria anónima (IP + navegador + día), sin cookies
  ip                VARCHAR(45)  NOT NULL,
  metodo            VARCHAR(8)   NOT NULL DEFAULT 'GET',
  ruta              VARCHAR(255) NOT NULL,
  idioma            CHAR(2)      NOT NULL DEFAULT '',
  idioma_navegador  VARCHAR(100) NOT NULL DEFAULT '',
  referente         VARCHAR(500) NOT NULL DEFAULT '',
  fuente            VARCHAR(100) NOT NULL DEFAULT '',   -- Google, Facebook, Directo, dominio…
  utm_source        VARCHAR(100) NOT NULL DEFAULT '',
  utm_medium        VARCHAR(100) NOT NULL DEFAULT '',
  utm_campaign      VARCHAR(100) NOT NULL DEFAULT '',
  ua                VARCHAR(500) NOT NULL DEFAULT '',
  navegador         VARCHAR(40)  NOT NULL DEFAULT '',
  sistema           VARCHAR(40)  NOT NULL DEFAULT '',
  dispositivo       VARCHAR(20)  NOT NULL DEFAULT '',   -- Celular, Tableta, Computadora, Bot
  pais_codigo       CHAR(2)      NOT NULL DEFAULT '',
  pais              VARCHAR(80)  NOT NULL DEFAULT '',
  region            VARCHAR(80)  NOT NULL DEFAULT '',
  ciudad            VARCHAR(80)  NOT NULL DEFAULT '',
  codigo_postal     VARCHAR(20)  NOT NULL DEFAULT '',
  latitud           DECIMAL(8,4) NULL,
  longitud          DECIMAL(8,4) NULL,
  radio_km          SMALLINT UNSIGNED NULL,
  zona_horaria      VARCHAR(40)  NOT NULL DEFAULT '',
  asn               INT UNSIGNED NULL,
  organizacion      VARCHAR(150) NOT NULL DEFAULT '',   -- proveedor de internet o empresa dueña de la IP
  centro_datos      TINYINT(1)   NOT NULL DEFAULT 0,    -- IP de servidores (AWS, Google Cloud…), no de un hogar
  host_inverso      VARCHAR(255) NULL,                  -- se consulta al abrir el detalle
  tipo              VARCHAR(12)  NOT NULL,              -- humano, probable, sospechoso, bot, vista_previa, herramienta, interno
  nombre_bot        VARCHAR(60)  NOT NULL DEFAULT '',
  motivo            VARCHAR(255) NOT NULL DEFAULT '',
  js                TINYINT(1)   NOT NULL DEFAULT 0,    -- el navegador ejecutó JavaScript
  interaccion       TINYINT(1)   NOT NULL DEFAULT 0,    -- movió, tocó, desplazó o tecleó
  duracion_seg      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  pantalla          VARCHAR(20)  NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_visitas_token (token),
  KEY idx_visitas_fecha (fecha),
  KEY idx_visitas_tipo_fecha (tipo, fecha),
  KEY idx_visitas_ip (ip),
  KEY idx_visitas_visitante (visitante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acciones dentro de la visita: clic a WhatsApp, llamar, correo, mapa, reproducir video, enviar formulario
CREATE TABLE IF NOT EXISTS visitas_eventos (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visita_id BIGINT UNSIGNED NOT NULL,
  fecha     DATETIME     NOT NULL,
  evento    VARCHAR(20)  NOT NULL,
  detalle   VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_eventos_visita (visita_id),
  KEY idx_eventos_evento_fecha (evento, fecha),
  CONSTRAINT fk_eventos_visita FOREIGN KEY (visita_id) REFERENCES visitas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
  ('visitas_dias',    '180', 'Días que se guardan las visitas antes de borrarse'),
  ('visitas_ips_excluidas', '', 'IPs propias separadas por coma: sus visitas se marcan como internas'),
  ('visitas_sal', SHA2(CONCAT(RAND(), UUID(), NOW(6)), 256), 'Valor secreto para la huella anónima de visitantes');

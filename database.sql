-- =========================================================
--  Base de datos: agendafacil  (Sistema de citas - Santa Cruz)
--  Versión 2
--
--  Importar con:   mysql -u root -e "source C:/PAGINAWEB/database.sql"
--  o en HeidiSQL:  Archivo > Cargar archivo SQL > F9
--
--  ATENCIÓN: borra y vuelve a crear la base de datos (datos de prueba).
-- =========================================================

SET NAMES utf8mb4;

DROP DATABASE IF EXISTS agendafacil;
CREATE DATABASE agendafacil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agendafacil;

-- ---------- USUARIOS ----------
CREATE TABLE usuarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    email       VARCHAR(120) NOT NULL UNIQUE,
    telefono    VARCHAR(20),
    password    VARCHAR(255) NOT NULL,
    rol         ENUM('cliente','negocio','admin') NOT NULL DEFAULT 'cliente',
    activo      TINYINT(1) NOT NULL DEFAULT 1,
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- NEGOCIOS ----------
CREATE TABLE negocios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT NULL,                         -- dueño (rol negocio)
    nombre      VARCHAR(120) NOT NULL,
    categoria   VARCHAR(60)  NOT NULL,
    icono       VARCHAR(40)  DEFAULT 'store',     -- nombre de ícono Font Awesome
    zona        VARCHAR(60)  DEFAULT 'Centro',    -- barrio / zona de Santa Cruz
    direccion   VARCHAR(200),
    telefono    VARCHAR(20),
    whatsapp    VARCHAR(20),                      -- solo números, ej: 59170000001
    descripcion TEXT,
    foto        VARCHAR(255) NULL,                -- ruta local subida por el negocio
    activo      TINYINT(1) DEFAULT 1,
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_busqueda (categoria, zona)
) ENGINE=InnoDB;

-- ---------- SERVICIOS ----------
CREATE TABLE servicios (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id   INT NOT NULL,
    nombre       VARCHAR(100) NOT NULL,
    duracion_min INT NOT NULL DEFAULT 30,
    precio       DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- HORARIO SEMANAL ----------
-- dia: 1 = lunes ... 7 = domingo. Si un día no tiene fila, el negocio está cerrado.
CREATE TABLE horarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id  INT NOT NULL,
    dia         TINYINT NOT NULL,
    abre        TIME NOT NULL,
    cierra      TIME NOT NULL,
    UNIQUE KEY uq_dia (negocio_id, dia),
    FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- DÍAS LIBRES (feriados, vacaciones) ----------
CREATE TABLE dias_libres (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id  INT NOT NULL,
    fecha       DATE NOT NULL,
    motivo      VARCHAR(120),
    UNIQUE KEY uq_fecha (negocio_id, fecha),
    FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- CITAS ----------
CREATE TABLE citas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT NOT NULL,
    negocio_id  INT NOT NULL,
    servicio_id INT NOT NULL,
    fecha       DATE NOT NULL,
    hora        TIME NOT NULL,
    hora_fin    TIME NOT NULL,                    -- hora + duración del servicio
    estado      ENUM('pendiente','confirmada','cancelada','completada') DEFAULT 'pendiente',
    notas       VARCHAR(255),
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE,
    FOREIGN KEY (negocio_id)  REFERENCES negocios(id)  ON DELETE CASCADE,
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE,
    INDEX idx_agenda (negocio_id, fecha, hora)
) ENGINE=InnoDB;

-- ---------- CALIFICACIONES ----------
CREATE TABLE calificaciones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    cita_id     INT NOT NULL UNIQUE,              -- una calificación por cita
    usuario_id  INT NOT NULL,
    negocio_id  INT NOT NULL,
    estrellas   TINYINT NOT NULL,                 -- 1 a 5
    comentario  VARCHAR(500),
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cita_id)    REFERENCES citas(id)    ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (negocio_id) REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- RECUPERAR CONTRASEÑA ----------
CREATE TABLE password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT NOT NULL,
    token_hash  CHAR(64) NOT NULL UNIQUE,         -- se guarda el hash, nunca el token
    expira      DATETIME NOT NULL,
    usado       TINYINT(1) DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- INTENTOS DE LOGIN (anti fuerza bruta) ----------
CREATE TABLE intentos_login (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(120) NOT NULL,
    ip          VARCHAR(45)  NOT NULL,
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_intentos (email, ip, creado_en)
) ENGINE=InnoDB;

-- ---------- TOKENS DE LA API (app móvil) ----------
CREATE TABLE api_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT NOT NULL,
    token_hash  CHAR(64) NOT NULL UNIQUE,
    expira      DATETIME NOT NULL,
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- ENCUESTA DE SATISFACCIÓN ----------

CREATE TABLE encuestas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    general       TINYINT NOT NULL,            -- 1 a 5: ¿qué te pareció?
    facilidad     TINYINT NOT NULL,            -- 1 a 5: ¿fue fácil reservar?
    diseno        TINYINT NOT NULL,            -- 1 a 5: ¿te gustó el diseño?
    usaria        ENUM('si','tal_vez','no') NOT NULL,
    perfil        ENUM('cliente','negocio','estudiante','otro') NOT NULL,
    dispositivo   ENUM('celular','computadora','tablet') NOT NULL,
    gusto         VARCHAR(500),
    mejora        VARCHAR(500),
    ip_hash       CHAR(64) NOT NULL,           -- IP cifrada (anti-spam, sin guardar la IP real)
    creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_hash, creado_en)
) ENGINE=InnoDB;

-- ---------- SESIONES (necesario en Vercel: cada visita puede ir a otro servidor) ----------
CREATE TABLE IF NOT EXISTS sesiones (
    id          VARCHAR(128) NOT NULL PRIMARY KEY,
    datos       MEDIUMTEXT NOT NULL,
    actualizado INT UNSIGNED NOT NULL,
    INDEX idx_actualizado (actualizado)
) ENGINE=InnoDB;

-- ---------- FOTOS DE LOS NEGOCIOS (en Vercel no se pueden guardar archivos) ----------
CREATE TABLE IF NOT EXISTS fotos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    negocio_id  INT NOT NULL,
    mime        VARCHAR(30) NOT NULL,
    datos       MEDIUMBLOB NOT NULL,
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_negocio (negocio_id)
) ENGINE=InnoDB;

-- ---------- CHATBOT: conversación en curso con cada persona ----------
CREATE TABLE IF NOT EXISTS bot_conversaciones (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    canal          ENUM('telegram','whatsapp','web') NOT NULL,
    chat_id        VARCHAR(40) NOT NULL,            -- id del chat de Telegram o número de WhatsApp
    usuario_id     INT NULL,                        -- cliente vinculado (se crea al reservar)
    estado         VARCHAR(30) NOT NULL DEFAULT 'menu',
    datos          TEXT,                            -- JSON con lo elegido (negocio, servicio, fecha...)
    ultimo_mensaje VARCHAR(120),                    -- evita procesar dos veces el mismo mensaje
    actualizado    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_chat (canal, chat_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
--  DATOS DE PRUEBA
--  admin@agendafacil.com    / admin123  (administrador)
--  barberia@agendafacil.com / 123456    (vendedor - Barbería El Corte)
--  cliente@agendafacil.com  / 123456    (cliente)
-- =========================================================
INSERT INTO usuarios (nombre, email, telefono, password, rol) VALUES
('Administrador', 'admin@agendafacil.com',    '70000000', '$2y$10$ibHheMv99mxgWyIRG8J2mOocZgVjOcEqICMefKmFtFlvnXjHj1HzW', 'admin'),
('Carlos Rojas',  'barberia@agendafacil.com', '70000001', '$2y$10$faBNH182AFHsUM6szdE//uzCFj2h99KsZAyUC3RCa9.MvVrB6sHYC', 'negocio'),
('Ana Pérez',     'cliente@agendafacil.com',  '70000002', '$2y$10$faBNH182AFHsUM6szdE//uzCFj2h99KsZAyUC3RCa9.MvVrB6sHYC', 'cliente');

INSERT INTO negocios (usuario_id, nombre, categoria, icono, zona, direccion, telefono, whatsapp, descripcion) VALUES
(2,    'Barbería El Corte',     'Barbería',    'scissors',      'Equipetrol', 'Av. San Martín, 3er anillo',  '33300001', '59170000001', 'Cortes clásicos y modernos, arreglo de barba y más.'),
(NULL, 'Spa Armonía',           'Spa',         'spa',           'Urubó',      'Calle Libertad #120',          '33300002', '59170000002', 'Masajes y tratamientos faciales para relajarte.'),
(NULL, 'Clínica Dental Sonríe', 'Odontología', 'tooth',         'Centro',     'Calle Sucre #45, Casco Viejo', '33300003', '59170000003', 'Odontología general y estética dental.'),
(NULL, 'Uñas Glam',             'Manicure',    'hand-sparkles', 'Norte',      'Av. Banzer, 4to anillo',       '33300004', '59170000004', 'Manicure, pedicure y uñas acrílicas.');

INSERT INTO servicios (negocio_id, nombre, duracion_min, precio) VALUES
(1, 'Corte clásico',       30,  50.00),
(1, 'Corte + barba',       60,  80.00),
(2, 'Masaje relajante',    60, 200.00),
(2, 'Limpieza facial',     45, 150.00),
(3, 'Consulta general',    30, 100.00),
(3, 'Limpieza dental',     60, 180.00),
(4, 'Manicure',            45,  70.00),
(4, 'Uñas acrílicas',      90, 180.00);

-- Horarios: Barbería lun-sáb 09-19 | Spa todos los días 10-20 | Dental lun-vie 08-18 | Uñas lun-sáb 09-18
INSERT INTO horarios (negocio_id, dia, abre, cierra) VALUES
(1,1,'09:00','19:00'),(1,2,'09:00','19:00'),(1,3,'09:00','19:00'),(1,4,'09:00','19:00'),(1,5,'09:00','19:00'),(1,6,'09:00','19:00'),
(2,1,'10:00','20:00'),(2,2,'10:00','20:00'),(2,3,'10:00','20:00'),(2,4,'10:00','20:00'),(2,5,'10:00','20:00'),(2,6,'10:00','20:00'),(2,7,'10:00','16:00'),
(3,1,'08:00','18:00'),(3,2,'08:00','18:00'),(3,3,'08:00','18:00'),(3,4,'08:00','18:00'),(3,5,'08:00','18:00'),
(4,1,'09:00','18:00'),(4,2,'09:00','18:00'),(4,3,'09:00','18:00'),(4,4,'09:00','18:00'),(4,5,'09:00','18:00'),(4,6,'09:00','18:00');

INSERT INTO citas (usuario_id, negocio_id, servicio_id, fecha, hora, hora_fin, estado) VALUES
(3, 1, 1, CURDATE() + INTERVAL 1 DAY, '10:30:00', '11:00:00', 'confirmada'),
(3, 2, 3, CURDATE() + INTERVAL 3 DAY, '15:00:00', '16:00:00', 'pendiente'),
(3, 1, 2, CURDATE() - INTERVAL 5 DAY, '11:00:00', '12:00:00', 'completada');

-- Calificación de prueba de la cita completada
INSERT INTO calificaciones (cita_id, usuario_id, negocio_id, estrellas, comentario) VALUES
(3, 3, 1, 5, 'Calificación de prueba');

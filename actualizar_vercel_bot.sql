-- =========================================================
--  Agrega las tablas para VERCEL y el CHATBOT sin borrar tus datos.
--  Local:   mysql -u root agendafacil -e "source C:/PAGINAWEB/actualizar_vercel_bot.sql"
-- =========================================================
SET NAMES utf8mb4;

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

-- Si ya tenías la tabla del bot sin el canal 'web' (chat de la página):
ALTER TABLE bot_conversaciones MODIFY canal ENUM('telegram','whatsapp','web') NOT NULL;

-- =========================================================
--  Agrega la tabla de la ENCUESTA sin borrar tus datos.
--  Local:   mysql -u root agendafacil -e "source C:/PAGINAWEB/actualizar_encuesta.sql"
--  Hosting: phpMyAdmin > tu base > Importar este archivo
-- =========================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS encuestas (
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

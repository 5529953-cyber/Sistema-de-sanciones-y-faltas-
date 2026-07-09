-- ============================================================
-- SISFAL - Script de creación de Base de Datos
-- Ejecuta este script en tu servidor MySQL remoto
-- ============================================================

CREATE DATABASE IF NOT EXISTS sisfal_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sisfal_db;

-- ------------------------------------------------------------
-- Tabla: usuarios (administradores del sistema)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    apellido    VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    usuario     VARCHAR(60)  NOT NULL UNIQUE,
    contrasena  VARCHAR(255) NOT NULL,          -- bcrypt hash
    rol         ENUM('admin','docente','orientador') NOT NULL DEFAULT 'docente',
    activo      TINYINT(1) NOT NULL DEFAULT 1,
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario)
);

-- Usuario admin por defecto: admin / Admin1234!
INSERT IGNORE INTO usuarios (nombre, apellido, email, usuario, contrasena, rol)
VALUES ('Admin', 'SISFAL', 'admin@sisfal.edu', 'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Contraseña por defecto: password  (cámbiala inmediatamente)

-- ------------------------------------------------------------
-- Tabla: estudiantes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estudiantes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nie         VARCHAR(20)  NOT NULL UNIQUE,
    nombre      VARCHAR(100) NOT NULL,
    apellido    VARCHAR(100) NOT NULL,
    grado       VARCHAR(20)  NOT NULL,
    seccion     VARCHAR(5)   NOT NULL,
    fecha_nac   DATE,
    telefono    VARCHAR(20),
    email       VARCHAR(150),
    activo      TINYINT(1) NOT NULL DEFAULT 1,
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nie (nie),
    INDEX idx_nombre (nombre, apellido)
);

-- ------------------------------------------------------------
-- Tabla: tipos_falta
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_falta (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    descripcion TEXT,
    gravedad    ENUM('leve','moderada','grave') NOT NULL DEFAULT 'leve'
);

INSERT IGNORE INTO tipos_falta (id, nombre, gravedad) VALUES
(1, 'Impuntualidad',          'leve'),
(2, 'Conducta inapropiada',   'moderada'),
(3, 'Falta de respeto',       'moderada'),
(4, 'Daños a la propiedad',   'grave'),
(5, 'Agresión física',        'grave'),
(6, 'Ausencia injustificada', 'leve');

-- ------------------------------------------------------------
-- Tabla: faltas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS faltas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id   INT NOT NULL,
    tipo_falta_id   INT NOT NULL,
    descripcion     TEXT,
    fecha           DATE NOT NULL,
    registrado_por  INT NOT NULL,       -- usuario que registra
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id)  REFERENCES estudiantes(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_falta_id)  REFERENCES tipos_falta(id),
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    INDEX idx_estudiante (estudiante_id),
    INDEX idx_fecha (fecha)
);

-- ------------------------------------------------------------
-- Tabla: sanciones
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sanciones (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    falta_id        INT NOT NULL,
    estudiante_id   INT NOT NULL,
    tipo_sancion    ENUM('amonestacion','suspension','citacion_padre','otro') NOT NULL,
    descripcion     TEXT,
    fecha_inicio    DATE NOT NULL,
    fecha_fin       DATE,
    estado          ENUM('activa','cumplida','cancelada') NOT NULL DEFAULT 'activa',
    registrado_por  INT NOT NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (falta_id)       REFERENCES faltas(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id)  REFERENCES estudiantes(id) ON DELETE CASCADE,
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    INDEX idx_estudiante (estudiante_id),
    INDEX idx_estado (estado)
);

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================

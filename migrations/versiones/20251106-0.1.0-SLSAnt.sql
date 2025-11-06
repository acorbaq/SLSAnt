-- =============================================================================
-- MIGRACIÓN UNIFICADA PARA PRODUCCIÓN - SLSAnt
-- =============================================================================
-- Versión: 0.1.0
-- Fecha: 2025-11-06
-- Descripción: Schema completo inicial para sistema de trazabilidad alimentaria
--
-- IMPORTANTE:
-- - Ejecutar sobre base de datos vacía
-- - SQLite3 requerido
-- - Todos los IDs comienzan desde 1 (AUTOINCREMENT automático)
-- - Valores seed incluidos para tipos, unidades, alérgenos y roles
-- =============================================================================

PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

-- =============================================================================
-- TABLAS DE CATÁLOGO BASE
-- =============================================================================

-- Tabla de alérgenos (seed con 14 alérgenos obligatorios UE)
CREATE TABLE IF NOT EXISTS alergenos (
  id_alergeno INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL UNIQUE
);

INSERT INTO alergenos (nombre) VALUES
  ('Gluten'),
  ('Crustáceos'),
  ('Huevos'),
  ('Pescado'),
  ('Cacahuetes'),
  ('Soja'),
  ('Leche'),
  ('Frutos de cáscara'),
  ('Apio'),
  ('Mostaza'),
  ('Sésamo'),
  ('Dióxido de azufre y sulfitos'),
  ('Altramuces'),
  ('Moluscos');

-- Tabla de unidades de medida
CREATE TABLE IF NOT EXISTS unidades_medida (
  id_unidad INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  abreviatura TEXT NOT NULL,
  UNIQUE (nombre, abreviatura)
);

INSERT INTO unidades_medida (nombre, abreviatura) VALUES
  ('Kilogramo', 'kg'),
  ('Gramo', 'g'),
  ('Litro', 'l'),
  ('Mililitro', 'ml'),
  ('Unidad', 'ud'),
  ('Docena', 'dz'),
  ('Caja', 'caja'),
  ('Paquete', 'paq'),
  ('No especificado', 'n.c.');

-- Tabla de tipos de elaboración
CREATE TABLE IF NOT EXISTS tipo_elaboracion (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE,
    descripcion TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Trigger para evitar renombrados accidentales
CREATE TRIGGER IF NOT EXISTS tipo_elaboracion_prevent_rename
BEFORE UPDATE ON tipo_elaboracion
FOR EACH ROW
WHEN NEW.nombre IS NOT NULL AND TRIM(NEW.nombre) <> TRIM(OLD.nombre)
BEGIN
    SELECT RAISE(ABORT, 'No está permitido renombrar tipos de elaboración');
END;

INSERT INTO tipo_elaboracion (nombre, descripcion) VALUES
    ('Elaboración', 'Proceso de elaboración de productos.'),
    ('Escandallo',   'Proceso de cálculo de costes de productos.'),
    ('Envasado',     'Proceso de envasado de productos.'),
    ('Congelación',  'Proceso de congelación de productos.');

-- =============================================================================
-- GESTIÓN DE USUARIOS Y PERMISOS
-- =============================================================================

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  email TEXT,
  password TEXT NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT DEFAULT (datetime('now')),
  last_login TEXT
);

CREATE TABLE IF NOT EXISTS roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE
);

INSERT INTO roles (name) VALUES
  ('admin'),
  ('gestor'),
  ('calidad'),
  ('operador');

CREATE TABLE IF NOT EXISTS users_roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  role_id INTEGER NOT NULL,
  UNIQUE (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_users_roles_user ON users_roles(user_id);
CREATE INDEX IF NOT EXISTS idx_users_roles_role ON users_roles(role_id);

-- =============================================================================
-- INGREDIENTES Y RELACIONES
-- =============================================================================

CREATE TABLE IF NOT EXISTS ingredientes (
  id_ingrediente INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL UNIQUE,
  indicaciones TEXT
);

CREATE TABLE IF NOT EXISTS ingredientes_alergenos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_ingrediente INTEGER NOT NULL,
  id_alergeno INTEGER NOT NULL,
  UNIQUE (id_ingrediente, id_alergeno),
  FOREIGN KEY (id_ingrediente) REFERENCES ingredientes(id_ingrediente) ON DELETE CASCADE,
  FOREIGN KEY (id_alergeno) REFERENCES alergenos(id_alergeno) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_ingredientes_alergenos_ing ON ingredientes_alergenos(id_ingrediente);
CREATE INDEX IF NOT EXISTS idx_ingredientes_alergenos_alg ON ingredientes_alergenos(id_alergeno);

-- =============================================================================
-- ELABORADOS (RECETAS/ESCANDALLOS)
-- =============================================================================

CREATE TABLE IF NOT EXISTS elaborados (
  id_elaborado INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  descripcion TEXT,
  peso_obtenido NUMERIC NOT NULL,
  dias_viabilidad INTEGER NOT NULL,
  tipo INTEGER NOT NULL DEFAULT 1,
  FOREIGN KEY (tipo) REFERENCES tipo_elaboracion(id) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_elaborados_tipo ON elaborados(tipo);

-- Relación elaborados <-> ingredientes (con origen)
CREATE TABLE IF NOT EXISTS elaborados_ingredientes (
  id_elaborado INTEGER NOT NULL,
  id_ingrediente INTEGER NOT NULL,
  cantidad NUMERIC NOT NULL,
  id_unidad INTEGER NOT NULL,
  es_origen INTEGER NOT NULL DEFAULT 0 CHECK (es_origen IN (0,1)),
  PRIMARY KEY (id_elaborado, id_ingrediente),
  FOREIGN KEY (id_elaborado) REFERENCES elaborados(id_elaborado) ON DELETE CASCADE,
  FOREIGN KEY (id_ingrediente) REFERENCES ingredientes(id_ingrediente) ON DELETE RESTRICT,
  FOREIGN KEY (id_unidad) REFERENCES unidades_medida(id_unidad) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_elaborados_ingredientes_elaborado ON elaborados_ingredientes(id_elaborado);
CREATE INDEX IF NOT EXISTS idx_elaborados_ingredientes_ingrediente ON elaborados_ingredientes(id_ingrediente);

-- =============================================================================
-- GESTIÓN DE LOTES Y TRAZABILIDAD
-- =============================================================================

-- Lotes de producción/salida
CREATE TABLE IF NOT EXISTS lotes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    elaboracion_id INTEGER NOT NULL,
    numero_lote TEXT,
    fecha_produccion DATE NOT NULL,
    fecha_caducidad DATE,
    peso_total REAL NOT NULL,
    unidad_peso TEXT NOT NULL,
    temp_inicio REAL,
    temp_final REAL,
    parent_lote_id INTEGER,
    is_derivado INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT (CURRENT_TIMESTAMP),
    FOREIGN KEY (elaboracion_id) REFERENCES elaborados(id_elaborado) ON DELETE RESTRICT,
    FOREIGN KEY (parent_lote_id) REFERENCES lotes(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_lotes_elaboracion_id ON lotes(elaboracion_id);
CREATE INDEX IF NOT EXISTS idx_lotes_parent_id ON lotes(parent_lote_id);
CREATE INDEX IF NOT EXISTS idx_lotes_numero_lote ON lotes(numero_lote);

-- Trigger para evitar cambios en numero_lote una vez asignado
CREATE TRIGGER IF NOT EXISTS lotes_prevent_numero_change
BEFORE UPDATE ON lotes
FOR EACH ROW
WHEN NEW.numero_lote IS NOT NULL AND OLD.numero_lote IS NOT NULL AND NEW.numero_lote <> OLD.numero_lote
BEGIN
    SELECT RAISE(ABORT, 'Cambio de numero_lote no permitido sin proceso específico');
END;

-- Líneas de ingredientes en lotes (trazabilidad entrada)
CREATE TABLE IF NOT EXISTS lotes_ingredientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lote_elaboracion_id INTEGER NOT NULL,
    ingrediente_resultante TEXT,
    ingrediente_id INTEGER,
    peso REAL NOT NULL DEFAULT 0,
    porcentaje_origen REAL,
    referencia_proveedor TEXT,
    lote TEXT,
    fecha_caducidad TEXT,
    created_at DATETIME DEFAULT (CURRENT_TIMESTAMP),
    FOREIGN KEY (lote_elaboracion_id) REFERENCES lotes(id) ON DELETE CASCADE,
    FOREIGN KEY (ingrediente_id) REFERENCES ingredientes(id_ingrediente) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_lotes_ingredientes_lote_id ON lotes_ingredientes(lote_elaboracion_id);
CREATE INDEX IF NOT EXISTS idx_lotes_ingredientes_ingrediente ON lotes_ingredientes(ingrediente_id);

-- Cierres de lote (etiquetado/validación)
CREATE TABLE IF NOT EXISTS lotes_cierres (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lote_id INTEGER NOT NULL,
    gramos_gastados REAL DEFAULT 0,
    n_etiquetas INTEGER DEFAULT 0,
    modo TEXT,
    gramos_por_envase REAL,
    unidades REAL,
    usuario TEXT,
    metadata TEXT,
    created_at DATETIME DEFAULT (CURRENT_TIMESTAMP),
    FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_lotes_cierres_lote_id ON lotes_cierres(lote_id);

-- =============================================================================
-- VERIFICACIÓN FINAL
-- =============================================================================

-- Verificar integridad referencial
PRAGMA foreign_key_check;

-- Mostrar resumen de tablas creadas
SELECT 'Schema creado correctamente. Tablas:' AS mensaje;
SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;

PRAGMA foreign_keys = ON;
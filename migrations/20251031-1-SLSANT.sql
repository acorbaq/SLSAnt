-- Migration: tablas para gestión de lotes, líneas, productos comerciales y cierres (SQLite)
PRAGMA foreign_keys = ON;

-- Tabla lotes (lotes de salida / producción)
CREATE TABLE IF NOT EXISTS lotes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    elaboracion_id INTEGER NOT NULL, -- FK a elaborados.id_elaborado
    numero_lote TEXT,
    fecha_produccion DATE NOT NULL,
    fecha_caducidad DATE,
    peso_total REAL NOT NULL,
    unidad_peso TEXT NOT NULL,
    temp_inicio REAL,
    temp_final REAL,
    parent_lote_id INTEGER,          -- referencia al lote de origen (nullable)
    is_derivado INTEGER DEFAULT 0,   -- 0 = no, 1 = sí
    created_at DATETIME DEFAULT (CURRENT_TIMESTAMP),
    FOREIGN KEY (elaboracion_id) REFERENCES elaborados(id_elaborado) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (parent_lote_id) REFERENCES lotes(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_lotes_elaboracion_id ON lotes(elaboracion_id);
CREATE INDEX IF NOT EXISTS idx_lotes_parent_id ON lotes(parent_lote_id);

-- Tabla productos_comerciales (lotes/partidas de entrada, trazables)
CREATE TABLE IF NOT EXISTS productos_comerciales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ingrediente_id INTEGER, -- FK a ingredientes.id_ingrediente (opcional)
    nombre TEXT NOT NULL,
    referencia TEXT,
    fecha_caducidad DATE,
    peso_total REAL,        -- peso disponible en esta partida
    unidad_peso TEXT,
    cantidad REAL,         -- unidades si corresponde
    duracion_valor INTEGER, -- tiempo de conservación (valor)
    duracion_unidad TEXT,   -- ej. 'dias', 'meses'
    proveedor_id INTEGER,   -- opcional: referencia a tabla proveedores si existe
    created_at DATETIME DEFAULT (CURRENT_TIMESTAMP),
    FOREIGN KEY (ingrediente_id) REFERENCES ingredientes(id_ingrediente) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_prodcom_ingrediente_id ON productos_comerciales(ingrediente_id);

-- Tabla lotes_ingredientes (líneas del lote)
CREATE TABLE IF NOT EXISTS lotes_ingredientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lote_elaboracion_id INTEGER NOT NULL, -- FK a lotes.id
    ingrediente_resultante TEXT,          -- nombre/resultante (texto)
    ingrediente_id INTEGER,               -- FK opcional a ingredientes.id_ingrediente
    peso REAL NOT NULL DEFAULT 0,         -- peso consumido de este ingrediente
    porcentaje_origen REAL,               -- porcentaje procedencia si aplica
    producto_comercial_id INTEGER,        -- FK a productos_comerciales.id si procede (partida trazable)
    referencia_proveedor TEXT,
    created_at DATETIME DEFAULT (CURRENT_TIMESTAMP),
    FOREIGN KEY (lote_elaboracion_id) REFERENCES lotes(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_comercial_id) REFERENCES productos_comerciales(id) ON DELETE SET NULL,
    FOREIGN KEY (ingrediente_id) REFERENCES ingredientes(id_ingrediente) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_lotes_ingredientes_lote_id ON lotes_ingredientes(lote_elaboracion_id);
CREATE INDEX IF NOT EXISTS idx_lotes_ingredientes_prodcom ON lotes_ingredientes(producto_comercial_id);

-- Tabla lotes_cierres (registro de cierres/validaciones/etiquetado)
CREATE TABLE IF NOT EXISTS lotes_cierres (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lote_id INTEGER NOT NULL, -- FK a lotes.id
    gramos_gastados REAL DEFAULT 0,
    n_etiquetas INTEGER DEFAULT 0,
    modo TEXT,                  -- ej. 'manual'|'parcial'|'final'
    gramos_por_envase REAL,
    unidades REAL,
    usuario TEXT,
    metadata TEXT,              -- JSON/string con datos adicionales
    created_at DATETIME DEFAULT (CURRENT_TIMESTAMP),
    FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_lotes_cierres_lote_id ON lotes_cierres(lote_id);

-- Buenas prácticas: evitar renombrados/alteraciones accidentales (ejemplo para lotes.numero_lote)
DROP TRIGGER IF EXISTS lotes_prevent_numero_change;
CREATE TRIGGER lotes_prevent_numero_change
BEFORE UPDATE ON lotes
FOR EACH ROW
WHEN NEW.numero_lote IS NOT NULL AND OLD.numero_lote IS NOT NULL AND NEW.numero_lote <> OLD.numero_lote
BEGIN
    SELECT RAISE(ABORT, 'Cambio de numero_lote no permitido sin proceso específico');
END;

PRAGMA foreign_keys = ON;
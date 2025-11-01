-- 1) Eliminar índices relacionados si existen
DROP INDEX IF EXISTS idx_prodcom_ingrediente_id;
DROP INDEX IF EXISTS idx_lotes_ingredientes_prodcom;

-- 2) Recrear la tabla lotes_ingredientes sin la columna producto_comercial_id
CREATE TABLE IF NOT EXISTS lotes_ingredientes_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lote_elaboracion_id INTEGER NOT NULL,
    ingrediente_resultante TEXT,
    ingrediente_id INTEGER,
    peso REAL NOT NULL DEFAULT 0,
    porcentaje_origen REAL,
    referencia_proveedor TEXT,
    created_at DATETIME DEFAULT (CURRENT_TIMESTAMP),
    FOREIGN KEY (lote_elaboracion_id) REFERENCES lotes(id) ON DELETE CASCADE,
    FOREIGN KEY (ingrediente_id) REFERENCES ingredientes(id_ingrediente) ON DELETE SET NULL
);

-- 3) Copiar datos (omitiendo producto_comercial_id)
INSERT INTO lotes_ingredientes_new (id, lote_elaboracion_id, ingrediente_resultante, ingrediente_id, peso, porcentaje_origen, referencia_proveedor, created_at)
SELECT id, lote_elaboracion_id, ingrediente_resultante, ingrediente_id, peso, porcentaje_origen, referencia_proveedor, created_at
FROM lotes_ingredientes;

-- 4) Reemplazar tabla antigua
DROP TABLE IF EXISTS lotes_ingredientes;
ALTER TABLE lotes_ingredientes_new RENAME TO lotes_ingredientes;

-- 5) Recrear índices necesarios
CREATE INDEX IF NOT EXISTS idx_lotes_ingredientes_lote_id ON lotes_ingredientes(lote_elaboracion_id);

-- 6) Eliminar tabla productos_comerciales
DROP TABLE IF EXISTS productos_comerciales;
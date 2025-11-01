
-- Añadir columna 'lote' si no existe (SQLite no tiene IF NOT EXISTS para columnas;
-- en entornos donde ya exista la columna, el ALTER dará error. Asegúrate de
-- aplicar esta migration sólo en bases donde la columna no exista o haz backup previo).
ALTER TABLE lotes_ingredientes ADD COLUMN lote TEXT;

-- Añadir columna 'fecha_caducidad' (formato YYYY-MM-DD)
ALTER TABLE lotes_ingredientes ADD COLUMN fecha_caducidad TEXT;

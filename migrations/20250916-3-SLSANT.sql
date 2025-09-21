-- Añade columna para marcar la relación como origen (0 = no, 1 = sí)
ALTER TABLE elaborados_ingredientes
ADD COLUMN es_origen INTEGER NOT NULL DEFAULT 0 CHECK (es_origen IN (0,1));
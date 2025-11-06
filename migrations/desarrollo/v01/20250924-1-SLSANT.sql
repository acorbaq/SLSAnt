PRAGMA foreign_keys = ON;

-- Asegurar índice único (si no existe ya, la migración previa lo creó)
CREATE UNIQUE INDEX IF NOT EXISTS idx_unidades_nombre_abreviatura ON unidades_medida (nombre, abreviatura);

-- Insertar la unidad "No especificado" (abreviatura 'n.c.') si no existe
INSERT INTO unidades_medida (nombre, abreviatura)
SELECT 'No especificado', 'n.c.'
WHERE NOT EXISTS (
  SELECT 1 FROM unidades_medida WHERE lower(trim(abreviatura)) = 'n.c.' OR lower(trim(nombre)) = lower('No especificado')
);

PRAGMA foreign_keys = ON;
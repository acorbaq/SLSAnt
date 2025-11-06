PRAGMA foreign_keys = OFF;

-- Renombrar la tabla original
ALTER TABLE elaborados RENAME TO elaborados_old;

-- Crear la nueva tabla elaborados con la columna corregida
CREATE TABLE elaborados (
  id_elaborado INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  descripcion TEXT,
  peso_obtenido NUMERIC NOT NULL,
  dias_viabilidad INTEGER NOT NULL, -- número de días de viabilidad desde la elaboración
  tipo INTEGER NOT NULL DEFAULT 0 CHECK (tipo IN (0,1)) -- 0 = elaboración/receta, 1 = escandallo
);

-- Migrar los datos de la tabla antigua a la nueva
-- ⚠️ IMPORTANTE: Como no se puede convertir automáticamente fecha→días,
-- aquí se inicializan todos los registros con un valor por defecto (ej: 0).
-- Este valor puede ajustarse manualmente después según la lógica de negocio.
INSERT INTO elaborados (id_elaborado, nombre, descripcion, peso_obtenido, dias_viabilidad, tipo)
SELECT id_elaborado, nombre, descripcion, peso_obtenido, 0 AS dias_viabilidad, tipo
FROM elaborados_old;

-- Eliminar la tabla antigua
DROP TABLE elaborados_old;

PRAGMA foreign_keys = ON;

PRAGMA foreign_keys = OFF;

-- 1) Renombrar la tabla actual de relación
ALTER TABLE elaborados_ingredientes RENAME TO elaborados_ingredientes_old;

-- 2) Crear de nuevo elaborados_ingredientes con la FK hacia la tabla elaborados (nueva)
CREATE TABLE elaborados_ingredientes (
  id_elaborado INTEGER NOT NULL,
  id_ingrediente INTEGER NOT NULL,
  cantidad NUMERIC NOT NULL,
  id_unidad INTEGER NOT NULL,
  PRIMARY KEY (id_elaborado, id_ingrediente),
  FOREIGN KEY (id_elaborado) REFERENCES elaborados(id_elaborado) ON DELETE CASCADE ON UPDATE NO ACTION,
  FOREIGN KEY (id_ingrediente) REFERENCES ingredientes(id_ingrediente) ON DELETE RESTRICT ON UPDATE NO ACTION,
  FOREIGN KEY (id_unidad) REFERENCES unidades_medida(id_unidad) ON DELETE RESTRICT ON UPDATE NO ACTION
);

-- 3) Migrar los datos desde la tabla vieja
INSERT INTO elaborados_ingredientes (id_elaborado, id_ingrediente, cantidad, id_unidad)
SELECT id_elaborado, id_ingrediente, cantidad, id_unidad
FROM elaborados_ingredientes_old;

-- 4) Eliminar la tabla temporal
DROP TABLE elaborados_ingredientes_old;

-- 5) Recrear los índices
CREATE INDEX IF NOT EXISTS idx_elaborados_ingredientes_elaborado
  ON elaborados_ingredientes (id_elaborado);

CREATE INDEX IF NOT EXISTS idx_elaborados_ingredientes_ingrediente
  ON elaborados_ingredientes (id_ingrediente);

PRAGMA foreign_keys = ON;

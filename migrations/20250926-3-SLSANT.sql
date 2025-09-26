PRAGMA foreign_keys = OFF;

-- 0) Comprobar existencia de los tipos principales (fallará si no existen)
SELECT id FROM tipo_elaboracion WHERE nombre IN ('Elaboración','Escandallo') LIMIT 2;

-- 1) Crear tabla nueva con esquema deseado (tipo ahora referencia a tipo_elaboracion.id)
CREATE TABLE IF NOT EXISTS elaborados_new (
  id_elaborado INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  descripcion TEXT,
  peso_obtenido NUMERIC NOT NULL,
  dias_viabilidad INTEGER NOT NULL,
  tipo INTEGER NOT NULL DEFAULT 1,
  FOREIGN KEY (tipo) REFERENCES tipo_elaboracion(id) ON UPDATE CASCADE ON DELETE RESTRICT
);

-- 2) Copiar datos mapeando 0->Elaboración, 1->Escandallo, manteniendo id_elaborado
INSERT INTO elaborados_new (id_elaborado, nombre, descripcion, peso_obtenido, dias_viabilidad, tipo)
SELECT
  id_elaborado,
  nombre,
  descripcion,
  peso_obtenido,
  dias_viabilidad,
  CASE
    WHEN tipo = 0 THEN (SELECT id FROM tipo_elaboracion WHERE nombre = 'Elaboración' LIMIT 1)
    WHEN tipo = 1 THEN (SELECT id FROM tipo_elaboracion WHERE nombre = 'Escandallo' LIMIT 1)
    ELSE (SELECT id FROM tipo_elaboracion WHERE nombre = 'Elaboración' LIMIT 1) -- fallback
  END
FROM elaborados;

-- 3) Comprobaciones rápidas (opcional; ejecutar manualmente si prefieres)
-- SELECT COUNT(*) AS old_count FROM elaborados;
-- SELECT COUNT(*) AS new_count FROM elaborados_new;
-- SELECT id_elaborado, tipo FROM elaborados_new LIMIT 10;

-- 4) Sustituir tabla antigua por la nueva
DROP TABLE IF EXISTS elaborados;
ALTER TABLE elaborados_new RENAME TO elaborados;

-- 5) Ajustar sqlite_sequence para que AUTOINCREMENT no reuse ids bajos (opcional)
-- Si quieres que el next id sea 1000:
DELETE FROM sqlite_sequence WHERE name = 'elaborados';
INSERT INTO sqlite_sequence(name, seq)
SELECT 'elaborados', 999
WHERE NOT EXISTS (SELECT 1 FROM sqlite_sequence WHERE name = 'elaborados');

PRAGMA foreign_keys = ON;
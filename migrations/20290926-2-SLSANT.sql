-- Adaptación a SQLite

-- Eliminar tabla antigua (no hay referencias según tu comentario)
DROP TABLE IF EXISTS tipo_elaboracion;

-- Crear tabla nueva (usar INTEGER PRIMARY KEY AUTOINCREMENT para controlar sqlite_sequence)
CREATE TABLE tipo_elaboracion (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    descripcion TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insertar los 4 tipos con ids controlados
INSERT INTO tipo_elaboracion (id, nombre, descripcion) VALUES
    (1, 'Elaboración', 'Proceso de elaboración de productos.'),
    (2, 'Escandallo',   'Proceso de cálculo de costes de productos.'),
    (3, 'Envasado',     'Proceso de envasado de productos.'),
    (4, 'Congelación',  'Proceso de congelación de productos.');

-- Ajustar AUTOINCREMENT para evitar reutilizar ids bajos.
-- Para que el próximo id asignado sea 1000, sqlite_sequence.seq debe quedar en 999.
-- (sqlite_sequence existe para tablas con AUTOINCREMENT después de ser creadas/usar.)
UPDATE sqlite_sequence SET seq = 999 WHERE name = 'tipo_elaboracion';
INSERT INTO sqlite_sequence(name, seq)
SELECT 'tipo_elaboracion', 999
WHERE NOT EXISTS (SELECT 1 FROM sqlite_sequence WHERE name = 'tipo_elaboracion');

-- Unicidad sobre nombre
CREATE UNIQUE INDEX IF NOT EXISTS ux_tipo_elaboracion_nombre ON tipo_elaboracion (nombre);

-- Evitar renombrados accidentales a nivel de BD (usar RAISE en triggers SQLite)
DROP TRIGGER IF EXISTS tipo_elaboracion_prevent_rename;
CREATE TRIGGER tipo_elaboracion_prevent_rename
BEFORE UPDATE ON tipo_elaboracion
FOR EACH ROW
BEGIN
    SELECT CASE
        WHEN NEW.nombre IS NOT NULL AND TRIM(NEW.nombre) <> TRIM(OLD.nombre)
        THEN RAISE(ABORT, 'No está permitido renombrar tipos de elaboración')
    END;
END;

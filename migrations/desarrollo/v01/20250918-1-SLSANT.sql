PRAGMA foreign_keys = ON;

-- Tabla "elaborados" (recetas / elaboraciones / escandallos)
CREATE TABLE IF NOT EXISTS elaborados (
  id_elaborado INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  descripcion TEXT,
  peso_obtenido NUMERIC NOT NULL,
  fecha_caducidad DATE NOT NULL,
  tipo INTEGER NOT NULL DEFAULT 0 CHECK (tipo IN (0,1)) -- 0 = elaboración/receta, 1 = escandallo
);

-- Tabla de unidades de medida
CREATE TABLE IF NOT EXISTS unidades_medida (
  id_unidad INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  abreviatura TEXT NOT NULL
);

-- Tabla intermedia elaborados_ingredientes (componentes de cada elaborado / escandallo)
CREATE TABLE IF NOT EXISTS elaborados_ingredientes (
  id_elaborado INTEGER NOT NULL,
  id_ingrediente INTEGER NOT NULL,
  cantidad NUMERIC NOT NULL,
  id_unidad INTEGER NOT NULL,
  PRIMARY KEY (id_elaborado, id_ingrediente),
  FOREIGN KEY (id_elaborado) REFERENCES elaborados(id_elaborado) ON DELETE CASCADE ON UPDATE NO ACTION,
  FOREIGN KEY (id_ingrediente) REFERENCES ingredientes(id_ingrediente) ON DELETE RESTRICT ON UPDATE NO ACTION,
  FOREIGN KEY (id_unidad) REFERENCES unidades_medida(id_unidad) ON DELETE RESTRICT ON UPDATE NO ACTION
);

-- Índices para consultas frecuentes
CREATE INDEX IF NOT EXISTS idx_elaborados_ingredientes_elaborado ON elaborados_ingredientes (id_elaborado);
CREATE INDEX IF NOT EXISTS idx_elaborados_ingredientes_ingrediente ON elaborados_ingredientes (id_ingrediente);

-- Añadir unidades de medida comunes
CREATE UNIQUE INDEX IF NOT EXISTS idx_unidades_nombre_abreviatura ON unidades_medida (nombre, abreviatura);

INSERT OR IGNORE INTO unidades_medida (nombre, abreviatura) VALUES
  ('Kilogramo', 'kg'),
  ('Gramo', 'g'),
  ('Litro', 'l'),
  ('Mililitro', 'ml'),
  ('Unidad', 'ud'),
  ('Docena', 'dz'),
  ('Caja', 'caja'),
  ('Paquete', 'paq');
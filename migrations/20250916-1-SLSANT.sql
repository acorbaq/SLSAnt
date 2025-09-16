PRAGMA foreign_keys = ON;

-- 1) Tabla de alergenos
CREATE TABLE IF NOT EXISTS alergenos (
  id_alergeno INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL UNIQUE
);

-- 2) Tabla de ingredientes
CREATE TABLE IF NOT EXISTS ingredientes (
  id_ingrediente INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL UNIQUE,
  indicaciones TEXT
);

-- 3) Tabla pivote N:M entre ingredientes y alergenos
CREATE TABLE IF NOT EXISTS ingredientes_alergenos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_ingrediente INTEGER NOT NULL,
  id_alergeno INTEGER NOT NULL,
  CONSTRAINT uq_ingrediente_alergeno UNIQUE (id_ingrediente, id_alergeno),
  FOREIGN KEY (id_ingrediente) REFERENCES ingredientes(id_ingrediente) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_alergeno)    REFERENCES alergenos(id_alergeno)    ON DELETE CASCADE ON UPDATE CASCADE
);
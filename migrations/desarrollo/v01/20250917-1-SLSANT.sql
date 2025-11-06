-- Migration idempotente: asegurar índice en roles, insertar los 4 roles y seed base de alérgenos.
-- NOTA: no usa transacciones por petición del autor y respeta migraciones previas.

-- Asegurar índice único por nombre en roles (tabla creada en migración anterior)
CREATE UNIQUE INDEX IF NOT EXISTS idx_roles_name ON roles(name);

-- Añadir roles sin duplicados (admin, gestor, calidad, operador)
INSERT OR IGNORE INTO roles (name) VALUES
  ('admin'),
  ('gestor'),
  ('calidad'),
  ('operador');

-- Seed de alérgenos base (tabla creada en migración anterior)
INSERT OR IGNORE INTO alergenos (nombre) VALUES
  ('Gluten'),
  ('Crustáceos'),
  ('Huevos'),
  ('Pescado'),
  ('Cacahuetes'),
  ('Soja'),
  ('Leche'),
  ('Frutos de cáscara'),
  ('Apio'),
  ('Mostaza'),
  ('Sésamo'),
  ('Dióxido de azufre y sulfitos'),
  ('Altramuces'),
  ('Moluscos');
CREATE TABLE tipo_elaboracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO tipo_elaboracion (nombre, descripcion) VALUES
('Elaboración', 'Proceso de elaboración de productos.'),
('Escandallo', 'Proceso de cálculo de costos de productos.'),
('Envasado', 'Proceso de envasado de productos.'),
('Congelación', 'Proceso de congelación de productos.');
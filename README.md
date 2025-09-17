# Seguimiento de Lotes Seguro (SLSAnt)
SLSAnt es una aplicación web diseñada para facilitar el seguimiento y la gestión segura de lotes de produtos ayudando a las empresas a la implantación de sistemas APPCC, incluyendo funcionalidades de trazabilidad, control de calidad y gestión de lotes.

## Características Principales
- **Gestión de alergenos**: Permite resgistrar y gestionar ingredientes y seguir la trazabilidad de los alergenos en los elaboraciones y los escandallos.
- **Gestión de elaboraciones**: Facilita la creación y gestion de elaboraciones, incluyendo la asignación de ingredientes y alergenos.
- **Gestión de escandallos**: Permite crear y gestionar escandallos, asignando elaboraciones y controlando los lotes asociados.
- **Gestión de lotes**: Facilita la creación y gestión de lotes, incluyendo la asignación de fechas de caducidad y el control de envasado.
- **Impresión de etiquetas**: Genera etiquetas para los lotes con información relevante como ingredientes, alergenos y fechas de caducidad.

## Tecnologías Utilizadas
- SQLite: Base de datos ligera y fácil de usar.
- PHP: Lenguaje de programación del lado del servidor.
- HTML/CSS: Estructura y estilo de la aplicación web.
- JavaScript: Interactividad en el lado del cliente.
- Bootstrap: Framework CSS para diseño responsivo.
- jQuery: Biblioteca JavaScript para simplificar la manipulación del DOM.
- DataTables: Plugin jQuery para mejorar las tablas HTML.
- Dompdf: Biblioteca PHP para generar PDFs.

## Instalación

A continuación instrucciones mínimas para levantar el proyecto. Hay dos flujos: desarrollo y producción.

### Requisitos previos
- PHP >= 8.1 con las extensiones pdo y pdo_sqlite.
- Composer (gestor de dependencias PHP).
- Node.js + npm (para Tailwind / assets).
- sqlite3 (opcional, útil para inspeccionar la BD).

### Instalación (desarrollo)
1. Clonar el repositorio:
   ```bash
   git clone <repo-url> slsant
   cd slsant
   ```

2. Configurar variables de entorno:
   - Copiar .env de ejemplo o crear .env en la raíz y ajustar:
     ```
     APP_ENV=local
     APP_DEBUG=true
     APP_NAME=SLSAnt
     ```
   - El bootstrap lee .env; asegúrate de que la ruta a la base de datos sea correcta (database/database.sqlite).

3. Dependencias PHP:
   ```bash
   composer install
   ```

4. Dependencias frontend y assets:
   ```bash
   npm install
   npm run dev      # modo desarrollo (watch)
   ```

5. Migraciones / esquema de BD:
   - Si existe el script de migración:
     ```bash
     php scripts/migrator.php
     ```
   - Alternativamente crear la BD y tablas con sqlite3:
     ```bash
     mkdir -p database
     sqlite3 database/database.sqlite < sql/schema.sql   # si tienes schema
     ```
   - Seed rápido de alérgenos (SQLite):
     ```bash
     sqlite3 database/database.sqlite "CREATE TABLE IF NOT EXISTS alergenos (id_alergeno INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL UNIQUE);"
     sqlite3 database/database.sqlite "INSERT OR IGNORE INTO alergenos (nombre) VALUES ('Gluten'),('Crustáceos'),('Huevos'),('Pescado'),('Cacahuetes'),('Soja'),('Leche'),('Frutos de cáscara'),('Apio'),('Mostaza'),('Sésamo'),('Dióxido de azufre y sulfitos'),('Altramuces'),('Moluscos');"
     ```

6. Crear usuario admin (seed):
   ```bash
   php scripts/seed_admin.php
   ```

7. Levantar servidor de desarrollo:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
   - Abrir http://127.0.0.1:8000

### Instalación (producción)
1. Clonar en servidor y posicionarse en la raíz del proyecto.

2. Variables de entorno:
   - Crear `.env` con:
     ```
     APP_ENV=production
     APP_DEBUG=false
     APP_NAME=SLSAnt
     ```
   - Asegurarse que la ruta a la base de datos existe y es accesible por el usuario del servidor web.

3. Dependencias PHP (sin dev):
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

4. Construir assets optimizados:
   ```bash
   npm install --production
   npm run build
   ```

5. Migraciones y seeds (igual que en dev). Ejecutar migrator / seed_admin y asegurarse de que la tabla `alergenos` contiene los 14 valores.

6. Permisos:
   - Asegurar permisos de escritura para la BD y cualquier carpeta de uploads:
     ```bash
     chown -R www-data:www-data database public/assets
     chmod -R 775 database public/assets
     ```

7. Configurar servidor web (recommended):
   - Configurar Nginx/Apache para apuntar al directorio `public/` y usar php-fpm.
   - Deshabilitar display_errors en php.ini; APP_DEBUG=false.

### Comandos útiles
- Ejecutar migraciones (si existe el script):
  ```bash
  php scripts/migrator.php
  ```
- Sembrar admin y roles:
  ```bash
  php scripts/seed_admin.php
  ```
- Inspeccionar BD:
  ```bash
  sqlite3 database/database.sqlite "SELECT * FROM alergenos;"
  ```

### Notas y buenas prácticas
- En producción siempre poner APP_DEBUG=false y asegurar que el fichero .env no sea accesible públicamente.
- Mantener backups periódicos de database/database.sqlite.
- Para entornos con tráfico real, considerar migrar a MySQL/Postgres y adaptar migrator/seeders.
- Revisar permisos y propietarios de ficheros para evitar problemas de escritura por el servidor web.



## Dependencias de producción (`require`)

```json
"require": {
    "php": ">=8.0",
    "vlucas/phpdotenv": "^5.5"
}
```

### `php` (>=8.0)
* **Qué es**: no es un paquete, es la **versión mínima del lenguaje PHP** que requiere tu proyecto.
* **Para qué sirve**: obliga a que el proyecto se ejecute solo en entornos con **PHP 8 o superior**, asegurando compatibilidad con las funciones modernas del lenguaje.
* **En tu caso**: te permite usar tipado fuerte, `match`, `attributes`, mejoras de rendimiento, etc.


### `vlucas/phpdotenv`
* **Qué es**: una librería que permite cargar **variables de configuración** desde un archivo oculto `.env`.
* **Para qué sirve**: separar la configuración del código.
  * Ejemplo: en `.env` defines la ruta de la base de datos SQLite o las credenciales de MySQL.
  * Así no guardas contraseñas ni configuraciones fijas dentro del código.
* **En tu caso**:
  * Podrás tener `.env` con:
    ```
    DB_CONNECTION=sqlite
    DB_DATABASE=/ruta/a/data/database.sqlite
    ```

  * Y en tu código, con `phpdotenv`, lo lees:
    ```php
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    $dbPath = $_ENV['DB_DATABASE'];
    ```

## Dependencias de desarrollo (`require-dev`)

```json
"require-dev": {
    "phpunit/phpunit": "^10.0"
}
```

### 🔹 `phpunit/phpunit`
* **Qué es**: el framework de **testing más usado en PHP**.
* **Para qué sirve**: crear y ejecutar **pruebas unitarias** y asegurar que tu código funciona como esperas.
  * Puedes escribir tests para tus modelos, servicios y repositorios.
  * Ejemplo: comprobar que `Lote::calcularFechaCaducidad()` devuelve el valor correcto.
* **En tu caso**:
  * Guardas los tests en `/tests`.
  * Ejecutas con:
    ```bash
    ./vendor/bin/phpunit
    ```
  * O si defines script:

    ```bash
    composer test
    ```

## 🧾 Resumen claro
* **php**: define la versión mínima del lenguaje que debe tener el servidor.
* **vlucas/phpdotenv**: carga configuraciones desde `.env` para separar código y datos sensibles (base de datos, rutas, etc.).
* **phpunit/phpunit** (solo desarrollo): framework de pruebas unitarias para asegurar que tus clases y funciones trabajan como deben.

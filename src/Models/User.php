<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Class User
 *
 * Modelo ligero con utilidades enfocadas en la tabla `users`.
 *
 * Responsabilidad:
 *  - Encapsular consultas y operaciones relacionadas con la entidad "user" que
 *    se usan desde controladores o servicios (ej. login, audit).
 *  - Mantener SQL centralizado para facilitar cambios y pruebas.
 *
 * Diseño y decisiones:
 *  - No es un ORM completo: sólo métodos estáticos para las operaciones necesarias.
 *  - Usa prepared statements para evitar inyección SQL.
 *  - Devuelve arrays asociativos (o null) para evitar acoplamiento a objetos ricos.
 *
 * Ejemplos de uso:
 *  - User::updateLastLogin($pdo, $id);
 *  - $user = User::findById($pdo, $id);
 *
 * @package App\Models
 */
class User
{
    /**
     * Actualiza el campo last_login a la hora actual según SQLite.
     *
     * phpDoc:
     *  - @param PDO $pdo Conexión PDO hacia la base de datos (espera SQLite).
     *  - @param int $id Identificador del usuario a actualizar.
     *  - @return void
     *
     * Lógica/flujo:
     *  1) Prepara una sentencia parametrizada para evitar inyección.
     *  2) Ejecuta la sentencia con el id provisto.
     *  3) No devuelve resultado: se asume éxito si no lanza excepción.
     *
     * Comentarios operativos:
     *  - Usar SQLite datetime('now') mantiene timestamps coherentes con la BD.
     *  - Si se requiere comprobación del número de filas afectadas, usar
     *    $stmt->rowCount() después de execute().
     *
     * @param PDO $pdo
     * @param int $id
     * @return void
     */
    public static function updateLastLogin(PDO $pdo, int $id): void
    {
        // Preparar la consulta: placeholder nombrado :id
        $stmt = $pdo->prepare("UPDATE users SET last_login = datetime('now') WHERE id = :id");

        // Ejecutar con el parámetro tipado (int).
        // Si hay error PDO lanzará una excepción si está configurado (ERRMODE_EXCEPTION).
        $stmt->execute([':id' => $id]);
    }

    /**
     * Recupera datos públicos del usuario por su id (excluye password).
     *
     * phpDoc:
     *  - @param PDO $pdo Conexión PDO.
     *  - @param int $id Identificador del usuario.
     *  - @return array|null Array asociativo con campos públicos o null si no existe.
     *
     * Flujo/explicación:
     *  1) Prepara una consulta parametrizada solicitando sólo columnas no sensibles:
     *     id, username, email, is_active, created_at, last_login.
     *  2) Ejecuta la consulta con el id proporcionado.
     *  3) Llama a fetch(PDO::FETCH_ASSOC) que devuelve false si no hay filas.
     *  4) Retorna null en caso de no existir el usuario o el array de datos si existe.
     *
     * Consideraciones de diseño:
     *  - No se incluye el campo password en la selección para evitar fugas accidentales.
     *  - El método devuelve null en lugar de false para una semántica más explícita.
     *  - Es recomendable que el llamador valide la presencia y el flag is_active.
     *
     * @param PDO $pdo
     * @param int $id
     * @return array|null
     */
    public static function findById(PDO $pdo, int $id): ?array
    {
        // Preparar consulta segura seleccionando únicamente columnas públicas.
        $stmt = $pdo->prepare(
            "SELECT id, username, email, is_active, created_at, last_login FROM users WHERE id = :id LIMIT 1"
        );

        // Ejecutar con el parámetro id.
        $stmt->execute([':id' => $id]);

        // fetch devuelve false si no hay fila; normalizamos a null para el caller.
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
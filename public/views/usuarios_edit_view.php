<?php
declare(strict_types=1);
/**
 * usuarios_edit_view.php
 *
 * Formulario de edición/creación de usuario.
 *
 * Variables esperadas:
 *  - array|null $user    : datos del usuario si se edita, null si se crea.
 *  - array $roles        : lista de roles (strings).
 *  - string|null $viewerRole
 *  - string $csrf
 *  - bool $debug
 *
 * Acceso: solo Admin puede entrar desde la UI, pero el controlador también valida permisos.
 */
?>
<body class="bg-gray-50 min-h-screen">
  <main class="max-w-lg mx-auto py-12 px-4">
    <header class="mb-6">
      <h1 class="text-2xl font-bold"><?php echo $user ? 'Modificar usuario' : 'Crear usuario'; ?></h1>
    </header>

    <form method="post" action="/usuarios.php" autocomplete="off" class="bg-white p-6 rounded shadow">
      <input type="hidden" name="csrf" value="<?php echo htmlentities($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?php echo (int)($user['id'] ?? 0); ?>">

      <div class="mb-3">
        <label class="block text-sm">Usuario</label>
        <input name="username" required class="w-full px-3 py-2 border rounded" value="<?php echo htmlentities((string)($user['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
      </div>

      <div class="mb-3">
        <label class="block text-sm">Email</label>
        <input name="email" type="email" class="w-full px-3 py-2 border rounded" value="<?php echo htmlentities((string)($user['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
      </div>

      <div class="mb-3">
        <label class="block text-sm">Contraseña (dejar en blanco para no cambiar)</label>
        <input name="password" type="password" class="w-full px-3 py-2 border rounded" value="">
      </div>

      <div class="mb-3">
        <label class="inline-flex items-center">
          <input type="checkbox" name="is_active" value="1" <?php echo (!empty($user['is_active'])) ? 'checked' : ''; ?>>
          <span class="ml-2 text-sm">Activo</span>
        </label>
      </div>

      <div class="mb-4">
        <div class="text-sm mb-2">Roles</div>
        <?php
          $userRoles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
          foreach ($roles as $r):
            $roleName = is_array($r) ? ($r['name'] ?? '') : (string)$r;
            $checked = in_array($roleName, $userRoles, true) ? 'checked' : '';
        ?>
          <label class="inline-flex items-center mr-3 mb-2">
            <input type="checkbox" name="roles[]" value="<?php echo htmlentities($roleName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" <?php echo $checked; ?>>
            <span class="ml-2 text-sm"><?php echo htmlentities($roleName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="flex gap-2">
        <button type="submit" class="px-4 py-2 bg-teal-500 text-white rounded">Guardar</button>
        <a href="/usuarios.php" class="px-4 py-2 border rounded">Cancelar</a>
      </div>
    </form>

    <?php if (!empty($debug)): ?>
      <pre class="mt-4"><?php echo htmlentities(var_export($user, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
    <?php endif; ?>
  </main>
</body>
</html>
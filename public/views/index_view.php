<?php
declare(strict_types=1);
/**
 * Vista principal (esquema básico).
 *
 * Muestra 6 menús/cards con título y descripción breve. Cada card es un enlace
 * a la ruta correspondiente (todavía no implementada). Se pueden convertir
 * en botones o ítems de menú según la navegación que prefieras.
 *
 * Nota: el item "Ingredientes y Alergenos" y "Elaborados/Escandallo" pueden fusionarse
 * si decides unificar las formas de generar ingredientes.
 */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>SLSAnt · Panel</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gray-50 min-h-screen">
  <main class="max-w-6xl mx-auto py-12 px-4">
    <header class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">Panel principal</h1>
      <p class="mt-2 text-sm text-gray-600">Seleccione una sección para gestionar ingredientes, producción y calidad.</p>
    </header>

    <section class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
      <!-- 1) Ingredientes y Alergenos -->
      <a href="/ingredientes.php" class="block bg-white p-6 rounded-lg shadow hover:shadow-md transition">
        <h2 class="text-lg font-semibold text-gray-800">Ingredientes y Alergenos</h2>
        <p class="mt-2 text-sm text-gray-600">
          Visualizar ingredientes, alérgenos y condiciones de conservación. (Lista, filtros y fichas).
        </p>
      </a>

      <!-- 2) Elaborados / Escandallo -->
      <a href="/elaborados.php" class="block bg-white p-6 rounded-lg shadow hover:shadow-md transition">
        <h2 class="text-lg font-semibold text-gray-800">Elaborados / Escandallo</h2>
        <p class="mt-2 text-sm text-gray-600">
          Crear recetas de elaborados o escandallos. Escandallos generan ingredientes derivados; elaborados pueden marcarse como ingrediente.
        </p>
      </a>

      <!-- 3) Lotes -->
      <a href="/lotes.php" class="block bg-white p-6 rounded-lg shadow hover:shadow-md transition">
        <h2 class="text-lg font-semibold text-gray-800">Lotes</h2>
        <p class="mt-2 text-sm text-gray-600">
          Gestión de lotes de producción: asignar lotes de origen/final, generar lotes desde recetas e imprimir etiquetas.
        </p>
      </a>

      <!-- 4) Impresión -->
      <a href="/impresion.php" class="block bg-white p-6 rounded-lg shadow hover:shadow-md transition">
        <h2 class="text-lg font-semibold text-gray-800">Impresión</h2>
        <p class="mt-2 text-sm text-gray-600">
          Interfaz de impresión individual (EZPL). Plantillas básicas y reimpresión de etiquetas antiguas.
        </p>
      </a>

      <!-- 5) APPCC -->
      <a href="/appcc.php" class="block bg-white p-6 rounded-lg shadow hover:shadow-md transition">
        <h2 class="text-lg font-semibold text-gray-800">APPCC</h2>
        <p class="mt-2 text-sm text-gray-600">
          Fichas de producción por elaboración/escandallo con fases desde la recepción de materias primas. (Diseño pendiente).
        </p>
      </a>

      <!-- 6) Calidad -->
      <a href="/calidad.php" class="block bg-white p-6 rounded-lg shadow hover:shadow-md transition">
        <h2 class="text-lg font-semibold text-gray-800">Calidad</h2>
        <p class="mt-2 text-sm text-gray-600">
          Registro de puntos de control críticos (APPCC) y otros controles de calidad y trazabilidad. (Diseño pendiente).
        </p>
      </a>
    </section>

    <footer class="mt-12 text-sm text-gray-500">
      <p>Nota: estas secciones son esbozos iniciales. Haz click para crear las páginas funcionales correspondientes.</p>
    </footer>
  </main>
</body>
</html>
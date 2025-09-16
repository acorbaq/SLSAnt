# Qué hace el archivo migrator.php
El archivo migrator permite actualizar la base de datos automaticamente validando que las migraciones en el directorio migrations. Lo que hace es generar una tabla SQL en nuestra base de datos que registra cuando incluimos un nuevo archivo, si existe un archivo que no esta registrado en nuestra base de datos lo instala.

# Cómo ejecutar las migraciones
Para ejecutar las migraciones simplemente hay que ejecutar el script desde la terminal:

```bash
php scripts/migrator.php
```

# Migrations
Este directorio incluye archivos sql que definien y actualizan diferentes tablas de nuestro proyecto.
## 20250916-1-SLSANT.sql
Tabla alergenos. Es una tabla simple y fija que podria ser incluida directamente sin ser editable desde nuestra aplicación. Desde el año 2005 llevan vigentes los mismo alergenos lo que hace que sea algo extraño que se produzcan cambios bruscos en esta tabla.
    - id_alergeno: Identificador unico del alergeno.
    - nombre: Nombre del alergeno.

Tabla ingredientes. Esta tabla es editable desde la aplicación y permite definir los ingredientes que se emplearan en los elaborados o los que se generan desde el escandallo. El objetivo es sencillo identificar ingredientes y mediente controladores facilitar la herencia de alergenos e indicaciones.
    - id_ingrediente: Identificador unico del ingrediente.
    - nombre: Nombre del ingrediente.
    - indicaciones: Metodo de conservación o indicaciones especiales.

Tabla pivote N:M entre ingredientes y alergenos. Esta tabla permite relacionar varios alergenos a un ingrediente y viceversa.
    - id: Identificador unico de la relacion.
    - id_ingrediente: Identificador del ingrediente.
    - id_alergeno: Identificador del alergeno.


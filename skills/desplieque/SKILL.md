# Despliegue — Ebike Manager (Skill: desplieque)

Guía para desplegar el proyecto en **InfinityFree** (gratis) y con **Docker**
(local o en un VPS). No ejecuta el despliegue por sí misma: es una referencia.

## Parte 1 — Despliegue en InfinityFree (gratis)

### Requisitos
- Cuenta en https://infinityfree.net
- Proyecto completo (incluye `vendor/fpdf/fpdf.php` y `config/database.php`)

### Pasos
1. Crear una cuenta y un sitio (subdominio gratuito, ej. `ebike.infinityfreeapp.com`).
2. En el panel, crear una **base de datos MySQL** y anotar:
   - Host (ej. `sql123.infinityfree.com`)
   - Nombre de BD (ej. `if0_123456_ebike`)
   - Usuario (ej. `if0_123456`)
   - Contraseña
3. Abrir **phpMyAdmin** del hosting e importar:
   - `ebike_management.sql` (tablas + datos)
   - `sql/stored_procedures.sql` (los 5 SPs)
4. Editar `config/database.php` con los datos del hosting:
   ```php
   return [
       'host'    => 'sql123.infinityfree.com',
       'user'    => 'if0_123456',
       'pass'    => 'tu_password',
       'dbname'  => 'if0_123456_ebike',
       'charset' => 'utf8mb4',
   ];
   ```
5. Subir los archivos del proyecto a la carpeta `htdocs/` del hosting (por FTP
   con FileZilla o por el File Manager del panel). Estructura esperada:
   ```
   htdocs/
   ├── public/        (index.php, assets/)
   ├── src/
   ├── config/
   ├── vendor/
   ├── autoload.php
   └── ...
   ```
   > InfinityFree sirve desde `htdocs/`. Si quieres que la URL sea
   > `dominio.com/` directamente, copia el contenido de `public/` a `htdocs/`
   > y ajusta las rutas (`../src`, `../config`, `../autoload.php`).
6. Verificar que la extensión **GD** esté activa (suele estarlo). Prueba
   abriendo `http://tu-dominio/public/`.
7. Si ves errores, revisa `php_errors.log` y la consola del navegador.

### Notas
- InfinityFree puede suspender sitios por inactividad o alto uso de CPU.
- Los SPs se importan manualmente; sin ellos el dashboard no funciona.

## Parte 2 — Despliegue con Docker (local o VPS)

### Requisitos
- Docker y Docker Compose instalados.

### Archivos incluidos
- `Dockerfile` — imagen PHP 8.3 + Apache + GD + PDO MySQL.
- `docker-compose.yml` — servicios `web` (PHP/Apache) y `db` (MySQL 8).
- `.dockerignore` — excluye archivos innecesarios.

### Pasos
1. Clonar el repositorio:
   ```bash
   git clone https://github.com/LillianaU/informe_bisicletas_corta.git
   cd informe_bisicletas_corta
   ```
2. Construir y levantar:
   ```bash
   docker compose up -d --build
   ```
3. La BD se inicializa automáticamente con `ebike_management.sql` y
   `sql/stored_procedures.sql`.
4. Abrir `http://localhost:8080/` (Apache sirve `public/`).
5. Para detener:
   ```bash
   docker compose down
   ```
   Para borrar volúmenes (BD):
   ```bash
   docker compose down -v
   ```

### Variables de entorno (opcionales)
El `config/database.php` lee variables de entorno con valores por defecto:
- `DB_HOST` (default `localhost`)
- `DB_NAME` (default `ebike_management`)
- `DB_USER` (default `root`)
- `DB_PASS` (default ``)
- `DB_CHARSET` (default `utf8mb4`)

En `docker-compose.yml` se configuran automáticamente para el servicio `web`.

## Parte 3 — No ejecutar automáticamente
Este skill solo documenta el proceso. No ejecuta despliegues ni modifica
servidores remotos. Ejecuta los comandos manualmente cuando estés listo.

# riveraurbano.com

Sitio para rentar los dos terrenos de Calle Novena y Calzada Cetys (Mexicali), en español e inglés. Los textos viven en MariaDB.

## Cómo funciona el idioma

| URL | Idioma |
|---|---|
| `/es/` | Español |
| `/en/` | Inglés |
| `/` | El último que eligió el visitante (cookie) o el de su navegador; si no, español |

Si falta un texto en inglés, se muestra el español para que la página nunca quede vacía.

## Base de datos

- `database/01_esquema.sql`: tablas `idiomas`, `textos`, `configuracion` y la vista `textos_sin_traducir`.
- `database/02_datos.sql`: los 170 textos en español e inglés, más teléfono, correo, video y mapa. Se puede volver a ejecutar sin borrar tus cambios: solo agrega lo que falte.

Cambiar un texto:

```sql
UPDATE textos SET valor = '1,250 m²' WHERE clave = 'lote.a.superficie' AND idioma = 'es';
UPDATE textos SET valor = '1,250 m² (13,455 sq ft)' WHERE clave = 'lote.a.superficie' AND idioma = 'en';
```

Cambiar teléfono, correo o video:

```sql
UPDATE configuracion SET valor = '5216861234567' WHERE clave = 'whatsapp';
UPDATE configuracion SET valor = '686 123 4567'  WHERE clave = 'telefono';
```

Revisar qué falta traducir: `SELECT * FROM textos_sin_traducir;`

Agregar otro idioma: inserta una fila en `idiomas` y sus textos en `textos`. El selector de idioma aparece solo. (Para que `/fr/` funcione, agrega `fr` a la regla de `.htaccess`.)

Los cambios se ven al recargar la página. Si la base de datos se cae, el sitio sigue mostrándose con la última copia guardada en `cache/`.

## Probar en tu computadora con Docker

```bash
cp .env.example .env        # pon contraseñas nuevas
docker compose up -d
```

Sitio en http://localhost:8080 · MariaDB en `localhost:3307` (para HeidiSQL, DBeaver o DataGrip). La primera vez se crean las tablas y los textos solos.

## Publicar en hosting con PHP (cPanel, etc.)

1. Crea la base de datos y el usuario en el panel, e importa `01_esquema.sql` y luego `02_datos.sql` (phpMyAdmin → Importar).
2. Copia `config/config.example.php` como `config/config.php` y pon los datos de conexión.
3. Sube los archivos. Requiere PHP 8.1+, extensión `pdo_mysql` y `mod_rewrite` de Apache.
4. Dale permiso de escritura a la carpeta `cache/`.

Con Nginx, en lugar de `.htaccess`:

```nginx
location ~ ^/(includes|config|database|cache|docker|tools)/ { deny all; }
location ~ ^/(es|en)/?$ { rewrite ^ /index.php last; }
```

> GitHub Pages ya no sirve para esta versión porque necesita PHP y base de datos.

## Imágenes

`tools/imagenes.py` genera la vista aérea, la portada del video y la imagen para redes en los dos idiomas:

```bash
pip install cairosvg
python3 tools/imagenes.py assets/img
```

## Seguridad

Nunca subas `config/config.php` ni `.env` (ya están en `.gitignore`). La contraseña anterior de la base de datos quedó en el historial público del repositorio: cámbiala.

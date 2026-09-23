# riveraurbano.com

Sitio para rentar los dos terrenos de Calle Novena y Calzada Cetys (Mexicali), en español e inglés. Los textos viven en MariaDB.

## Cómo funciona el idioma

| URL | Idioma |
|---|---|
| `/es/` | Español |
| `/en/` | Inglés |
| `/` | El último que eligió el visitante (cookie) o el de su navegador; si no, español |

Si falta un texto en inglés, se muestra el español para que la página nunca quede vacía.

## Panel de administración (`/admin`)

En **https://riveraurbano.com/admin** puedes, sin tocar código ni SQL:

- **Textos:** editar todo lo que dice el sitio, en español e inglés lado a lado, agrupado por sección y con buscador. Marca en amarillo lo que cambiaste y avisa si intentas salir sin guardar.
- **Contacto y ajustes:** WhatsApp, teléfono, correo, video de YouTube y ubicación del mapa. Acepta el número con espacios o `+` y un enlace completo de YouTube; los limpia solo.
- **Video:** subir o reemplazar el video del dron y el clip de la portada, con barra de progreso (hasta 600 MB).
- **Inicio:** pendientes (por ejemplo, si el WhatsApp sigue siendo el de ejemplo) y bitácora de quién cambió qué.

### Crear tu usuario (una vez)

En el servidor, dentro de `/opt/riveraurbano/app`:

```bash
docker compose -f docker-compose.prod.yml exec web php tools/crear-admin.php damian "Damián"
```

Muestra una contraseña segura **una sola vez**. Entra con ella y cámbiala en *Mi cuenta*. El mismo comando sirve para recuperar el acceso si olvidas la contraseña.

### Seguridad del panel

Contraseñas con hash (bcrypt), bloqueo de 15 minutos tras 5 intentos fallidos por usuario o 10 por IP, protección CSRF en todos los formularios, sesión que se cierra tras 2 horas sin actividad, cookies solo por HTTPS y el panel oculto para buscadores.

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

Cambios de textos que deban llegar a un sitio ya instalado van como archivo nuevo en `database/migraciones/` (ej. `004_nuevo_texto.sql`). El pipeline aplica cada archivo una sola vez.

Los cambios se ven al recargar la página. Si la base de datos se cae, el sitio sigue mostrándose con la última copia guardada en `cache/`.

## Probar en tu computadora con Docker

```bash
cp .env.example .env        # pon contraseñas nuevas
docker compose up -d
```

Sitio en http://localhost:8080 · MariaDB en `localhost:3307` (para HeidiSQL, DBeaver o DataGrip). La primera vez se crean las tablas y los textos solos.

## Despliegue en Vultr (riveraurbano.com)

Cada `push` a `development` corre las pruebas. Cada `push` a `main` corre las pruebas y, si pasan, publica en el servidor. Todo está en `.github/workflows/despliegue.yml`.

En el servidor corren tres contenedores: **Caddy** (HTTPS automático con Let's Encrypt y redirección de `www`), **web** (PHP 8.3 + Apache) y **db** (MariaDB 11, sin acceso desde internet).

### Una sola vez

1. **Servidor en Vultr:** Cloud Compute con Ubuntu 24.04, mínimo 1 GB de RAM (mejor 2 GB), región Los Ángeles o Ciudad de México.
2. **DNS:** en tu proveedor del dominio crea registros A de `riveraurbano.com` y `www.riveraurbano.com` hacia la IP del servidor.
3. **Preparar el servidor:** entra como root y corre `deploy/servidor-inicial.sh` (instala Docker, firewall, usuario `deploy`, swap y respaldos diarios).
4. **Llave para GitHub Actions** (en tu computadora):
   ```bash
   ssh-keygen -t ed25519 -f riveraurbano_deploy -N "" -C "github-actions"
   ssh-copy-id -i riveraurbano_deploy.pub deploy@IP_DEL_SERVIDOR   # o pega el .pub en authorized_keys
   ssh-keyscan IP_DEL_SERVIDOR                                      # salida para VULTR_KNOWN_HOSTS
   ```
5. **GitHub → Settings → Environments → New environment `produccion`** y agrega estos secretos:

   | Secreto | Valor |
   |---|---|
   | `VULTR_HOST` | IP del servidor |
   | `VULTR_USUARIO` | `deploy` |
   | `VULTR_SSH_KEY` | contenido del archivo `riveraurbano_deploy` (la privada) |
   | `VULTR_KNOWN_HOSTS` | salida de `ssh-keyscan` |
   | `DB_PASS` | contraseña nueva para el usuario de la BD |
   | `DB_ROOT_PASS` | contraseña nueva para root de la BD |
   | `ACME_EMAIL` | tu correo (Let's Encrypt avisa ahí si hay problemas con el certificado) |

   Opcional: en el environment activa *Required reviewers* para aprobar cada publicación.

6. Haz merge de `development` a `main`. El primer despliegue crea la base de datos con todos los textos.

### El video

Lo más fácil es subirlo desde el panel (`/admin` → Video). También puedes copiarlo directo al servidor:

```bash
scp dron.mp4 dron-corto.mp4 deploy@IP_DEL_SERVIDOR:/opt/riveraurbano/media/video/
```

### Tareas comunes

- **Editar textos en producción:** abre un túnel SSH y conecta HeidiSQL, DBeaver o DataGrip a `127.0.0.1:3307` (usuario `riverauser` y tu `DB_PASS`):
  `ssh -N -L 3307:127.0.0.1:3307 deploy@IP_DEL_SERVIDOR`
  La base de datos solo escucha dentro del servidor; sin el túnel no se puede entrar desde internet.
- **Ver logs:** `docker compose -f docker-compose.prod.yml logs -f web`
- **Respaldos:** `/opt/riveraurbano/respaldos/` (se guardan 14 días). Restaurar:
  `gunzip -c rivera-FECHA.sql.gz | docker compose -f docker-compose.prod.yml exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD"'`
- **Regresar a una versión anterior:** `git revert` del commit en `main` y `push`; el pipeline vuelve a publicar.
- **Salud del sitio:** `https://riveraurbano.com/salud.php` (sirve para UptimeRobot u otro monitor).

> `DB_PASS` y `DB_ROOT_PASS` solo se aplican al crear la base por primera vez. Para cambiarlas después, cámbialas dentro de MariaDB y luego en los secretos.

## Imágenes

`tools/imagenes.py` genera la vista aérea, la portada del video y la imagen para redes en los dos idiomas:

```bash
pip install cairosvg
python3 tools/imagenes.py assets/img
```

## Seguridad

Nunca subas `config/config.php` ni `.env` (ya están en `.gitignore`). La contraseña anterior de la base de datos quedó en el historial público del repositorio: cámbiala.

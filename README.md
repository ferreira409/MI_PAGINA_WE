# AgendaFácil SCZ — Sistema de citas para negocios de Santa Cruz

Proyecto universitario (Programación Avanzada) desarrollado en **PHP 8 + MySQL**.
Permite que los clientes reserven citas en barberías, spas, clínicas y otros negocios
de Santa Cruz de la Sierra, y que los negocios administren su agenda desde un panel.

## Funcionalidades

**Clientes**
- Registro, inicio de sesión y recuperación de contraseña
- Buscador de negocios por nombre, categoría y zona de Santa Cruz
- Página de cada negocio: servicios, precios, horario, opiniones, mapa y WhatsApp
- Reserva en 3 pasos (servicio → día → hora) con horarios libres en tiempo real
- "Mis citas": próximas e historial, cancelar, escribir al negocio y calificar con estrellas
- Perfil: editar datos y cambiar contraseña

**Vendedores (negocios)**
- Panel con resumen, gráficos (citas por día y por estado) e ingresos del mes
- Confirmar, completar o cancelar citas y enviar recordatorios por WhatsApp
- Editar datos del negocio, subir foto de portada y definir horario semanal
- Días libres y feriados (no se puede reservar esos días)
- Servicios con duración y precio (la duración bloquea los horarios ocupados)

**Administrador**
- Todo lo del vendedor, para todos los negocios
- Activar / desactivar negocios
- Gestión de usuarios: buscar, cambiar rol y bloquear cuentas

**API REST (JSON)** para una futura app móvil → ver [`docs/API.md`](docs/API.md)

## Seguridad implementada
- Contraseñas con `password_hash` (bcrypt) y reglas mínimas (8 caracteres, letras y números)
- Consultas preparadas con PDO (protección contra inyección SQL)
- Escape de toda salida con `htmlspecialchars` (protección XSS)
- Token CSRF en **todos** los formularios POST
- Límite de 5 intentos de login fallidos cada 15 minutos
- Cookies de sesión `HttpOnly` y `SameSite`, regeneración del ID al iniciar sesión
- Control de permisos por rol en cada página y en cada acción
- Reservas con bloqueo transaccional (`SELECT ... FOR UPDATE`): dos personas no pueden tomar la misma hora
- Subida de imágenes validada por tipo real (MIME) y tamaño; la carpeta de fotos no ejecuta PHP
- Tokens de recuperación y de API guardados como hash SHA-256, con vencimiento

## Requisitos
- PHP 8.0 o superior (con PDO MySQL y fileinfo)
- MySQL 8 / MariaDB 10.4 o superior
- Recomendado: **Laragon** (Windows)

## Instalación local (Laragon)

1. Abrí Laragon y presioná **Start All**.
2. Abrí la terminal (Cmder) en la carpeta del proyecto y creá la base de datos:
   ```
   cd C:\PAGINAWEB
   mysql -u root -e "source C:/PAGINAWEB/database.sql"
   ```
3. Levantá el servidor (el archivo `router.php` activa la API y la página 404):
   ```
   php -S localhost:8000 router.php
   ```
4. Abrí **http://localhost:8000**

> Si preferís usar Apache de Laragon, copiá la carpeta a `C:\laragon\www\PAGINAWEB`
> y cambiá `BASE_URL` a `'/PAGINAWEB/'` en `config/config.php`.

### Cuentas de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@agendafacil.com | admin123 |
| Vendedor (Barbería El Corte) | barberia@agendafacil.com | 123456 |
| Cliente | cliente@agendafacil.com | 123456 |

## Estructura de carpetas

```
PAGINAWEB/
├── index.php              Página de inicio
├── 404.php                Página de error
├── router.php             Router para "php -S" (desarrollo)
├── .htaccess              Reglas para Apache / hosting
├── database.sql           Base de datos + datos de prueba
├── config/
│   ├── config.php         ⚙️ Configuración (BD, URL, modo demo)
│   ├── bd.php             Conexión PDO
│   ├── funciones.php      Lógica: login, reservas, disponibilidad, etc.
│   ├── imagenes.php       URLs de imágenes e íconos
│   └── conexion.php       Arranque de las páginas web (sesión + CSRF)
├── includes/              Encabezado, pie y tarjeta de negocio
├── auth/                  Login, registro, logout, recuperar contraseña
├── cuenta/                Perfil del usuario
├── negocios/              Buscador y página de cada negocio
│   └── citas/             Reservar, mis citas, calificar
├── admin/                 Panel de vendedor y administrador
├── api/                   API REST (JSON)
├── assets/                CSS
├── js/                    JavaScript (interacción y gráficos)
├── imagenes/negocios/     Fotos subidas por los negocios
└── docs/                  Diagrama de la BD y documentación de la API
```

## Base de datos
Ver el diagrama entidad-relación en [`docs/diagrama-er.md`](docs/diagrama-er.md)
(o abrir `docs/diagrama-er.html` en el navegador).

## Abrir la página desde un celular

### Opción A — Misma red WiFi (rápido, para probar)
1. Levantá el servidor escuchando en toda la red:
   ```
   php -S 0.0.0.0:8000 router.php
   ```
2. Averiguá la IP de tu PC con `ipconfig` (ej: `192.168.1.25`).
3. En el celular (conectado al mismo WiFi) abrí `http://192.168.1.25:8000`.
   Si no carga, permití PHP en el Firewall de Windows (redes privadas).

### Opción B — Publicar en internet (link para cualquier persona)
Hosting gratuito recomendado: **InfinityFree** (PHP + MySQL + SSL gratis).

1. Creá una cuenta en infinityfree.com y un sitio nuevo (podés usar un subdominio gratis).
2. En el panel → **MySQL Databases** creá una base de datos. Anotá *host*, *nombre*, *usuario* y *contraseña*.
3. Abrí **phpMyAdmin**, elegí esa base de datos → **Importar** → `database_hosting.sql`.
4. Descomprimí `AgendaFacil_hosting.zip`, editá `config/config.php` con los datos del paso 2
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) y dejá `BASE_URL` en `'/'`.
5. Con el **File Manager** (o FileZilla) subí todo el contenido a la carpeta **htdocs**.
6. En el panel activá el **SSL gratis** para tener `https://` (necesario para instalar la app).
7. Compartí tu link: `https://tu-sitio.infinityfreeapp.com`

## Instalar como aplicación (PWA)
La página es una **app web instalable**: tiene ícono, pantalla completa y página sin conexión.
Requiere `https://` (Opción B).

- **Android (Chrome):** abrir el link → aparece el aviso *"Instalá AgendaFácil"* → **Instalar**.
  También desde el menú ⋮ → *Instalar aplicación*.
- **iPhone (Safari):** abrir el link → botón **Compartir** → **Agregar a inicio**.

Para publicarla en **Play Store** más adelante se puede empaquetar esta misma PWA con
[PWABuilder](https://www.pwabuilder.com) (genera el archivo para Android a partir del link).

## Créditos
Fotos de Santa Cruz: Wikimedia Commons (autores y licencias en el pie de la página).
Fotos de categorías: Unsplash. Íconos: Font Awesome. Gráficos: Chart.js.

# Cómo publicar AgendaFácil en Vercel + chatbot de Telegram y WhatsApp

Todo es gratis. Vas a necesitar unos **40 minutos** la primera vez.

```
Celular (Telegram / WhatsApp)          Navegador
          │                                 │
          ▼                                 ▼
   bot/telegram.php  ─┐            index.php, negocios/, admin/ ...
   bot/whatsapp.php  ─┤                     │
                      ▼                     ▼
               ┌──────────── VERCEL (api/vercel.php) ────────────┐
                                    │
                                    ▼
                    MySQL en la nube (Aiven, plan Free)
```

La web y el bot usan **la misma base de datos**: una cita reservada por Telegram
aparece al instante en el panel del negocio.

---

## Qué se cambió en el código

| Archivo | Para qué |
|---|---|
| `vercel.json` | Le dice a Vercel que use PHP y que mande todas las visitas a `api/vercel.php` |
| `api/vercel.php` | Hace en Vercel lo mismo que `router.php` en local (y bloquea `config/`, `.sql`, etc.) |
| `api/php.ini` | Zona horaria de Bolivia y límites de subida |
| `.vercelignore` | No sube a Vercel la carpeta `mysql-8.4`, los `.zip` ni `docs/` |
| `config/config.php` | Lee los datos de la base y del bot desde **variables de entorno** (en local sigue usando root sin contraseña) |
| `config/bd.php` | Conexión con SSL (Aiven lo exige) |
| `config/sesiones.php` | Las sesiones se guardan en MySQL: en Vercel los archivos se pierden y el login fallaría |
| `foto.php` + `config/funciones.php` | Las fotos de los negocios se guardan en la base (Vercel no deja guardar archivos) |
| `instalar_bd.php` | Instala la base en la nube, protegido con una clave |
| `bot/` | El chatbot: `motor.php` (la conversación), `web.php` (chat de la página), `telegram.php`, `whatsapp.php`, `configurar.php` |
| `includes/chatbot.php`, `assets/chatbot.css`, `js/chatbot.js` | El globito del asistente en la página |
| `actualizar_vercel_bot.sql` | Agrega las tablas nuevas a tu base local sin borrar datos |

En local todo sigue funcionando igual. Solo corré una vez:

```
mysql -u root agendafacil -e "source C:/PAGINAWEB/actualizar_vercel_bot.sql"
```

---

## Paso 1 — Base de datos MySQL en la nube (Aiven)

1. Entrá a <https://aiven.io/free-mysql-database> y creá una cuenta.
2. **Create service → MySQL → plan Free**. Elegí la región más cercana que te ofrezca.
3. Cuando diga *Running*, en **Overview → Connection information** copiá:
   - Host, Port, User (`avnadmin`), Password, Database name (`defaultdb`)
   - **CA certificate → Show → copiar todo** (desde `-----BEGIN CERTIFICATE-----` hasta `-----END CERTIFICATE-----`)

## Paso 2 — Subir el código a GitHub

En la terminal de Laragon:

```
cd C:\PAGINAWEB
git init
git add .
git commit -m "AgendaFácil lista para Vercel"
```

Creá un repositorio **privado** en GitHub (ej. `agendafacil-scz`) y seguí las instrucciones que te da
(`git remote add origin ...` y `git push -u origin main`).

> El `.gitignore` ya excluye la carpeta `mysql-8.4` y los `.zip`.

## Paso 3 — Crear el proyecto en Vercel

1. Entrá a <https://vercel.com> con tu cuenta de GitHub → **Add New → Project** → importá el repositorio.
2. **Framework Preset: Other**. No cambies nada más.
3. Abrí **Environment Variables** y agregá:

| Variable | Valor |
|---|---|
| `DB_HOST` | el host de Aiven |
| `DB_PORT` | el puerto de Aiven (no es 3306) |
| `DB_NAME` | `defaultdb` |
| `DB_USER` | `avnadmin` |
| `DB_PASS` | la contraseña de Aiven |
| `DB_SSL` | `true` |
| `DB_CA_PEM` | el certificado completo que copiaste |
| `CLAVE_INSTALACION` | una clave que inventes, ej. `AgendaSCZ2026x` |
| `MODO_DEMO` | `true` (para la presentación) |

4. **Deploy**. En 1 minuto te da una dirección tipo `https://agendafacil-scz.vercel.app`.

> **Región:** en `vercel.json` está `"regions": ["sfo1"]` (California). Ponela cerca de tu base de Aiven:
> Aiven en Virginia/EE.UU. este → `"iad1"`; en São Paulo → `"gru1"`; en California → dejá `"sfo1"`.

## Paso 4 — Instalar la base de datos

Abrí en el navegador:

```
https://TU-PROYECTO.vercel.app/instalar_bd.php?clave=TU_CLAVE_INSTALACION
```

Tiene que decir **Base de datos lista**. Ya podés entrar con las cuentas de prueba
(`admin@agendafacil.com / admin123`, etc.).

---

## Paso 5 — Bot de Telegram

1. En Telegram buscá **@BotFather** → `/newbot` → poné un nombre (ej. *AgendaFácil SCZ*)
   y un usuario terminado en `bot` (ej. `AgendaFacilSczBot`).
2. BotFather te da un **token** (`123456789:AA...`).
3. En Vercel → **Settings → Environment Variables** agregá:

| Variable | Valor |
|---|---|
| `TELEGRAM_TOKEN` | el token de BotFather |
| `TELEGRAM_SECRET` | un texto inventado, **solo letras, números, `_` o `-`** (ej. `secreto_agenda_2026`) |
| `TELEGRAM_USUARIO` | el usuario del bot sin @ (sirve para mostrar el enlace en la página) |

4. **Deployments → ⋯ → Redeploy** (las variables nuevas solo se aplican después de volver a desplegar).
5. Abrí `https://TU-PROYECTO.vercel.app/bot/configurar.php?clave=TU_CLAVE_INSTALACION`
   → tiene que mostrar ✅ en Telegram. Eso registra el webhook.
6. Escribile `/start` a tu bot. 🎉

## Paso 6 — Bot de WhatsApp (WhatsApp Cloud API de Meta)

1. Entrá a <https://developers.facebook.com> → **Mis apps → Crear app** → tipo **Empresa / Business**.
2. Agregá el producto **WhatsApp** → **Configuración de la API**. Meta te regala un **número de prueba**.
3. En *Para*, agregá **tu número de celular** y verificalo con el código que te llega
   (en modo prueba el bot solo les responde a los números que agregues aquí, hasta 5).
4. Copiá:
   - **Token de acceso temporal** (dura 24 h; para la presentación generalo ese mismo día,
     o creá un token permanente con un *Usuario del sistema* en el Business Manager)
   - **Identificador del número de teléfono** (Phone number ID)
   - En **Configuración de la app → Básica**: la **Clave secreta de la app** (App secret)
5. En Vercel agregá:

| Variable | Valor |
|---|---|
| `WA_TOKEN` | el token de acceso |
| `WA_PHONE_ID` | el Phone number ID |
| `WA_APP_SECRET` | la clave secreta de la app |
| `WA_VERIFY_TOKEN` | un texto inventado (ej. `verificar_agenda_2026`) |
| `WA_NUMERO` | el número de prueba de Meta, solo dígitos (para el enlace de la página) |

6. **Redeploy**.
7. En Meta → WhatsApp → **Configuración → Webhook → Editar**:
   - URL de devolución de llamada: `https://TU-PROYECTO.vercel.app/bot/whatsapp.php`
   - Token de verificación: el mismo `WA_VERIFY_TOKEN`
   - **Verificar y guardar**, después en *Campos del webhook* suscribite a **messages**.
8. Desde tu celular escribile **hola** al número de prueba. 🎉

---

## Chat en la página web

En todas las páginas (menos el panel) aparece un **globito verde** abajo a la derecha:
es el mismo asistente que el de Telegram y WhatsApp, pero dentro de la página.
No necesita configurar nada: funciona apenas se publica.

- Archivos: `includes/chatbot.php` (el globito), `assets/chatbot.css`, `js/chatbot.js` y `bot/web.php`.
- Si el cliente **inició sesión**, la cita queda en su cuenta (*Mis citas*) sin pedirle datos.
- Si es visitante, le pide nombre y celular.
- La conversación se mantiene aunque cambie de página.

## Qué sabe hacer el bot

- **Reservar citas**: negocio → servicio → día (solo los que tienen horarios libres) → hora → nombre → confirmar.
  Usa la misma función `crearCita()` de la web, así que no puede haber dos citas a la misma hora.
- **Preguntas frecuentes**: horarios, precios y dirección de cada negocio, cómo reservar, cómo se paga,
  cómo cancelar, registrar un negocio.
- **Entiende texto libre**: "cuánto cuesta un masaje", "a qué hora abren", "quiero reservar un corte", "cómo pago".
- Se puede responder tocando los botones o escribiendo el número de la opción. `menu` vuelve al inicio.
- En WhatsApp, si el número ya está registrado en la web como cliente, la cita aparece en su cuenta
  (*Mis citas*). Si no, se crea un cliente nuevo con ese número.

## Si algo falla

| Problema | Solución |
|---|---|
| "Error de conexión a la base de datos" | Revisá `DB_HOST`, `DB_PORT`, `DB_PASS`, `DB_SSL=true` y que `DB_CA_PEM` tenga el certificado completo |
| "La página expiró" al iniciar sesión | Falta la tabla `sesiones`: entrá a `/instalar_bd.php?clave=...&actualizar=1` |
| El bot de Telegram no responde | Abrí `/bot/configurar.php?clave=...` y revisá que todo esté en ✅ |
| WhatsApp no verifica el webhook | `WA_VERIFY_TOKEN` en Vercel tiene que ser igual al de Meta, y hay que hacer Redeploy |
| WhatsApp dejó de responder al día siguiente | Venció el token temporal: generá otro y actualizá `WA_TOKEN` |
| Ver errores | Vercel → tu proyecto → **Logs** |

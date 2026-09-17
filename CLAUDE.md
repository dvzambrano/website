# website (micalme.com)

Laravel app modular (`Modules/` vía nwidart) con los bots de Telegram
(KashioBot, ZentroTraderBot, ZentroOwnerBot, GutoTradeBot, etc.) y los
webhooks de TradingView. Comandos útiles de desarrollo en `HowTo.txt`.

## Servidor (Hostinger, hosting compartido)

Mismo hosting compartido que usan Poker (`micalmoker.sbs`) y Micalpays
(`micalpays.sbs`): cuenta `u650901517`, sin root, sin systemd/supervisor,
con PHP 8.3, `composer`, `git` y `mysql` por SSH pero sin `npm`/`node`.
Producción es `micalme.com` y staging `dev.micalme.com`.

- **Acceso SSH**: alias `hostinger` en `~/.ssh/config` (HostName, Port
  65002, User e IdentityFile viven ahí, no en el repo). Usar siempre el
  alias: `ssh hostinger '<comando>'`, `scp archivo hostinger:ruta`,
  `rsync ... hostinger:ruta`. Los permisos de Claude Code para esos
  comandos están en `.claude/settings.local.json`.
- **Layout en el servidor** (confirmado 2026-09-17):
  `~/domains/micalme.com/public_html/` despacha por `Host` vía
  `.htaccess`: `micalme.com` → `domain/` (producción), `dev.micalme.com`
  → `subdomain_devtest/` (staging); también hay reglas para
  `kashio.micalme.com` → `subdomain_kashio/` y `pkr.micalme.com` →
  `subdomain_pkr/` (carpetas hoy inexistentes). `~/public_html` es un
  symlink a esa carpeta. `backup/` guarda copias de los `.env`.
- **Deploy**: `domain/` y `subdomain_devtest/` son checkouts git de este
  repo en `main`, actualizados automáticamente por el Git deploy de
  hPanel en cada push (`reset` + `pull --quiet` en el reflog). O sea:
  **un push a `main` va a producción y a staging a la vez**, sin
  pipeline propio ni `deploy.sh`. Cambios que requieran
  `composer install`, migraciones o limpiar caché hay que hacerlos a
  mano por SSH después del push (el host sí tiene `composer` y `mysql`
  en `/usr/local/bin` y `/usr/bin`, PHP 8.3, sin `npm`/`node`).
- **Cron**: no existe el binario `crontab` en el host; los cron jobs se
  gestionan solo desde hPanel.
- **`.env` de cada ambiente** vive solo en el servidor, nunca en git.
  No sobreescribirlo en un deploy.
- **Antes de modificar algo en el servidor**: hacer backup del archivo
  (`cp x x.bak-YYYYMMDD`) y, si es un cambio en producción, confirmar
  con el usuario primero. Después de cambiar código PHP correr
  `php artisan optimize:clear` en la carpeta de la app.
- Si `ssh hostinger` responde `Permission denied (publickey,password)`,
  la llave local (`~/.ssh/id_ed25519.pub`, comentario
  `dvzambrano@gmail.com`) fue quitada de `~/.ssh/authorized_keys` del
  hosting (autorizada el 2026-09-17): pedirle al usuario que la vuelva a
  agregar (él escribe la contraseña del hosting, nunca se comparte).
- El reloj del servidor está en UTC (4 h adelante de la máquina local).

## Convenciones

- Identificadores de código en inglés; texto visible al usuario en
  español neutro LATAM.
- Nunca editar `vendor/`: los paquetes `dvzambrano/*` se editan en su
  repo bajo `Proyecto/Module/<Paquete>` y se traen con `composer update`.
- Commit + push al terminar cada cambio (sin pedir confirmación), con la
  suite de tests corrida antes.

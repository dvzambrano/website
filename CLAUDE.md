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
- **Deploy** (desde el 2026-10-03, igual que Poker y Micalpays): desde
  la máquina local con `deploy/release.sh staging|production`. Exige
  `main` limpio y pusheado, corre la suite una vez por commit, arma el
  release (`git archive` + `composer install --no-dev`, sin build de
  Vite: `package-lock.json` no coincide con `package.json` y el
  servidor nunca tuvo `public/build`), lo sube por rsync a `~/tmp/`,
  hace backup
  del `.env` y mysqldump de la base central en
  `~/backups/micalme-deploy/`, reemplaza los directorios de código
  (nunca `.env` ni `storage/`; tampoco `public/autodestroy`) y corre
  `deploy/deploy.sh` (`route:cache`, `view:cache`, `event:cache`; sin
  `config:cache` porque hay `env()` en runtime). **Un push a `main` ya no
  despliega nada**: los dos webhooks de Hostinger del repo en GitHub
  quedaron desactivados (`active=false`, no borrados). Producción exige
  que staging ya corra ese mismo commit (archivo `REVISION`) y solo se
  despliega cuando el usuario lo pide expresamente cada vez.
  - **Migraciones**: no corren en el deploy. `php artisan migrate` ve
    las migraciones de los paquetes de bots (tablas de tenant) y las
    crearía en la base central; `modules:migrate-seed` hace
    `migrate:fresh` (borra datos). Se corren a mano por SSH con la base
    y el `--path` que correspondan.
  - **Tests**: la suite solo cubre el proyecto (auth, perfil, webhooks
    propios); cada bot/tenant se prueba en su propio repo. Los tests con
    base de datos usan `Tests\RefreshDatabase`, que migra solo
    `database/migrations` (las migraciones de los paquetes apuntan a la
    conexión `tenant`, inexistente aquí).
  - `domain/` y `subdomain_devtest/` conservan el `.git` del esquema
    anterior (checkout de `main`), ya sin uso.
- **Cron**: no existe el binario `crontab` en el host; los cron jobs se
  gestionan solo desde hPanel.
- **`.env` de cada ambiente** vive solo en el servidor, nunca en git.
  No sobreescribirlo en un deploy.
- **Antes de modificar algo en el servidor**: hacer backup del archivo
  (`cp x x.bak-YYYYMMDD`) y, si es un cambio en producción, confirmar
  con el usuario primero. Después de cambiar código PHP a mano
  en el servidor, regenerar los caches con `bash deploy/deploy.sh` en la
  carpeta de la app.
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

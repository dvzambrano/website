# website (micalme.com)

Laravel app modular (`Modules/` vía nwidart) con los bots de Telegram
(KashioBot, ZentroTraderBot, ZentroOwnerBot, GutoTradeBot, etc.) y los
webhooks de TradingView. Comandos útiles de desarrollo en `HowTo.txt`.

## Servidor (Hostinger, hosting compartido)

Mismo hosting compartido que usan Poker (`micalmoker.sbs`) y Micalpays
(`micalpays.sbs`): cuenta `u650901517`, sin root, sin systemd/supervisor,
sin `composer`/`npm` en el PATH SSH (solo PHP). Producción es
`micalme.com` y staging `dev.micalme.com`.

- **Acceso SSH**: alias `hostinger` en `~/.ssh/config` (HostName, Port
  65002, User e IdentityFile viven ahí, no en el repo). Usar siempre el
  alias: `ssh hostinger '<comando>'`, `scp archivo hostinger:ruta`,
  `rsync ... hostinger:ruta`. Los permisos de Claude Code para esos
  comandos están en `.claude/settings.local.json`.
- **Layout en el servidor**: `~/domains/<dominio>/public_html/`. En
  Poker/Micalpays `public_html/` despacha por `Host` vía `.htaccess`
  hacia `domain/` (producción) y `subdomain_devtest/` (staging).
  Confirmar el layout real de `micalme.com` con `ssh hostinger 'ls
  ~/domains/micalme.com/public_html'` antes de tocar nada.
- **Cron**: `crontab` por SSH es de solo lectura (alias que hace `cat`);
  los cron jobs se cargan desde hPanel.
- **`.env` de cada ambiente** vive solo en el servidor, nunca en git.
  No sobreescribirlo en un deploy.
- **Antes de modificar algo en el servidor**: hacer backup del archivo
  (`cp x x.bak-YYYYMMDD`) y, si es un cambio en producción, confirmar
  con el usuario primero. Después de cambiar código PHP correr
  `php artisan optimize:clear` en la carpeta de la app.
- Si `ssh hostinger` responde `Permission denied (publickey,password)`,
  la llave local (`~/.ssh/id_ed25519.pub`) no está en
  `~/.ssh/authorized_keys` del hosting: pedirle al usuario que la agregue
  (él escribe la contraseña del hosting, nunca se comparte con Claude).

## Convenciones

- Identificadores de código en inglés; texto visible al usuario en
  español neutro LATAM.
- Nunca editar `vendor/`: los paquetes `dvzambrano/*` se editan en su
  repo bajo `Proyecto/Module/<Paquete>` y se traen con `composer update`.
- Commit + push al terminar cada cambio (sin pedir confirmación), con la
  suite de tests corrida antes.

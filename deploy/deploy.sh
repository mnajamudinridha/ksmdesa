#!/usr/bin/env bash
# Manual deploy for ksmdesa.my.id — DirectAdmin 103.126.232.5, user `ksmdesa`.
#
# The server runs a COPY of this file from ~/deploy/deploy.sh (outside the
# repo), because `git reset --hard` below would otherwise rewrite the script
# while bash is still reading it. After changing this file, reinstall it:
#   cp ~/ksmdesa/deploy/deploy.sh ~/deploy/deploy.sh
#
# Usage (as ksmdesa — from root: sudo -u ksmdesa ~ksmdesa/deploy/deploy.sh):
#   deploy.sh              deploy origin/main
#   deploy.sh <sha|ref>    deploy that commit (also the rollback path)
#   deploy.sh --status     show deployed commit vs origin/main, change nothing
#   FORCE=1 deploy.sh      redeploy even if already on the target commit
set -Eeuo pipefail

APP_DIR="$HOME/ksmdesa"
DEPLOY_DIR="$HOME/deploy"
BRANCH=main
HOST=ksmdesa.my.id
ORIGIN_IP=103.126.232.5
DB_NAME=ksmdesa_app
KEEP_BACKUPS=10
export PATH="$HOME/.local/node22/bin:/usr/local/bin:$PATH"

log() { printf '[%s] %s\n' "$(date '+%F %T')" "$*"; }
die() { log "GAGAL: $*"; exit 1; }

[ "$(id -un)" = ksmdesa ] || die "jalankan sebagai user ksmdesa (root: sudo -u ksmdesa $0)"
mkdir -p "$DEPLOY_DIR/backups" "$DEPLOY_DIR/logs"
exec 9>"$DEPLOY_DIR/.lock"
flock -n 9 || die "deploy lain sedang berjalan"
cd "$APP_DIR"

git fetch --quiet origin "$BRANCH"
CURRENT=$(git rev-parse HEAD)

if [ "${1:-}" = "--status" ]; then
    log "terpasang : $(git log -1 --format='%h %ad %s' --date=short "$CURRENT")"
    log "origin    : $(git log -1 --format='%h %ad %s' --date=short "origin/$BRANCH")"
    git --no-pager log --oneline "$CURRENT..origin/$BRANCH" | sed 's/^/  belum dideploy: /'
    exit 0
fi

TARGET=$(git rev-parse --verify --quiet "${1:-origin/$BRANCH}^{commit}") || die "ref tidak dikenal: ${1:-origin/$BRANCH}"

# Tracked-only check: untracked files (package-lock churn, uploads) never block a deploy,
# and reset --hard does not touch them anyway.
[ -z "$(git status --porcelain -uno)" ] || die "ada perubahan lokal di $APP_DIR — lihat 'git status', commit atau buang dulu"

if [ "$CURRENT" = "$TARGET" ] && [ -z "${FORCE:-}" ]; then
    log "sudah di ${TARGET:0:7}, tidak ada yang dideploy (FORCE=1 untuk paksa)"
    exit 0
fi

LOG_FILE="$DEPLOY_DIR/logs/deploy-$(date +%Y%m%d-%H%M%S).log"
exec > >(tee -a "$LOG_FILE") 2>&1

log "deploy ${CURRENT:0:7} -> ${TARGET:0:7}"
git --no-pager log --oneline "$CURRENT..$TARGET" 2>/dev/null | head -30 || true

# 1. Backup DB before anything changes. Rolling back code does NOT undo migrations;
#    this dump is the only way back for data. --no-tablespaces: app user lacks PROCESS.
DUMP="$DEPLOY_DIR/backups/db-$(date +%Y%m%d-%H%M%S)-${CURRENT:0:7}.sql.gz"
mysqldump --no-tablespaces --single-transaction --routines --triggers "$DB_NAME" | gzip > "$DUMP"
zcat "$DUMP" | tail -n 1 | grep -q "Dump completed" || die "backup DB tidak lengkap: $DUMP"
log "backup DB: $DUMP ($(du -h "$DUMP" | cut -f1))"
ls -1t "$DEPLOY_DIR"/backups/db-*.sql.gz | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm --

IN_MAINTENANCE=0
on_error() {
    if [ "$IN_MAINTENANCE" = 1 ]; then
        log "GAGAL di baris $1. Situs dibiarkan dalam maintenance mode (php artisan up untuk membuka)."
    else
        log "GAGAL di baris $1. Situs sudah dibuka lagi tetapi health check gagal — periksa segera."
    fi
    log "  perbaiki lalu ulangi : $0 $TARGET"
    log "  atau rollback kode   : $0 $CURRENT"
    log "  backup DB            : $DUMP"
    log "  log                  : $LOG_FILE"
}
trap 'on_error $LINENO' ERR

# 2. Swap code under maintenance mode: new code + old vendor/build must never serve traffic.
php artisan down --retry=15 || true # first deploy: no vendor yet
IN_MAINTENANCE=1
git reset --quiet --hard "$TARGET"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
npm ci --no-audit --no-fund
npm run build
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize
php artisan filament:optimize
php artisan up
IN_MAINTENANCE=0

# 3. Health check against the origin (bypass DNS). /admin must redirect to login:
#    a 200 there would mean the auth guard is gone, not that the site is healthy.
check() { # url expected_code
    local code
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 --resolve "$(echo "$1" | sed -E 's#https://([^/]+).*#\1#'):443:$ORIGIN_IP" "$1")
    [ "$code" = "$2" ] || { log "health: $1 -> $code (harap $2)"; return 1; }
    log "health: $1 -> $code"
}
check "https://$HOST/up" 200
check "https://$HOST/" 200
check "https://$HOST/admin/login" 200
check "https://$HOST/admin" 302
check "https://deploy-healthcheck.$HOST/up" 200
check "https://deploy-healthcheck.$HOST/admin/login" 404

trap - ERR
echo "$TARGET" > "$DEPLOY_DIR/last_good_sha"
log "SELESAI: $(git log -1 --format='%h %s' "$TARGET")"

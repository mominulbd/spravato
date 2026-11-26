#!/usr/bin/env bash
#
# Spravato — zero-downtime releases for Laravel (PHP-FPM) and the Next.js
# site (standalone node server) on one server. Runbook: deploy/README.md.
#
#   deploy.sh setup                 create the directory layout (idempotent)
#   deploy.sh deploy [REF]          build REF (default $DEPLOY_REF) and switch to it
#   deploy.sh deploy --archive F    same, from a tarball of the repository (CI upload)
#   deploy.sh rollback [RELEASE]    switch back to the previous (or a named) release
#   deploy.sh status                releases, the live one and the live site port
#
# Layout (APP_DIR, default /var/www/spravato):
#
#   current -> releases/<id>        what nginx and PHP-FPM serve
#   releases/<id>/                  one built checkout per release
#   site-3000, site-3001 -> releases/<id>/site/.next/standalone/site
#   shared/.env                     Laravel        (linked into each release)
#   shared/site.env                 Next.js        (→ site/.env.production)
#   shared/storage/                 Laravel storage (→ storage/)
#   shared/next-static/             every release's /_next/static, served by nginx
#   shared/nginx/site-upstream.conf the live Next.js port, included by nginx
#   shared/site-port                the live Next.js port (systemd mode)
#   shared/repo.git                 bare mirror of the repository
#   shared/deploy.env               settings for this script (see below)
#
# A release: fetch → link shared files → composer → admin build → migrate →
# caches → site build → start the new site on the idle port and check it →
# swap `current` atomically, point nginx at the new port, reload PHP-FPM and
# nginx, restart the queue → check /up, / and /admin/login through nginx →
# stop the old site. Any failed check before the swap aborts with the old
# release still live; any failed check after it switches straight back.
#
# Migrations run before the swap against the live database, so they must be
# backward compatible with the release still serving (add, then remove in a
# later release). A rollback never reverts migrations.

set -euo pipefail

# ── Settings ────────────────────────────────────────────────────────────────
# Override in $APP_DIR/shared/deploy.env or the environment.
APP_DIR="${APP_DIR:-/var/www/spravato}"
if [[ -f "$APP_DIR/shared/deploy.env" ]]; then
    # shellcheck source=/dev/null
    source "$APP_DIR/shared/deploy.env"
fi

REPO_URL="${REPO_URL:-}"                      # git URL the server can read (deploy key)
DEPLOY_REF="${DEPLOY_REF:-main}"              # branch, tag or commit
DOMAIN="${DOMAIN:-}"                          # public host, e.g. spravato.example.com
KEEP_RELEASES="${KEEP_RELEASES:-5}"
SITE_MANAGER="${SITE_MANAGER:-systemd}"       # systemd (blue/green) | pm2
SITE_PORTS="${SITE_PORTS:-3000 3001}"         # systemd mode: the two instance ports
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
PM2_BIN="${PM2_BIN:-pm2}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"
SUDO="${SUDO-sudo -n}"                        # set to empty when run as root
SYSTEMCTL="${SYSTEMCTL:-/usr/bin/systemctl}"
NGINX_BIN="${NGINX_BIN:-/usr/sbin/nginx}"
WEB_SERVER="${WEB_SERVER:-nginx}"             # nginx | apache (apache needs SITE_MANAGER=pm2)
APACHECTL_BIN="${APACHECTL_BIN:-/usr/sbin/apache2ctl}"
# Health checks go to this server's own nginx, with the real host name and
# certificate (curl --resolve), so DNS or a CDN in front never matters.
HEALTH_IP="${HEALTH_IP:-127.0.0.1}"
HEALTH_TIMEOUT="${HEALTH_TIMEOUT:-60}"        # seconds to wait for a new site instance
DRAIN_SECONDS="${DRAIN_SECONDS:-10}"          # old site keeps running this long after the switch
STATIC_RETENTION_DAYS="${STATIC_RETENTION_DAYS:-14}"
PRUNE_NODE_MODULES="${PRUNE_NODE_MODULES:-true}" # drop build-only node_modules after a build

RELEASES="$APP_DIR/releases"
SHARED="$APP_DIR/shared"
CURRENT="$APP_DIR/current"
LOCK_DIR="$APP_DIR/.deploy.lock"
UPSTREAM_FILE="$SHARED/nginx/site-upstream.conf"
PORT_FILE="$SHARED/site-port"

# ── Helpers ─────────────────────────────────────────────────────────────────
log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33mwarning:\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

need() {
    local cmd
    for cmd in "$@"; do
        command -v "$cmd" >/dev/null 2>&1 || die "'$cmd' is not installed."
    done
}

# Run a privileged command through the narrow sudoers rules
# (deploy/sudoers/spravato). $SUDO is split on purpose ("sudo -n").
privileged() {
    if [[ -n "$SUDO" ]]; then
        local -a sudo_cmd
        IFS=' ' read -r -a sudo_cmd <<< "$SUDO"
        "${sudo_cmd[@]}" "$@"
    else
        "$@"
    fi
}

# Point a symlink somewhere new atomically: rename(2) over the old link.
swap_link() {
    local target="$1" link="$2" tmp
    tmp="$link.tmp.$$"
    ln -sfn "$target" "$tmp"
    if mv -T "$tmp" "$link" 2>/dev/null; then
        return 0
    fi
    # Not GNU mv (only when trying the script outside Linux): not atomic.
    rm -f "$tmp"
    ln -sfn "$target" "$link"
}

current_release() {
    if [[ -L "$CURRENT" ]]; then
        readlink "$CURRENT"
    fi
}

# Release directories, oldest first (ids sort by time).
list_releases() {
    if [[ -d "$RELEASES" ]]; then
        find "$RELEASES" -mindepth 1 -maxdepth 1 -type d -exec basename {} \; | sort
    fi
}

active_port() {
    if [[ -f "$PORT_FILE" ]]; then
        tr -dc '0-9' < "$PORT_FILE"
    fi
}

idle_port() {
    local live port
    live="$(active_port)"
    for port in $SITE_PORTS; do
        if [[ "$port" != "$live" ]]; then
            printf '%s' "$port"
            return 0
        fi
    done
    die "No idle port in SITE_PORTS='$SITE_PORTS'."
}

write_upstream() {
    local port="$1"
    mkdir -p "$(dirname "$UPSTREAM_FILE")"
    cat > "$UPSTREAM_FILE.tmp" <<EOF
# Generated by deploy/deploy.sh — the live Next.js instance. Do not edit.
upstream spravato_site {
    server 127.0.0.1:$port;
    keepalive 16;
}
EOF
    mv -f "$UPSTREAM_FILE.tmp" "$UPSTREAM_FILE"
}

# Test the web server config, then reload it gracefully (no dropped requests).
reload_web() {
    if [[ "$WEB_SERVER" == "apache" ]]; then
        privileged "$APACHECTL_BIN" configtest 2>/dev/null || { privileged "$APACHECTL_BIN" configtest || true; return 1; }
        privileged "$SYSTEMCTL" reload apache2
        return
    fi
    privileged "$NGINX_BIN" -t 2>/dev/null || { privileged "$NGINX_BIN" -t || true; return 1; }
    privileged "$SYSTEMCTL" reload nginx
}

reload_php() {
    privileged "$SYSTEMCTL" reload "$PHP_FPM_SERVICE"
}

# HTTP status of a URL, "000" when unreachable.
http_status() {
    local url="$1"
    shift
    curl -sS -o /dev/null -w '%{http_code}' --max-time 10 "$@" "$url" 2>/dev/null || printf '000'
}

# Wait until a URL answers 200, up to $HEALTH_TIMEOUT seconds.
wait_for_200() {
    local url="$1" deadline status
    shift
    deadline=$(( $(date +%s) + HEALTH_TIMEOUT ))
    while :; do
        status="$(http_status "$url" "$@")"
        [[ "$status" == "200" ]] && return 0
        if (( $(date +%s) >= deadline )); then
            warn "$url answered $status (wanted 200)."
            return 1
        fi
        sleep 2
    done
}

# The public paths that must work after a switch, through this server's nginx.
public_checks() {
    local path status ok=0 resolve
    resolve="$DOMAIN:443:$HEALTH_IP"
    for path in /up / /admin/login; do
        status="$(http_status "https://$DOMAIN$path" --resolve "$resolve")"
        if [[ "$status" == "200" ]]; then
            log "  https://$DOMAIN$path → $status"
        else
            warn "https://$DOMAIN$path answered $status (wanted 200)."
            ok=1
        fi
    done
    return "$ok"
}

artisan() {
    (cd "$1" && "$PHP_BIN" artisan "${@:2}" --no-interaction)
}

acquire_lock() {
    mkdir -p "$APP_DIR"
    if ! mkdir "$LOCK_DIR" 2>/dev/null; then
        die "Another deploy is running ($LOCK_DIR exists). If none is, remove it and retry."
    fi
    trap 'rm -rf "$LOCK_DIR"' EXIT
}

require_settings() {
    [[ -n "$DOMAIN" ]] || die "DOMAIN is not set (shared/deploy.env)."
    [[ -f "$SHARED/.env" ]] || die "$SHARED/.env is missing (deploy/README.md, first-time setup)."
    [[ -f "$SHARED/site.env" ]] || die "$SHARED/site.env is missing (deploy/README.md, first-time setup)."
    case "$SITE_MANAGER" in
        systemd|pm2) ;;
        *) die "SITE_MANAGER must be systemd or pm2, not '$SITE_MANAGER'." ;;
    esac
    case "$WEB_SERVER" in
        nginx) ;;
        apache) [[ "$SITE_MANAGER" == "pm2" ]] || die "WEB_SERVER=apache proxies one fixed port: use SITE_MANAGER=pm2." ;;
        *) die "WEB_SERVER must be nginx or apache, not '$WEB_SERVER'." ;;
    esac
}

# ── setup ───────────────────────────────────────────────────────────────────
cmd_setup() {
    log "Creating the layout under $APP_DIR"
    mkdir -p "$RELEASES" "$SHARED/nginx" "$SHARED/next-static" \
        "$SHARED/storage/app/public" "$SHARED/storage/app/private" \
        "$SHARED/storage/framework/cache/data" "$SHARED/storage/framework/sessions" \
        "$SHARED/storage/framework/views" "$SHARED/storage/logs"
    chmod -R u+rwX,g+rX "$SHARED/storage"

    if [[ ! -f "$UPSTREAM_FILE" ]]; then
        write_upstream "${SITE_PORTS%% *}"
        log "Wrote $UPSTREAM_FILE (port ${SITE_PORTS%% *})"
    fi
    if [[ -n "$REPO_URL" && ! -d "$SHARED/repo.git" ]]; then
        log "Mirroring $REPO_URL"
        git clone --quiet --mirror "$REPO_URL" "$SHARED/repo.git"
    fi

    [[ -f "$SHARED/.env" ]] || warn "Create $SHARED/.env from .env.example (deploy/README.md)."
    [[ -f "$SHARED/site.env" ]] || warn "Create $SHARED/site.env from site/.env.example (deploy/README.md)."
    [[ -f "$SHARED/deploy.env" ]] || warn "Create $SHARED/deploy.env with REPO_URL and DOMAIN (deploy/README.md)."
    log "Setup done."
}

# ── deploy ──────────────────────────────────────────────────────────────────
new_release_id() {
    local id
    id="$(date -u +%Y%m%d%H%M%S)"
    # Two deploys in the same second (a quick retry): wait for the next one.
    while [[ -e "$RELEASES/$id" ]]; do
        sleep 1
        id="$(date -u +%Y%m%d%H%M%S)"
    done
    printf '%s' "$id"
}

fetch_source() {
    local release="$1" archive="$2" ref="$3" sha
    mkdir -p "$release"
    if [[ -n "$archive" ]]; then
        log "Unpacking $archive"
        tar -xzf "$archive" -C "$release"
        printf 'archive:%s\n' "$(basename "$archive")" > "$release/REVISION"
        return
    fi

    [[ -n "$REPO_URL" ]] || die "REPO_URL is not set (shared/deploy.env), and no --archive given."
    if [[ ! -d "$SHARED/repo.git" ]]; then
        git clone --quiet --mirror "$REPO_URL" "$SHARED/repo.git"
    fi
    log "Fetching $ref"
    git --git-dir="$SHARED/repo.git" remote set-url origin "$REPO_URL"
    git --git-dir="$SHARED/repo.git" fetch --quiet --prune origin '+refs/heads/*:refs/heads/*' '+refs/tags/*:refs/tags/*'
    sha="$(git --git-dir="$SHARED/repo.git" rev-parse --verify --quiet "$ref^{commit}")" \
        || die "Unknown ref '$ref'."
    git --git-dir="$SHARED/repo.git" archive --format=tar "$sha" | tar -x -C "$release"
    printf '%s %s\n' "$sha" "$ref" > "$release/REVISION"
    log "Release source: $ref ($sha)"
}

link_shared() {
    local release="$1"
    ln -sfn "$SHARED/.env" "$release/.env"
    rm -rf "$release/storage"
    ln -sfn "$SHARED/storage" "$release/storage"
    # Read by `next build` (NEXT_PUBLIC_* and API_URL are inlined at build time).
    ln -sfn "$SHARED/site.env" "$release/site/.env.production"
}

build_laravel() {
    local release="$1"
    log "composer install"
    (cd "$release" && "$COMPOSER_BIN" install --no-dev --optimize-autoloader --classmap-authoritative \
        --no-interaction --no-progress --prefer-dist)

    log "Admin build (Vite)"
    (cd "$release" && "$NPM_BIN" ci --no-audit --no-fund --loglevel=error && "$NPM_BIN" run build)

    log "Migrations"
    artisan "$release" migrate --force

    log "Laravel caches and storage link"
    artisan "$release" storage:link --force
    artisan "$release" config:cache
    artisan "$release" route:cache
    artisan "$release" view:cache
    artisan "$release" event:cache
}

build_site() {
    local release="$1" app
    log "Site build (Next.js standalone)"
    (cd "$release/site" && "$NPM_BIN" ci --no-audit --no-fund --loglevel=error && "$NPM_BIN" run build)

    # postbuild (site/scripts/standalone-assets.mjs) copies .next/static and
    # public/ beside server.js; make sure it did.
    app="$release/site/.next/standalone/site"
    [[ -f "$app/server.js" ]] || die "No $app/server.js: is output: \"standalone\" still set?"
    [[ -d "$app/.next/static" ]] || die "No $app/.next/static: did the postbuild step run?"

    # Every release's hashed assets in one place (nginx /_next/static/), so
    # pages from the previous release keep finding theirs after the switch.
    cp -R "$app/.next/static/." "$SHARED/next-static/"

    if [[ "$PRUNE_NODE_MODULES" == "true" ]]; then
        # The standalone server carries its own traced node_modules.
        rm -rf "$release/node_modules" "$release/site/node_modules"
    fi
}

# Start the site from $release on the idle port and check it directly.
# Prints the port it started on.
start_site_instance() {
    local release="$1" port
    port="$(idle_port)" || return 1
    swap_link "$release/site/.next/standalone/site" "$APP_DIR/site-$port" || return 1
    log "Starting the site on 127.0.0.1:$port" >&2
    privileged "$SYSTEMCTL" restart "spravato-site@$port" >&2 || return 1
    if ! wait_for_200 "http://127.0.0.1:$port/" -H "Host: $DOMAIN" -H "X-Forwarded-Proto: https" >&2; then
        privileged "$SYSTEMCTL" stop "spravato-site@$port" >&2 || true
        return 1
    fi
    printf '%s' "$port"
}

# Point `current` (and, in systemd mode, nginx) at a release, then reload.
# Every step is checked by hand: callers run this inside `if`, where bash
# ignores `set -e`.
switch_to() {
    local release="$1" port="$2"
    swap_link "$release" "$CURRENT" || return 1
    if [[ "$SITE_MANAGER" == "systemd" ]]; then
        write_upstream "$port" || return 1
        printf '%s\n' "$port" > "$PORT_FILE" || return 1
    else
        "$PM2_BIN" startOrReload "$release/deploy/pm2/ecosystem.config.cjs" --update-env || return 1
    fi
    reload_php || return 1
    reload_web || return 1
    artisan "$release" queue:restart || warn "queue:restart failed; restart spravato-queue by hand."
}

# The swap itself, with an automatic switch back on a failed check.
activate() {
    local release="$1" previous old_port="" new_port switched=false
    previous="$(current_release)"

    if [[ "$SITE_MANAGER" == "systemd" ]]; then
        old_port="$(active_port)"
        new_port="$(start_site_instance "$release")" \
            || die "The new site did not answer on its own port; nothing was switched. Logs: journalctl -u 'spravato-site@*' -n 100"
    else
        new_port="${SITE_PORTS%% *}"
    fi

    log "Switching to $(basename "$release")"
    if switch_to "$release" "$new_port"; then
        log "Checking through nginx"
        public_checks && switched=true
    else
        warn "The switch itself failed."
    fi

    if [[ "$switched" != true ]]; then
        if [[ -n "$previous" && "$previous" != "$release" ]]; then
            warn "Checks failed: switching back to $(basename "$previous")."
            switch_to "$previous" "${old_port:-$new_port}" \
                || die "Switching back failed too: fix by hand (deploy.sh status, journalctl, nginx -t)."
            if [[ "$SITE_MANAGER" == "systemd" && -n "$old_port" ]]; then
                privileged "$SYSTEMCTL" stop "spravato-site@$new_port" || true
            fi
            die "Release $(basename "$release") was rolled back. Logs: shared/storage/logs, journalctl -u 'spravato-site@*' (or pm2 logs)."
        fi
        die "Checks failed and there is no previous release to go back to."
    fi

    if [[ "$SITE_MANAGER" == "systemd" ]]; then
        privileged "$SYSTEMCTL" enable "spravato-site@$new_port" >/dev/null 2>&1 || true
        if [[ -n "$old_port" && "$old_port" != "$new_port" ]]; then
            log "Draining the old site on :$old_port for ${DRAIN_SECONDS}s"
            sleep "$DRAIN_SECONDS"
            privileged "$SYSTEMCTL" stop "spravato-site@$old_port" || true
            privileged "$SYSTEMCTL" disable "spravato-site@$old_port" >/dev/null 2>&1 || true
        fi
    fi
    touch "$release/.deployed"
    log "Live: $(basename "$release")"
}

prune() {
    local keep="$KEEP_RELEASES" live pinned id count
    live="$(basename "$(current_release)")"
    pinned=""
    for id in "$APP_DIR"/site-*; do
        [[ -L "$id" ]] && pinned="$pinned $(readlink "$id" | sed -E "s#^$RELEASES/([^/]+)/.*#\\1#")"
    done

    count="$(list_releases | wc -l | tr -d ' ')"
    for id in $(list_releases); do
        (( count > keep )) || break
        if [[ "$id" == "$live" || " $pinned " == *" $id "* ]]; then
            continue
        fi
        log "Removing old release $id"
        rm -rf "${RELEASES:?}/$id"
        count=$(( count - 1 ))
    done

    # Hashed assets no release has (re)written for a while. Every build
    # rewrites its own, so the live release's are always fresh.
    find "$SHARED/next-static" -type f -mtime "+$STATIC_RETENTION_DAYS" -delete 2>/dev/null || true
    find "$SHARED/next-static" -mindepth 1 -type d -empty -delete 2>/dev/null || true
}

# The release being built; global so the EXIT trap can still see it.
DEPLOY_RELEASE=""

on_deploy_exit() {
    rm -rf "$LOCK_DIR"
    if [[ -n "$DEPLOY_RELEASE" && -d "$DEPLOY_RELEASE" && ! -e "$DEPLOY_RELEASE/.activated" ]]; then
        warn "Removing the unfinished release $(basename "$DEPLOY_RELEASE")."
        if [[ "$(current_release)" == "$DEPLOY_RELEASE" ]]; then
            rm -f "$CURRENT"   # a first release that never finished
        fi
        rm -rf "$DEPLOY_RELEASE"
    fi
}

cmd_deploy() {
    local archive="" ref="$DEPLOY_REF" release id first_release=false
    while (( $# > 0 )); do
        case "$1" in
            --archive) archive="${2:-}"; [[ -f "$archive" ]] || die "No archive at '$archive'."; shift 2 ;;
            -*) die "Unknown option $1." ;;
            *) ref="$1"; shift ;;
        esac
    done

    require_settings
    need git curl tar "$PHP_BIN" "$COMPOSER_BIN" "$NPM_BIN" node
    if [[ "$SITE_MANAGER" == "pm2" ]]; then need "$PM2_BIN"; fi
    acquire_lock
    cmd_setup >/dev/null

    id="$(new_release_id)"
    release="$RELEASES/$id"
    [[ -n "$(current_release)" ]] || first_release=true

    # Until the site build is done, a failure only removes the half-built release.
    DEPLOY_RELEASE="$release"
    trap on_deploy_exit EXIT

    log "Release $id"
    fetch_source "$release" "$archive" "$ref"
    [[ -f "$release/artisan" && -f "$release/site/package.json" ]] || die "$release does not look like the Spravato repository."
    link_shared "$release"
    build_laravel "$release"

    if [[ "$first_release" == true ]]; then
        # Nothing is live yet, so nothing can break: put Laravel up now, as
        # `next build` prerenders pages from the API.
        log "First release: serving Laravel before the site build"
        swap_link "$release" "$CURRENT"
        reload_php
    fi

    build_site "$release"
    # From here on the release is kept, even if its checks fail (for the logs).
    touch "$release/.activated"

    activate "$release"
    prune
}

# ── rollback ────────────────────────────────────────────────────────────────
cmd_rollback() {
    local target="${1:-}" live id previous=""
    require_settings
    acquire_lock
    live="$(basename "$(current_release)")"

    if [[ -z "$target" ]]; then
        # The newest older release that once passed its checks.
        for id in $(list_releases); do
            [[ "$id" == "$live" ]] && break
            [[ -e "$RELEASES/$id/.deployed" ]] && previous="$id"
        done
        [[ -n "$previous" ]] || die "No earlier release that went live to roll back to."
        target="$previous"
    fi
    [[ -d "$RELEASES/$target" ]] || die "No release $target."
    [[ "$target" != "$live" ]] || die "$target is already live."
    [[ -f "$RELEASES/$target/site/.next/standalone/site/server.js" ]] || die "$target has no site build."

    warn "Rolling back $live → $target. Database migrations are NOT reverted."
    # Its cached assets may have been pruned from shared/next-static.
    cp -R "$RELEASES/$target/site/.next/standalone/site/.next/static/." "$SHARED/next-static/"
    activate "$RELEASES/$target"
}

# ── status ──────────────────────────────────────────────────────────────────
cmd_status() {
    local live id marker
    live="$(basename "$(current_release)" 2>/dev/null || true)"
    printf 'App dir:      %s\n' "$APP_DIR"
    printf 'Live release: %s\n' "${live:-none}"
    printf 'Site manager: %s\n' "$SITE_MANAGER"
    [[ "$SITE_MANAGER" == "systemd" ]] && printf 'Site port:    %s\n' "$(active_port || true)"
    printf 'Releases:\n'
    for id in $(list_releases); do
        marker=" "
        [[ "$id" == "$live" ]] && marker="*"
        printf '  %s %s  %s\n' "$marker" "$id" "$(head -c 60 "$RELEASES/$id/REVISION" 2>/dev/null | head -1)"
    done
}


main() {
    local command="${1:-}"
    [[ $# -gt 0 ]] && shift
    case "$command" in
        setup)    cmd_setup "$@" ;;
        deploy)   cmd_deploy "$@" ;;
        rollback) cmd_rollback "$@" ;;
        status)   cmd_status "$@" ;;
        *) sed -n '3,10p' "$0" | sed 's/^# \{0,1\}//'; [[ -z "$command" ]] || exit 1 ;;
    esac
}

main "$@"

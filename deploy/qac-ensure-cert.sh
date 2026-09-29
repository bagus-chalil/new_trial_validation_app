#!/usr/bin/env bash
# Ensure every trial-validation nginx vhost serves a certificate signed by the
# QAC internal root CA (see deploy/README.md section 10). Idempotent: safe to
# run on every deploy; it only changes something when needed.
#
#   1. Root CA: created ONCE if missing, never overwritten. Regenerating it
#      would invalidate the CA already installed on every user device.
#   2. Server cert: (re)issued only if missing, not signed by that CA, missing
#      one of the configured IPs in subjectAltName, or expiring within 30 days.
#   3. Vhosts: rewrites ssl_certificate/ssl_certificate_key in
#      /etc/nginx/sites-available/trial-validation*.conf to the shared cert.
#      Backs every file up first and restores them if `nginx -t` fails.
#   4. Reloads nginx only if the cert or a vhost actually changed.
#
# Runs as root. It is installed to /usr/local/sbin/qac-ensure-cert (root-owned)
# and the GitLab runner may only sudo that exact path — never point sudoers at
# this repo copy, which gitlab-runner can overwrite. Takes no arguments on
# purpose; settings come from /etc/default/qac-cert (root-owned) if present:
#
#   SERVER_IPS="100.100.160.23"            # space-separated, all go into SAN
#   VHOST_GLOB="/etc/nginx/sites-available/trial-validation*.conf"

set -euo pipefail

if [[ $EUID -ne 0 ]]; then
  echo "qac-ensure-cert: must run as root" >&2
  exit 1
fi

SERVER_IPS="100.100.160.23"
VHOST_GLOB="/etc/nginx/sites-available/trial-validation*.conf"
# shellcheck source=/dev/null
[[ -f /etc/default/qac-cert ]] && . /etc/default/qac-cert

SSL_DIR=/etc/nginx/ssl/qac
CA_KEY=$SSL_DIR/ca/qac-root-ca.key
CA_CRT=$SSL_DIR/ca/qac-root-ca.crt
SRV_KEY=$SSL_DIR/qac-server.key
SRV_CRT=$SSL_DIR/qac-server.crt
RENEW_BEFORE_SECONDS=$((30 * 24 * 3600))

# production and development pipelines can run at the same time.
exec 9>/run/qac-ensure-cert.lock
flock -w 300 9

changed=0
log() { echo "qac-ensure-cert: $*"; }

install -d -m 0755 "$SSL_DIR"
# ca/ is world-listable so CI (gitlab-runner) can read the PUBLIC CA cert and
# publish it on the portal; the CA key inside stays 0600 root-only.
install -d -m 0755 "$SSL_DIR/ca"

# 1. Root CA -----------------------------------------------------------------
if [[ ! -f $CA_KEY || ! -f $CA_CRT ]]; then
  if [[ -f $CA_KEY || -f $CA_CRT ]]; then
    log "only one of $CA_KEY / $CA_CRT exists; refusing to guess, fix manually" >&2
    exit 1
  fi
  log "creating root CA (one-time)"
  (umask 077 && openssl genrsa -out "$CA_KEY" 4096 2>/dev/null)
  openssl req -x509 -new -nodes -key "$CA_KEY" -sha256 -days 3650 \
    -subj "/O=Cosmax Indonesia/CN=QAC Internal Root CA" -out "$CA_CRT"
fi
chmod 0600 "$CA_KEY"
chmod 0644 "$CA_CRT"

# 2. Server cert -------------------------------------------------------------
needs_issue() {
  [[ -f $SRV_KEY && -f $SRV_CRT ]] || { log "server cert missing"; return 0; }
  openssl verify -CAfile "$CA_CRT" "$SRV_CRT" >/dev/null 2>&1 \
    || { log "server cert not signed by the QAC CA"; return 0; }
  openssl x509 -checkend "$RENEW_BEFORE_SECONDS" -noout -in "$SRV_CRT" >/dev/null \
    || { log "server cert expires within 30 days"; return 0; }
  local san
  san=$(openssl x509 -noout -ext subjectAltName -in "$SRV_CRT" 2>/dev/null || true)
  for ip in $SERVER_IPS; do
    grep -q "IP Address:$ip\b" <<<"$san" || { log "SAN missing $ip"; return 0; }
  done
  return 1
}

if needs_issue; then
  log "issuing server cert for: $SERVER_IPS"
  tmp=$(mktemp -d)
  trap 'rm -rf "$tmp"' EXIT
  san=$(for ip in $SERVER_IPS; do printf 'IP:%s,' "$ip"; done)
  cat >"$tmp/ext" <<EXT
basicConstraints=CA:FALSE
keyUsage=digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectAltName=${san%,}
EXT
  first_ip=${SERVER_IPS%% *}
  (umask 077 && openssl genrsa -out "$tmp/key" 2048 2>/dev/null)
  openssl req -new -key "$tmp/key" -subj "/CN=$first_ip" -out "$tmp/csr"
  openssl x509 -req -in "$tmp/csr" -CA "$CA_CRT" -CAkey "$CA_KEY" \
    -CAserial "$SSL_DIR/ca/qac-root-ca.srl" -CAcreateserial \
    -days 825 -sha256 -extfile "$tmp/ext" -out "$tmp/crt" 2>/dev/null
  install -m 0600 "$tmp/key" "$SRV_KEY"
  install -m 0644 "$tmp/crt" "$SRV_CRT"
  changed=1
fi

# 3. Vhosts ------------------------------------------------------------------
backup_dir=$(mktemp -d /tmp/qac-vhost-backup.XXXXXX)
edited=()
shopt -s nullglob
for vhost in $VHOST_GLOB; do
  grep -qE '^\s*ssl_certificate\s' "$vhost" || continue
  if grep -qE "^\s*ssl_certificate\s+$SRV_CRT;" "$vhost" \
    && grep -qE "^\s*ssl_certificate_key\s+$SRV_KEY;" "$vhost"; then
    continue
  fi
  cp -p "$vhost" "$backup_dir/"
  sed -i -E \
    -e "s#^(\s*)ssl_certificate\s+[^;]+;#\1ssl_certificate     $SRV_CRT;#" \
    -e "s#^(\s*)ssl_certificate_key\s+[^;]+;#\1ssl_certificate_key $SRV_KEY;#" \
    "$vhost"
  edited+=("$vhost")
  log "vhost now uses QAC cert: $vhost"
done

if ((${#edited[@]})); then
  changed=1
fi

# 4. Validate + reload -------------------------------------------------------
if ((changed)); then
  if ! nginx -t 2>&1; then
    log "nginx -t failed, restoring vhost backups" >&2
    for vhost in "${edited[@]}"; do
      cp -p "$backup_dir/$(basename "$vhost")" "$vhost"
    done
    exit 1
  fi
  systemctl reload nginx
  log "nginx reloaded"
else
  log "nothing to do"
fi
rm -rf "$backup_dir"

log "server cert valid until $(openssl x509 -enddate -noout -in "$SRV_CRT" | cut -d= -f2)"

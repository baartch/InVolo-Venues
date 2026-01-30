#!/usr/bin/env bash
set -euo pipefail

FTP_HOST="ftp.baart.ch"
FTP_USER="bathiismfu"
FTP_PASS="${FTP_PASSWORD:?FTP_PASSWORD environment variable is not set}"
REMOTE_DIR="/ch.involo.venues"

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

EXCLUDES=(
  ".git/"
  ".gitignore"
  "node_modules/"
  #"config/config.php"
  "bun.lock"
  "package.json"
  "tsconfig.json"
  "src/"
  "README.md"
  "setup.sh"
  "docs/"
  "AGENTS.md"
)

EXCLUDE_ARGS=$(printf " --exclude-glob %s" "${EXCLUDES[@]}")

lftp -u "${FTP_USER}","${FTP_PASS}" "${FTP_HOST}" <<EOF
set ftp:ssl-allow no
set net:max-retries 2
set net:timeout 10
set net:persist-retries 1

mirror -R --verbose --delete --delete-first --delete-excluded --only-newer \
  ${EXCLUDE_ARGS} \
  "${PROJECT_ROOT}" "${REMOTE_DIR}"

bye
EOF

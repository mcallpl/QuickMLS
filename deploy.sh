#!/bin/bash
# QuickMLS — Deploy to Digital Ocean
# Usage: ./deploy.sh

SERVER="root@64.227.108.128"
REMOTE_DIR="/var/www/html/QuickMLS"

echo "Deploying QuickMLS to Digital Ocean..."

rsync -avz \
    --exclude='config.local.php' \
    --exclude='.git' \
    --exclude='.claude' \
    --exclude='.DS_Store' \
    --delete \
    ./ "${SERVER}:${REMOTE_DIR}/"

ssh "${SERVER}" "chown -R www-data:www-data ${REMOTE_DIR}"

echo "Done! Live at: http://64.227.108.128/QuickMLS/"

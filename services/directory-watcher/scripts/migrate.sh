#!/bin/sh

USERNAME=$(cat /etc/svc-events/secrets/directory-watcher.ap-directory-watcher.credentials/username)
PASSWORD=$(cat /etc/svc-events/secrets/directory-watcher.ap-directory-watcher.credentials/password)

DATABASE_URL="postgres://$USERNAME:$PASSWORD@ap-directory-watcher:5432/directory_watcher" \
  dbmate --migrations-dir "./migrations/" migrate
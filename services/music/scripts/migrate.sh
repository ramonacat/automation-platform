#!/bin/sh

USERNAME=$(cat /etc/svc-events/secrets/music.ap-music.credentials/username)
PASSWORD=$(cat /etc/svc-events/secrets/music.ap-music.credentials/password)

ls ./migrations/

DATABASE_URL="postgres://$USERNAME:$PASSWORD@ap-music:5432/music" \
  dbmate --migrations-dir "./migrations/" migrate

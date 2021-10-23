#!/bin/sh

USERNAME=$(cat /etc/svc-events/secrets/events.ap-events.credentials/username)
PASSWORD=$(cat /etc/svc-events/secrets/events.ap-events.credentials/password)

DATABASE_URL="postgres://$USERNAME:$PASSWORD@ap-events:5432/events" \
  dbmate --migrations-dir "./migrations/" migrate
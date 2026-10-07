#!/bin/sh
set -e
echo "Waiting for MinIO..."
i=0
while [ "$i" -lt 30 ]; do
  if mc alias set local http://minio:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"; then
    break
  fi
  i=$((i + 1))
  sleep 2
done
mc mb -p local/livekit || true
echo "MinIO bucket livekit is ready."

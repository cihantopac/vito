#!/bin/bash


FILE_PATH="{{ $path }}"

if [ ! -f "$FILE_PATH" ]; then
    echo "Error: File '$FILE_PATH' does not exist"
    exit 1
fi

PERMISSIONS=$(stat -c "%a" "$FILE_PATH")
OWNER=$(stat -c "%U:%G" "$FILE_PATH")

sudo truncate -s 0 "$FILE_PATH"

sudo chown "$OWNER" "$FILE_PATH"
sudo chmod "$PERMISSIONS" "$FILE_PATH"

echo "File '$FILE_PATH' cleared successfully"

if ! /usr/bin/php{!! escapeshellarg($version) !!} -r 'echo json_encode(get_loaded_extensions());'; then
    echo 'VITO_SSH_ERROR' && exit 1
fi

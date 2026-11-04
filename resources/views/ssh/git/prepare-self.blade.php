if ! git config --global --get-all safe.directory | grep -qxF -- {!! $path !!}; then
    if ! git config --global --add safe.directory {!! $path !!}; then
        echo 'VITO_SSH_ERROR' && exit 1
    fi
fi

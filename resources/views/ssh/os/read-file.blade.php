sudo -n sh -c 'if [ -f "$1" ]; then cat -- "$1"; fi' sh {!! escapeshellarg($path) !!}

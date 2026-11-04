<?php

namespace App\ServerProviders;

interface AttachesPrivateNetworks extends ProvidesPrivateNetworks
{
    public function attachPrivateNetwork(array $instanceIds, string $name, ?string $networkId = null): ?array;
}

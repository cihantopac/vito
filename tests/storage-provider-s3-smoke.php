<?php

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\StorageProviders\S3;
use Aws\Command;
use Aws\S3\Exception\S3Exception;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message."\nExpected: {$expected}\nActual: {$actual}");
    }
}

assertSameValue(
    'Bucket not found. Check the bucket name.',
    S3::connectionErrorMessage(new S3Exception('missing bucket', new Command('ListBuckets'), ['code' => 'NoSuchBucket'])),
    'NoSuchBucket must be actionable in the UI.',
);

assertSameValue(
    'Access was denied. Check the storage token permissions.',
    S3::connectionErrorMessage(new S3Exception('denied', new Command('ListBuckets'), ['code' => 'AccessDenied'])),
    'AccessDenied must be actionable in the UI.',
);

assertSameValue(
    'The access key or secret key is invalid.',
    S3::connectionErrorMessage(new S3Exception('bad credentials', new Command('ListBuckets'), ['code' => 'SignatureDoesNotMatch'])),
    'Credential failures must be actionable in the UI.',
);

echo "PASS: S3 connection errors are safe and actionable.\n";

$provider = new class(new App\Models\StorageProvider(['provider' => 's3'])) extends S3
{
    public bool $connected = true;

    public function connect(array $credentials): bool
    {
        return $this->connected;
    }

    public function connectionError(): ?string
    {
        return 'Access was denied. Check the storage token permissions.';
    }
};

$verification = new App\Actions\StorageProvider\VerifyStorageConnection;
$verification->verify($provider, []);
$provider->connected = false;

try {
    $verification->verify($provider, []);
    throw new RuntimeException('Failed connections must block persistence.');
} catch (Illuminate\Validation\ValidationException $e) {
    assertSameValue($provider->connectionError(), $e->errors()['provider'][0], 'The provider error must reach form validation.');
}

echo "PASS: shared verification accepts valid connections and rejects failures with actionable errors.\n";

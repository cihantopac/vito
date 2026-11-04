<?php

namespace App\Tooling;

final class ToolingRegistry
{
    /** @var array<string, ToolingInterface>|null */
    private static ?array $instances = null;

    /** @return array<string, ToolingInterface> */
    public static function all(): array
    {
        if (self::$instances !== null) {
            return self::$instances;
        }

        $out = [];
        foreach (config('tooling.providers', []) as $class) {
            $out[$class::id()] = new $class;
        }

        return self::$instances = $out;
    }

    public static function find(string $id): ?ToolingInterface
    {
        return self::all()[$id] ?? null;
    }

    public static function ids(): array
    {
        return array_keys(self::all());
    }

    public static function commandReferences(string $command, string $toolId): bool
    {
        $tool = self::find($toolId);
        if (! $tool || $tool::commands() === []) {
            return false;
        }

        $alts = implode('|', array_map('preg_quote', $tool::commands()));
        $boundary = '[\s;&|`()]';
        $pattern = "/(^|$boundary)($alts)($boundary|$)/";

        return preg_match($pattern, $command) === 1;
    }
}

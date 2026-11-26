<?php
declare(strict_types=1);

final class ModuleRegistry
{
    public function __construct(private array $modules) { uasort($this->modules, static fn(array $a, array $b): int => $a['orden'] <=> $b['orden']); }
    public function all(): array { return $this->modules; }
    public function visible(): array
    {
        return array_filter($this->modules, static fn(array $module): bool => ($module['activo'] ?? false) && (empty($module['permiso']) || Permissions::allows((string) $module['permiso'])));
    }
    public function get(string $id): ?array { return $this->modules[$id] ?? null; }
}

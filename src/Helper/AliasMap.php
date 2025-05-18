<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Helper;

class AliasMap
{
	/**
	 * @var array<string, string>
	 */
	private array $aliasMap = [];

	public function addAlias(string $alias, string $className): void
	{
		$this->aliasMap[$alias] = $className;
	}

	public function getAlias(string $alias): ?string
	{
		return $this->aliasMap[$alias] ?? null;
	}

	public function has(string $alias): bool
	{
		return array_key_exists($alias, $this->aliasMap);
	}

    /**
     * @return string[]
     */
    public function getAliases(): array
	{
		return array_keys($this->aliasMap);
	}
}

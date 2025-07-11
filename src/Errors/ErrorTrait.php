<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Errors;

trait ErrorTrait
{
	public static function nonIndexedColumnError(string $entityClass, array $properties, int $line): array {
		return [
			'entityClass' => $entityClass,
			'properties' => $properties,
			'lineNumber' => $line,
		];
	}

	public static function genericError(string $message, string $identifer, int $line, ?string $tip): array {
		$result = [
			'message' => $message,
			'identifier' => $identifer,
			'line' => $line,
		];
		if ($tip !== null){
			$result['tip'] = $tip;
		}

		return $result;

	}
}

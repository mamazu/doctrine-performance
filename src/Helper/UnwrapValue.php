<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance\Helper;

use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PHPStan\Type\ThisType;
use PHPStan\Type\VerbosityLevel;

class UnwrapValue
{
	public static function string($string, Scope $scope): ?string
	{
		if (!is_object($string)) {
			return (string) $string;
		}

		if ($string instanceof String_) {
			return $string->value;

		} else if ($string instanceof ClassConstFetch) {
			return $scope->getType($string)->getValue();
		}

		return null;
	}

	public static function className($className, Scope $scope): string
	{
		if ($className instanceof String_) {
			return $className->value;
		}

		if ($className instanceof ClassConstFetch) {
			return $scope->getType($className)->getValue();
		}

		if ($className instanceof ThisType) {
			return $className->getStaticObjectType()->describe(VerbosityLevel::typeOnly());
		}

		debug_print_backtrace();
		dd($className);
	}
}

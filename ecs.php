<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;

return ECSConfig::configure()
	->withPaths([
		__DIR__ . '/src',
		__DIR__ . '/tests',
	])
	->withSpacing(Option::INDENTATION_TAB)

	// add sets - group of rules
	->withPreparedSets(
		symplify: true,
		arrays: true,
		namespaces: true,
		spaces: true,
		docblocks: true,
		comments: true,
		cleanCode: true,
	)
	;

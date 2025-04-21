<?php

declare(strict_types=1);

namespace Mamazu\DoctrinePerformance;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\String_;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<MethodCall>
 */
class DoctrineQueryBuilderPerformanceRule implements Rule
{
    private const RULE_IDENTIFIER = 'doctrine.performance';

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Identifier || (stripos((string) $node->name, 'where')) === false) {
            return [];
        }

        // Get the type of the object the method is called on
        $calledOnType = $scope->getType($node->var);

        // Doctrine\ORM\QueryBuilder or Doctrine\DBAL\Query\QueryBuilder
        if ($calledOnType->isInstanceOf('Doctrine\ORM\QueryBuilder')->no() &&
            $calledOnType->isInstanceOf('Doctrine\DBAL\Query\QueryBuilder')->no()) {
            return [];
        }

        $varName = $node->var->name;

        // Searching for the ->from() call to get the entity class name
        $methodCall = $this->getCallFromChain($node->var, 'from');
        if ($methodCall === null) {
            echo "Hallo";
            return [];
        }

        $argument = $methodCall->getArgs()[0]->value;
        $className = null;

        if ($argument instanceof String_) {
            $className = $argument->value;
        }

        if ($argument instanceof ClassConstFetch) {
            $className = $scope->getType($argument)->getValue();
        }

        return [
            RuleErrorBuilder::message('Class name: ' . $className . '|' . $methodCall->name)
                ->build(),
        ];
    }

    private function getCallFromChain(Node $node, string $name): ?MethodCall
    {
        if ($node instanceof MethodCall) {
            if ((string) $node->name === $name) {
                return $node;
            }

            return $this->getCallFromChain($node->var, $name);
        }

        return null;
    }
}

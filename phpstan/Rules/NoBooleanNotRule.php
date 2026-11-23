<?php

declare(strict_types=1);

namespace UserDb\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\BooleanNot;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports every "!" operator. The project writes negation explicitly as "=== false",
 * because "!" is easy to overlook when reading.
 *
 * @implements Rule<BooleanNot>
 */
final class NoBooleanNotRule implements Rule
{
    public function getNodeType(): string {
        return BooleanNot::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array {
        return [
            RuleErrorBuilder::message('Negation with "!" is not allowed, compare with "=== false" instead.')
                ->identifier('userdb.booleanNot')
                ->tip('Write "!$x->isValid()" as "$x->isValid() === false". For values that are not bool, compare with the actual value ("=== null", "=== \'\'", "=== []").')
                ->build(),
        ];
    }
}

<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace Rector\OxidEsales\Rector;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Core\Rector\AbstractRector;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UpdateToV7UnderscoredMethodsRector extends AbstractRector
{
    private const PSR_12_DEPRECATION_TEXT = 'underscore prefix violates PSR12, will be renamed to';

    /** @var string */
    private static $storage;

    /** @var PhpDocTagRemover */
    private $phpDocTagRemover;

    public static function setStorage(string $storage): void
    {
        self::$storage = $storage;
    }

    public function __construct(
        PhpDocTagRemover $phpDocTagRemover
    ) {
        $this->phpDocTagRemover = $phpDocTagRemover;
    }

    /** @inheritDoc */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /** @inheritDoc */
    public function refactor(Node $node): ?Node
    {
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        $deprecatedTagValues = $this->phpDocInfoFactory
            ->createFromNode($node)
            ->getPhpDocNode()
            ->getDeprecatedTagValues();

        $file = fopen(self::$storage, 'ab');
        foreach ($deprecatedTagValues as $deprecatedTagValue) {
            if (str_starts_with($deprecatedTagValue->description, self::PSR_12_DEPRECATION_TEXT)) {
                $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $deprecatedTagValue);
                $classNode = $node->getAttribute(AttributeKey::PARENT_NODE);
                fputcsv($file, [$this->getName($classNode), $this->getName($node)]);
            }
        }
        fclose($file);

        return $node;
    }

    /** @inheritDoc */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Removes Oxid sShop v7.0 PSR12 method deprecations and collects affected class and methods to file for further processing.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
    * @deprecated underscore prefix violates PSR12, will be renamed to "getObjectKey" in next major
     */
    protected function _methodName() // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
     */
    protected function _methodName() // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
CODE_SAMPLE
                ),
            ]
        );
    }

}

<?php

declare (strict_types=1);
namespace Rector\Symfony\Rector\Attribute;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use Rector\Core\Rector\AbstractRector;
use Rector\FileSystemRector\ValueObject\AddedFileWithNodes;
use Rector\Symfony\ConstantNameAndValueMatcher;
use Rector\Symfony\ConstantNameAndValueResolver;
use Rector\Symfony\NodeFactory\RouteNameClassFactory;
use Rector\Symfony\ValueObject\ClassName;
use Rector\Symfony\ValueObject\ConstantNameAndValue;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ExtraFileCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use RectorPrefix20210609\Symplify\SmartFileSystem\SmartFileSystem;
/**
 * @see https://tomasvotruba.com/blog/2020/12/21/5-new-combos-opened-by-symfony-52-and-php-80/
 *
 * @see \Rector\Symfony\Tests\Rector\Attribute\ExtractAttributeRouteNameConstantsRector\ExtractAttributeRouteNameConstantsRectorTest
 */
final class ExtractAttributeRouteNameConstantsRector extends \Rector\Core\Rector\AbstractRector
{
    /**
     * @var string
     */
    private const ROUTE_NAME_FILE_LOCATION = 'src/ValueObject/Routing/RouteName.php';
    /**
     * @var bool
     */
    private $isRouteNameValueObjectCreated = \false;
    /**
     * @var \Rector\Symfony\NodeFactory\RouteNameClassFactory
     */
    private $routeNameClassFactory;
    /**
     * @var \Rector\Symfony\ConstantNameAndValueMatcher
     */
    private $constantNameAndValueMatcher;
    /**
     * @var \Rector\Symfony\ConstantNameAndValueResolver
     */
    private $constantNameAndValueResolver;
    /**
     * @var \Symplify\SmartFileSystem\SmartFileSystem
     */
    private $smartFileSystem;
    public function __construct(\Rector\Symfony\NodeFactory\RouteNameClassFactory $routeNameClassFactory, \Rector\Symfony\ConstantNameAndValueMatcher $constantNameAndValueMatcher, \Rector\Symfony\ConstantNameAndValueResolver $constantNameAndValueResolver, \RectorPrefix20210609\Symplify\SmartFileSystem\SmartFileSystem $smartFileSystem)
    {
        $this->routeNameClassFactory = $routeNameClassFactory;
        $this->constantNameAndValueMatcher = $constantNameAndValueMatcher;
        $this->constantNameAndValueResolver = $constantNameAndValueResolver;
        $this->smartFileSystem = $smartFileSystem;
    }
    public function getRuleDefinition() : \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
    {
        return new \Symplify\RuleDocGenerator\ValueObject\RuleDefinition('Extract #[Route] attribute name argument from string to constant', [new \Symplify\RuleDocGenerator\ValueObject\CodeSample\ExtraFileCodeSample(<<<'CODE_SAMPLE'
use Symfony\Component\Routing\Annotation\Route;

class SomeClass
{
    #[Route(path: "/path", name: "name")]
    public function run()
    {
    }
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
use Symfony\Component\Routing\Annotation\Route;

class SomeClass
{
    #[Route(path: "/path", name: RouteName::NAME)]
    public function run()
    {
    }
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
final class RouteName
{
    /**
     * @var string
     */
    public NAME = 'name';
}
CODE_SAMPLE
)]);
    }
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes() : array
    {
        return [\PhpParser\Node\Attribute::class];
    }
    /**
     * @param Attribute $node
     */
    public function refactor(\PhpParser\Node $node) : ?\PhpParser\Node
    {
        if (!$this->isName($node->name, 'Symfony\\Component\\Routing\\Annotation\\Route')) {
            return null;
        }
        $this->createRouteNameValueObject();
        foreach ($node->args as $arg) {
            if (!$this->isName($arg, 'name')) {
                continue;
            }
            $constantNameAndValue = $this->constantNameAndValueMatcher->matchFromArg($arg, 'ROUTE_');
            if (!$constantNameAndValue instanceof \Rector\Symfony\ValueObject\ConstantNameAndValue) {
                continue;
            }
            $arg->value = $this->nodeFactory->createClassConstFetch(\Rector\Symfony\ValueObject\ClassName::ROUTE_CLASS_NAME, $constantNameAndValue->getName());
        }
        return $node;
    }
    private function createRouteNameValueObject() : void
    {
        if ($this->isRouteNameValueObjectCreated) {
            return;
        }
        $smartFileInfo = $this->file->getSmartFileInfo();
        $directory = $smartFileInfo->getPath();
        $routeNameFilePath = $directory . '/' . self::ROUTE_NAME_FILE_LOCATION;
        if ($this->smartFileSystem->exists($routeNameFilePath)) {
            // avoid override
            return;
        }
        $routeAttributes = $this->nodeRepository->findAttributes('Symfony\\Component\\Routing\\Annotation\\Route');
        $constantNameAndValues = $this->constantNameAndValueResolver->resolveFromAttributes($routeAttributes, 'ROUTE_');
        // do not create empty file
        if ($constantNameAndValues === []) {
            return;
        }
        $namespace = $this->routeNameClassFactory->create($constantNameAndValues, $routeNameFilePath);
        $addedFileWithNodes = new \Rector\FileSystemRector\ValueObject\AddedFileWithNodes($routeNameFilePath, [$namespace]);
        $this->removedAndAddedFilesCollector->addAddedFile($addedFileWithNodes);
        $this->isRouteNameValueObjectCreated = \true;
    }
}

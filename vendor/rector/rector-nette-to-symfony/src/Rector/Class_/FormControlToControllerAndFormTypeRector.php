<?php

declare (strict_types=1);
namespace Rector\NetteToSymfony\Rector\Class_;

use RectorPrefix20210609\Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PHPStan\Type\ObjectType;
use Rector\Core\Rector\AbstractRector;
use Rector\FileSystemRector\ValueObject\AddedFileWithNodes;
use Rector\NetteToSymfony\Collector\OnFormVariableMethodCallsCollector;
use Rector\NetteToSymfony\NodeFactory\BuildFormClassMethodFactory;
use Rector\NetteToSymfony\NodeFactory\SymfonyControllerFactory;
use Rector\NetteToSymfony\NodeFactory\SymfonyMethodCallsFactory;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ExtraFileCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
/**
 * @changelog https://symfony.com/doc/current/forms.html#creating-form-classes
 *
 * @see \Rector\NetteToSymfony\Tests\Rector\Class_\FormControlToControllerAndFormTypeRector\FormControlToControllerAndFormTypeRectorTest
 */
final class FormControlToControllerAndFormTypeRector extends \Rector\Core\Rector\AbstractRector
{
    /**
     * @var \Rector\NetteToSymfony\Collector\OnFormVariableMethodCallsCollector
     */
    private $onFormVariableMethodCallsCollector;
    /**
     * @var \Rector\NetteToSymfony\NodeFactory\SymfonyControllerFactory
     */
    private $symfonyControllerFactory;
    /**
     * @var \Rector\NetteToSymfony\NodeFactory\BuildFormClassMethodFactory
     */
    private $buildFormClassMethodFactory;
    /**
     * @var \Rector\NetteToSymfony\NodeFactory\SymfonyMethodCallsFactory
     */
    private $symfonyMethodCallsFactory;
    public function __construct(\Rector\NetteToSymfony\Collector\OnFormVariableMethodCallsCollector $onFormVariableMethodCallsCollector, \Rector\NetteToSymfony\NodeFactory\SymfonyControllerFactory $symfonyControllerFactory, \Rector\NetteToSymfony\NodeFactory\BuildFormClassMethodFactory $buildFormClassMethodFactory, \Rector\NetteToSymfony\NodeFactory\SymfonyMethodCallsFactory $symfonyMethodCallsFactory)
    {
        $this->onFormVariableMethodCallsCollector = $onFormVariableMethodCallsCollector;
        $this->symfonyControllerFactory = $symfonyControllerFactory;
        $this->buildFormClassMethodFactory = $buildFormClassMethodFactory;
        $this->symfonyMethodCallsFactory = $symfonyMethodCallsFactory;
    }
    public function getRuleDefinition() : \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
    {
        return new \Symplify\RuleDocGenerator\ValueObject\RuleDefinition('Change Form that extends Control to Controller and decoupled FormType', [new \Symplify\RuleDocGenerator\ValueObject\CodeSample\ExtraFileCodeSample(<<<'CODE_SAMPLE'
use Nette\Application\UI\Form;
use Nette\Application\UI\Control;

class SomeForm extends Control
{
    public function createComponentForm()
    {
        $form = new Form();
        $form->addText('name', 'Your name');

        $form->onSuccess[] = [$this, 'processForm'];
    }

    public function processForm(Form $form)
    {
        // process me
    }
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SomeFormController extends AbstractController
{
    /**
     * @Route(...)
     */
    public function actionSomeForm(Request $request): Response
    {
        $form = $this->createForm(SomeFormType::class);
        $form->handleRequest($request);

        if ($form->isSuccess() && $form->isValid()) {
            // process me
        }
    }
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
<?php

namespace RectorPrefix20210609;

use RectorPrefix20210609\Symfony\Component\Form\AbstractType;
use RectorPrefix20210609\Symfony\Component\Form\Extension\Core\Type\TextType;
use RectorPrefix20210609\Symfony\Component\Form\FormBuilderInterface;
class SomeFormType extends \RectorPrefix20210609\Symfony\Component\Form\AbstractType
{
    public function buildForm(\RectorPrefix20210609\Symfony\Component\Form\FormBuilderInterface $formBuilder, array $options)
    {
        $formBuilder->add('name', \RectorPrefix20210609\Symfony\Component\Form\Extension\Core\Type\TextType::class, ['label' => 'Your name']);
    }
}
\class_alias('SomeFormType', 'SomeFormType', \false);
CODE_SAMPLE
)]);
    }
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes() : array
    {
        return [\PhpParser\Node\Stmt\Class_::class];
    }
    /**
     * @param Class_ $node
     */
    public function refactor(\PhpParser\Node $node) : ?\PhpParser\Node
    {
        if (!$this->isObjectType($node, new \PHPStan\Type\ObjectType('Nette\\Application\\UI\\Control'))) {
            return null;
        }
        foreach ($node->getMethods() as $classMethod) {
            if (!$this->isName($classMethod->name, 'createComponent*')) {
                continue;
            }
            $formTypeClass = $this->collectFormMethodCallsAndCreateFormTypeClass($classMethod);
            if (!$formTypeClass instanceof \PhpParser\Node\Stmt\Class_) {
                continue;
            }
            $symfonyControllerNamespace = $this->symfonyControllerFactory->createNamespace($node, $formTypeClass);
            if (!$symfonyControllerNamespace instanceof \PhpParser\Node\Stmt\Namespace_) {
                continue;
            }
            $shortClassName = $this->resolveControllerClassName($node);
            $smartFileInfo = $this->file->getSmartFileInfo();
            $directory = $smartFileInfo->getPath();
            $controllerFilePath = $directory . '/' . $shortClassName . '.php';
            $addedFileWithNodes = new \Rector\FileSystemRector\ValueObject\AddedFileWithNodes($controllerFilePath, [$symfonyControllerNamespace]);
            $this->removedAndAddedFilesCollector->addAddedFile($addedFileWithNodes);
            return $formTypeClass;
        }
        return null;
    }
    private function collectFormMethodCallsAndCreateFormTypeClass(\PhpParser\Node\Stmt\ClassMethod $classMethod) : ?\PhpParser\Node\Stmt\Class_
    {
        $onFormVariableMethodCalls = $this->onFormVariableMethodCallsCollector->collectFromClassMethod($classMethod);
        if ($onFormVariableMethodCalls === []) {
            return null;
        }
        $formBuilderVariable = new \PhpParser\Node\Expr\Variable('formBuilder');
        // public function buildForm(\Symfony\Component\Form\FormBuilderInterface $formBuilder, array $options)
        $buildFormClassMethod = $this->buildFormClassMethodFactory->create($formBuilderVariable);
        $symfonyMethodCalls = $this->symfonyMethodCallsFactory->create($onFormVariableMethodCalls, $formBuilderVariable);
        $buildFormClassMethod->stmts = $symfonyMethodCalls;
        return $this->createFormTypeClassFromBuildFormClassMethod($buildFormClassMethod);
    }
    private function createFormTypeClassFromBuildFormClassMethod(\PhpParser\Node\Stmt\ClassMethod $buildFormClassMethod) : \PhpParser\Node\Stmt\Class_
    {
        $formTypeClass = new \PhpParser\Node\Stmt\Class_('SomeFormType');
        $formTypeClass->flags |= \PhpParser\Node\Stmt\Class_::MODIFIER_FINAL;
        $formTypeClass->extends = new \PhpParser\Node\Name\FullyQualified('Symfony\\Component\\Form\\AbstractType');
        $formTypeClass->stmts[] = $buildFormClassMethod;
        return $formTypeClass;
    }
    private function resolveControllerClassName(\PhpParser\Node\Stmt\Class_ $class) : string
    {
        $shortClassName = $this->nodeNameResolver->getShortName($class);
        if (\substr_compare($shortClassName, 'Form', -\strlen('Form')) === 0) {
            $shortClassName = \RectorPrefix20210609\Nette\Utils\Strings::before($shortClassName, 'Form');
        } else {
            $shortClassName = \RectorPrefix20210609\Nette\Utils\Strings::before($shortClassName, 'Control');
        }
        return $shortClassName . 'Controller';
    }
}

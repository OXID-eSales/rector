<?php

declare (strict_types=1);
namespace Rector\Core\DependencyInjection\CompilerPass;

use Rector\Core\Configuration\Option;
use RectorPrefix20210609\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use RectorPrefix20210609\Symfony\Component\DependencyInjection\ContainerBuilder;
final class DeprecationWarningCompilerPass implements \RectorPrefix20210609\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface
{
    /**
     * @var array<string, string>
     */
    private const DEPRECATED_PARAMETERS = [\Rector\Core\Configuration\Option::SETS => 'Use $containerConfigurator->import(<set>); instead'];
    public function process(\RectorPrefix20210609\Symfony\Component\DependencyInjection\ContainerBuilder $containerBuilder) : void
    {
        $parameterBag = $containerBuilder->getParameterBag();
        foreach (self::DEPRECATED_PARAMETERS as $parameter => $message) {
            if (!$parameterBag->has($parameter)) {
                continue;
            }
            $setsParameters = $parameterBag->get($parameter);
            if ($setsParameters === []) {
                continue;
            }
            $message = \sprintf('The "%s" parameter is deprecated. %s', $parameter, $message);
            \trigger_error($message);
            // to make it noticable
            \sleep(2);
        }
    }
}

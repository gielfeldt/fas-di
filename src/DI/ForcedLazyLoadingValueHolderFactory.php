<?php

declare(strict_types=1);

namespace Fas\DI;

use Closure;
use Laminas\Code\Generator\ClassGenerator;
use OutOfBoundsException;
use ProxyManager\Configuration;
use ProxyManager\Proxy\VirtualProxyInterface;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolderGenerator;
use ProxyManager\ProxyGenerator\ProxyGeneratorInterface;
use ProxyManager\Signature\Exception\InvalidSignatureException;
use ProxyManager\Signature\Exception\MissingSignatureException;
use ProxyManager\Version;
use ReflectionClass;

/**
 * Base factory common logic
 */
class ForcedLazyLoadingValueHolderFactory
{
    protected Configuration $configuration;

    private LazyLoadingValueHolderGenerator $generator;

    public function __construct(?Configuration $configuration = null)
    {
        $this->configuration = $configuration ?? new Configuration();

        $this->generator = new LazyLoadingValueHolderGenerator();
    }

    /**
     * @param array<string, mixed> $proxyOptions
     *
     * @psalm-template RealObjectType of object
     *
     * @psalm-param class-string<RealObjectType> $className
     * @psalm-param Closure(
     *   RealObjectType|null=,
     *   RealObjectType&ValueHolderInterface<RealObjectType>&VirtualProxyInterface=,
     *   string=,
     *   array<string, mixed>=,
     *   ?Closure=
     * ) : bool $initializer
     *
     * @psalm-return RealObjectType&ValueHolderInterface<RealObjectType>&VirtualProxyInterface
     *
     * @psalm-suppress MixedInferredReturnType We ignore type checks here, since `staticProxyConstructor` is not
     *                                         interfaced (by design)
     */
    public function createProxy(
        string $className,
        Closure $initializer,
        array $proxyOptions = []
    ): VirtualProxyInterface {
        $proxyClassName = $this->generateProxy($className, $proxyOptions);

        /**
         * We ignore type checks here, since `staticProxyConstructor` is not interfaced (by design)
         *
         * @psalm-suppress MixedMethodCall
         * @psalm-suppress MixedReturnStatement
         */
        return $proxyClassName::staticProxyConstructor($initializer);
    }

    /**
     * Generate a proxy from a class name
     *
     * @param array<string, mixed> $proxyOptions
     *
     * @throws InvalidSignatureException
     * @throws MissingSignatureException
     * @throws OutOfBoundsException
     *
     * @psalm-template RealObjectType of object
     *
     * @psalm-param class-string<RealObjectType> $className
     *
     * @psalm-return class-string<RealObjectType>
     */
    protected function generateProxy(string $className, array $proxyOptions = []): string
    {
        $proxyParameters = [
            'className'           => $className,
            'factory'             => static::class,
            'proxyManagerVersion' => Version::getVersion(),
            'proxyOptions'        => $proxyOptions,
        ];
        $proxyClassName  = $this
            ->configuration
            ->getClassNameInflector()
            ->getProxyClassName($className, $proxyParameters);

        $this->generateProxyClass(
            $proxyClassName,
            $className,
            $proxyParameters,
            $proxyOptions
        );

        $this
            ->configuration
            ->getSignatureChecker()
            ->checkSignature(new ReflectionClass($proxyClassName), $proxyParameters);

        return $proxyClassName;
    }

    protected function getGenerator(): ProxyGeneratorInterface
    {
        return $this->generator;
    }

    /**
     * Generates the provided `$proxyClassName` from the given `$className` and `$proxyParameters`
     *
     * @param array<string, mixed> $proxyParameters
     * @param array<string, mixed> $proxyOptions
     *
     * @psalm-param class-string $proxyClassName
     * @psalm-param class-string $className
     */
    private function generateProxyClass(
        string $proxyClassName,
        string $className,
        array $proxyParameters,
        array $proxyOptions = []
    ): void {
        $className = $this->configuration->getClassNameInflector()->getUserClassName($className);
        $phpClass  = new ClassGenerator($proxyClassName);

        /**
         * @psalm-suppress TooManyArguments - generator interface was not updated due to BC compliance
         */
        $this->getGenerator()->generate(new ReflectionClass($className), $phpClass, $proxyOptions);

        $phpClass = $this->configuration->getClassSignatureGenerator()->addSignature($phpClass, $proxyParameters);

        /**
         * @psalm-suppress TooManyArguments - generator interface was not updated due to BC compliance
         */
        $this->configuration->getGeneratorStrategy()->generate($phpClass, $proxyOptions);

        $autoloader = $this->configuration->getProxyAutoloader();

        $autoloader($proxyClassName);
    }
}

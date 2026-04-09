<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Tests;

use MulerTech\CaptchaBundle\Controller\CaptchaController;
use MulerTech\CaptchaBundle\MulerTechCaptchaBundle;
use MulerTech\CaptchaBundle\Service\CaptchaGenerator;
use MulerTech\CaptchaBundle\Service\CaptchaImageRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader as RoutingPhpFileLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;

final class MulerTechCaptchaBundleTest extends TestCase
{
    private MulerTechCaptchaBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new MulerTechCaptchaBundle();
    }

    public function testLoadExtensionRegistersServices(): void
    {
        $container = new ContainerBuilder();

        $twigExtension = new class implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void {}

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): string|false
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'twig';
            }
        };
        $container->registerExtension($twigExtension);

        $fileLocator = new FileLocator();
        $phpLoader = new PhpFileLoader($container, $fileLocator);
        $instanceof = [];
        $configurator = new ContainerConfigurator($container, $phpLoader, $instanceof, '', __DIR__);

        $this->bundle->loadExtension([], $configurator, $container);

        self::assertTrue($container->hasDefinition('mulertech_captcha.generator'));
        self::assertTrue($container->hasDefinition('mulertech_captcha.image_renderer'));
        self::assertTrue($container->hasDefinition(CaptchaController::class));
        self::assertTrue($container->hasDefinition('mulertech_captcha.form_type'));
        self::assertTrue($container->hasDefinition('mulertech_captcha.validator'));
        self::assertTrue($container->hasAlias(CaptchaGenerator::class));
        self::assertTrue($container->hasAlias(CaptchaImageRenderer::class));
    }

    public function testPrependExtensionConfiguresTwigFormTheme(): void
    {
        $container = new ContainerBuilder();

        $twigExtension = new class implements ExtensionInterface {
            /** @var array<int, array<string, mixed>> */
            public array $configs = [];

            public function load(array $configs, ContainerBuilder $container): void
            {
                $this->configs = $configs;
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): string|false
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'twig';
            }
        };
        $container->registerExtension($twigExtension);

        $frameworkExtension = new class implements ExtensionInterface {
            /** @var array<int, array<string, mixed>> */
            public array $configs = [];

            public function load(array $configs, ContainerBuilder $container): void
            {
                $this->configs = $configs;
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): string|false
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'framework';
            }
        };
        $container->registerExtension($frameworkExtension);

        $fileLocator = new FileLocator();
        $phpLoader = new PhpFileLoader($container, $fileLocator);
        $instanceof = [];
        $configurator = new ContainerConfigurator($container, $phpLoader, $instanceof, '', __DIR__);

        $this->bundle->prependExtension($configurator, $container);

        $twigConfig = $container->getExtensionConfig('twig');
        self::assertNotEmpty($twigConfig);
        self::assertSame(['@MulerTechCaptcha/form/captcha.html.twig'], $twigConfig[0]['form_themes']);

        $frameworkConfig = $container->getExtensionConfig('framework');
        self::assertNotEmpty($frameworkConfig);
        self::assertArrayHasKey('translator', $frameworkConfig[0]);
    }

    public function testPrependExtensionWithoutTwigOrFramework(): void
    {
        $container = new ContainerBuilder();

        $fileLocator = new FileLocator();
        $phpLoader = new PhpFileLoader($container, $fileLocator);
        $instanceof = [];
        $configurator = new ContainerConfigurator($container, $phpLoader, $instanceof, '', __DIR__);

        $this->bundle->prependExtension($configurator, $container);

        self::assertEmpty($container->getExtensionConfig('twig'));
        self::assertEmpty($container->getExtensionConfig('framework'));
    }

    public function testLoadRoutesRegistersRoutes(): void
    {
        $collection = new RouteCollection();
        $fileLocator = new FileLocator();
        $phpLoader = new RoutingPhpFileLoader($fileLocator);
        $yamlLoader = new YamlFileLoader($fileLocator);
        $resolver = new LoaderResolver([$phpLoader, $yamlLoader]);
        $phpLoader->setResolver($resolver);
        $yamlLoader->setResolver($resolver);

        $configurator = new RoutingConfigurator($collection, $phpLoader, '', '');

        $this->bundle->loadRoutes($configurator);

        self::assertNotNull($collection->get('mulertech_captcha_image'));
        self::assertNotNull($collection->get('mulertech_captcha_refresh'));
    }
}

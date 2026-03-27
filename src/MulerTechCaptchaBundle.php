<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle;

use MulerTech\CaptchaBundle\Controller\CaptchaController;
use MulerTech\CaptchaBundle\Form\CaptchaType;
use MulerTech\CaptchaBundle\Service\CaptchaGenerator;
use MulerTech\CaptchaBundle\Service\CaptchaImageRenderer;
use MulerTech\CaptchaBundle\Validator\ValidCaptchaValidator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class MulerTechCaptchaBundle extends AbstractBundle
{
    protected string $extensionAlias = 'mulertech_captcha';

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('twig')) {
            $container->extension('twig', [
                'form_themes' => ['@MulerTechCaptcha/form/captcha.html.twig'],
            ]);
        }

        if ($builder->hasExtension('framework')) {
            $container->extension('framework', [
                'translator' => [
                    'paths' => [$this->getPath().'/translations'],
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set('mulertech_captcha.generator', CaptchaGenerator::class)
            ->alias(CaptchaGenerator::class, 'mulertech_captcha.generator');

        $container->services()
            ->set('mulertech_captcha.image_renderer', CaptchaImageRenderer::class)
            ->alias(CaptchaImageRenderer::class, 'mulertech_captcha.image_renderer');

        $container->services()
            ->set(CaptchaController::class)
            ->args([
                new Reference('mulertech_captcha.generator'),
                new Reference('mulertech_captcha.image_renderer'),
                new Reference('request_stack'),
                new Reference('router'),
            ])
            ->public()
            ->tag('controller.service_arguments');

        $container->services()
            ->set('mulertech_captcha.form_type', CaptchaType::class)
            ->args([
                new Reference('mulertech_captcha.generator'),
                new Reference('request_stack'),
                new Reference('router'),
                new Reference('MulerTech\CspBundle\CspNonceGenerator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->tag('form.type');

        $container->services()
            ->set('mulertech_captcha.validator', ValidCaptchaValidator::class)
            ->args([
                new Reference('mulertech_captcha.generator'),
                new Reference('request_stack'),
            ])
            ->tag('validator.constraint_validator');
    }

    public function loadRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../config/routes.yaml');
    }
}

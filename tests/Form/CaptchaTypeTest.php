<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Tests\Form;

use MulerTech\CaptchaBundle\Form\CaptchaType;
use MulerTech\CaptchaBundle\Service\CaptchaGenerator;
use MulerTech\CaptchaBundle\Validator\ValidCaptcha;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CaptchaTypeTest extends TestCase
{
    private RequestStack $requestStack;
    private UrlGeneratorInterface $urlGenerator;
    private CaptchaType $type;

    protected function setUp(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $generator = new CaptchaGenerator();
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->requestStack->method('getSession')->willReturn($session);
        $this->urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $this->type = new CaptchaType($generator, $this->requestStack, $this->urlGenerator);
    }

    public function testGetBlockPrefix(): void
    {
        self::assertSame('mulertech_captcha', $this->type->getBlockPrefix());
    }

    public function testConfigureOptions(): void
    {
        $resolver = new OptionsResolver();
        $this->type->configureOptions($resolver);

        $resolved = $resolver->resolve([]);

        self::assertFalse($resolved['mapped']);
        self::assertFalse($resolved['label']);
        self::assertFalse($resolved['inherit_data']);
        self::assertFalse($resolved['error_bubbling']);
        self::assertNull($resolved['csp_nonce']);
        self::assertIsArray($resolved['constraints']);
        self::assertCount(1, $resolved['constraints']);
        self::assertInstanceOf(ValidCaptcha::class, $resolved['constraints'][0]);
    }

    public function testConfigureOptionsWithCspNonce(): void
    {
        $resolver = new OptionsResolver();
        $this->type->configureOptions($resolver);

        $resolved = $resolver->resolve(['csp_nonce' => 'test-nonce']);

        self::assertSame('test-nonce', $resolved['csp_nonce']);
    }

    public function testBuildFormAddsTokenAndAnswerFields(): void
    {
        /** @var FormBuilderInterface&MockObject $builder */
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::exactly(2))
            ->method('add')
            ->willReturnSelf();

        $this->type->buildForm($builder, []);
    }

    public function testBuildViewSetsCaptchaUrls(): void
    {
        $this->urlGenerator
            ->method('generate')
            ->willReturnOnConsecutiveCalls('/captcha/image?token=token123', '/captcha/refresh');

        $view = new FormView();

        $tokenField = $this->createStub(FormInterface::class);
        $tokenField->method('getData')->willReturn('token123');

        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturn($tokenField);

        $this->type->buildView($view, $form, ['csp_nonce' => null]);

        self::assertSame('/captcha/image?token=token123', $view->vars['captcha_image_url']);
        self::assertSame('/captcha/refresh', $view->vars['captcha_refresh_url']);
    }

    public function testBuildViewWithNullTokenUsesEmptyString(): void
    {
        $this->urlGenerator
            ->method('generate')
            ->willReturnOnConsecutiveCalls('/captcha/image?token=', '/captcha/refresh');

        $view = new FormView();

        $tokenField = $this->createStub(FormInterface::class);
        $tokenField->method('getData')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturn($tokenField);

        $this->type->buildView($view, $form, ['csp_nonce' => null]);

        self::assertSame('/captcha/image?token=', $view->vars['captcha_image_url']);
        self::assertSame('/captcha/refresh', $view->vars['captcha_refresh_url']);
    }

    public function testBuildViewWithExplicitCspNonce(): void
    {
        $this->urlGenerator
            ->method('generate')
            ->willReturn('/captcha/url');

        $view = new FormView();

        $tokenField = $this->createStub(FormInterface::class);
        $tokenField->method('getData')->willReturn('token123');

        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturn($tokenField);

        $this->type->buildView($view, $form, ['csp_nonce' => 'explicit-nonce']);

        self::assertSame('explicit-nonce', $view->vars['csp_nonce']);
    }

    public function testBuildViewWithoutCspNonceReturnsNull(): void
    {
        $this->urlGenerator
            ->method('generate')
            ->willReturn('/captcha/url');

        $view = new FormView();

        $tokenField = $this->createStub(FormInterface::class);
        $tokenField->method('getData')->willReturn('token123');

        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturn($tokenField);

        $this->type->buildView($view, $form, ['csp_nonce' => null]);

        self::assertNull($view->vars['csp_nonce']);
    }

    public function testBuildViewAutoDetectsCspNonceGenerator(): void
    {
        $nonceGenerator = new class {
            public function getNonce(string $handle): string
            {
                return 'auto-nonce-' . $handle;
            }
        };

        $session = new Session(new MockArraySessionStorage());
        $generator = new CaptchaGenerator();
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/captcha/url');

        $type = new CaptchaType($generator, $requestStack, $urlGenerator, $nonceGenerator);

        $view = new FormView();

        $tokenField = $this->createStub(FormInterface::class);
        $tokenField->method('getData')->willReturn('token123');

        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturn($tokenField);

        $type->buildView($view, $form, ['csp_nonce' => null]);

        self::assertSame('auto-nonce-main', $view->vars['csp_nonce']);
    }

    public function testBuildViewExplicitNonceTakesPrecedence(): void
    {
        $nonceGenerator = new class {
            public function getNonce(string $handle): string
            {
                return 'auto-nonce';
            }
        };

        $session = new Session(new MockArraySessionStorage());
        $generator = new CaptchaGenerator();
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/captcha/url');

        $type = new CaptchaType($generator, $requestStack, $urlGenerator, $nonceGenerator);

        $view = new FormView();

        $tokenField = $this->createStub(FormInterface::class);
        $tokenField->method('getData')->willReturn('token123');

        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturn($tokenField);

        $type->buildView($view, $form, ['csp_nonce' => 'explicit-nonce']);

        self::assertSame('explicit-nonce', $view->vars['csp_nonce']);
    }
}

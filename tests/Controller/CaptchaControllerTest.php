<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Tests\Controller;

use MulerTech\CaptchaBundle\Controller\CaptchaController;
use MulerTech\CaptchaBundle\Service\CaptchaGenerator;
use MulerTech\CaptchaBundle\Service\CaptchaImageRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CaptchaControllerTest extends TestCase
{
    private CaptchaGenerator $generator;
    private Session $session;
    private CaptchaController $controller;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->generator = new CaptchaGenerator();
        $renderer = new CaptchaImageRenderer();
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getSession')->willReturn($this->session);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/captcha/image?token=sometoken');
        $this->controller = new CaptchaController(
            $this->generator,
            $renderer,
            $requestStack,
            $urlGenerator,
        );
    }

    public function testImageReturnsNotFoundForInvalidToken(): void
    {
        $request = new Request(['token' => 'invalid_token']);
        $response = $this->controller->image($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('Captcha not found.', $response->getContent());
    }

    public function testImageReturnsJpegForValidToken(): void
    {
        $captchaData = $this->generator->generate($this->session);

        $request = new Request(['token' => $captchaData->token]);
        $response = $this->controller->image($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        self::assertNotEmpty($response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    public function testRefreshReturnsJsonWithTokenAndImageUrl(): void
    {
        $response = $this->controller->refresh();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        /** @var array{token: string, imageUrl: string} $data */
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('imageUrl', $data);
        self::assertNotEmpty($data['token']);
    }
}

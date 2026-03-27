<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Controller;

use MulerTech\CaptchaBundle\Service\CaptchaGenerator;
use MulerTech\CaptchaBundle\Service\CaptchaImageRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CaptchaController
{
    public function __construct(
        private readonly CaptchaGenerator $generator,
        private readonly CaptchaImageRenderer $renderer,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function image(Request $request): Response
    {
        $token = $request->query->getString('token');
        $session = $this->requestStack->getSession();
        $question = $this->generator->getQuestion($session, $token);

        if (null === $question) {
            return new Response('Captcha not found.', Response::HTTP_NOT_FOUND);
        }

        $binary = $this->renderer->render($question);

        return new Response($binary, Response::HTTP_OK, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function refresh(): JsonResponse
    {
        $session = $this->requestStack->getSession();
        $captchaData = $this->generator->generate($session);

        $imageUrl = $this->urlGenerator->generate(
            'mulertech_captcha_image',
            ['token' => $captchaData->token],
        );

        $response = new JsonResponse([
            'token' => $captchaData->token,
            'imageUrl' => $imageUrl,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}

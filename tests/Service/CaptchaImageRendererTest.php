<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Tests\Service;

use MulerTech\CaptchaBundle\Service\CaptchaImageRenderer;
use PHPUnit\Framework\TestCase;

final class CaptchaImageRendererTest extends TestCase
{
    private CaptchaImageRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new CaptchaImageRenderer();
    }

    public function testRenderReturnsNonEmptyString(): void
    {
        $result = $this->renderer->render('5 + 3');

        self::assertNotEmpty($result);
    }

    public function testRenderReturnsValidJpeg(): void
    {
        $result = $this->renderer->render('7 - 2');

        self::assertSame("\xFF\xD8", substr($result, 0, 2));
    }

    public function testRenderWithDifferentQuestions(): void
    {
        $questions = ['1 + 1', '15 - 0', '8 + 7'];

        foreach ($questions as $question) {
            $result = $this->renderer->render($question);
            self::assertNotEmpty($result, sprintf('Render failed for question: %s', $question));
        }
    }
}

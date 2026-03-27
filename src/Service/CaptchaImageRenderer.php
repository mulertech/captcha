<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Service;

final class CaptchaImageRenderer
{
    private const int WIDTH = 180;
    private const int HEIGHT = 60;
    private const int JPEG_QUALITY = 45;
    private const int NOISE_PIXELS = 300;

    public function render(string $question): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        assert(false !== $image);

        $background = imagecolorallocate($image, 245, 245, 248);
        assert(false !== $background);
        imagefilledrectangle($image, 0, 0, self::WIDTH - 1, self::HEIGHT - 1, $background);

        $this->addNoise($image);
        $this->addLines($image);
        $this->addText($image, $question.' = ?');

        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);
        $binary = ob_get_clean();
        imagedestroy($image);

        if (false === $binary || '' === $binary) {
            throw new \RuntimeException('Failed to render captcha image.');
        }

        return $binary;
    }

    private function addNoise(\GdImage $image): void
    {
        for ($i = 0; $i < self::NOISE_PIXELS; ++$i) {
            $color = imagecolorallocate($image, random_int(100, 210), random_int(100, 210), random_int(100, 210));
            assert(false !== $color);
            imagesetpixel($image, random_int(0, self::WIDTH - 1), random_int(0, self::HEIGHT - 1), $color);
        }
    }

    private function addLines(\GdImage $image): void
    {
        for ($i = 0; $i < 4; ++$i) {
            $color = imagecolorallocate($image, random_int(160, 210), random_int(160, 210), random_int(160, 210));
            assert(false !== $color);
            imageline($image, random_int(0, self::WIDTH), random_int(0, self::HEIGHT), random_int(0, self::WIDTH), random_int(0, self::HEIGHT), $color);
        }
    }

    private function addText(\GdImage $image, string $text): void
    {
        $textColor = imagecolorallocate($image, 30, 30, 80);
        assert(false !== $textColor);

        $fontWidth = imagefontwidth(5);
        $fontHeight = imagefontheight(5);
        $textWidth = strlen($text) * $fontWidth;
        $baseX = (int) max(5, (self::WIDTH - $textWidth) / 2);
        $baseY = (int) ((self::HEIGHT - $fontHeight) / 2);

        foreach (str_split($text) as $i => $char) {
            $yOffset = random_int(-3, 3);
            imagestring($image, 5, $baseX + $i * $fontWidth, $baseY + $yOffset, $char, $textColor);
        }
    }
}

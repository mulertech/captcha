<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Service;

final readonly class CaptchaData
{
    public function __construct(
        public string $token,
        public string $question,
    ) {
    }
}

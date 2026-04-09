<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Service;

use MulerTech\CaptchaBundle\Tests\Service\CaptchaImageRendererObGetCleanOverride;

function ob_get_clean(): string|false
{
    if (CaptchaImageRendererObGetCleanOverride::$returnFalse) {
        \ob_get_clean();

        return false;
    }

    return \ob_get_clean();
}

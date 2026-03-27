<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ValidCaptcha extends Constraint
{
    public string $message = 'captcha.error.invalid';
    public string $expiredMessage = 'captcha.error.expired';
}

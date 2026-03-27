<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Validator;

use MulerTech\CaptchaBundle\Service\CaptchaGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidCaptchaValidator extends ConstraintValidator
{
    public function __construct(
        private readonly CaptchaGenerator $generator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidCaptcha) {
            throw new UnexpectedTypeException($constraint, ValidCaptcha::class);
        }

        if (!is_array($value)) {
            throw new UnexpectedValueException($value, 'array');
        }

        $token = isset($value['token']) && is_string($value['token']) ? $value['token'] : '';
        $answer = trim(isset($value['answer']) && is_string($value['answer']) ? $value['answer'] : '');

        if ('' === $token || '' === $answer) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }

        if (!is_numeric($answer)) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }

        $session = $this->requestStack->getSession();

        if (!$this->generator->hasToken($session, $token)) {
            $this->context->buildViolation($constraint->expiredMessage)->addViolation();

            return;
        }

        if (!$this->generator->validate($session, $token, (int) $answer)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}

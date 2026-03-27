<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Tests\Validator;

use MulerTech\CaptchaBundle\Service\CaptchaGenerator;
use MulerTech\CaptchaBundle\Validator\ValidCaptcha;
use MulerTech\CaptchaBundle\Validator\ValidCaptchaValidator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ValidCaptchaValidator>
 */
final class ValidCaptchaValidatorTest extends ConstraintValidatorTestCase
{
    private Session $session;
    private RequestStack $requestStack;
    private CaptchaGenerator $generator;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->requestStack->method('getSession')->willReturn($this->session);
        $this->generator = new CaptchaGenerator();

        parent::setUp();
    }

    protected function createValidator(): ValidCaptchaValidator
    {
        return new ValidCaptchaValidator($this->generator, $this->requestStack);
    }

    public function testValidAnswerPassesValidation(): void
    {
        $token = 'validtoken123';
        $this->session->set(CaptchaGenerator::SESSION_PREFIX . $token, [
            'question' => '3 + 4',
            'answer' => 7,
            'created_at' => time(),
        ]);

        $this->validator->validate(['token' => $token, 'answer' => '7'], new ValidCaptcha());

        $this->assertNoViolation();
    }

    public function testWrongAnswerFailsWithMessage(): void
    {
        $constraint = new ValidCaptcha();
        $token = 'validtoken456';
        $this->session->set(CaptchaGenerator::SESSION_PREFIX . $token, [
            'question' => '3 + 4',
            'answer' => 7,
            'created_at' => time(),
        ]);

        $this->validator->validate(['token' => $token, 'answer' => '99'], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testExpiredTokenFailsWithExpiredMessage(): void
    {
        $constraint = new ValidCaptcha();

        $this->validator->validate(['token' => 'nonexistent', 'answer' => '5'], $constraint);

        $this->buildViolation($constraint->expiredMessage)->assertRaised();
    }

    public function testTimedOutTokenFailsWithExpiredMessage(): void
    {
        $constraint = new ValidCaptcha();
        $token = 'timedout';
        $this->session->set(CaptchaGenerator::SESSION_PREFIX . $token, [
            'question' => '3 + 4',
            'answer' => 7,
            'created_at' => time() - 700,
        ]);

        $this->validator->validate(['token' => $token, 'answer' => '7'], $constraint);

        $this->buildViolation($constraint->expiredMessage)->assertRaised();
    }

    public function testEmptyTokenFailsWithMessage(): void
    {
        $constraint = new ValidCaptcha();

        $this->validator->validate(['token' => '', 'answer' => '5'], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testEmptyAnswerFailsWithMessage(): void
    {
        $constraint = new ValidCaptcha();

        $this->validator->validate(['token' => 'sometoken', 'answer' => ''], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testNonNumericAnswerFailsWithMessage(): void
    {
        $constraint = new ValidCaptcha();

        $this->validator->validate(['token' => 'sometoken', 'answer' => 'abc'], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testValidationRemovesSessionKey(): void
    {
        $token = 'removetoken';
        $key = CaptchaGenerator::SESSION_PREFIX . $token;
        $this->session->set($key, [
            'question' => '2 + 2',
            'answer' => 4,
            'created_at' => time(),
        ]);

        self::assertTrue($this->session->has($key));

        $this->validator->validate(['token' => $token, 'answer' => '4'], new ValidCaptcha());

        self::assertFalse($this->session->has($key));
    }

    public function testNonArrayValueThrowsException(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate('not-an-array', new ValidCaptcha());
    }

    public function testWrongConstraintThrowsException(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $wrongConstraint = new class extends Constraint {};
        $this->validator->validate(['token' => 'x', 'answer' => '1'], $wrongConstraint);
    }
}

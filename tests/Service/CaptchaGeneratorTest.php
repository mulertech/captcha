<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Tests\Service;

use MulerTech\CaptchaBundle\Service\CaptchaData;
use MulerTech\CaptchaBundle\Service\CaptchaGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CaptchaGeneratorTest extends TestCase
{
    private CaptchaGenerator $generator;
    private Session $session;

    protected function setUp(): void
    {
        $this->generator = new CaptchaGenerator();
        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testGenerateReturnsCaptchaData(): void
    {
        $data = $this->generator->generate($this->session);

        self::assertInstanceOf(CaptchaData::class, $data);
        self::assertNotEmpty($data->token);
        self::assertNotEmpty($data->question);
    }

    public function testQuestionFormat(): void
    {
        $data = $this->generator->generate($this->session);

        self::assertMatchesRegularExpression('/^\d+ [+\-] \d+$/', $data->question);
    }

    public function testSubtractionAlwaysPositive(): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $session = new Session(new MockArraySessionStorage());
            $captchaData = $this->generator->generate($session);

            /** @var array{question: string, answer: int} $stored */
            $stored = $session->get(CaptchaGenerator::SESSION_PREFIX.$captchaData->token);

            if (str_contains($captchaData->question, '-')) {
                self::assertGreaterThanOrEqual(1, $stored['answer']);
            }
        }
    }

    public function testGetQuestionReturnsNullForUnknownToken(): void
    {
        $result = $this->generator->getQuestion($this->session, 'nonexistent_token');

        self::assertNull($result);
    }

    public function testGetQuestionReturnsQuestionForValidToken(): void
    {
        $data = $this->generator->generate($this->session);

        $question = $this->generator->getQuestion($this->session, $data->token);

        self::assertSame($data->question, $question);
    }

    public function testValidateReturnsTrueForCorrectAnswer(): void
    {
        $captchaData = $this->generator->generate($this->session);

        /** @var array{question: string, answer: int} $stored */
        $stored = $this->session->get(CaptchaGenerator::SESSION_PREFIX.$captchaData->token);
        $answer = $stored['answer'];

        $result = $this->generator->validate($this->session, $captchaData->token, $answer);

        self::assertTrue($result);
    }

    public function testValidateReturnsFalseForWrongAnswer(): void
    {
        $captchaData = $this->generator->generate($this->session);

        /** @var array{question: string, answer: int} $stored */
        $stored = $this->session->get(CaptchaGenerator::SESSION_PREFIX.$captchaData->token);
        $wrongAnswer = $stored['answer'] + 999;

        $result = $this->generator->validate($this->session, $captchaData->token, $wrongAnswer);

        self::assertFalse($result);
    }

    public function testValidateReturnsFalseForUnknownToken(): void
    {
        $result = $this->generator->validate($this->session, 'unknown_token', 5);

        self::assertFalse($result);
    }

    public function testValidateRemovesSessionKey(): void
    {
        $captchaData = $this->generator->generate($this->session);
        $key = CaptchaGenerator::SESSION_PREFIX.$captchaData->token;

        self::assertTrue($this->session->has($key));

        $this->generator->validate($this->session, $captchaData->token, 99999);

        self::assertFalse($this->session->has($key));
    }

    public function testHasTokenReturnsTrueForValidToken(): void
    {
        $captchaData = $this->generator->generate($this->session);

        self::assertTrue($this->generator->hasToken($this->session, $captchaData->token));
    }

    public function testHasTokenReturnsFalseForUnknownToken(): void
    {
        self::assertFalse($this->generator->hasToken($this->session, 'nonexistent'));
    }

    public function testHasTokenReturnsFalseForExpiredToken(): void
    {
        $token = 'expired_token';
        $this->session->set(CaptchaGenerator::SESSION_PREFIX.$token, [
            'question' => '1 + 1',
            'answer' => 2,
            'created_at' => time() - 700,
        ]);

        self::assertFalse($this->generator->hasToken($this->session, $token));
        self::assertFalse($this->session->has(CaptchaGenerator::SESSION_PREFIX.$token));
    }

    public function testGetQuestionReturnsNullForExpiredToken(): void
    {
        $token = 'expired_token';
        $this->session->set(CaptchaGenerator::SESSION_PREFIX.$token, [
            'question' => '1 + 1',
            'answer' => 2,
            'created_at' => time() - 700,
        ]);

        self::assertNull($this->generator->getQuestion($this->session, $token));
        self::assertFalse($this->session->has(CaptchaGenerator::SESSION_PREFIX.$token));
    }

    public function testValidateReturnsFalseForExpiredToken(): void
    {
        $token = 'expired_token';
        $this->session->set(CaptchaGenerator::SESSION_PREFIX.$token, [
            'question' => '1 + 1',
            'answer' => 2,
            'created_at' => time() - 700,
        ]);

        self::assertFalse($this->generator->validate($this->session, $token, 2));
    }

    public function testSessionLimitEnforced(): void
    {
        $tokens = [];
        for ($i = 0; $i < 7; ++$i) {
            $tokens[] = $this->generator->generate($this->session);
        }

        $activeCount = 0;
        foreach ($tokens as $data) {
            if ($this->session->has(CaptchaGenerator::SESSION_PREFIX.$data->token)) {
                ++$activeCount;
            }
        }

        self::assertLessThanOrEqual(5, $activeCount);
    }

    public function testIsExpiredReturnsTrueWhenCreatedAtMissing(): void
    {
        $token = 'no_created_at';
        $this->session->set(CaptchaGenerator::SESSION_PREFIX.$token, [
            'question' => '1 + 1',
            'answer' => 2,
        ]);

        self::assertFalse($this->generator->hasToken($this->session, $token));
    }

    public function testIsExpiredReturnsTrueWhenCreatedAtNotInt(): void
    {
        $token = 'bad_created_at';
        $this->session->set(CaptchaGenerator::SESSION_PREFIX.$token, [
            'question' => '1 + 1',
            'answer' => 2,
            'created_at' => 'not_an_int',
        ]);

        self::assertFalse($this->generator->hasToken($this->session, $token));
    }

    public function testEnforceTokenLimitBreaksWhenAllDataNull(): void
    {
        for ($i = 0; $i < 6; ++$i) {
            $this->session->set(CaptchaGenerator::SESSION_PREFIX.'null_'.$i, null);
        }

        $data = $this->generator->generate($this->session);

        self::assertInstanceOf(CaptchaData::class, $data);
        self::assertTrue($this->session->has(CaptchaGenerator::SESSION_PREFIX.$data->token));
    }

    public function testExpiredTokensPrunedOnGenerate(): void
    {
        $this->session->set(CaptchaGenerator::SESSION_PREFIX.'old1', [
            'question' => '1 + 1',
            'answer' => 2,
            'created_at' => time() - 700,
        ]);
        $this->session->set(CaptchaGenerator::SESSION_PREFIX.'old2', [
            'question' => '2 + 2',
            'answer' => 4,
            'created_at' => time() - 700,
        ]);

        $this->generator->generate($this->session);

        self::assertFalse($this->session->has(CaptchaGenerator::SESSION_PREFIX.'old1'));
        self::assertFalse($this->session->has(CaptchaGenerator::SESSION_PREFIX.'old2'));
    }
}

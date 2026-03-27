<?php

declare(strict_types=1);

namespace MulerTech\CaptchaBundle\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CaptchaGenerator
{
    public const string SESSION_PREFIX = 'captcha_';
    private const int MAX_ACTIVE_TOKENS = 5;
    private const int TOKEN_TTL_SECONDS = 600;

    public function generate(SessionInterface $session): CaptchaData
    {
        $token = bin2hex(random_bytes(16));
        [$first, $operator, $second, $answer] = $this->buildOperation();
        $question = sprintf('%d %s %d', $first, $operator, $second);

        $this->pruneExpiredTokens($session);
        $this->enforceTokenLimit($session);

        $session->set(self::SESSION_PREFIX.$token, [
            'question' => $question,
            'answer' => $answer,
            'created_at' => time(),
        ]);

        return new CaptchaData($token, $question);
    }

    public function getQuestion(SessionInterface $session, string $token): ?string
    {
        /** @var array{question: string, answer: int, created_at: int}|null $data */
        $data = $session->get(self::SESSION_PREFIX.$token);

        if (null === $data) {
            return null;
        }

        if ($this->isExpired($data)) {
            $session->remove(self::SESSION_PREFIX.$token);

            return null;
        }

        return $data['question'];
    }

    public function hasToken(SessionInterface $session, string $token): bool
    {
        /** @var array{question: string, answer: int, created_at: int}|null $data */
        $data = $session->get(self::SESSION_PREFIX.$token);

        if (null === $data) {
            return false;
        }

        if ($this->isExpired($data)) {
            $session->remove(self::SESSION_PREFIX.$token);

            return false;
        }

        return true;
    }

    public function validate(SessionInterface $session, string $token, int $submittedAnswer): bool
    {
        /** @var array{question: string, answer: int, created_at: int}|null $data */
        $data = $session->get(self::SESSION_PREFIX.$token);

        $session->remove(self::SESSION_PREFIX.$token);

        if (null === $data || $this->isExpired($data)) {
            return false;
        }

        return $data['answer'] === $submittedAnswer;
    }

    /**
     * @return array{int, string, int, int}
     */
    private function buildOperation(): array
    {
        $first = random_int(1, 15);
        $operator = 0 === random_int(0, 1) ? '+' : '-';

        if ('+' === $operator) {
            $second = random_int(1, 15);
            $answer = $first + $second;
        } else {
            $second = random_int(0, $first - 1);
            $answer = $first - $second;
        }

        return [$first, $operator, $second, $answer];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isExpired(array $data): bool
    {
        if (!isset($data['created_at']) || !is_int($data['created_at'])) {
            return true;
        }

        return (time() - $data['created_at']) > self::TOKEN_TTL_SECONDS;
    }

    private function pruneExpiredTokens(SessionInterface $session): void
    {
        foreach ($this->getCaptchaKeys($session) as $key) {
            /** @var array{question: string, answer: int, created_at: int}|null $data */
            $data = $session->get($key);

            if (null !== $data && $this->isExpired($data)) {
                $session->remove($key);
            }
        }
    }

    private function enforceTokenLimit(SessionInterface $session): void
    {
        $keys = $this->getCaptchaKeys($session);

        while (\count($keys) >= self::MAX_ACTIVE_TOKENS) {
            $oldestKey = null;
            $oldestTime = PHP_INT_MAX;

            foreach ($keys as $key) {
                /** @var array{question: string, answer: int, created_at: int}|null $data */
                $data = $session->get($key);

                if (null !== $data && $data['created_at'] < $oldestTime) {
                    $oldestTime = $data['created_at'];
                    $oldestKey = $key;
                }
            }

            if (null !== $oldestKey) {
                $session->remove($oldestKey);
                $keys = array_filter($keys, static fn (string $k): bool => $k !== $oldestKey);
            } else {
                break;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getCaptchaKeys(SessionInterface $session): array
    {
        /** @var array<string, mixed> $all */
        $all = $session->all();

        $keys = [];
        foreach (array_keys($all) as $key) {
            if (str_starts_with($key, self::SESSION_PREFIX)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}

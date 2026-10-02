<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Throwable;

class RedactSensitiveLogContext
{
    private const SENSITIVE_KEY_PATTERN = '/(?:password|passphrase|token|secret|otp|totp|authorization|cookie|session|api[_-]?key|private[_-]?key|file[_-]?(?:content|contents)|document[_-]?content)/i';

    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $exception = $record->context['exception'] ?? null;
            $message = $exception instanceof Throwable && $record->message === $exception->getMessage()
                ? 'Unhandled exception.'
                : $record->message;

            return $record->with(
                message: $this->redact($message),
                context: $this->sanitize($record->context),
                extra: $this->sanitize($record->extra),
            );
        });
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1) {
            return '[REDACTED]';
        }

        if ($value instanceof Throwable) {
            return [
                'exception_class' => $value::class,
                'file' => $this->sanitizePath($value->getFile()),
                'line' => $value->getLine(),
                'code' => $value->getCode(),
            ];
        }

        if (is_array($value)) {
            foreach ($value as $childKey => $childValue) {
                $value[$childKey] = $this->sanitize($childValue, is_string($childKey) ? $childKey : null);
            }
        } elseif (is_string($value)) {
            $value = $this->redact($value);
        }

        return $value;
    }

    private function redact(string $value): string
    {
        return (string) preg_replace([
            '/(?<=:\/\/)[^:\/\s@]+:[^@\/\s]+(?=@)/',
            '/\b(password|passwd|pwd|secret|token|api[_-]?key|authorization)\s*[=:]\s*[^\s,;)\]]+/i',
            '/\bBearer\s+[A-Za-z0-9\-._~+\/]{8,}=*/i',
        ], [
            '[REDACTED]',
            '$1=[REDACTED]',
            'Bearer [REDACTED]',
        ], $value);
    }

    private function sanitizePath(string $path): string
    {
        $base = base_path();

        return str_starts_with($path, $base)
            ? ltrim(substr($path, strlen($base)), '/\\')
            : basename($path);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Drops secrets from every log record's context and extra
 * (docs/ARCHITECTURE.md §9): passwords, tokens, cookies, authorization
 * headers and patient contact details (consultation requests are health
 * information) never reach a log file.
 *
 * Applied as a `tap` on each channel in config/logging.php.
 */
final class RedactSensitiveData
{
    /** Whole key segments only, so `pin_hash` is redacted but `shipping` is not. */
    private const string PATTERN = '/(^|[_\-.])(password|token|secret|authorization|cookie|phone|email|dob|ssn|message|note|totp|recovery_code)s?($|[_\-.])/i';

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (method_exists($monolog, 'pushProcessor')) {
            $monolog->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
                context: self::redact($record->context),
                extra: self::redact($record->extra),
            ));
        }
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    public static function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::PATTERN, $key) === 1) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $values[$key] = self::redact($value);
            }
        }

        return $values;
    }
}

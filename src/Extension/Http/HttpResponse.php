<?php

declare(strict_types=1);

namespace YiiPress\Extension\Http;

use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Status and headers of a response; the body is in the destination file passed to the client.
 */
final readonly class HttpResponse
{
    /**
     * @param array<string, list<string>> $headers Keyed by lowercase name.
     */
    public function __construct(
        public int $status,
        public array $headers = [],
    ) {}

    /**
     * Builds the headers from raw lines; the status line and lines without a colon are skipped.
     *
     * @param list<string> $lines
     */
    public static function fromHeaderLines(int $status, array $lines): self
    {
        $headers = [];
        foreach ($lines as $line) {
            $colon = strpos($line, ':');
            if ($colon === false || $colon === 0) {
                continue;
            }
            $headers[strtolower(substr($line, 0, $colon))][] = trim(substr($line, $colon + 1));
        }

        return new self($status, $headers);
    }

    /**
     * First value of the header, `null` when absent.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }
}

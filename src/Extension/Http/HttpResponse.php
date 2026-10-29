<?php

declare(strict_types=1);

namespace YiiPress\Extension\Http;

use function strtolower;

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
     * First value of the header, `null` when absent.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace YiiPress\Extension\Http;

use YiiPress\Extension\Install\InstallException;

/**
 * Downloads one HTTPS URL into a file. Redirects are followed only to `https` URLs on GitHub hosts.
 */
interface HttpClientInterface
{
    /**
     * Any HTTP status is returned, never thrown. The body is written to `$destination` whatever the status.
     *
     * @param int $maxBytes Largest body accepted; a larger one is `download_too_large`.
     *
     * @throws InstallException On a transport failure (`network_failed`), a refused redirect (`redirect_refused`)
     * or a body over `$maxBytes` (`download_too_large`).
     */
    public function get(string $url, string $destination, int $maxBytes): HttpResponse;
}

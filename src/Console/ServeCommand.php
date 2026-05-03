<?php

declare(strict_types=1);

namespace YiiPress\Console;

use YiiPress\Environment;
use YiiPress\Web\LiveReload\LiveReloadMiddleware;
use YiiPress\Web\LiveReload\SiteBuildRunner;
use FilesystemIterator;
use HttpSoft\Message\ServerRequest;
use HttpSoft\Message\Stream;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use React\Stream\ReadableResourceStream;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Yii\Console\ExitCode;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

use function fclose;
use function file_get_contents;
use function filesize;
use function fopen;
use function getcwd;
use function is_dir;
use function is_file;
use function is_resource;
use function microtime;
use function pcntl_async_signals;
use function pcntl_fork;
use function pcntl_signal;
use function pcntl_wait;
use function pcntl_wexitstatus;
use function pcntl_wifexited;
use function pcntl_wifsignaled;
use function parse_url;
use function pathinfo;
use function posix_kill;
use function preg_split;
use function realpath;
use function sprintf;
use function strlen;
use function str_starts_with;
use function strtolower;
use function substr;
use function strpos;
use function trim;

#[AsCommand('serve', 'Serves content preview with live reload')]
final class ServeCommand extends Command
{
    private const string DEFAULT_ADDRESS = '127.0.0.1';
    private const string DEFAULT_PORT = '19777';
    private const int DEFAULT_WORKERS = 2;
    private const int MAX_REQUEST_SIZE = 10_485_760;
    private const string LIVE_RELOAD_PATH = '/_live-reload';
    private const int LIVE_RELOAD_RETRY_MILLISECONDS = 1_000;
    private const float LIVE_RELOAD_PING_SECONDS = 10.0;
    private const array MIME_TYPES = [
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json',
        'xml' => 'application/xml; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'txt' => 'text/plain; charset=utf-8',
    ];

    private float $lastLiveReloadBuildTime = 0.0;
    private bool $outputBuildAttempted = false;
    private ?string $contentDir = null;
    private ?string $outputDir = null;
    /** @var resource|null */
    private mixed $liveReloadStream = null;
    /** @var array<int, ConnectionInterface> */
    private array $liveReloadClients = [];
    private int $nextLiveReloadClientId = 1;
    private ?TimerInterface $liveReloadPingTimer = null;

    public function configure(): void
    {
        $this
            ->setHelp(
                'In order to access server from remote machines use 0.0.0.0:8000. That is especially useful when running server in a virtual machine.'
            )
            ->addUsage('--content-dir=content --output-dir=output')
            ->addArgument('address', InputArgument::OPTIONAL, 'Host to serve at', self::DEFAULT_ADDRESS)
            ->addOption(
                'content-dir',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to the content directory',
                'content',
            )
            ->addOption(
                'output-dir',
                'o',
                InputOption::VALUE_REQUIRED,
                'Path to the output directory',
                'output',
            )
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to serve at', self::DEFAULT_PORT)
            ->addOption(
                'workers',
                'w',
                InputOption::VALUE_OPTIONAL,
                'Workers number the server will start with',
                self::DEFAULT_WORKERS
            );
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('address')) {
            $suggestions->suggestValues(['localhost', '127.0.0.1', '0.0.0.0']);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeReactServer($input, $output);
    }

    private function executeReactServer(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $address */
        $address = $input->getArgument('address');

        /** @var string $port */
        $port = $input->getOption('port');

        $root = $this->workingDirectory();
        /** @var string $contentDirOption */
        $contentDirOption = $input->getOption('content-dir');
        $this->contentDir = $this->resolvePath($contentDirOption, $root);

        /** @var string $outputDirOption */
        $outputDirOption = $input->getOption('output-dir');
        $this->outputDir = $this->resolvePath($outputDirOption, $root);

        if (!str_contains($address, ':')) {
            $address .= ':' . $port;
        }

        $workers = (int) $input->getOption('workers');
        if ($workers < 1) {
            $workers = 1;
        }

        $output->writeln(sprintf('Serving http://%s', $address));

        if (Environment::appEnv() === Environment::TEST) {
            return ExitCode::OK;
        }

        try {
            $server = new SocketServer($address);
        } catch (InvalidArgumentException | RuntimeException $e) {
            $output->writeln(sprintf('<error>Unable to listen on http://%s: %s</error>', $address, $e->getMessage()));
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($workers > 1 && function_exists('pcntl_fork')) {
            return $this->runWorkerPool($server, $address, $workers);
        }

        return $this->runServer($server, $address);
    }

    private function runWorkerPool(SocketServer $server, string $address, int $workers): int
    {
        $children = [];

        for ($worker = 0; $worker < $workers; $worker++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->terminateWorkers($children);
                $server->close();

                return ExitCode::UNSPECIFIED_ERROR;
            }

            if ($pid === 0) {
                return $this->runServer($server, $address);
            }

            $children[] = $pid;
        }

        $server->close();
        Loop::get()->stop();

        $stopping = false;
        pcntl_async_signals(true);
        $stop = function () use (&$children, &$stopping): void {
            $stopping = true;
            $this->terminateWorkers($children);
        };
        pcntl_signal(SIGINT, $stop);
        pcntl_signal(SIGTERM, $stop);

        $exitCode = ExitCode::OK;
        while ($children !== []) {
            $status = 0;
            $pid = pcntl_wait($status);
            if ($pid <= 0) {
                break;
            }

            $children = array_values(array_filter($children, static fn(int $child): bool => $child !== $pid));

            if ($stopping || pcntl_wifsignaled($status)) {
                continue;
            }

            if (pcntl_wifexited($status)) {
                $childExitCode = pcntl_wexitstatus($status);
                if ($childExitCode !== ExitCode::OK) {
                    $exitCode = $childExitCode;
                    $stopping = true;
                    $this->terminateWorkers($children);
                }
            }
        }

        return $exitCode;
    }

    /**
     * @param list<int> $children
     */
    private function terminateWorkers(array $children): void
    {
        foreach ($children as $pid) {
            posix_kill($pid, SIGTERM);
        }
    }

    private function runServer(SocketServer $server, string $address): int
    {
        $server->on('connection', function (ConnectionInterface $connection) use ($address): void {
            $this->handleConnection($connection, $address);
        });
        $server->on('error', static function (): void {});

        $loop = Loop::get();
        $stop = function () use ($server, $loop): void {
            $this->closeLiveReloadWatcher();
            $server->close();
            $loop->stop();
        };
        $loop->addSignal(SIGINT, $stop);
        $loop->addSignal(SIGTERM, $stop);
        $loop->run();

        return ExitCode::OK;
    }

    private function handleConnection(ConnectionInterface $connection, string $address): void
    {
        $buffer = '';
        $handled = false;

        $connection->on('data', function (string $chunk) use ($connection, $address, &$buffer, &$handled): void {
            if ($handled) {
                return;
            }

            $buffer .= $chunk;
            $requestLength = $this->requestLength($buffer);
            if ($requestLength === null && strlen($buffer) <= self::MAX_REQUEST_SIZE) {
                return;
            }

            $handled = true;
            if ($requestLength === null || $requestLength > self::MAX_REQUEST_SIZE) {
                $this->writeBadRequest($connection);
                return;
            }

            $rawRequest = substr($buffer, 0, $requestLength);
            if ($this->isLiveReloadRequest($rawRequest)) {
                $this->writeLiveReloadResponse($connection);
                return;
            }

            $staticResponse = $this->createStaticResponse($rawRequest);
            if ($staticResponse !== null) {
                $this->writeStaticResponse($connection, $staticResponse);
                return;
            }

            $request = $this->createRequest($rawRequest, $address);
            if ($request === null) {
                $this->writeBadRequest($connection);
                return;
            }

            $this->writeResponse($connection, $this->createHttpRunner()->runAndGetResponse($request));
        });
        $connection->on('error', static function (): void {});
    }

    private function createHttpRunner(): HttpApplicationRunner
    {
        return new HttpApplicationRunner(
            rootPath: $this->packageRoot(),
            debug: Environment::appDebug(),
            checkEvents: Environment::appDebug(),
            environment: Environment::appEnv(),
        );
    }

    private function isLiveReloadRequest(string $rawRequest): bool
    {
        $requestLine = $this->parseRequestLine($rawRequest);
        if ($requestLine === null) {
            return false;
        }

        [$method, $target] = $requestLine;

        return strtoupper($method) === 'GET' && parse_url($target, PHP_URL_PATH) === self::LIVE_RELOAD_PATH;
    }

    private function writeLiveReloadResponse(ConnectionInterface $connection): void
    {
        $connection->write(
            "HTTP/1.1 200 OK\r\n"
            . "Content-Type: text/event-stream\r\n"
            . "Cache-Control: no-cache\r\n"
            . "Connection: keep-alive\r\n"
            . "X-Accel-Buffering: no\r\n"
            . "\r\n"
            . 'retry: ' . self::LIVE_RELOAD_RETRY_MILLISECONDS . "\n",
        );

        $this->ensureLiveReloadWatcher();

        $clientId = $this->nextLiveReloadClientId++;
        $this->liveReloadClients[$clientId] = $connection;
        $connection->once('close', function () use ($clientId): void {
            unset($this->liveReloadClients[$clientId]);
        });
    }

    private function ensureLiveReloadWatcher(): void
    {
        if ($this->liveReloadPingTimer === null) {
            $this->liveReloadPingTimer = Loop::addPeriodicTimer(
                self::LIVE_RELOAD_PING_SECONDS,
                function (): void {
                    $this->broadcastLiveReloadEvent('ping', 'ok', false);
                },
            );
        }

        if ($this->liveReloadStream !== null) {
            return;
        }

        $stream = function_exists('inotify_init') ? inotify_init() : false;
        if ($stream === false) {
            return;
        }

        stream_set_blocking($stream, false);

        foreach ($this->liveReloadWatchedDirectories() as $directory) {
            @inotify_add_watch($stream, $directory, $this->liveReloadNativeWatchMask());
        }

        $this->liveReloadStream = $stream;

        Loop::addReadStream($stream, function () use ($stream): void {
            $events = inotify_read($stream);
            if (!is_array($events) || $events === []) {
                return;
            }

            $this->buildLiveReloadSite();
            $this->broadcastLiveReloadEvent('reload', 'changed', true);
        });
    }

    private function broadcastLiveReloadEvent(string $event, string $data, bool $close): void
    {
        foreach ($this->liveReloadClients as $clientId => $client) {
            if (!$client->isWritable()) {
                unset($this->liveReloadClients[$clientId]);
                continue;
            }

            if ($close) {
                $this->finishLiveReloadResponse($client, $event, $data);
                unset($this->liveReloadClients[$clientId]);
            } else {
                $client->write("event: {$event}\ndata: {$data}\n\n");
            }
        }
    }

    /**
     * @param resource $stream
     */
    private function closeLiveReloadStream(mixed $stream): void
    {
        Loop::removeReadStream($stream);
        fclose($stream);
    }

    private function closeLiveReloadWatcher(): void
    {
        if ($this->liveReloadPingTimer !== null) {
            Loop::cancelTimer($this->liveReloadPingTimer);
            $this->liveReloadPingTimer = null;
        }

        if ($this->liveReloadStream !== null) {
            $this->closeLiveReloadStream($this->liveReloadStream);
            $this->liveReloadStream = null;
        }

        foreach ($this->liveReloadClients as $client) {
            $client->close();
        }
        $this->liveReloadClients = [];
    }

    private function buildLiveReloadSite(): void
    {
        $now = microtime(true);
        if ($now - $this->lastLiveReloadBuildTime < 1.0) {
            return;
        }

        $this->lastLiveReloadBuildTime = $now;
        $this->createLiveReloadBuildRunner()->build();
    }

    private function finishLiveReloadResponse(ConnectionInterface $connection, string $event, string $data): void
    {
        $connection->end("event: {$event}\ndata: {$data}\n\n");
    }

    private function createLiveReloadBuildRunner(): SiteBuildRunner
    {
        $root = $this->workingDirectory();
        $yiiBinary = $_SERVER['argv'][0] ?? PHP_BINARY;
        if (!str_starts_with($yiiBinary, '/')) {
            $yiiBinary = $root . '/' . $yiiBinary;
        }

        return new SiteBuildRunner(
            yiiBinary: $yiiBinary,
            contentDir: $this->contentDir(),
            outputDir: $this->outputDir(),
        );
    }

    /**
     * @return list<string>
     */
    private function liveReloadWatchedDirectories(): array
    {
        $directories = [];

        foreach ([$this->contentDir(), $this->workingDirectory() . '/themes'] as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $directories[] = $directory;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $item) {
                /** @var SplFileInfo $item */
                if ($item->isDir()) {
                    $directories[] = $item->getPathname();
                }
            }
        }

        return $directories;
    }

    private function liveReloadNativeWatchMask(): int
    {
        return constant('IN_ATTRIB')
            | constant('IN_CLOSE_WRITE')
            | constant('IN_CREATE')
            | constant('IN_DELETE')
            | constant('IN_DELETE_SELF')
            | constant('IN_MODIFY')
            | constant('IN_MOVE_SELF')
            | constant('IN_MOVED_FROM')
            | constant('IN_MOVED_TO');
    }

    private function createRequest(string $rawRequest, string $address): ?ServerRequest
    {
        $parts = explode("\r\n\r\n", $rawRequest, 2);
        $head = $parts[0] ?? '';
        $body = $parts[1] ?? '';
        $requestLine = $this->parseRequestLine($rawRequest);
        if ($requestLine === null) {
            return null;
        }

        [$method, $target] = $requestLine;
        $headers = [];
        foreach (array_slice(explode("\r\n", $head), 1) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            $headerParts = explode(':', $line, 2);
            if (count($headerParts) !== 2) {
                continue;
            }

            [$name, $value] = $headerParts;
            $headers[trim($name)] = trim($value);
        }

        $host = $headers['Host'] ?? $headers['host'] ?? $address;
        $uri = str_starts_with(strtolower($target), 'http://') || str_starts_with(strtolower($target), 'https://')
            ? $target
            : 'http://' . $host . $target;

        $bodyStream = null;
        if ($body !== '') {
            $bodyStream = new Stream();
            $bodyStream->write($body);
            $bodyStream->rewind();
        }

        return new ServerRequest(
            method: $method,
            uri: $uri,
            headers: $headers,
            body: $bodyStream,
            serverParams: [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $target,
                'SERVER_PROTOCOL' => 'HTTP/1.1',
                'HTTP_HOST' => $host,
                'QUERY_STRING' => parse_url($target, PHP_URL_QUERY) ?: '',
            ],
        );
    }

    /**
     * @return array{0:string,1:string,2:string}|null
     */
    private function parseRequestLine(string $rawRequest): ?array
    {
        $head = explode("\r\n", $rawRequest, 2)[0] ?? '';
        if ($head === '') {
            return null;
        }

        $requestLine = preg_split('/\s+/', trim($head));
        if ($requestLine === false || count($requestLine) !== 3) {
            return null;
        }

        return [$requestLine[0], $requestLine[1], $requestLine[2]];
    }

    private function requestLength(string $buffer): ?int
    {
        $headerEnd = strpos($buffer, "\r\n\r\n");
        if ($headerEnd === false) {
            return null;
        }

        $contentLength = 0;
        foreach (explode("\r\n", substr($buffer, 0, $headerEnd)) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            $headerParts = explode(':', $line, 2);
            if (count($headerParts) !== 2) {
                continue;
            }

            [$name, $value] = $headerParts;
            if (strtolower(trim($name)) === 'content-length') {
                $contentLength = (int) trim($value);
                break;
            }
        }

        $requestLength = $headerEnd + 4 + $contentLength;

        return strlen($buffer) >= $requestLength ? $requestLength : null;
    }

    /**
     * @return array{status:int,reason:string,headers:array<string,string>,body:string|null,file:string|null,head:bool}|null
     */
    private function createStaticResponse(string $rawRequest): ?array
    {
        $requestLine = $this->parseRequestLine($rawRequest);
        if ($requestLine === null) {
            return null;
        }

        [$method, $target] = $requestLine;
        $method = strtoupper($method);
        if ($method !== 'GET' && $method !== 'HEAD') {
            return null;
        }

        $this->ensureOutputBuilt();

        $filePath = $this->resolveStaticFilePath(parse_url($target, PHP_URL_PATH) ?: '/');
        if ($filePath === null) {
            return $this->createNotFoundResponse($method === 'HEAD');
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $contentType = self::MIME_TYPES[$extension] ?? 'application/octet-stream';

        $body = null;
        if (str_contains($contentType, 'text/html')) {
            $body = file_get_contents($filePath);
            if ($body === false) {
                return $this->createNotFoundResponse($method === 'HEAD');
            }

            $body = LiveReloadMiddleware::injectScript($body);
        }

        return [
            'status' => 200,
            'reason' => 'OK',
            'headers' => ['Content-Type' => $contentType],
            'body' => $body,
            'file' => $body === null ? $filePath : null,
            'head' => $method === 'HEAD',
        ];
    }

    private function ensureOutputBuilt(): void
    {
        if ($this->outputBuildAttempted) {
            return;
        }

        $this->outputBuildAttempted = true;

        $output = $this->outputDir();
        if (!is_dir($output) || $this->isEmptyDirectory($output)) {
            $this->createLiveReloadBuildRunner()->build();
        }
    }

    private function resolveStaticFilePath(string $path): ?string
    {
        $path = '/' . trim(urldecode($path), '/');
        $root = $this->outputDir();

        $candidate = $root . $path;
        if (is_file($candidate)) {
            return $this->secureStaticPath($candidate, $root);
        }

        if (is_dir($candidate)) {
            $index = rtrim($candidate, '/') . '/index.html';
            if (is_file($index)) {
                return $this->secureStaticPath($index, $root);
            }
        }

        return null;
    }

    private function secureStaticPath(string $filePath, string $root): ?string
    {
        $realPath = realpath($filePath);
        $realRoot = realpath($root);

        if ($realPath === false || $realRoot === false) {
            return null;
        }

        if ($realPath !== $realRoot && !str_starts_with($realPath, $realRoot . '/')) {
            return null;
        }

        return $realPath;
    }

    /**
     * @return array{status:int,reason:string,headers:array<string,string>,body:string|null,file:string|null,head:bool}
     */
    private function createNotFoundResponse(bool $head): array
    {
        $body = '<!DOCTYPE html><html lang="en"><body><h1>404 Not Found</h1></body></html>';
        $file = $this->outputDir() . '/404.html';
        if (is_file($file)) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $body = LiveReloadMiddleware::injectScript($content);
            }
        }

        return [
            'status' => 404,
            'reason' => 'Not Found',
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
            'file' => null,
            'head' => $head,
        ];
    }

    private function isEmptyDirectory(string $path): bool
    {
        $handle = opendir($path);
        if ($handle === false) {
            return true;
        }

        while (($entry = readdir($handle)) !== false) {
            if ($entry !== '.' && $entry !== '..') {
                closedir($handle);
                return false;
            }
        }

        closedir($handle);
        return true;
    }

    /**
     * @param array{status:int,reason:string,headers:array<string,string>,body:string|null,file:string|null,head:bool} $response
     */
    private function writeStaticResponse(ConnectionInterface $connection, array $response): void
    {
        $body = $response['body'];
        $file = $response['file'];
        $contentLength = $body === null && $file !== null ? filesize($file) : strlen($body ?? '');
        if ($contentLength === false) {
            $this->writeBadRequest($connection);
            return;
        }

        $responseText = sprintf("HTTP/1.1 %d %s\r\n", $response['status'], $response['reason']);
        foreach ($response['headers'] as $name => $value) {
            $responseText .= $name . ': ' . $value . "\r\n";
        }

        $responseText .= 'Content-Length: ' . $contentLength . "\r\n";
        $responseText .= "Connection: close\r\n\r\n";

        if ($response['head']) {
            $connection->end($responseText);
            return;
        }

        if ($body !== null) {
            $connection->end($responseText . $body);
            return;
        }

        if ($file === null) {
            $connection->end($responseText);
            return;
        }

        $resource = fopen($file, 'rb');
        if (!is_resource($resource)) {
            $this->writeBadRequest($connection);
            return;
        }

        $connection->write($responseText);

        $stream = new ReadableResourceStream($resource, Loop::get(), 65_536);
        $stream->on('error', static function () use ($connection): void {
            $connection->close();
        });
        $connection->on('close', static function () use ($stream): void {
            $stream->close();
        });
        $stream->pipe($connection);
    }

    private function writeBadRequest(ConnectionInterface $connection): void
    {
        $connection->end("HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
    }

    private function writeResponse(ConnectionInterface $connection, ResponseInterface $response): void
    {
        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $contents = $body->getContents();

        $responseText = sprintf(
            "HTTP/1.1 %d %s\r\n",
            $response->getStatusCode(),
            $response->getReasonPhrase(),
        );

        $hasContentLength = false;
        foreach ($response->getHeaders() as $name => $values) {
            $name = (string) $name;
            if (strtolower($name) === 'content-length') {
                $hasContentLength = true;
            }

            foreach ($values as $value) {
                $responseText .= $name . ': ' . $value . "\r\n";
            }
        }

        if (!$hasContentLength) {
            $responseText .= 'Content-Length: ' . strlen($contents) . "\r\n";
        }

        $connection->end($responseText . "Connection: close\r\n\r\n" . $contents);
    }

    private function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function workingDirectory(): string
    {
        $directory = getcwd();
        if ($directory === false) {
            throw new RuntimeException('Failed to get current working directory.');
        }

        return $directory;
    }

    private function contentDir(): string
    {
        return $this->contentDir ?? $this->workingDirectory() . '/content';
    }

    private function outputDir(): string
    {
        return $this->outputDir ?? $this->workingDirectory() . '/output';
    }

    private function resolvePath(string $path, string $rootPath): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $rootPath . '/' . $path;
    }
}

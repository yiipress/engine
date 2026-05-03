<?php

declare(strict_types=1);

namespace App\Console;

use App\Environment;
use HttpSoft\Message\ServerRequest;
use HttpSoft\Message\Stream;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiisoft\Yii\Console\Command\Serve;
use Yiisoft\Yii\Console\ExitCode;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

use function getcwd;
use function pcntl_async_signals;
use function pcntl_fork;
use function pcntl_signal;
use function pcntl_wait;
use function pcntl_wexitstatus;
use function pcntl_wifexited;
use function pcntl_wifsignaled;
use function parse_url;
use function posix_kill;
use function preg_split;
use function sprintf;
use function strlen;
use function str_starts_with;
use function strtolower;
use function substr;
use function strpos;
use function trim;

#[AsCommand('serve', 'Runs PHP built-in web server')]
final class ServeCommand extends Command
{
    private const int MAX_REQUEST_SIZE = 10_485_760;

    private string $defaultAddress;
    private string $defaultPort;
    private string $defaultDocroot;
    private string $defaultRouter;
    private int $defaultWorkers;

    /**
     * @psalm-param array{
     *     address?:non-empty-string,
     *     port?:non-empty-string,
     *     docroot?:string,
     *     router?:string,
     *     workers?:int|string
     * } $options
     */
    public function __construct(
        private readonly ?string $appRootPath = null,
        private readonly ?array $options = [],
        private readonly ?bool $packaged = null,
    ) {
        $this->defaultAddress = $options['address'] ?? '127.0.0.1';
        $this->defaultPort = $options['port'] ?? '8080';
        $this->defaultDocroot = $options['docroot'] ?? 'public';
        $this->defaultRouter = $options['router'] ?? 'public/index.php';
        $this->defaultWorkers = (int) ($options['workers'] ?? 2);

        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->setHelp(
                'In order to access server from remote machines use 0.0.0.0:8000. That is especially useful when running server in a virtual machine.'
            )
            ->addArgument('address', InputArgument::OPTIONAL, 'Host to serve at', $this->defaultAddress)
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to serve at', $this->defaultPort)
            ->addOption(
                'docroot',
                't',
                InputOption::VALUE_OPTIONAL,
                'Document root to serve from',
                $this->defaultDocroot
            )
            ->addOption('router', 'r', InputOption::VALUE_OPTIONAL, 'Path to router script', $this->defaultRouter)
            ->addOption(
                'workers',
                'w',
                InputOption::VALUE_OPTIONAL,
                'Workers number the server will start with',
                $this->defaultWorkers
            )
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'It is only used for testing.')
            ->addOption('open', 'o', InputOption::VALUE_OPTIONAL, 'Opens the serving server in the default browser.', false)
            ->addOption('xdebug', 'x', InputOption::VALUE_OPTIONAL, 'Enables XDEBUG session.', false);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('address')) {
            $suggestions->suggestValues(['localhost', '127.0.0.1', '0.0.0.0']);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isPackaged()) {
            return (new Serve($this->appRootPath, $this->options))->run($input, $output);
        }

        return $this->executePackaged($input, $output);
    }

    private function executePackaged(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Yii3 Development Server');
        $io->writeln('https://yiiframework.com' . "\n");

        /** @var string $address */
        $address = $input->getArgument('address');

        /** @var string $port */
        $port = $input->getOption('port');

        if (!str_contains($address, ':')) {
            $address .= ':' . $port;
        }

        /** @var string $env */
        $env = $input->getOption('env');
        $workers = (int) $input->getOption('workers');
        if ($workers < 1) {
            $workers = 1;
        }

        $outputTable = [
            ['PHP', PHP_VERSION],
            ['Workers', $workers, '--workers, -w'],
            ['Address', $address],
            ['Document root', $this->workingDirectory()],
            ['Routing file', 'embedded PHAR application'],
        ];

        $io->table(['Configuration', null, 'Options'], $outputTable);
        $io->success('Quit the server with CTRL-C or COMMAND-C.');

        if ($env === 'test') {
            return ExitCode::OK;
        }

        try {
            $server = new SocketServer($address);
        } catch (InvalidArgumentException | RuntimeException $e) {
            $io->error(sprintf('Unable to listen on http://%s: %s', $address, $e->getMessage()));
            return Serve::EXIT_CODE_ADDRESS_TAKEN_BY_ANOTHER_PROCESS;
        }

        if ($workers > 1 && function_exists('pcntl_fork')) {
            return $this->runPackagedWorkerPool($server, $address, $workers);
        }

        return $this->runPackagedServer($server, $address);
    }

    private function runPackagedWorkerPool(SocketServer $server, string $address, int $workers): int
    {
        $children = [];

        for ($worker = 0; $worker < $workers; $worker++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->terminateWorkers($children);
                $server->close();

                return Serve::EXIT_CODE_ADDRESS_TAKEN_BY_ANOTHER_PROCESS;
            }

            if ($pid === 0) {
                return $this->runPackagedServer($server, $address);
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

    private function runPackagedServer(SocketServer $server, string $address): int
    {
        $server->on('connection', function (ConnectionInterface $connection) use ($address): void {
            $this->handleConnection($connection, $address);
        });
        $server->on('error', static function (): void {});

        $loop = Loop::get();
        $stop = static function () use ($server, $loop): void {
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

            $request = $this->createRequest(substr($buffer, 0, $requestLength), $address);
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

    private function createRequest(string $rawRequest, string $address): ?ServerRequest
    {
        $parts = explode("\r\n\r\n", $rawRequest, 2);
        $head = $parts[0] ?? '';
        $body = $parts[1] ?? '';
        $lines = explode("\r\n", $head);
        if (!isset($lines[0]) || $lines[0] === '') {
            return null;
        }

        $requestLine = preg_split('/\s+/', trim($lines[0]));
        if ($requestLine === false || count($requestLine) !== 3) {
            return null;
        }

        [$method, $target] = $requestLine;
        $headers = [];
        foreach (array_slice($lines, 1) as $line) {
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

    private function isPackaged(): bool
    {
        return $this->packaged ?? str_starts_with(__FILE__, 'phar://');
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
}

<?php

declare(strict_types=1);

namespace App\Console;

use App\Environment;
use HttpSoft\Message\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Socket;
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
use function parse_url;
use function preg_split;
use function sprintf;
use function strlen;
use function strtolower;
use function trim;

#[AsCommand('serve', 'Runs PHP built-in web server')]
final class ServeCommand extends Command
{
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

        $outputTable = [
            ['PHP', PHP_VERSION],
            ['Workers', 'Not supported by packaged server', '--workers, -w'],
            ['Address', $address],
            ['Document root', $this->workingDirectory()],
            ['Routing file', 'embedded PHAR application'],
        ];

        $io->table(['Configuration', null, 'Options'], $outputTable);
        $io->success('Quit the server with CTRL-C or COMMAND-C.');

        if ($env === 'test') {
            return ExitCode::OK;
        }

        $server = $this->createServerSocket($address);
        if (!$server instanceof Socket) {
            $errorCode = socket_last_error();
            $io->error(sprintf(
                'Unable to listen on http://%s: [%d] %s',
                $address,
                $errorCode,
                socket_strerror($errorCode),
            ));
            return Serve::EXIT_CODE_ADDRESS_TAKEN_BY_ANOTHER_PROCESS;
        }

        $running = true;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, static function () use (&$running): void {
                $running = false;
            });
            pcntl_signal(SIGTERM, static function () use (&$running): void {
                $running = false;
            });
        }

        socket_set_nonblock($server);

        while ($running) {
            $connection = @socket_accept($server);
            if (!$connection instanceof Socket) {
                usleep(100000);
                continue;
            }

            $this->handleConnection($connection, $address);
            socket_close($connection);
        }

        socket_close($server);
        return ExitCode::OK;
    }

    private function createServerSocket(string $address): ?Socket
    {
        $addressParts = explode(':', $address, 2);
        if (count($addressParts) !== 2) {
            return null;
        }

        [$host, $port] = $addressParts;

        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket instanceof Socket) {
            return null;
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (@socket_bind($socket, $host, (int) $port) === false || @socket_listen($socket) === false) {
            socket_close($socket);
            return null;
        }

        return $socket;
    }

    private function handleConnection(Socket $connection, string $address): void
    {
        $rawRequest = '';
        while (!str_contains($rawRequest, "\r\n\r\n") && strlen($rawRequest) < 65536) {
            $chunk = @socket_read($connection, 2048, PHP_BINARY_READ);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $rawRequest .= $chunk;
        }

        $request = $this->createRequest($rawRequest, $address);
        if ($request === null) {
            socket_write($connection, "HTTP/1.1 400 Bad Request\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
            return;
        }

        $this->writeResponse($connection, $this->createHttpRunner()->runAndGetResponse($request));
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

        return new ServerRequest(
            method: $method,
            uri: $uri,
            headers: $headers,
            serverParams: [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $target,
                'SERVER_PROTOCOL' => 'HTTP/1.1',
                'HTTP_HOST' => $host,
                'QUERY_STRING' => parse_url($target, PHP_URL_QUERY) ?: '',
            ],
        );
    }

    private function writeResponse(Socket $connection, ResponseInterface $response): void
    {
        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $contents = $body->getContents();

        socket_write(
            $connection,
            sprintf(
                "HTTP/1.1 %d %s\r\n",
                $response->getStatusCode(),
                $response->getReasonPhrase(),
            ),
        );

        $hasContentLength = false;
        foreach ($response->getHeaders() as $name => $values) {
            $name = (string) $name;
            if (strtolower($name) === 'content-length') {
                $hasContentLength = true;
            }

            foreach ($values as $value) {
                socket_write($connection, $name . ': ' . $value . "\r\n");
            }
        }

        if (!$hasContentLength) {
            socket_write($connection, 'Content-Length: ' . strlen($contents) . "\r\n");
        }

        socket_write($connection, "Connection: close\r\n\r\n");
        socket_write($connection, $contents);
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

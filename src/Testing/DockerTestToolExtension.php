<?php

declare(strict_types=1);

namespace NuraBiz\Common\Testing;

use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Symfony\Component\Process\Process;

final class DockerTestToolExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if (!$parameters->has('mysql')) {
            throw new \InvalidArgumentException('DockerTestToolExtension requires the "mysql" parameter.');
        }

        if ('true' !== $parameters->get('mysql')) {
            throw new \InvalidArgumentException('The "mysql" parameter must have value="true".');
        }

        $environment = static function (string $name): string {
            $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
            if (!is_string($value) || '' === $value) {
                throw new \InvalidArgumentException(sprintf('Required environment variable %s is missing.', $name));
            }

            return $value;
        };
        $mysql = [
            'image' => 'mysql:'.$environment('TEST_MYSQL_VERSION'),
            'database' => $environment('TEST_MYSQL_DATABASE'),
            'user' => $environment('TEST_MYSQL_USER'),
            'password' => $environment('TEST_MYSQL_PASSWORD'),
            'rootPassword' => $environment('TEST_MYSQL_ROOT_PASSWORD'),
            'port' => filter_var($environment('TEST_MYSQL_PORT'), FILTER_VALIDATE_INT),
            'timeout' => 180,
        ];
        foreach (['image', 'database', 'user', 'password', 'rootPassword'] as $key) {
            if (!is_string($mysql[$key]) || '' === $mysql[$key]) {
                throw new \InvalidArgumentException(sprintf('mysql.%s must be a non-empty string.', $key));
            }
        }
        if (!is_int($mysql['port']) || $mysql['port'] < 1 || $mysql['port'] > 65535
            || !is_int($mysql['timeout']) || $mysql['timeout'] < 1) {
            throw new \InvalidArgumentException('mysql.port and mysql.timeout must be positive integers (port <= 65535).');
        }

        $migrations = $parameters->has('migrations') ? $parameters->get('migrations') : 'false';
        if (!in_array($migrations, ['true', 'false'], true)) {
            throw new \InvalidArgumentException('The "migrations" parameter must be "true" or "false".');
        }
        if ('true' === $migrations && $parameters->has('afterStart')) {
            throw new \InvalidArgumentException('Use either "migrations" or "afterStart", not both.');
        }

        $afterStart = $parameters->has('afterStart')
            ? json_decode($parameters->get('afterStart'), true, flags: JSON_THROW_ON_ERROR)
            : ('true' === $migrations ? [
                '@php', 'bin/console', 'doctrine:migrations:migrate',
                '--env=test', '--no-interaction', '--allow-no-migration',
            ] : []);
        if (!is_array($afterStart) || !array_is_list($afterStart)) {
            throw new \InvalidArgumentException('The "afterStart" parameter must be a JSON array of command arguments.');
        }
        foreach ($afterStart as $argument) {
            if (!is_string($argument)) {
                throw new \InvalidArgumentException('Every afterStart argument must be a string.');
            }
        }

        $directory = $parameters->has('directory') ? $parameters->get('directory') : getcwd();
        if (false === $directory || !is_dir($directory)) {
            throw new \InvalidArgumentException('The test project directory does not exist.');
        }

        $facade->registerSubscriber(new class($mysql, $afterStart, $directory) implements ExecutionStartedSubscriber {
            public function __construct(
                private readonly array $mysql,
                private readonly array $afterStart,
                private readonly string $directory,
            ) {
            }

            public function notify(ExecutionStarted $event): void
            {
                $name = 'phpunit-mysql-'.getmypid().'-'.bin2hex(random_bytes(6));
                // Register before startup so failed starts and migrations are cleaned up too.
                register_shutdown_function(function () use ($name): void {
                    $process = new Process(['docker', 'rm', '--force', '--volumes', $name], $this->directory);
                    $process->setTimeout(60);
                    $process->run();
                    if (!$process->isSuccessful()) {
                        fwrite(STDERR, $process->getErrorOutput());
                    }
                });

                $process = new Process([
                    'docker', 'run', '--detach', '--rm', '--name', $name,
                    '--publish', '127.0.0.1:'.$this->mysql['port'].':3306',
                    '--tmpfs', '/var/lib/mysql:rw',
                    '--env', 'MYSQL_ROOT_PASSWORD='.$this->mysql['rootPassword'],
                    '--env', 'MYSQL_DATABASE='.$this->mysql['database'],
                    '--env', 'MYSQL_USER='.$this->mysql['user'],
                    '--env', 'MYSQL_PASSWORD='.$this->mysql['password'],
                    $this->mysql['image'],
                ], $this->directory);
                $process->setTimeout(300);
                $process->mustRun();

                $deadline = microtime(true) + $this->mysql['timeout'];
                do {
                    $probe = new Process([
                        'docker', 'exec', '--env', 'MYSQL_PWD='.$this->mysql['password'], $name,
                        'mysql', '--protocol=TCP', '--host=127.0.0.1',
                        '--user='.$this->mysql['user'], '--database='.$this->mysql['database'],
                        '--execute=SELECT 1',
                    ], $this->directory);
                    $probe->setTimeout(5);
                    try {
                        $probe->run();
                        if ($probe->isSuccessful()) {
                            break;
                        }
                    } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException) {
                        // Retry until the server startup deadline.
                    }
                    if (microtime(true) >= $deadline) {
                        throw new \RuntimeException('MySQL did not become ready: '.$probe->getErrorOutput());
                    }
                    usleep(250_000);
                } while (true);

                if ([] !== $this->afterStart) {
                    $command = $this->afterStart;
                    if ('@php' === $command[0]) {
                        $command[0] = PHP_BINARY;
                    }
                    $environment = ['APP_ENV' => $_SERVER['APP_ENV'] ?? 'test'];
                    if (isset($_SERVER['DATABASE_URL'])) {
                        $environment['DATABASE_URL'] = $_SERVER['DATABASE_URL'];
                    }
                    $process = new Process($command, $this->directory, $environment);
                    $process->setTimeout(60);
                    $process->mustRun();
                }
            }
        });
    }
}

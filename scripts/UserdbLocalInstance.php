<?php

declare(strict_types=1);

/**
 * The database and runtime files of one local web in Flox. The developer's own instance and the one
 * of the browser tests live side by side in a worktree and never share data.
 */
final class UserdbLocalInstance
{
    public const VARIABLE = 'USERDB_LOCAL_INSTANCE';

    public const E2E = 'e2e';

    /** @var array<string, array{database: string, user: string, password: string, directory: string}> */
    private const INSTANCES = [
        '' => ['database' => 'userdb_dev', 'user' => 'userdb_dev', 'password' => 'dev-only', 'directory' => ''],
        self::E2E => ['database' => 'userdb_e2e', 'user' => 'userdb_e2e', 'password' => 'e2e-only', 'directory' => '/e2e'],
    ];

    private function __construct(private readonly string $name) {
    }

    /** An unknown name is an error: falling back would point the browser tests at the developer's data. */
    public static function fromEnvironment(): self {
        $name = getenv(self::VARIABLE);
        $name = $name === false ? '' : $name;
        if (!isset(self::INSTANCES[$name])) {
            throw new RuntimeException(self::VARIABLE . ' must be empty or "' . self::E2E . '".');
        }
        return new self($name);
    }

    /** The browser tests start from a new database every time; the developer's data is only ever completed. */
    public function isRebuiltOnSetup(): bool {
        return $this->name === self::E2E;
    }

    public function database(): string {
        return self::INSTANCES[$this->name]['database'];
    }

    public function user(): string {
        return self::INSTANCES[$this->name]['user'];
    }

    public function password(): string {
        return self::INSTANCES[$this->name]['password'];
    }

    /** Sessions, stored files and e-mails written by the web. */
    public function runtimeDirectory(string $cache): string {
        return $cache . self::INSTANCES[$this->name]['directory'];
    }

    public function dsn(string $socket): string {
        return 'mysql:unix_socket=' . $socket . ';dbname=' . $this->database();
    }
}

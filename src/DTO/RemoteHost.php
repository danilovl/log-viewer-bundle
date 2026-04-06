<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\DTO;

final readonly class RemoteHost
{
    /**
     * @param string[] $dirs
     * @param string[] $files
     * @param string[] $ignore
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $host,
        public int $port,
        public array $dirs,
        public array $files,
        public array $ignore = [],
        public ?string $user = null,
        public ?string $password = null,
        public ?string $sshKey = null,
        public ?int $maxFileSize = null,
    ) {}

    /**
     * @param array{
     *     name: string,
     *     type: string,
     *     host: string,
     *     port: int,
     *     user?: string,
     *     password?: string,
     *     ssh_key?: string,
     *     max_file_size?: int,
     *     dirs: string[],
     *     files: string[],
     *     ignore: string[]
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'],
            host: $data['host'],
            port: $data['port'],
            dirs: $data['dirs'],
            files: $data['files'],
            ignore: $data['ignore'],
            user: $data['user'] ?? null,
            password: $data['password'] ?? null,
            sshKey: $data['ssh_key'] ?? null,
            maxFileSize: $data['max_file_size'] ?? null,
        );
    }
}

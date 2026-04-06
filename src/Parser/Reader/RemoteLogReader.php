<?php declare(strict_types=1);

namespace Danilovl\LogViewerBundle\Parser\Reader;

use Danilovl\LogViewerBundle\DTO\RemoteHost;
use Danilovl\LogViewerBundle\Service\ConfigurationProvider;
use RuntimeException;

class RemoteLogReader
{
    /** @var array<string, resource> */
    private array $connections = [];

    public function __construct(private readonly ConfigurationProvider $configurationProvider) {}

    /**
     * @return resource
     */
    public function openFile(string $hostName, string $path, ?int $offset = null)
    {
        $hostConfig = $this->findRemoteHost($hostName);
        if (!$hostConfig) {
            throw new RuntimeException("Remote host configuration not found: $hostName");
        }

        $type = $hostConfig->type;

        if ($type === 'ssh' || $type === 'sftp') {
            $handle = $this->openSftpFile($hostName, $path, $hostConfig);
            if ($offset !== null && $offset > 0) {
                fseek($handle, $offset);
            }

            return $handle;
        }

        if ($type === 'http' || $type === 'https') {
            $url = $this->buildHttpUrl($hostConfig, $path);
            $context = null;
            if ($offset !== null && $offset > 0) {
                $context = stream_context_create([
                    'http' => [
                        'header' => "Range: bytes=$offset-\r\n"
                    ]
                ]);
            }

            $handle = @fopen($url, 'rb', false, $context);
            if ($handle === false) {
                throw new RuntimeException("Could not open remote URL: $url");
            }

            return $handle;
        }

        throw new RuntimeException("Unsupported remote host type: $type");
    }

    /**
     * @return resource
     */
    private function openSftpFile(string $hostName, string $path, RemoteHost $hostConfig)
    {
        if (!function_exists('ssh2_connect')) {
            throw new RuntimeException("The 'ssh2' PHP extension is required for SSH/SFTP logs.");
        }

        $connection = $this->getConnection($hostName, $hostConfig);
        $sftp = ssh2_sftp($connection);
        if ($sftp === false) {
            throw new RuntimeException("Could not initialize SFTP for host: $hostName");
        }

        $sftpPath = "ssh2.sftp://" . (int) $sftp . $path;
        $handle = fopen($sftpPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Could not open remote file: $sftpPath");
        }

        return $handle;
    }

    /**
     * @return resource
     */
    private function getConnection(string $hostName, RemoteHost $hostConfig)
    {
        if (isset($this->connections[$hostName])) {
            return $this->connections[$hostName];
        }

        $host = $hostConfig->host;
        $port = $hostConfig->port;
        $user = $hostConfig->user ?? '';
        $password = $hostConfig->password ?? '';
        $sshKey = $hostConfig->sshKey ?? '';

        $connection = ssh2_connect($host, $port);
        if ($connection === false) {
            throw new RuntimeException("Could not connect to remote host: $hostName ($host:$port)");
        }

        if ($sshKey !== '') {
            if (!ssh2_auth_pubkey_file($connection, $user, $sshKey . '.pub', $sshKey, $password)) {
                throw new RuntimeException("SSH authentication failed for $hostName using public key.");
            }
        } elseif ($user !== '' && $password !== '') {
            if (!ssh2_auth_password($connection, $user, $password)) {
                throw new RuntimeException("SSH authentication failed for $hostName using password.");
            }
        } elseif ($user !== '') {
            if (!ssh2_auth_none($connection, $user)) {
                // Some servers allow this, otherwise it fails.
            }
        }

        $this->connections[$hostName] = $connection;

        return $connection;
    }

    private function findRemoteHost(string $name): ?RemoteHost
    {
        foreach ($this->configurationProvider->sourceRemoteHosts as $hostConfig) {
            if ($hostConfig->name === $name) {
                return $hostConfig;
            }
        }

        return null;
    }

    private function buildHttpUrl(RemoteHost $hostConfig, string $path): string
    {
        $protocol = $hostConfig->type;
        $host = $hostConfig->host;
        $user = $hostConfig->user;
        $password = $hostConfig->password;

        $url = "$protocol://";
        if ($user !== null && $password !== null) {
            $url .= "$user:$password@";
        } elseif ($user !== null) {
            $url .= "$user@";
        }

        $url .= $host;
        $url .= ":" . $hostConfig->port;

        return $url . $path;
    }
}

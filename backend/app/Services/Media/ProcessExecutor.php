<?php

namespace App\Services\Media;

use App\Exceptions\ExtractionFailedException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ProcessExecutor
{
    /**
     * Run an external process securely using argument arrays (NO raw shell concatenation).
     *
     * @param array<int, string> $command
     * @param int|null $timeout
     * @param string|null $cwd
     * @param callable|null $outputCallback
     * @return array{exit_code: int, stdout: string, stderr: string}
     *
     * @throws ExtractionFailedException
     */
    public function execute(
        array $command,
        ?int $timeout = null,
        ?string $cwd = null,
        ?callable $outputCallback = null
    ): array {
        $timeout = $timeout ?? config('media.download_timeout', 300);

        // Sanitize command array
        $safeCommand = array_values(array_filter($command, fn($item) => $item !== '' && $item !== null));

        Log::info('Executing media command', [
            'binary' => $safeCommand[0] ?? 'unknown',
            'argc' => count($safeCommand),
            'timeout' => $timeout,
        ]);

        $process = new Process($safeCommand, $cwd, $this->getProcessEnvironment(), null, $timeout);

        try {
            $stdoutBuffer = '';
            $stderrBuffer = '';

            $process->run(function ($type, $buffer) use (&$stdoutBuffer, &$stderrBuffer, $outputCallback) {
                if ($type === Process::OUT) {
                    $stdoutBuffer .= $buffer;
                    if ($outputCallback) {
                        $outputCallback('stdout', $buffer);
                    }
                } else {
                    $stderrBuffer .= $buffer;
                    if ($outputCallback) {
                        $outputCallback('stderr', $buffer);
                    }
                }
            });

            return [
                'exit_code' => $process->getExitCode() ?? 1,
                'stdout' => $stdoutBuffer,
                'stderr' => $stderrBuffer,
            ];
        } catch (\Throwable $e) {
            Log::error('Process execution exception: ' . $e->getMessage(), [
                'command' => $safeCommand[0] ?? '',
            ]);
            throw new ExtractionFailedException('Media processing process failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Parse binary string into command array (handles e.g. "python -m yt_dlp").
     *
     * @return array<int, string>
     */
    public function parseBinary(string $binaryConfig): array
    {
        $parts = preg_split('/\s+/', trim($binaryConfig));
        return $parts ?: [$binaryConfig];
    }

    /**
     * Get process environment variables ensuring Windows networking/Winsock dependencies.
     *
     * @return array<string, string>
     */
    protected function getProcessEnvironment(): array
    {
        $env = getenv();

        if (DIRECTORY_SEPARATOR === '\\') {
            $systemRoot = getenv('SystemRoot') ?: (getenv('SYSTEMROOT') ?: 'C:\\Windows');
            $env['SystemRoot'] = $systemRoot;
            $env['SYSTEMROOT'] = $systemRoot;
            $env['windir'] = getenv('windir') ?: (getenv('WINDIR') ?: $systemRoot);
            $env['WINDIR'] = $systemRoot;
            $env['PATH'] = getenv('PATH') ?: '';
            $env['APPDATA'] = getenv('APPDATA') ?: '';
            $env['LOCALAPPDATA'] = getenv('LOCALAPPDATA') ?: '';
            $env['USERPROFILE'] = getenv('USERPROFILE') ?: '';
            $env['HOMEDRIVE'] = getenv('HOMEDRIVE') ?: 'C:';
            $env['HOMEPATH'] = getenv('HOMEPATH') ?: '\\Users\\Default';
            $env['COMSPEC'] = getenv('COMSPEC') ?: ($systemRoot . '\\system32\\cmd.exe');
            $env['TEMP'] = getenv('TEMP') ?: sys_get_temp_dir();
            $env['TMP'] = getenv('TMP') ?: sys_get_temp_dir();

            $_SERVER['SystemRoot'] = $systemRoot;
            $_SERVER['SYSTEMROOT'] = $systemRoot;
            $_SERVER['windir'] = $systemRoot;
            $_SERVER['WINDIR'] = $systemRoot;
        }

        return array_filter($env, fn($v) => is_string($v));
    }
}

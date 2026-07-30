<?php

namespace App\Services;

/**
 * Writes key=value pairs into an .env-style file, replacing existing
 * keys in place and appending any that aren't already present. Used by
 * ApplicationInstaller to persist the database configuration chosen in
 * the installation wizard.
 */
class EnvFileWriter
{
    public function __construct(private readonly string $path) {}

    /**
     * @param  array<string, string>  $values
     */
    public function set(array $values): void
    {
        $contents = file_exists($this->path) ? file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->formatValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, $line, $contents);
            } else {
                $contents = rtrim($contents, "\n")."\n".$line."\n";
            }
        }

        file_put_contents($this->path, $contents);
    }

    private function formatValue(string $value): string
    {
        if ($value === '' || preg_match('/\s|#/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}

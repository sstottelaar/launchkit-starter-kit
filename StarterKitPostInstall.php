<?php

class StarterKitPostInstall
{
    public function handle($console): void
    {
        $siteName = $console->ask('Site name', config('app.name', 'Statamic'));
        $siteUrl = $console->ask('Site URL', config('app.url', 'http://localhost'));

        $this->updateSitesYaml($siteName, $siteUrl);
        $this->setEnvValueInFile(base_path('.env'), 'APP_NAME', $siteName);
        $this->setEnvValueInFile(base_path('.env'), 'APP_URL', $siteUrl);

        $console->info('<info>[✓]</info> Site configured.');

        if (! $console->confirm('Would you like to configure Digital Ocean Spaces for media storage?', true)) {
            return;
        }

        $key = $console->ask('Spaces Key');
        $secret = $console->secret('Spaces Secret');
        $console->line('Bucket info: <href=https://cloud.digitalocean.com/spaces>https://cloud.digitalocean.com/spaces</>');
        $bucket = $console->ask('Spaces Bucket');

        if (empty($key) || empty($secret) || empty($bucket)) {
            $console->warn('Skipping Spaces configuration: key, secret and bucket are required.');

            return;
        }

        $region = 'ams3';
        $endpoint = "https://{$region}.digitaloceanspaces.com/";
        $url = "https://{$bucket}.{$region}.cdn.digitaloceanspaces.com";

        $updates = [
            'DIGITALOCEAN_SPACES_KEY' => $key,
            'DIGITALOCEAN_SPACES_SECRET' => $secret,
            'DIGITALOCEAN_SPACES_BUCKET' => $bucket,
            'DIGITALOCEAN_SPACES_REGION' => $region,
            'DIGITALOCEAN_SPACES_FOLDER' => 'assets',
            'DIGITALOCEAN_SPACES_ENDPOINT' => $endpoint,
            'DIGITALOCEAN_SPACES_URL' => $url,
        ];

        $envPath = base_path('.env');
        $content = app('files')->get($envPath);
        foreach ($updates as $name => $value) {
            $content = $this->setEnvValue($content, $name, $value);
        }
        app('files')->put($envPath, $content);

        $console->info('<info>[✓]</info> Digital Ocean Spaces configured.');

        $process = new \Symfony\Component\Process\Process(['php', 'artisan', 'config:clear']);
        $process->setWorkingDirectory(base_path());
        $process->run();
    }

    private function updateSitesYaml(string $name, string $url): void
    {
        $path = base_path('resources/sites.yaml');
        $content = app('files')->get($path);

        $content = preg_replace("/^(\s*name:).*$/m", '$1 '.$this->yamlQuote($name), $content);
        $content = preg_replace("/^(\s*url:).*$/m", '$1 '.$this->yamlQuote(rtrim($url, '/')), $content);

        app('files')->put($path, $content);
    }

    private function yamlQuote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    private function setEnvValueInFile(string $path, string $key, string $value): void
    {
        $content = app('files')->get($path);
        app('files')->put($path, $this->setEnvValue($content, $key, $value));
    }

    private function setEnvValue(string $content, string $key, string $value): string
    {
        $quoted = '"'.str_replace('"', '\\"', $value).'"';
        $pattern = '/^'.preg_quote($key).'=.*$/m';

        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $key.'='.$quoted, $content);
        }

        return $content."\n{$key}={$quoted}\n";
    }
}

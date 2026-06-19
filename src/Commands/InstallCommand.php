<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature   = 'mikopbx:install';
    protected $description = 'Install bitdreamit/laravel-mikopbx — publish config, run migrations';

    public function handle(): int
    {
        $this->info('bitdreamit/laravel-mikopbx — Premium MikoPBX & Asterisk Package for Laravel 12');
        $this->newLine();

        $this->call('vendor:publish', ['--tag' => 'mikopbx-config', '--force' => true]);
        $this->info('Config published to config/mikopbx.php');

        $this->call('vendor:publish', ['--tag' => 'mikopbx-migrations', '--force' => true]);
        $this->info('Migrations published to database/migrations/');

        if ($this->confirm('Run database migrations now?', true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->info('Installation complete!');
        $this->newLine();

        $this->table(['Variable', 'Description', 'Required'], [
            ['MIKOPBX_URL',           'MikoPBX VPS URL e.g. https://1.2.3.4',  'YES'],
            ['MIKOPBX_API_KEY',       'MikoPBX REST API key (64 char)',          'YES'],
            ['MIKOPBX_AMI_HOST',      'MikoPBX VPS IP for AMI TCP',             'YES'],
            ['MIKOPBX_AMI_PORT',      'AMI port (default 5038)',                 'NO'],
            ['MIKOPBX_AMI_USER',      'AMI username',                            'YES'],
            ['MIKOPBX_AMI_SECRET',    'AMI password',                            'YES'],
            ['MIKOPBX_ARI_URL',       'ARI URL e.g. http://1.2.3.4:8088',       'OPTIONAL'],
            ['MIKOPBX_ARI_USER',      'ARI username',                            'OPTIONAL'],
            ['MIKOPBX_ARI_SECRET',    'ARI password',                            'OPTIONAL'],
            ['MIKOPBX_WEBHOOK_SECRET','Webhook HMAC signing secret',             'OPTIONAL'],
        ]);

        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Add .env variables above');
        $this->line('  2. Enable AMI in MikoPBX: Admin Panel -> System -> Asterisk Managers');
        $this->line('  3. php artisan mikopbx:listen (add to Supervisor)');
        $this->line('  4. php artisan mikopbx:health');
        $this->line('  Docs: https://github.com/bitdreamit/laravel-mikopbx');
        $this->newLine();

        return self::SUCCESS;
    }
}

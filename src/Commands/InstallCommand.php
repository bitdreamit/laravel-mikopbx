<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    protected $signature   = 'mikopbx:install';
    protected $description = 'Install bitdreamit/laravel-mikopbx — publish config, run migrations, create Supervisor config';

    public function handle(): int
    {
        $this->info('');
        $this->info('  ██████╗ ██╗████████╗██████╗ ██████╗ ███████╗ █████╗ ███╗   ███╗ ██╗████████╗');
        $this->info('  ██╔══██╗██║╚══██╔══╝██╔══██╗██╔══██╗██╔════╝██╔══██╗████╗ ████║ ██║╚══██╔══╝');
        $this->info('  ██████╔╝██║   ██║   ██║  ██║██████╔╝█████╗  ███████║██╔████╔██║ ██║   ██║   ');
        $this->info('  BitDream IT  — laravel-mikopbx  — bitdreamit.com');
        $this->info('');

        $this->call('vendor:publish', ['--tag' => 'mikopbx-config', '--force' => true]);
        $this->info('✅ Config published → config/mikopbx.php');

        $this->call('vendor:publish', ['--tag' => 'mikopbx-migrations']);
        $this->info('✅ Migrations published');

        $this->call('migrate', ['--force' => true]);
        $this->info('✅ Migrations ran');

        $this->writeSupervisorConfig();
        $this->writeEnvExample();

        $this->info('');
        $this->info('🎉 Installation complete!');
        $this->info('');
        $this->info('Next steps:');
        $this->info('  1. Add MIKOPBX_* variables to your .env');
        $this->info('  2. sudo supervisorctl reread && sudo supervisorctl update');
        $this->info('  3. Visit /pbx in your browser');
        $this->info('');

        return 0;
    }

    private function writeSupervisorConfig(): void
    {
        $path = base_path('docs/supervisor-mikopbx-ami.conf');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $this->supervisorConf());
        $this->info("✅ Supervisor config → {$path}");
    }

    private function writeEnvExample(): void
    {
        $example = base_path('.env.mikopbx.example');
        file_put_contents($example, <<<'ENV'
# MikoPBX Connection
MIKOPBX_URL=https://YOUR-MIKOPBX-VPS-IP
MIKOPBX_API_KEY=your-64-char-api-key-from-mikopbx-admin-panel
MIKOPBX_TIMEOUT=10
MIKOPBX_VERIFY_SSL=false

# AMI (Asterisk Manager Interface)
MIKOPBX_AMI_HOST=YOUR-MIKOPBX-VPS-IP
MIKOPBX_AMI_PORT=5038
MIKOPBX_AMI_USER=laravelapp
MIKOPBX_AMI_SECRET=your-strong-ami-secret

# ARI (Asterisk REST Interface)
MIKOPBX_ARI_URL=http://YOUR-MIKOPBX-VPS-IP:8088
MIKOPBX_ARI_USER=admin
MIKOPBX_ARI_PASSWORD=your-ari-password
MIKOPBX_ARI_APP=laravel-mikopbx

# Web Dialer (WebRTC softphone)
MIKOPBX_DIALER_ENABLED=true
MIKOPBX_SIP_SERVER=YOUR-MIKOPBX-VPS-IP
MIKOPBX_SIP_WS_PORT=8088
MIKOPBX_SIP_WSS=false
MIKOPBX_STUN=stun:stun.l.google.com:19302

# SMS Alerts (optional)
MIKOPBX_SMS_ENABLED=false
MIKOPBX_SMS_DRIVER=ssl_wireless
MIKOPBX_SMS_API_KEY=
MIKOPBX_SMS_FROM=YourSenderID

# Route settings
MIKOPBX_ROUTE_PREFIX=pbx
ENV);
        $this->info("✅ .env example → {$example}");
    }

    private function supervisorConf(): string
    {
        $artisan = base_path('artisan');
        $php     = PHP_BINARY;
        $log     = storage_path('logs');
        $dir     = base_path();

        return <<<CONF
[program:mikopbx-ami]
command={$php} {$artisan} mikopbx:listen
directory={$dir}
autostart=true
autorestart=true
startretries=5
numprocs=1
redirect_stderr=false
stdout_logfile={$log}/mikopbx-ami.log
stderr_logfile={$log}/mikopbx-ami-err.log
user=www-data
stopasgroup=true
killasgroup=true
CONF;
    }
}

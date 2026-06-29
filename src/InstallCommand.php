<?php

namespace BitDreamIT\MikoPBX\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature   = 'mikopbx:install';
    protected $description = 'Install bitdreamit/laravel-mikopbx — publish config, run migrations, write Supervisor config';

    public function handle(): int
    {
        $this->info('');
        $this->info('  ╔══════════════════════════════════════════════╗');
        $this->info('  ║  bitdreamit/laravel-mikopbx                  ║');
        $this->info('  ║  BitDream IT — bitdreamit.com                ║');
        $this->info('  ╚══════════════════════════════════════════════╝');
        $this->info('');

        // 1. Publish config
        $this->call('vendor:publish', [
            '--tag'   => 'mikopbx-config',
            '--force' => true,
        ]);
        $this->info('✅  Config published → config/mikopbx.php');

        // 2. Publish migrations
        $this->call('vendor:publish', ['--tag' => 'mikopbx-migrations']);
        $this->info('✅  Migrations published → database/migrations/');

        // 2b. Publish public JS assets (JsSIP etc.) to public/vendor/mikopbx/
        $this->call('vendor:publish', [
            '--tag'   => 'mikopbx-public',
            '--force' => true,
        ]);
        $this->info('✅  Public assets published → public/vendor/mikopbx/jssip.min.js');

        // 3. Run migrations
        $this->call('migrate', ['--force' => true]);
        $this->info('✅  Migrations ran (mikopbx_* tables + pbx fields on users)');

        // 4. Write Supervisor config
        $this->writeSupervisorConfig();

        // 5. Write .env example
        $this->writeEnvExample();

        $this->info('');
        $this->info('🎉  Installation complete!');
        $this->info('');
        $this->line('  Next steps:');
        $this->line('  1. Copy .env.mikopbx.example values into your .env');
        $this->line('  2. In MikoPBX Admin → System → AMI Users → Add user "laravelapp"');
        $this->line('  3. In MikoPBX Admin → Settings → API Keys → Generate key');
        $this->line('  4. Assign extensions to users:');
        $this->line('       $user->update([\'pbx_extension\' => \'101\', \'pbx_sip_password\' => \'secret\']);');
        $this->line('  5. Add trait to User model:');
        $this->line('       use BitDreamIT\\MikoPBX\\Traits\\HasMikoPBXExtension;');
        $this->line('  6. Start AMI listener:');
        $this->line('       sudo cp docs/supervisor-mikopbx-ami.conf /etc/supervisor/conf.d/');
        $this->line('       sudo supervisorctl reread && supervisorctl update');
        $this->line('       sudo supervisorctl start mikopbx-ami');
        $this->line('  7. Sync extensions from MikoPBX:');
        $this->line('       php artisan mikopbx:sync-extensions');
        $this->line('  8. Sync CDR (call logs):');
        $this->line('       php artisan mikopbx:cdr-sync --days=7');
        $this->line('  9. Visit /pbx in your browser');
        $this->info('');

        return 0;
    }

    private function writeSupervisorConfig(): void
    {
        $path = base_path('docs/supervisor-mikopbx-ami.conf');
        @mkdir(dirname($path), 0755, true);

        $artisan = base_path('artisan');
        $php     = PHP_BINARY;
        $log     = storage_path('logs');
        $dir     = base_path();

        file_put_contents($path, <<<CONF
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
CONF);
        $this->info("✅  Supervisor config → {$path}");
    }

    private function writeEnvExample(): void
    {
        $path = base_path('.env.mikopbx.example');

        file_put_contents($path, <<<'ENV'
# ── MikoPBX REST API v3 ──────────────────────────────────────────────────────
# Base URL of your MikoPBX server (no trailing slash)
MIKOPBX_URL=https://163.223.240.124

# JWT Bearer token — MikoPBX Admin → Settings → API Keys → Generate
MIKOPBX_API_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...

# HTTP request timeout in seconds
MIKOPBX_TIMEOUT=10

# Set false for self-signed SSL (common in local MikoPBX installs)
MIKOPBX_VERIFY_SSL=false

# ── AMI — Asterisk Manager Interface (call control + live events) ────────────
# Create in: MikoPBX Admin → System → AMI Users → Add
MIKOPBX_AMI_HOST=163.223.240.124
MIKOPBX_AMI_PORT=5038
MIKOPBX_AMI_USER=laravelapp
MIKOPBX_AMI_SECRET=your-strong-ami-secret
MIKOPBX_AMI_TIMEOUT=10

# ── ARI — Asterisk REST Interface (optional) ─────────────────────────────────
MIKOPBX_ARI_URL=http://163.223.240.124:8088
MIKOPBX_ARI_USER=ari_admin
MIKOPBX_ARI_PASSWORD=your-ari-password
MIKOPBX_ARI_APP=laravel-mikopbx

# ── Web Dialer — JsSIP WebRTC browser softphone ──────────────────────────────
# Users need pbx_extension and pbx_sip_password set on their user record.
# MikoPBX WebRTC extensions must have -WS suffix (handled automatically).
# WebSocket path for MikoPBX is always /asterisk/ws
MIKOPBX_DIALER_ENABLED=true
MIKOPBX_SIP_SERVER=pbx.htncr.org
MIKOPBX_SIP_WS_PORT=8089
MIKOPBX_SIP_WSS=true
MIKOPBX_STUN=stun:stun.l.google.com:19302

# ── SMS Alerts (optional) ────────────────────────────────────────────────────
MIKOPBX_SMS_ENABLED=false
MIKOPBX_SMS_DRIVER=ssl_wireless
MIKOPBX_SMS_API_KEY=
MIKOPBX_SMS_FROM=YourSenderID

# ── Route settings ───────────────────────────────────────────────────────────
# URL prefix for all package routes: /pbx/* by default
MIKOPBX_ROUTE_PREFIX=pbx
ENV);

        $this->info("✅  .env example → {$path}");
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds pbx_extension and pbx_sip_password to the users table.
 *
 * pbx_extension    — the MikoPBX extension number, e.g. "101"
 *                    The JsSIP UA will register as "101-WS" (MikoPBX WebRTC suffix)
 *
 * pbx_sip_password — the SIP password for this extension in MikoPBX.
 *                    Set in MikoPBX Admin → Extensions → edit → SIP password
 *
 * After migrating, assign extensions to users:
 *   $user->update(['pbx_extension' => '101', 'pbx_sip_password' => 'secret123']);
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pbx_extension', 20)->nullable()->after('email')
                ->comment('MikoPBX extension number e.g. 101');
            $table->string('pbx_sip_password')->nullable()->after('pbx_extension')
                ->comment('SIP password for this extension in MikoPBX');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pbx_extension', 'pbx_sip_password']);
        });
    }
};

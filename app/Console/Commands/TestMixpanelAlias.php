<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MixpanelService;

class TestMixpanelAlias extends Command
{
    protected $signature = 'mixpanel:test-alias {anon_id} {user_id}';
    protected $description = 'Manually test Mixpanel aliasing between anonymous ID and user ID/email';

    protected $mixpanel;

    public function __construct(MixpanelService $mixpanelService)
    {
        parent::__construct();
        $this->mixpanel = $mixpanelService;
    }

    public function handle()
    {
        $anonId = $this->argument('anon_id');
        $userId = $this->argument('user_id');
    
        $this->info("📡 Sending alias from [$anonId] to [$userId]...");
    
        try {
            $this->mixpanel->alias($anonId, $userId);
            $this->mixpanel->identifyUser($userId, [
                'Alias Linked Manually At' => now()->toDateTimeString(),
            ]);
    
            $this->info('✅ Alias and user profile updated in Mixpanel.');
        } catch (\Throwable $e) {
            $this->error('❌ Error: ' . $e->getMessage());
        }
    }
    
}

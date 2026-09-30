<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SystemUpdateGithubTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_and_token_are_saved_encrypted(): void
    {
        Process::fake(['*' => Process::result(exitCode: 1)]); // not a repo yet
        $owner = User::factory()->create(['password' => bcrypt('pw-123456')])->assignRole('super-admin');

        $this->actingAs($owner)->post('/admin/system/update/github', [
            'repo_url' => 'https://github.com/KlokAds/tasfiaplumbing', 'branch' => 'main', 'token' => 'github_pat_secret',
        ])->assertSessionHas('success');

        $this->assertSame('https://github.com/KlokAds/tasfiaplumbing.git', SiteSetting::get('deploy.repo_url'));
        $this->assertSame('github_pat_secret', Crypt::decryptString(SiteSetting::get('deploy.token')));

        $this->post('/admin/system/update/github', ['repo_url' => 'https://evil.example/x/y', 'branch' => 'main'])->assertSessionHasErrors('repo_url');
    }

    public function test_connect_runs_fixed_git_steps_with_the_token_only_in_the_environment(): void
    {
        SiteSetting::putMany([
            'deploy.repo_url' => 'https://github.com/KlokAds/tasfiaplumbing.git',
            'deploy.branch' => 'main',
            'deploy.token' => Crypt::encryptString('github_pat_secret'),
        ]);
        $owner = User::factory()->create(['password' => bcrypt('pw-123456')])->assignRole('super-admin');

        $connected = false;
        Process::fake(function (PendingProcess $p) use (&$connected) {
            $cmd = is_array($p->command) ? implode(' ', $p->command) : $p->command;
            if (str_contains($cmd, 'rev-parse --show-toplevel') || str_contains($cmd, 'rev-parse --verify HEAD')) {
                return $connected ? Process::result(base_path()) : Process::result(exitCode: 128);
            }
            if (str_contains($cmd, 'reset --hard')) {
                $connected = true;
            }

            return Process::result('ok');
        });

        $this->actingAs($owner)->postJson('/admin/system/update/deploy', ['password' => 'pw-123456', 'action' => 'connect'])
            ->assertOk()->assertJson(['status' => 'success']);

        Process::assertRan(fn (PendingProcess $p) => is_array($p->command) && array_slice($p->command, 1) === ['init']);
        Process::assertRan(fn (PendingProcess $p) => is_array($p->command) && in_array('fetch', $p->command, true)
            && ($p->environment['GIT_CONFIG_VALUE_0'] ?? '') === 'AUTHORIZATION: basic ' . base64_encode('x-access-token:github_pat_secret'));
        Process::assertRan(fn (PendingProcess $p) => is_array($p->command) && array_slice($p->command, 1) === ['reset', '--hard', 'origin/main']);
        Process::assertDidntRun(fn (PendingProcess $p) => str_contains(is_array($p->command) ? implode(' ', $p->command) : $p->command, 'github_pat_secret'));
    }
}

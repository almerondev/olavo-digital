<?php

namespace Tests\Feature\Communication;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Communication\Contracts\PushSender;
use App\Modules\Communication\Models\PushDevice;
use App\Modules\Communication\Services\FcmPushSender;
use App\Modules\Communication\Services\NotificationCenter;
use App\Modules\Communication\Services\NullPushSender;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, CatalogSeeder::class, CourseSeeder::class]);
        Cache::flush();
    }

    private function student(string $course): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::STUDENT)->value('id'));
        Student::create(['user_id' => $user->id])->courses()->attach(Course::where('slug', $course)->firstOrFail());

        return $user;
    }

    private function useFcm(): void
    {
        // No Windows o OpenSSL do PHP não acha um arquivo de configuração sozinho.
        $config = ['private_key_bits' => 2048, 'config' => tempnam(sys_get_temp_dir(), 'ssl')];
        file_put_contents($config['config'], "[req]
distinguished_name=dn
[dn]
");
        $key = openssl_pkey_new($config);
        openssl_pkey_export($key, $pem, null, $config);

        $this->app->instance(PushSender::class, new FcmPushSender(['project_id' => 'olavo-teste', 'client_email' => 'push@olavo-teste.iam', 'private_key' => $pem]));
    }

    private function device(User $user, string $token): void
    {
        PushDevice::create(['user_id' => $user->id, 'token' => $token, 'token_hash' => PushDevice::hash($token)]);
    }

    public function test_app_registers_device_and_login_change_moves_it(): void
    {
        $first = $this->student('preparatorio-enem');
        $second = $this->student('preparatorio-esa');

        $this->actingAs($first)->postJson('/api/v1/me/push-devices', ['token' => 'abc', 'platform' => 'android'])->assertNoContent();
        $this->actingAs($second)->postJson('/api/v1/me/push-devices', ['token' => 'abc', 'platform' => 'android'])->assertNoContent();

        $this->assertSame([$second->id], PushDevice::pluck('user_id')->all());

        $this->deleteJson('/api/v1/me/push-devices', ['token' => 'abc'])->assertNoContent();
        $this->assertSame(0, PushDevice::count());
    }

    public function test_push_reaches_only_the_course_audience_and_carries_the_link(): void
    {
        $this->useFcm();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'tk']), 'fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);

        $esa = $this->student('preparatorio-esa');
        $this->device($esa, 'token-esa');
        $this->device($this->student('preparatorio-enem'), 'token-enem');

        app(NotificationCenter::class)->send('manual', 'Prova', 'Amanhã', '/avisos', ['mode' => 'course', 'ids' => [Course::where('slug', 'preparatorio-esa')->value('id')]]);

        $sent = Http::recorded(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com'));

        $this->assertCount(1, $sent);
        $message = $sent->first()[0]->data()['message'];
        $this->assertSame('token-esa', $message['token']);
        $this->assertSame('Prova', $message['notification']['title']);
        $this->assertSame('/avisos', $message['data']['url']);
        $this->assertSame('Bearer tk', $sent->first()[0]->header('Authorization')[0]);
    }

    public function test_dead_token_is_removed(): void
    {
        $this->useFcm();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'tk']), 'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)]);

        $this->device($this->student('preparatorio-esa'), 'velho');

        app(NotificationCenter::class)->send('manual', 'Oi', 'Oi', null, ['mode' => 'all']);

        $this->assertSame(0, PushDevice::count());
    }

    public function test_without_firebase_the_platform_keeps_working_and_tells_the_app(): void
    {
        config(['services.fcm.credentials' => storage_path('nao-existe.json')]);

        $this->assertInstanceOf(NullPushSender::class, app(PushSender::class));
        $this->assertFalse($this->getJson("/api/v1/config")->json("data")["push.enabled"]);
    }
}

<?php

namespace Tests\Feature\Playback;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Services\HomeService;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialService;
use App\Modules\Materials\Services\MaterialWorkflow;
use App\Modules\Playback\Models\WatchHistory;
use App\Modules\Playback\Models\WatchProgress;
use App\Modules\Uploads\Models\Upload;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlaybackTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private Teacher $teacher;
    private Category $history;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, CatalogSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', Role::ADMIN)->first());
        $this->student = User::factory()->create();
        $this->student->roles()->attach(Role::where('slug', Role::STUDENT)->first());
        Student::create(['user_id' => $this->student->id]);
        $this->teacher = Teacher::create(['slug' => 'prof', 'display_name' => 'Prof']);
        $this->history = Category::where('slug', 'historia')->first();
        $this->actingAs($this->student, 'web');
    }

    private function video(string $title, array $overrides = []): Material
    {
        $upload = Upload::create([
            'uuid' => (string) Str::uuid(), 'disk' => 'uploads_private', 'object_key' => 'v.mp4', 'purpose' => 'material',
            'file_class' => 'video', 'original_name' => 'v.mp4', 'status' => 'ready',
        ]);
        $material = app(MaterialService::class)->save(null, $overrides + [
            'title' => $title, 'type' => 'video', 'visibility' => 'all', 'teacher_id' => $this->teacher->id,
            'file_upload_id' => $upload->id, 'category_ids' => [$this->history->id],
        ], $this->admin);

        return app(MaterialWorkflow::class)->publish($material, $this->admin);
    }

    private function homeLayout(string $source, string $title): void
    {
        HomeLayout::firstOrCreate(['surface' => 'home'], ['name' => 'Início', 'status' => 'published', 'published_at' => now()])
            ->sections()->create(['title' => $title, 'source_type' => $source]);
        app(HomeService::class)->flush();
    }

    public function test_progress_is_saved_and_playback_resumes_from_it(): void
    {
        $video = $this->video('Aula');

        $this->getJson("/api/v1/materials/{$video->slug}/playback")->assertOk()
            ->assertJsonPath('data.kind', 'file')
            ->assertJsonPath('data.resume_at_seconds', 0);

        $this->putJson("/api/v1/materials/{$video->slug}/progress", ['position' => 120, 'duration' => 600, 'watched' => 15])->assertNoContent();
        $this->putJson("/api/v1/materials/{$video->slug}/progress", ['position' => 240, 'duration' => 600, 'watched' => 15])->assertNoContent();

        $this->assertSame(1, WatchProgress::count());
        $this->getJson("/api/v1/materials/{$video->slug}/playback")->assertJsonPath('data.resume_at_seconds', 240);
    }

    public function test_short_video_resumes_from_saved_point(): void
    {
        $video = $this->video('Clipe');

        $this->putJson("/api/v1/materials/{$video->slug}/progress", ['position' => 7, 'duration' => 16])->assertNoContent();

        $this->getJson("/api/v1/materials/{$video->slug}/playback")->assertJsonPath('data.resume_at_seconds', 7);
    }

    public function test_reaching_95_percent_completes_and_next_viewing_restarts(): void
    {
        $video = $this->video('Aula');

        $this->putJson("/api/v1/materials/{$video->slug}/progress", ['position' => 95, 'duration' => 100])->assertNoContent();

        $this->assertNotNull(WatchProgress::first()->completed_at);
        $this->getJson("/api/v1/materials/{$video->slug}/playback")
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.resume_at_seconds', 0);
    }

    public function test_student_can_mark_as_completed_manually(): void
    {
        $video = $this->video('Aula');

        $this->postJson("/api/v1/materials/{$video->slug}/complete")->assertNoContent();

        $this->assertNotNull(WatchProgress::first()->completed_at);
    }

    public function test_student_cannot_save_progress_on_unavailable_material(): void
    {
        $video = $this->video('Interno', ['visibility' => 'staff']);

        $this->putJson("/api/v1/materials/{$video->slug}/progress", ['position' => 10])->assertNotFound();
        $this->getJson("/api/v1/materials/{$video->slug}/playback")->assertNotFound();
        $this->assertSame(0, WatchProgress::count());
    }

    public function test_staff_without_student_profile_gets_clear_error(): void
    {
        $video = $this->video('Aula');

        $this->actingAs($this->admin, 'web');
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'web');

        $this->getJson("/api/v1/materials/{$video->slug}/playback")->assertOk();
        $this->putJson("/api/v1/materials/{$video->slug}/progress", ['position' => 10])->assertForbidden()->assertJsonPath('code', 'student_only');
    }

    public function test_next_skips_completed_and_stays_in_the_category(): void
    {
        $math = Category::where('slug', 'matematica')->first();
        $current = $this->video('Atual');
        $done = $this->video('Já vista');
        $this->video('Pendente');
        $this->video('Outra matéria', ['category_ids' => [$math->id]]);

        $this->postJson("/api/v1/materials/{$done->slug}/complete")->assertNoContent();

        $this->getJson("/api/v1/materials/{$current->slug}/playback")->assertJsonPath('data.next.title', 'Pendente');
    }

    public function test_continue_watching_section_shows_only_started_videos_with_progress(): void
    {
        $started = $this->video('Começada');
        $finished = $this->video('Terminada');
        $this->video('Nova');
        $this->homeLayout('continue_watching', 'Continuar assistindo');

        $this->putJson("/api/v1/materials/{$started->slug}/progress", ['position' => 300, 'duration' => 600]);
        $this->putJson("/api/v1/materials/{$finished->slug}/progress", ['position' => 600, 'duration' => 600]);

        $this->getJson('/api/v1/home')
            ->assertJsonCount(1, 'data.sections.0.items')
            ->assertJsonPath('data.sections.0.items.0.title', 'Começada')
            ->assertJsonPath('data.sections.0.items.0.progress.percent', 50)
            ->assertJsonPath('data.sections.0.items.0.progress.remaining_seconds', 300);
    }

    public function test_recommended_uses_watched_categories_and_excludes_what_was_seen(): void
    {
        $math = Category::where('slug', 'matematica')->first();
        $seen = $this->video('Vista');
        $this->video('Da mesma matéria');
        $this->video('De outra matéria', ['category_ids' => [$math->id]]);
        $this->homeLayout('recommended', 'Recomendados');

        $this->putJson("/api/v1/materials/{$seen->slug}/progress", ['position' => 10, 'duration' => 100]);

        $this->getJson('/api/v1/home')
            ->assertJsonCount(1, 'data.sections.0.items')
            ->assertJsonPath('data.sections.0.items.0.title', 'Da mesma matéria');
    }

    public function test_favorites_add_list_filter_and_remove(): void
    {
        $math = Category::where('slug', 'matematica')->first();
        $one = $this->video('Um');
        $two = $this->video('Dois', ['category_ids' => [$math->id]]);

        $this->putJson("/api/v1/me/favorites/{$one->slug}")->assertNoContent();
        $this->putJson("/api/v1/me/favorites/{$one->slug}")->assertNoContent();
        $this->putJson("/api/v1/me/favorites/{$two->slug}")->assertNoContent();

        $this->getJson('/api/v1/me/favorites')->assertJsonCount(2, 'data')->assertJsonPath('data.0.is_favorite', true);
        $this->getJson("/api/v1/me/favorites?category={$math->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Dois');

        $this->deleteJson("/api/v1/me/favorites/{$one->slug}")->assertNoContent();
        $this->getJson('/api/v1/me/favorites')->assertJsonCount(1, 'data');
    }

    public function test_material_that_became_unavailable_leaves_my_list(): void
    {
        $video = $this->video('Aula');
        $this->putJson("/api/v1/me/favorites/{$video->slug}")->assertNoContent();

        $video->update(['visibility' => 'staff']);

        $this->getJson('/api/v1/me/favorites')->assertJsonCount(0, 'data');
    }

    public function test_favorites_are_private_to_each_student(): void
    {
        $video = $this->video('Aula');
        $this->putJson("/api/v1/me/favorites/{$video->slug}")->assertNoContent();

        $other = User::factory()->create();
        $other->roles()->attach(Role::where('slug', Role::STUDENT)->first());
        Student::create(['user_id' => $other->id]);
        $this->app['auth']->forgetGuards();

        $this->actingAs($other, 'web')->getJson('/api/v1/me/favorites')->assertJsonCount(0, 'data');
    }

    public function test_history_groups_a_session_and_opens_a_new_one_later(): void
    {
        $video = $this->video('Aula');

        $this->putJson("/api/v1/materials/{$video->slug}/progress", ['position' => 15, 'duration' => 100, 'watched' => 15]);
        $this->putJson("/api/v1/materials/{$video->slug}/progress", ['position' => 30, 'duration' => 100, 'watched' => 15]);
        $this->assertSame(1, WatchHistory::count());
        $this->assertSame(30, WatchHistory::first()->seconds_watched);

        $this->travel(2)->hours();
        $this->putJson("/api/v1/materials/{$video->slug}/progress", ['position' => 45, 'duration' => 100, 'watched' => 15]);

        $this->assertSame(2, WatchHistory::count());
    }

    public function test_watched_seconds_per_event_are_capped(): void
    {
        $video = $this->video('Aula');

        $this->putJson("/api/v1/materials/{$video->slug}/progress", ['position' => 1, 'duration' => 100, 'watched' => 99999]);

        $this->assertSame(60, WatchHistory::first()->seconds_watched);
    }

    public function test_non_video_access_enters_history_and_clearing_keeps_metrics(): void
    {
        $video = $this->video('Aula');
        $this->postJson("/api/v1/materials/{$video->slug}/access")->assertNoContent();

        $entry = $this->getJson('/api/v1/me/history')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.material.title', 'Aula')
            ->assertJsonPath('data.0.completed', false)
            ->json('data.0');

        $this->deleteJson('/api/v1/me/history')->assertNoContent();

        $this->getJson('/api/v1/me/history')->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('watch_history', ['id' => $entry['id']]);
    }

    public function test_student_can_hide_only_own_history_entry(): void
    {
        $video = $this->video('Aula');
        $otherStudent = Student::create(['user_id' => User::factory()->create()->id]);
        $foreign = WatchHistory::create(['student_id' => $otherStudent->id, 'material_id' => $video->id]);

        $this->deleteJson("/api/v1/me/history/{$foreign->id}")->assertNoContent();

        $this->assertNull($foreign->fresh()->hidden_at);
    }

    public function test_student_sets_avatar_but_cannot_use_upload_of_someone_else(): void
    {
        $foreign = Upload::create([
            'uuid' => (string) Str::uuid(), 'disk' => 'uploads_public', 'object_key' => 'a.webp', 'purpose' => 'avatar',
            'file_class' => 'image', 'original_name' => 'a.png', 'status' => 'ready', 'uploaded_by' => $this->admin->id,
        ]);
        $own = $foreign->replicate(['uuid']);
        $own->fill(['uuid' => (string) Str::uuid(), 'uploaded_by' => $this->student->id])->save();

        $this->patchJson('/api/v1/me', ['avatar_upload_id' => $foreign->id])->assertStatus(422);
        $this->patchJson('/api/v1/me', ['avatar_upload_id' => $own->id])->assertOk();

        $this->assertSame($own->id, $this->student->fresh()->avatar_upload_id);
    }

    public function test_student_may_start_avatar_upload_only(): void
    {
        $this->postJson('/api/v1/uploads', ['purpose' => 'avatar', 'name' => 'foto.png', 'size' => 100])->assertCreated();
        $this->postJson('/api/v1/uploads', ['purpose' => 'image', 'name' => 'foto.png', 'size' => 100])->assertForbidden();
        $this->postJson('/api/v1/uploads', ['purpose' => 'avatar', 'name' => 'foto.pdf', 'size' => 100])->assertStatus(422);
    }

    public function test_subtitle_must_be_vtt_and_reaches_playback(): void
    {
        $subtitle = Upload::create([
            'uuid' => (string) Str::uuid(), 'disk' => 'uploads_private', 'object_key' => 's.vtt', 'purpose' => 'material',
            'file_class' => 'subtitle', 'original_name' => 's.vtt', 'status' => 'ready',
        ]);
        $video = $this->video('Aula', ['subtitle_upload_id' => $subtitle->id]);

        $this->getJson("/api/v1/materials/{$video->slug}/playback")->assertJsonPath('data.subtitles.0.locale', 'pt-BR');
    }
}

<?php

namespace Tests\Feature\Uploads;

use App\Modules\Access\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Uploads\Enums\UploadStatus;
use App\Modules\Uploads\Models\Upload;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class UploadsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessSeeder::class);
        Storage::fake('uploads_private');
        Storage::fake('uploads_public');
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', Role::ADMIN)->first());
    }

    private function send(string $purpose, string $name, string $content): Upload
    {
        $start = $this->actingAs($this->admin, 'web')->postJson('/api/v1/uploads', [
            'purpose' => $purpose,
            'name' => $name,
            'size' => strlen($content),
        ])->assertCreated();

        $this->call('PUT', $start->json('target.url'), [], [], [], ['HTTP_ACCEPT' => 'application/json'], $content)->assertNoContent();

        $uuid = $start->json('data.uuid');
        $this->postJson("/api/v1/uploads/{$uuid}/complete")->assertOk();

        return Upload::where('uuid', $uuid)->firstOrFail();
    }

    private function png(int $width = 1200, int $height = 800): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function docx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('word/document.xml', '<document/>');
        $zip->close();
        $content = (string) file_get_contents($path);
        unlink($path);

        return $content;
    }

    public function test_pdf_is_validated_and_moved_to_private_storage(): void
    {
        $upload = $this->send('material', 'Apostila de História.pdf', "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF");

        $this->assertSame(UploadStatus::Ready, $upload->status);
        $this->assertSame('application/pdf', $upload->mime_type);
        $this->assertStringStartsWith("materials/{$upload->uuid}/", $upload->object_key);
        Storage::disk('uploads_private')->assertExists($upload->object_key);
        $this->assertSame([], $upload->imageUrls());
    }

    public function test_file_with_false_extension_is_rejected_and_removed(): void
    {
        $upload = $this->send('material', 'virus.pdf', 'MZ este conteúdo não é um PDF');

        $this->assertSame(UploadStatus::Rejected, $upload->status);
        $this->assertStringContainsString('não corresponde', $upload->rejection_reason);
        Storage::disk('uploads_private')->assertMissing($upload->object_key);
    }

    public function test_zip_renamed_as_docx_is_rejected_but_real_docx_is_accepted(): void
    {
        $zip = new ZipArchive;
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('qualquer.txt', 'conteúdo');
        $zip->close();

        $this->assertSame(UploadStatus::Rejected, $this->send('material', 'falso.docx', (string) file_get_contents($path))->status);
        unlink($path);

        $real = $this->send('material', 'resumo.docx', $this->docx());
        $this->assertSame(UploadStatus::Ready, $real->status);
        $this->assertSame('document', $real->file_class);
    }

    public function test_image_generates_webp_variants_in_public_storage(): void
    {
        $upload = $this->send('image', 'capa.png', $this->png());

        $this->assertSame(UploadStatus::Ready, $upload->status);
        $this->assertSame(1200, $upload->width);
        $this->assertSame(['480', '960', '1200'], array_map('strval', array_keys($upload->meta['variants'])));

        foreach ($upload->meta['variants'] as $key) {
            Storage::disk('uploads_public')->assertExists($key);
        }

        $this->assertCount(3, $upload->imageUrls());
    }

    public function test_format_and_size_limits_are_enforced_before_sending(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->postJson('/api/v1/uploads', ['purpose' => 'material', 'name' => 'script.exe', 'size' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->postJson('/api/v1/uploads', ['purpose' => 'image', 'name' => 'capa.pdf', 'size' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->postJson('/api/v1/uploads', ['purpose' => 'image', 'name' => 'capa.jpg', 'size' => 16 * 1024 * 1024])
            ->assertStatus(422)->assertJsonValidationErrors('size');
    }

    public function test_student_cannot_upload_and_unsigned_requests_are_refused(): void
    {
        $student = User::factory()->create();
        $student->roles()->attach(Role::where('slug', Role::STUDENT)->first());

        $this->actingAs($student, 'web')
            ->postJson('/api/v1/uploads', ['purpose' => 'image', 'name' => 'x.png', 'size' => 10])
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $upload = $this->send('material', 'a.pdf', '%PDF-1.7 teste');

        $this->getJson("/api/v1/uploads/{$upload->uuid}/file")->assertForbidden();
        $this->putJson("/api/v1/uploads/{$upload->uuid}/content")->assertForbidden();
    }

    public function test_signed_url_serves_private_file_and_expires(): void
    {
        $upload = $this->send('material', 'a.pdf', '%PDF-1.7 teste');
        $url = $this->getJson("/api/v1/uploads/{$upload->uuid}/access")->assertOk()->json('data.url');

        auth('web')->logout();
        $this->get($url)->assertOk();

        $this->travel(11)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_abandoned_uploads_are_pruned(): void
    {
        $upload = Upload::create([
            'uuid' => 'd7b9bdfe-0000-4000-8000-000000000001',
            'disk' => 'uploads_private',
            'object_key' => 'incoming/antigo.pdf',
            'purpose' => 'material',
            'file_class' => 'pdf',
            'original_name' => 'antigo.pdf',
        ]);
        Storage::disk('uploads_private')->put('incoming/antigo.pdf', 'x');

        $this->travel(2)->days();
        $this->artisan('uploads:prune')->assertSuccessful();

        $this->assertModelMissing($upload);
        Storage::disk('uploads_private')->assertMissing('incoming/antigo.pdf');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StaffPhotoUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvatarImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_avatar_streams_file_from_staff_uploads(): void
    {
        $dir = sys_get_temp_dir().'/helpdesk-avatar-test-'.uniqid('', true);
        mkdir($dir.DIRECTORY_SEPARATOR.'staff', 0755, true);
        $filename = 'unit-face.png';
        file_put_contents($dir.DIRECTORY_SEPARATOR.'staff'.DIRECTORY_SEPARATOR.$filename, 'fake-bytes');

        config(['helpdesk.staff_uploads_root' => $dir]);

        $user = User::factory()->create(['photo' => $filename]);
        $url = StaffPhotoUrl::forUser($user);
        $this->assertIsString($url);
        $this->assertStringStartsWith('/api/v1/avatar/'.$user->id, $url);

        $q = parse_url($url, PHP_URL_QUERY);
        $this->assertIsString($q);

        $this->get('/api/v1/avatar/'.$user->id.'?'.$q)
            ->assertOk();
    }

    public function test_signed_avatar_rejects_bad_signature(): void
    {
        $user = User::factory()->create(['photo' => 'x.png']);
        $this->get('/api/v1/avatar/'.$user->id.'?exp='.(time() + 3600).'&sig=deadbeef')
            ->assertForbidden();
    }
}

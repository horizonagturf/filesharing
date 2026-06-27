<?php

namespace Tests\Feature;

use App\Models\Bundle;
use Exception;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ServerErrorLoggingTest extends TestCase
{
    private ?string $slug = null;

    /** @var array<int, MessageLogged> */
    private array $loggedMessages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->loggedMessages = [];

        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->loggedMessages[] = $event;
        });
    }

    protected function tearDown(): void
    {
        Bundle::flushEventListeners();

        if ($this->slug !== null) {
            Bundle::where('slug', $this->slug)->delete();
        }

        parent::tearDown();
    }

    public function test_unhandled_exception_is_logged(): void
    {
        Route::middleware('web')->get('/__test/unhandled-exception', function () {
            throw new RuntimeException('unhandled test exception');
        });

        $this->get('/__test/unhandled-exception');

        $this->assertTrue(
            $this->hasLoggedErrorContaining('unhandled test exception'),
            'Expected unhandled exception to be written to the log.'
        );
    }

    public function test_download_zip_failure_is_logged(): void
    {
        $this->slug = 'emptydownloadbundle';
        $bundle = Bundle::create([
            'slug' => $this->slug,
            'owner_token' => substr(sha1('owner'), 0, 15),
            'preview_token' => substr(sha1('preview'), 0, 15),
            'completed' => true,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $response = $this->get('/bundle/'.$bundle->slug.'/download?auth='.$bundle->preview_token);

        $response->assertInternalServerError();
        $this->assertTrue(
            $this->hasLoggedErrorContaining('Failed to open stream')
                || $this->hasLoggedErrorContaining('Zip archive')
                || $this->hasLoggedErrorContaining('Cannot initialize'),
            'Expected zip download failure to be written to the log.'
        );
    }

    public function test_json_500_from_upload_controller_is_logged(): void
    {
        $this->slug = 'storefailbundle';
        $bundle = Bundle::create([
            'slug' => $this->slug,
            'owner_token' => substr(sha1('owner2'), 0, 15),
            'preview_token' => substr(sha1('preview2'), 0, 15),
            'completed' => false,
            'expiry' => '86400',
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        Bundle::saving(function () {
            throw new Exception('forced update failure');
        });

        $response = $this->postJson('/upload/'.$bundle->slug, [
            'title' => 'Test',
            'expiry' => '86400',
            'auth' => $bundle->owner_token,
        ], [
            'X-Upload-Auth' => $bundle->owner_token,
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertInternalServerError()
            ->assertJson([
                'result' => false,
                'message' => 'forced update failure',
            ]);

        $this->assertTrue(
            $this->hasLoggedErrorContaining('forced update failure'),
            'Expected upload controller JSON 500 to be written to the log.'
        );
    }

    public function test_client_errors_are_not_logged(): void
    {
        $this->slug = 'forbiddenbundle';
        $bundle = Bundle::create([
            'slug' => $this->slug,
            'owner_token' => substr(sha1('owner3'), 0, 15),
            'preview_token' => substr(sha1('preview3'), 0, 15),
            'completed' => true,
            'expiry' => '86400',
            'expires_at' => now()->addDay(),
            'fullsize' => 0,
            'max_downloads' => 0,
            'downloads' => 0,
        ]);

        $response = $this->get('/bundle/'.$bundle->slug.'/download');

        $response->assertForbidden();
        $this->assertEmpty($this->loggedMessages, 'Expected client errors to remain out of the log.');
    }

    private function hasLoggedErrorContaining(string $needle): bool
    {
        foreach ($this->loggedMessages as $event) {
            if ($event->level === 'error' && str_contains($event->message, $needle)) {
                return true;
            }
        }

        return false;
    }
}

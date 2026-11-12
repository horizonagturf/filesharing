<?php

namespace Tests\Unit;

use App\Helpers\Upload;
use Tests\TestCase;

class UploadFileMaxSizeTest extends TestCase
{
    private string|false $originalMemoryLimit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalMemoryLimit = ini_get('memory_limit');
    }

    protected function tearDown(): void
    {
        if ($this->originalMemoryLimit !== false) {
            ini_set('memory_limit', $this->originalMemoryLimit);
        }

        parent::tearDown();
    }

    public function test_unlimited_memory_limit_is_ignored(): void
    {
        ini_set('memory_limit', '-1');
        config(['sharing.max_filesize' => '5M']);

        $max = (int) Upload::fileMaxSize();

        $this->assertGreaterThan(0, $max);
        $this->assertLessThanOrEqual(5 * 1024 * 1024, $max);
    }

    public function test_smallest_positive_limit_wins(): void
    {
        ini_set('memory_limit', '2G');
        config(['sharing.max_filesize' => '1K']);

        $this->assertSame(1024, (int) Upload::fileMaxSize());
    }
}

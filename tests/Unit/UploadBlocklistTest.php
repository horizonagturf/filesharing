<?php

namespace Tests\Unit;

use App\Helpers\Upload;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadBlocklistTest extends TestCase
{
    public function test_parse_extension_list_normalizes_values(): void
    {
        $this->assertSame(
            ['exe', 'bat', 'ps1'],
            Upload::parseExtensionList(' .EXE, bat,.ps1, bat ')
        );
    }

    public function test_parse_extension_list_returns_empty_for_blank_value(): void
    {
        $this->assertSame([], Upload::parseExtensionList(''));
    }

    #[DataProvider('blockedFilenameProvider')]
    public function test_is_blocked_filename(string $filename, bool $expected): void
    {
        $blocked = ['exe', 'bat', 'ps1'];

        $this->assertSame($expected, Upload::isBlockedFilename($filename, $blocked));
    }

    public static function blockedFilenameProvider(): array
    {
        return [
            'exe extension' => ['malware.exe', true],
            'uppercase extension' => ['MALWARE.EXE', true],
            'hidden extension in chain' => ['evil.exe.pdf', true],
            'allowed extension' => ['document.txt', false],
            'extensionless file' => ['README', false],
        ];
    }

    public function test_empty_blocklist_allows_all_filenames(): void
    {
        $this->assertFalse(Upload::isBlockedFilename('malware.exe', []));
    }

    public function test_filename_extensions_returns_all_segments(): void
    {
        $this->assertSame(['exe', 'pdf'], Upload::filenameExtensions('evil.exe.pdf'));
        $this->assertSame([], Upload::filenameExtensions('README'));
    }
}

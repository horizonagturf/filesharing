<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Helpers\Upload;
use App\Http\Resources\BundleResource;
use App\Models\Bundle;
use App\Models\File;
use App\Services\Audit;
use App\Services\BundlePasswordAccess;
use App\Services\ImageThumbnailService;
use App\Services\RecipientAccess;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BundleController extends Controller
{
    public function __construct(
        private readonly ImageThumbnailService $thumbnailService,
    ) {}

    // The bundle content preview
    public function previewBundle(Request $request, Bundle $bundle)
    {
        Audit::log(AuditEvent::BundlePreviewed, [
            'bundle' => $bundle,
            'recipient_email' => RecipientAccess::emailFor($bundle),
        ]);

        $request->attributes->set('bundle_guest_preview', true);

        return view('download', [
            'bundle' => new BundleResource($bundle),
        ]);

    }

    public function unlock(Request $request, Bundle $bundle)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (! BundlePasswordAccess::unlock($bundle, (string) $request->input('password'))) {
            return response()->json([
                'result' => false,
                'message' => __('app.bundle-password-incorrect'),
            ], 422);
        }

        return response()->json([
            'result' => true,
            'password_unlocked' => true,
        ]);
    }

    public function thumbnail(Request $request, Bundle $bundle, File $file): Response
    {
        abort_unless($file->bundle_id === $bundle->id, 404);

        return $this->thumbnailService->streamResponse($file);
    }

    public function downloadFile(Request $request, Bundle $bundle, File $file): StreamedResponse|RedirectResponse|Response
    {
        abort_unless($file->bundle_id === $bundle->id, 404);

        if (($bundle->max_downloads ?? 0) > 0) {
            abort(403);
        }

        $locked = $this->ensurePasswordUnlocked($request, $bundle);
        if ($locked !== null) {
            return $locked;
        }

        $path = config('filesystems.disks.uploads.root').'/'.$file->fullpath;
        if (! file_exists($path)) {
            abort(404);
        }

        return $this->streamDownload(
            $bundle,
            $path,
            $file->original,
            AuditEvent::FileDownloaded,
            $file,
            countDownload: false,
        );
    }

    // The download method — full zip (or single unprotected zip passthrough)
    public function downloadZip(Request $request, Bundle $bundle): StreamedResponse|RedirectResponse|Response
    {
        $locked = $this->ensurePasswordUnlocked($request, $bundle);
        if ($locked !== null) {
            return $locked;
        }

        try {
            $passthrough = $this->passthroughSingleZipFile($bundle);
            if ($passthrough !== null) {
                $path = config('filesystems.disks.uploads.root').'/'.$passthrough->fullpath;
                if (! file_exists($path)) {
                    throw new Exception('Cannot find uploaded file');
                }

                return $this->streamDownload($bundle, $path, $passthrough->original);
            }

            // Download of the full bundle
            // We must create a Zip archive
            $filename = Storage::disk('uploads')->path('').'/'.$bundle->slug.'/bundle.zip';
            if (! file_exists($filename)) {
                $bundlezip = fopen($filename, 'w');

                // Creating the archive
                $zip = new ZipArchive;
                if (! @$zip->open($filename, ZipArchive::CREATE)) {
                    throw new Exception('Cannot initialize Zip archive');
                }

                // Setting password when required
                if (! empty($bundle->password)) {
                    $zip->setPassword($bundle->password);
                }

                // Adding the files into the Zip with their real names
                foreach ($bundle->files as $file) {
                    if (file_exists(config('filesystems.disks.uploads.root').'/'.$file->fullpath)) {
                        $name = $file->original;

                        // If a file in the archive has the same name
                        if ($zip->locateName($name) !== false) {
                            $i = 0;

                            // Exploding the basename and extension
                            $basename = (strrpos($name, '.') === false) ? $name : substr($name, 0, strrpos($name, '.'));
                            $extension = (strrpos($name, '.') === false) ? null : substr($name, strrpos($name, '.'));

                            // Looping to find the right name
                            do {
                                $i++;
                                $newname = $basename.'-'.$i.$extension;
                            } while ($zip->locateName($newname));

                            // Final name was found
                            $name = $newname;
                        }
                        // Finally adding files
                        $zip->addFile(config('filesystems.disks.uploads.root').'/'.$file->fullpath, $name);

                        if (! empty($bundle->password)) {
                            $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
                        }
                    }
                }

                if (! @$zip->close()) {
                    throw new Exception('Cannot close Zip archive');
                }

                fclose($bundlezip);
            }

            return $this->streamDownload(
                $bundle,
                $filename,
                Str::slug($bundle->title).'-'.time().'.zip'
            );
        }

        // Could not find the metadata file
        catch (Exception $e) {
            report($e);
            abort(500);
        }

    }

    /**
     * When a bundle contains a single unprotected .zip, serve it without rewrapping.
     */
    private function passthroughSingleZipFile(Bundle $bundle): ?File
    {
        if (! empty($bundle->password)) {
            return null;
        }

        $files = $bundle->files;
        if ($files->count() !== 1) {
            return null;
        }

        $file = $files->first();
        if (! str_ends_with(strtolower((string) $file->original), '.zip')) {
            return null;
        }

        return $file;
    }

    private function ensurePasswordUnlocked(Request $request, Bundle $bundle): RedirectResponse|Response|null
    {
        if (BundlePasswordAccess::isUnlocked($bundle)) {
            return null;
        }

        if ($request->expectsJson() || $request->ajax()) {
            abort(403, __('app.bundle-password-required'));
        }

        $params = ['bundle' => $bundle];
        if ($request->query('auth')) {
            $params['auth'] = $request->query('auth');
        }

        return redirect()->route('bundle.preview', $params);
    }

    private function streamDownload(
        Bundle $bundle,
        string $path,
        string $downloadName,
        AuditEvent $event = AuditEvent::BundleZipDownloaded,
        ?File $file = null,
        bool $countDownload = true,
    ): StreamedResponse {
        $filesize = filesize($path);

        if ($countDownload) {
            $query = Bundle::query()->whereKey($bundle->id);
            if (($bundle->max_downloads ?? 0) > 0) {
                $query->where('downloads', '<', $bundle->max_downloads);
            }

            $updated = $query->increment('downloads');
            if ($updated === 0 && ($bundle->max_downloads ?? 0) > 0) {
                abort(response()->view('bundle.unavailable', [
                    'reason' => 'max_downloads',
                ], 410));
            }

            $bundle->refresh();
        }

        Audit::log($event, [
            'bundle' => $bundle,
            'file' => $file,
            'recipient_email' => RecipientAccess::emailFor($bundle),
        ]);

        $safeName = str_replace(['/', '\\'], '_', $downloadName);
        $fallback = str_replace(['/', '\\'], '_', Str::ascii($safeName) ?: 'download.bin');

        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $safeName,
                $fallback
            ),
            'Cache-Control' => 'no-cache, must-revalidate',
            'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
            'Content-Length' => (string) $filesize,
        ];

        return response()->stream(function () use ($path) {
            if (config('sharing.download_limit_rate', false) !== false) {
                $limit_rate = Upload::humanReadableToBytes(config('sharing.download_limit_rate'));

                $fh = fopen($path, 'rb');
                while (! feof($fh)) {
                    echo fread($fh, round($limit_rate));
                    flush();
                    sleep(1);
                }
                fclose($fh);

                return;
            }

            readfile($path);
        }, 200, $headers);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Bundle;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeFiles extends Command
{
	protected $signature = 'fs:bundle:purge';

	protected $description = 'Purge expired uploaded files from the storage disk';

	public function handle()
	{
		try {
			$bundles = Bundle::all();

			if ($bundles->isEmpty()) {
				$this->line('No bundle was found');
				return;
			}

			foreach ($bundles as $bundle) {
				$this->line('');
				$this->line('Found bundle: '.$bundle->slug);

				if (empty($bundle->expires_at)) {
					$this->comment('-> bundle has no expiry date, skipping');
					continue;
				}

				$expiresAt = $bundle->expires_at instanceof Carbon
					? $bundle->expires_at
					: Carbon::parse($bundle->expires_at);

				if ($expiresAt->isFuture()) {
					$this->info('-> bundle is still valid (expiration date: '.$expiresAt->format('Y-m-d H:i:s').')');
					continue;
				}

				$this->comment('-> bundle has expired, must be removed');

				foreach ($bundle->files as $file) {
					$file->forceDelete();
				}

				if (Storage::disk('uploads')->deleteDirectory($bundle->slug)) {
					$this->info('-> upload directory deleted');
				}
				else {
					$this->error('-> upload directory could not be deleted');
				}

				$bundle->forceDelete();
				$this->info('-> bundle was properly deleted');
			}
		}
		catch (Exception $e) {
			$this->error($e->getMessage());
		}
	}
}

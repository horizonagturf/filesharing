<?php

namespace App\Http\Controllers;

use App\Services\HelpContent;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function __construct(
        private readonly HelpContent $help,
    ) {}

    public function index(): View
    {
        return view('help.index', [
            'topics' => $this->help->topics(),
        ]);
    }

    public function show(string $topic): View
    {
        abort_unless($this->help->exists($topic), 404);

        return view('help.show', [
            'topic' => $topic,
            'topics' => $this->help->topics(),
            'title' => $this->help->title($topic),
            'body' => $this->help->body($topic),
        ]);
    }
}

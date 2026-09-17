<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Public site shell (marketing pages + marketplace).
 *
 * Renders the dark Cover & Keys marketing theme with the shared header,
 * search pill and footer. Titles/descriptions feed the SEO tags.
 */
class PublicLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public bool $showSearch = true,
    ) {}

    public function render(): View
    {
        return view('layouts.public');
    }
}
<?php

namespace App\View\Components;

use App\Support\Seo;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Public site shell (marketing pages + marketplace).
 *
 * Renders the dark Cover & Keys marketing theme with the shared header,
 * search pill and footer. Title, description, image, canonical, breadcrumbs
 * and schema nodes feed the SEO head (meta, Open Graph, Twitter, JSON-LD).
 */
class PublicLayout extends Component
{
    /**
     * @param  array<string, string|null>  $breadcrumbs  label => url; the last (current) entry may be null
     * @param  array<int, array>  $schema  extra schema.org nodes (see App\Support\Seo)
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public bool $showSearch = true,
        public ?string $image = null,
        public ?string $canonical = null,
        public array $breadcrumbs = [],
        public array $schema = [],
        public bool $noindex = false,
    ) {}

    /** The JSON-LD graph: organization + page nodes + breadcrumb list. */
    public function jsonLd(): ?string
    {
        $graph = $this->schema;

        if ($this->breadcrumbs !== []) {
            $graph[] = Seo::breadcrumbs(['Home' => route('home')] + $this->breadcrumbs);
        }

        if ($graph === []) {
            return null;
        }

        array_unshift($graph, Seo::organization());

        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );
    }

    public function render(): View
    {
        return view('layouts.public');
    }
}

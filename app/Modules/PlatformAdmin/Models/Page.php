<?php

namespace App\Modules\PlatformAdmin\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** A CMS page (about, terms, privacy, help …) written in Markdown, served at /pages/{slug}. */
#[Fillable(['slug', 'title', 'meta_description', 'body', 'is_published', 'in_footer', 'updated_by'])]
class Page extends Model
{
    protected $table = 'cms_pages';

    protected $attributes = ['is_published' => false, 'in_footer' => false];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'in_footer' => 'boolean'];
    }

    /** Markdown → HTML with raw HTML stripped and unsafe links dropped. */
    public function html(): string
    {
        return Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}

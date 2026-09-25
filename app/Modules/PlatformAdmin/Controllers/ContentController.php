<?php

namespace App\Modules\PlatformAdmin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ModulePlan;
use App\Modules\PlatformAdmin\Models\Page;
use App\Modules\PlatformAdmin\Models\Setting;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Super Admin (Phase 28): CMS pages, module pricing and platform settings. */
class ContentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function pages()
    {
        return view('platform-admin::pages.index', ['pages' => Page::query()->orderBy('title')->get()]);
    }

    public function editPage(?Page $page = null)
    {
        return view('platform-admin::pages.form', ['page' => $page ?? new Page]);
    }

    public function savePage(Request $request, ?Page $page = null)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash', 'max:80', Rule::unique('cms_pages', 'slug')->ignore($page?->id)],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:100000'],
            'is_published' => ['boolean'],
            'in_footer' => ['boolean'],
        ]);

        $page ??= new Page;
        $page->fill($data + ['is_published' => false, 'in_footer' => false, 'updated_by' => $request->user()->id])->save();
        $this->audit->log('platform.page.saved', $page, null, ['slug' => $page->slug, 'published' => $page->is_published]);

        return redirect()->route('admin.pages.index')->with('success', 'Page "'.$page->title.'" saved.');
    }

    public function deletePage(Page $page)
    {
        $page->delete();
        $this->audit->log('platform.page.deleted', $page, null, ['slug' => $page->slug]);

        return back()->with('success', 'Page deleted.');
    }

    public function pricing()
    {
        return view('platform-admin::pricing', [
            'plans' => ModulePlan::query()->with('module')->get()->sortBy(fn ($p) => [$p->module->sort_order, $p->billing_interval])->values(),
        ]);
    }

    public function savePricing(Request $request)
    {
        $data = $request->validate([
            'plans' => ['required', 'array'],
            'plans.*.price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'plans.*.is_active' => ['nullable', 'boolean'],
        ]);

        foreach ($data['plans'] as $id => $row) {
            $plan = ModulePlan::query()->find($id);
            if (! $plan) {
                continue;
            }
            $old = $plan->only(['price_cents', 'is_active']);
            $plan->update(['price_cents' => (int) round($row['price'] * 100), 'is_active' => (bool) ($row['is_active'] ?? false)]);
            if ($plan->wasChanged()) {
                $this->audit->log('platform.pricing.updated', $plan, $old, $plan->only(['price_cents', 'is_active']));
            }
        }

        return back()->with('success', 'Pricing saved. New prices apply from each business\'s next invoice.');
    }

    public function settings()
    {
        return view('platform-admin::settings', ['values' => collect(Setting::KEYS)->map(fn ($meta, $key) => Setting::get($key))]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate(collect(Setting::KEYS)->map(fn ($meta) => $meta[1])->all());

        Setting::put($data);
        $this->audit->log('platform.settings.updated', null, null, array_keys($data));

        return back()->with('success', 'Settings saved.');
    }

    /** Public: /pages/{slug}. Drafts are 404 except to Super Admins previewing. */
    public function show(Request $request, string $slug)
    {
        $page = Page::query()->where('slug', $slug)->firstOrFail();
        abort_unless($page->is_published || $request->user()?->isPlatformAdmin(), 404);

        return view('platform-admin::pages.show', ['page' => $page]);
    }
}

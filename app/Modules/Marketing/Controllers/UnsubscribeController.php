<?php

namespace App\Modules\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Crm\Models\Contact;
use App\Modules\Crm\Services\CrmService;
use App\Support\TenantContext;
use Illuminate\Http\Request;

/** One-click opt-out from marketing emails (signed link; no login needed). */
class UnsubscribeController extends Controller
{
    public function __invoke(Request $request, string $tenant, string $contact)
    {
        abort_unless($request->hasValidSignature(), 403);

        $tenant = Tenant::query()->findOrFail($tenant);
        $context = app(TenantContext::class);
        $context->set($tenant);

        $contact = Contact::query()->findOrFail($contact);
        app(CrmService::class)->setConsent($contact, false);
        $context->forget();

        return response()->view('marketing::unsubscribed', ['business' => $tenant->name]);
    }
}

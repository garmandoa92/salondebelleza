<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function checkout(Request $request)
    {
        $request->validate([
            'tenant_id' => ['required', 'string'],
            'plan_slug' => ['required', 'string', 'exists:plans,slug'],
        ]);

        $plan = Plan::where('slug', $request->plan_slug)->firstOrFail();
        $tenant = Tenant::findOrFail($request->tenant_id);

        // Stripe Checkout session would be created here
        // For now, simulate activation
        if (! $plan->stripe_price_id) {
            // No Stripe configured - activate directly for development
            $tenant->update([
                'plan_id' => $plan->id,
                'trial_ends_at' => null, // No longer on trial
                'settings' => array_merge($tenant->settings ?? [], [
                    'subscription_active' => true,
                    'subscription_plan' => $plan->slug,
                    'subscription_started_at' => now()->toIso8601String(),
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plan activado (modo desarrollo sin Stripe).',
            ]);
        }

        // With Stripe configured:
        // $session = $tenant->newSubscription('default', $plan->stripe_price_id)
        //     ->checkout([
        //         'success_url' => url("/salon/{$tenant->id}/dashboard?billing=success"),
        //         'cancel_url' => url("/salon/{$tenant->id}/upgrade"),
        //     ]);
        // return response()->json(['checkout_url' => $session->url]);

        return response()->json(['message' => 'Stripe no configurado.'], 400);
    }

    public function portal(Request $request)
    {
        // Stripe Customer Portal
        // $url = $request->user()->billingPortalUrl(url("/salon/{tenant_id}/settings"));
        // return redirect($url);

        return back()->with('info', 'Portal de Stripe no configurado aun.');
    }
}

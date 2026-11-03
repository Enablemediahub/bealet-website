<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $isApiRequest = $request->routeIs('newsletter.api') || $request->expectsJson();
        $validator = Validator::make($request->all(), ['email' => ['required', 'email', 'max:255']]);
        if ($validator->fails()) {
            if ($isApiRequest) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()->all(),
                ], 422);
            }

            $validator->validate();
        }
        $data = $validator->validated();
        $email = Str::lower(trim($data['email']));
        $subscriber = NewsletterSubscriber::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($subscriber?->is_active) {
            if ($isApiRequest) {
                return response()->json(['success' => true, 'message' => 'You are already subscribed.']);
            }

            return back()->with('newsletter_status', 'You are already subscribed.');
        }

        if ($subscriber) {
            $subscriber->forceFill([
                'email' => $email,
                'is_active' => true,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'unsubscribe_token' => $subscriber->unsubscribe_token ?: Str::random(48),
                'ip_address' => $request->ip(),
            ])->save();
        } else {
            NewsletterSubscriber::create([
                'email' => $email,
                'ip_address' => $request->ip(),
                'is_active' => true,
                'unsubscribe_token' => Str::random(48),
                'subscribed_at' => now(),
            ]);
        }

        if ($isApiRequest) {
            return response()->json(['success' => true, 'message' => 'Successfully subscribed to the newsletter.']);
        }

        return back()->with('newsletter_status', 'Successfully subscribed to the newsletter.');
    }

    public function unsubscribe(string $token): View
    {
        $subscriber = NewsletterSubscriber::query()->where('unsubscribe_token', $token)->first();
        if (! $subscriber) {
            abort(404);
        }

        return view('newsletter.unsubscribe', compact('subscriber'));
    }

    public function confirmUnsubscribe(string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::query()->where('unsubscribe_token', $token)->firstOrFail();
        $subscriber->forceFill([
            'is_active' => false,
            'unsubscribed_at' => now(),
        ])->save();

        return redirect()->route('home')->with('status', 'You have been unsubscribed.');
    }
}

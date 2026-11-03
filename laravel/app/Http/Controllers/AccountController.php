<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);
        $guestSessionId = (string) $request->session()->getId();
        $key = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 8)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Please wait a minute and try again.',
            ]);
        }

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match an active account.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->mergeGuestCart((int) $request->user()->id, $guestSessionId);

        return redirect()->intended(route('account.index'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $guestSessionId = (string) $request->session()->getId();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+?233|0)?[0-9\s()-]{9,14}$/'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);
        $phoneDigits = preg_replace('/\D+/', '', $data['phone']);
        $phone = str_starts_with($phoneDigits, '233')
            ? '+'.$phoneDigits
            : (str_starts_with($phoneDigits, '0') ? '+233'.substr($phoneDigits, 1) : '+233'.$phoneDigits);

        $user = DB::transaction(fn (): User => User::create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'phone' => $phone,
            'password_hash' => Hash::make($data['password']),
            'is_admin' => false,
            'admin_role' => 'customer',
            'is_active' => true,
        ]));

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        $this->mergeGuestCart((int) $user->id, $guestSessionId);

        return redirect()->intended(route('account.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $appointments = $user->appointments()
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->limit(10)
            ->get();

        return view('account.index', compact('user', 'appointments'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+?233|0)?[0-9\s()-]{9,14}$/'],
        ]);
        $phoneDigits = preg_replace('/\D+/', '', $data['phone']);
        $phone = str_starts_with($phoneDigits, '233')
            ? '+'.$phoneDigits
            : (str_starts_with($phoneDigits, '0') ? '+233'.substr($phoneDigits, 1) : '+233'.$phoneDigits);

        $request->user()->forceFill([
            'name' => $data['name'],
            'phone' => $phone,
        ])->save();

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $request->user()->forceFill([
            'password_hash' => Hash::make($data['password']),
        ])->save();

        return back()->with('status', 'Password updated.');
    }

    public function orders(Request $request): View
    {
        $orders = $request->user()->orders()->withCount('items')->latest()->paginate(12);

        return view('account.orders', compact('orders'));
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $status = Password::sendResetLink(['email' => Str::lower($data['email'])]);

        return back()->with('status', __($status));
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $status = Password::reset(
            [
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (User $user) use ($data): void {
                $user->forceFill([
                    'password_hash' => Hash::make($data['password']),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return redirect()->route('login')->with('status', __($status));
    }

    public function showOrder(Request $request, Order $order): View
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        $order->load(['items', 'prescriptions']);

        return view('account.order-show', compact('order'));
    }

    private function mergeGuestCart(int $userId, string $sessionId): void
    {
        DB::transaction(function () use ($userId, $sessionId): void {
            $guestItems = DB::table('cart')
                ->where('session_id', $sessionId)
                ->whereNull('user_id')
                ->lockForUpdate()
                ->get();

            foreach ($guestItems as $guestItem) {
                $userItem = DB::table('cart')
                    ->where('user_id', $userId)
                    ->where('product_id', $guestItem->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($userItem) {
                    DB::table('cart')->where('id', $userItem->id)->update([
                        'quantity' => min(99, (int) $userItem->quantity + (int) $guestItem->quantity),
                        'updated_at' => now(),
                    ]);
                    DB::table('cart')->where('id', $guestItem->id)->delete();
                } else {
                    DB::table('cart')->where('id', $guestItem->id)->update([
                        'user_id' => $userId,
                        'session_id' => null,
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}

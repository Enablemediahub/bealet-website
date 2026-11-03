<?php

namespace App\Http\Controllers;

use App\Mail\PlainTextNotification;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AppointmentController extends Controller
{
    private const TIME_SLOTS = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];

    public function create(): View
    {
        return view('appointments.create', [
            'timeSlots' => self::TIME_SLOTS,
            'appointmentTypes' => [
                'eye_exam' => 'Eye exam',
                'frame_fitting' => 'Frame fitting',
                'consultation' => 'Consultation',
                'follow_up' => 'Follow-up',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(?:\\+?233|0)?[0-9\\s()-]{9,14}$/'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(30)->toDateString()],
            'appointment_time' => ['required', 'in:'.implode(',', self::TIME_SLOTS)],
            'appointment_type' => ['required', 'in:eye_exam,frame_fitting,consultation,follow_up'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $date = Carbon::parse($data['appointment_date']);
        if ($date->isSunday()) {
            throw ValidationException::withMessages([
                'appointment_date' => 'We are closed on Sundays. Please choose another date.',
            ]);
        }

        $appointment = DB::transaction(function () use ($data, $request): Appointment {
            $slotTime = $data['appointment_time'].':00';
            DB::table('appointment_slot_locks')->insertOrIgnore([
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $slotTime,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('appointment_slot_locks')
                ->where('appointment_date', $data['appointment_date'])
                ->where('appointment_time', $slotTime)
                ->lockForUpdate()
                ->first();

            $alreadyBooked = Appointment::query()
                ->whereDate('appointment_date', $data['appointment_date'])
                ->whereTime('appointment_time', $slotTime)
                ->whereIn('status', ['pending', 'confirmed'])
                ->exists();
            if ($alreadyBooked) {
                throw ValidationException::withMessages([
                    'appointment_time' => 'That time has already been requested. Please choose another slot.',
                ]);
            }

            return Appointment::create([
                'user_id' => $request->user()?->id,
                'name' => trim($data['name']),
                'email' => mb_strtolower($data['email']),
                'phone' => trim($data['phone']),
                'appointment_date' => $data['appointment_date'],
                'appointment_time' => $slotTime,
                'appointment_type' => $data['appointment_type'],
                'notes' => trim((string) ($data['notes'] ?? '')),
                'status' => 'pending',
            ]);
        });

        $this->sendConfirmation($appointment);

        return redirect()->route('appointments.create')
            ->with('status', 'Your appointment request is saved. We will confirm it with you shortly.');
    }

    public function index(Request $request): View
    {
        $appointments = $request->user()->appointments()
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->paginate(12);

        return view('appointments.index', compact('appointments'));
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless((int) $appointment->user_id === (int) $request->user()->id, 404);
        if ($appointment->status !== 'pending') {
            return back()->withErrors(['appointment' => 'Only pending appointments can be cancelled.']);
        }

        $appointment->update(['status' => 'cancelled']);

        return back()->with('status', 'Appointment cancelled.');
    }

    private function sendConfirmation(Appointment $appointment): void
    {
        $appointmentTime = Carbon::parse($appointment->appointment_time)->format('g:i A');
        $date = $appointment->appointment_date->format('F j, Y');
        $details = "Appointment request: {$appointment->appointment_type}, {$date} at {$appointmentTime}.";
        $this->sendMail(
            $appointment->email,
            'Appointment request received',
            "Hello {$appointment->name},\n\nWe received your {$details} Our team will confirm your booking shortly.\n\nBealet Optical Center",
            $appointment
        );

        $adminEmail = (string) config('services.contact_notification_email');
        if ($adminEmail !== '') {
            $this->sendMail(
                $adminEmail,
                'New appointment request',
                "A new appointment was requested by {$appointment->name} ({$appointment->email}, {$appointment->phone}).\n\n{$details}\n\nNotes: {$appointment->notes}",
                $appointment
            );
        }
    }

    private function sendMail(string $recipient, string $subject, string $body, Appointment $appointment): void
    {
        try {
            Mail::to($recipient)->send(new PlainTextNotification($subject, $body));
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Appointment notification email could not be delivered.', [
                'appointment_id' => $appointment->id,
                'recipient' => $recipient,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

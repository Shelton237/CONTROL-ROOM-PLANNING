<?php

namespace App\Support;

use App\Mail\WelcomeAgentMail;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Crée le compte de connexion (rôle agent) d'un employé qui a un e-mail, et
 * lui envoie ses identifiants par e-mail. Best-effort : un échec d'envoi
 * n'empêche jamais la création de l'employé ni du compte.
 */
class EmployeeAccountService
{
    /**
     * @return array{created: bool, email_sent: bool, password: ?string, reason: ?string}
     */
    public function createAccountAndNotify(Employee $employee): array
    {
        if (empty($employee->email)) {
            return ['created' => false, 'email_sent' => false, 'password' => null, 'reason' => 'no_email'];
        }

        if (User::where('email', $employee->email)->exists()) {
            return ['created' => false, 'email_sent' => false, 'password' => null, 'reason' => 'email_already_used'];
        }

        $password = Str::password(12);

        $user = User::create([
            'name' => $employee->name,
            'email' => $employee->email,
            'password' => $password,
            'role' => 'agent',
            'employee_id' => $employee->id,
        ]);

        $emailSent = false;
        try {
            $loginUrl = rtrim(config('app.url'), '/').'/login';
            Mail::to($user->email)->send(new WelcomeAgentMail($employee->name, $user->email, $password, $loginUrl));
            $emailSent = true;
        } catch (Throwable $e) {
            // best-effort : le compte existe même si l'e-mail échoue (ex. SMTP indisponible).
            Log::warning('Échec envoi e-mail de bienvenue agent', [
                'employee_id' => $employee->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        return ['created' => true, 'email_sent' => $emailSent, 'password' => $password, 'reason' => null];
    }
}

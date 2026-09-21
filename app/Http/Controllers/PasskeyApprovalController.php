<?php

namespace App\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Session\Store as SessionStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Support\WebAuthn;

class PasskeyApprovalController extends Controller
{
    /**
     * Genera el desafío WebAuthn para verificar la identidad del usuario
     * antes de una aprobación, restringido a las passkeys del usuario autenticado.
     */
    public function options(): JsonResponse
    {
        $user = Auth::guard(Config::string('passkeys.guard'))->user()
            ?? throw new AuthenticationException;

        $generate = app(GenerateVerificationOptions::class);

        $options = $generate($user);

        $request = request();
        $request->session()->put('passkey.verification_options', WebAuthn::toJson($options));

        return response()->json([
            'options' => WebAuthn::toBrowserArray($options),
        ]);
    }

    /**
     * Verifica la passkey y registra en sesión la evidencia para la aprobación,
     * sin iniciar sesión adicional ni redirigir.
     */
    public function verify(
        PasskeyVerificationRequest $request,
        VerifyPasskey $verify,
    ): JsonResponse {
        $user = Auth::guard(Config::string('passkeys.guard'))->user()
            ?? throw new AuthenticationException;

        try {
            $passkey = $verify(
                $request->credential(),
                $request->verificationOptions(),
                $user,
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'passkey' => __('La passkey no pudo verificarse. Intenta nuevamente.'),
            ]);
        }

        /** @var SessionStore $session */
        $session = $request->session();

        $session->put('approval_passkey_verified', true);
        $session->put('approval_passkey_verified_at', now());
        $session->put('approval_passkey_credential_id', $passkey->credential_id);

        return response()->json([
            'verified' => true,
            'credential_id' => $passkey->credential_id,
        ]);
    }
}

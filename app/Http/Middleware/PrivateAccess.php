<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the single-user application with the configured access password.
 */
class PrivateAccess
{
    /**
     * Allow configured public paths or validate the private access cookie.
     *
     * @param  Request  $request  The incoming request.
     * @param  Closure  $next  The next middleware callback.
     * @return Response The downstream response or access form.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $password = config('services.effemeral.access_password');

        if (! is_string($password) || $password === '') {
            if (config('app.env') === 'production') {
                return response('Private access is not configured.', 503);
            }

            return $next($request);
        }

        $username = (string) config('services.effemeral.access_username', 'effemeral');
        $providedPassword = (string) $request->getPassword();

        if ($request->getUser() !== $username || ! hash_equals($password, $providedPassword)) {
            return response('Authentication required.', 401, [
                'WWW-Authenticate' => 'Basic realm="Effemeral"',
            ]);
        }

        return $next($request);
    }
}

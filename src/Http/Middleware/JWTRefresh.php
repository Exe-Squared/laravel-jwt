<?php

namespace Clystnet\JWT\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class JWTRefresh
{
    protected $response;

    public function __construct()
    {
        //
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     * @throws TokenBlacklistedException
     */
    public function handle($request, \Closure $next)
    {
        $token = $this->authenticate($request);
        $response = $next($request);

        if ($token) {
            $response->header('Authorization', 'Bearer ' . $token);
        }

        return $response;
    }

    /**
     * Check the request for the presence of a token.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     *
     * @return void
     */
    public function checkForToken(Request $request)
    {
        if (!JWTAuth::parser()->setRequest($request)->hasToken()) {
            throw new UnauthorizedHttpException('jwt-auth', 'Token not provided');
        }
    }

    /**
     * Attempt to authenticate a user via the token in the request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return bool|void
     * @throws TokenBlacklistedException
     */
    public function authenticate(Request $request)
    {
        if (Auth::user()) {
            return false;
        }

        $this->checkForToken($request);

        try {
            if (!JWTAuth::parseToken()->authenticate()) {
                throw new UnauthorizedHttpException('jwt-auth', 'User not found');
            }
        } catch (TokenExpiredException $e) {
            // If the token is expired, then it will be refreshed and added to the headers
            try {
                return Auth::refresh();
            } catch (TokenExpiredException $e) {
                throw new UnauthorizedHttpException('jwt-auth', 'Refresh token has expired.');
            }
        } catch (TokenBlacklistedException $e) {
            throw new TokenBlacklistedException($e->getMessage(), 401);
        } catch (JWTException $e) {
            throw new UnauthorizedHttpException('jwt-auth', $e->getMessage(), $e, $e->getCode());
        }
    }
}

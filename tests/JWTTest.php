<?php
namespace Tests;

use Mockery as m;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Clystnet\JWT\Auth\Guards\JWTAuthGuard;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class JWTTest extends TestCase
{
    // https://github.com/laravel/framework/blob/5.5/tests/Auth/AuthGuardTest.php
    public function testRequireToken()
    {
        $jwt = $this->getTokenMockedJWT();
        $jwt->shouldReceive('getToken')
            ->once()
            ->andReturn(false);

        $guard = $this->getAuthGuard($jwt);
        $this->expectException(JWTException::class);
        $guard->requireToken();
    }

    public function testGetToken()
    {
        $jwt = $this->getTokenMockedJWT();
        $guard = $this->getAuthGuard($jwt);
        $this->assertEquals($guard->getToken(), 'token');
    }

    public function testGetCachedToken()
    {
        $jwt = $this->getTokenMockedJWT();

        Cache::shouldReceive('get')
            ->once()
            ->with(md5('token'))
            ->andReturn('cachedToken');

        $guard = $this->getAuthGuard($jwt);
        $this->assertEquals($guard->getCachedToken(), 'cachedToken');

        Cache::shouldReceive('put')
            ->once()
            ->with('key', 'newCachedToken', 'expiresAt')
            ->andReturn('newCachedToken');
        $guard->setCachedToken('key', 'newCachedToken', 'expiresAt');
        $this->assertEquals($guard->getCachedToken(), 'newCachedToken');
    }

    public function testRefresh()
    {
        $jwt = $this->getTokenMockedJWT();
        $guard = $this->getAuthGuard($jwt);

        Cache::shouldReceive('get')
            ->once()
            ->with(md5('token'))
            ->andReturn(null);
        Cache::shouldReceive('put')
            ->once()
            ->andReturn('refreshToken');

        Config::shouldReceive('get')
          ->with('laravel_jwt.cache_ttl')
          ->andReturn(60);

        JWTAuth::shouldReceive('refresh')
            ->once()
            ->andReturn('refreshToken');

        $this->assertEquals($guard->refresh(), 'refreshToken');
    }

    public function testUser()
    {
        $user = m::mock('Illuminate\Contracts\Auth\Authenticatable');
        $provider = m::mock('Illuminate\Contracts\Auth\UserProvider');
        $provider->shouldReceive('getModel')
            ->andReturn($user);
        $provider->shouldReceive('retrieveById')
            ->once()
            ->with('userId')
            ->andReturn(m::mock('Illuminate\Contracts\Auth\Authenticatable'));

        $jwt = $this->getTokenMockedJWT();
        $jwt->shouldReceive('check')
            ->once()
            ->with(true)
            ->andReturn(['sub' => 'userId']);
        $jwt->shouldReceive('checkProvider')
            ->once()
            ->with($user)
            ->andReturn(true);

        Cache::shouldReceive('get')
            ->once()
            ->with(md5('token'))
            ->andReturn(null);

        $guard = $this->getAuthGuard($jwt, $provider);
        $result = $guard->user();

        $this->assertTrue($result instanceof $user);
        $this->assertEquals($result, $user);
    }

    protected function getTokenMockedJWT()
    {
        $jwt = m::mock('PHPOpenSourceSaver\JWTAuth\JWT');
        $jwt->shouldReceive('parser')
            ->andReturn($jwt);
        $jwt->shouldReceive('parseToken')
            ->andReturn('token');
        $jwt->shouldReceive('setRequest')
            ->andReturn($jwt);
        $jwt->shouldReceive('getToken')
            ->andReturn('token');

        return $jwt;
    }

    protected function getAuthGuard($jwt = null, $provider = null)
    {
        return new JWTAuthGuard(
            $jwt ?: m::mock('PHPOpenSourceSaver\JWTAuth\JWT'),
            $provider ?: m::mock('Illuminate\Contracts\Auth\UserProvider'),
            \Illuminate\Http\Request::create('/', 'GET')
        );
    }
}

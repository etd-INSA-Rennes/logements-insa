<?php

namespace App\Http\Middleware;

use Closure;
use \ReflectionMethod;
use App\User;

class Owner
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $controller_name = explode('@', $request->route()->getAction()['uses'])[0];
        $controller = app($controller_name);

        $reflection_method = new ReflectionMethod($controller_name, 'getResource');
        $resource = $reflection_method->invokeArgs($controller, $request->route()->parameters());

        if (User::where('login', cas()->user())->first()->id != $resource->user_id && User::where('login', cas()->user())->first()->role != 'admin')
        {
            if ($request->ajax())
            {
                return response('Unauthorized.', 401);
            }
            else
            {
                return redirect('/home');
            }
        }

        $request->route()->setParameter($request->route()->parameterNames()[0], $resource);

        return $next($request);
    }
}

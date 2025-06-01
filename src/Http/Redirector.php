<?php

namespace Axcel\Http;

use Axcel\Http\Response\RedirectResponse;
use Axcel\Routing\Router;

class Redirector
{
    protected Request $request;
    protected Router $router;

    public function __construct(Request $request, Router $router)
    {
        $this->request = $request;
        $this->router = $router;
    }

    public function to(string $url, int $status = 302, array $headers = []): RedirectResponse
    {
        return new RedirectResponse($url, $status, $headers);
    }

    public function back(int $status = 302, array $headers = []): RedirectResponse
    {
        $referer = $this->request->server('HTTP_REFERER') ?? '/';
        return $this->to($referer, $status, $headers);
    }

    public function route(string $name, array $params = [], int $status = 302, array $headers = []): RedirectResponse
    {
        $url = $this->router->url($name, $params); // must be defined in your Router
        return $this->to($url, $status, $headers);
    }

    public function action(string $controllerMethod, array $params = [], int $status = 302, array $headers = []): RedirectResponse
    {
        $url = $this->router->action($controllerMethod, $params); // optional
        return $this->to($url, $status, $headers);
    }

    public function with(string $key, $value): RedirectResponse
    {
        return $this->back()->with($key, $value);
    }

    public function withErrors($errors, string $key = 'default'): RedirectResponse
    {
        return $this->back()->withErrors($errors, $key);
    }

    public function withInput(array $input = []): RedirectResponse
    {
        $input = $input ?: $this->request->all();
        return $this->back()->withInput($input);
    }
}

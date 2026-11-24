<?php

declare(strict_types=1);

namespace App\Http;

use App\Container;
use App\Models\User;
use App\Session\Session;
use App\Session\SessionManager;

trait InteractsWithSession
{
    private ?Session $currentSession = null;

    private bool $currentSessionLoaded = false;

    protected function sessions(): SessionManager
    {
        $manager = $this->service(SessionManager::class);

        return $manager;
    }

    protected function session(bool $refresh = false): ?Session
    {
        if ($this->currentSessionLoaded && !$refresh) {
            return $this->currentSession;
        }

        $this->currentSessionLoaded = true;
        $id = (string) $this->request->cookie($this->sessions()->cookieName(), '');

        $this->currentSession = $id !== '' ? $this->sessions()->find($id) : null;

        return $this->currentSession;
    }

    protected function sessionOrCreate(): Session
    {
        $session = $this->session();

        if ($session !== null) {
            return $session;
        }

        $this->currentSession = $this->sessions()->start(0);
        $this->currentSessionLoaded = true;

        return $this->currentSession;
    }

    protected function user(): ?User
    {
        $session = $this->session();

        if ($session === null || $session->userId <= 0) {
            return null;
        }

        $user = User::query()->find($session->userId);

        return $user;
    }

    protected function startSessionInternal(User $user, array $attributes = []): Session
    {
        $this->currentSession = $this->sessions()->start((int) $user->getKey(), $attributes);
        $this->currentSessionLoaded = true;

        return $this->currentSession;
    }

    protected function attachSession(Response $response, Session $session): Response
    {
        return $response->withCookie(
            $this->sessions()->cookieName(),
            $session->id,
            $session->expiresAt,
            '/',
            true,
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        );
    }

    protected function detachSession(Response $response): Response
    {
        return $response->withoutCookie($this->sessions()->cookieName());
    }

    protected function csrfToken(): string
    {
        return SessionManager::csrfToken($this->sessionOrCreate());
    }

    protected function csrfInput(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars($this->csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    protected function flashes(Session $session): array
    {
        [$session, $flashes] = $session->pullFlashes();
        $this->currentSession = $session;

        return [$flashes, $session];
    }

    protected function container(): Container
    {
        return Container::current() ?? Container::application();
    }
}

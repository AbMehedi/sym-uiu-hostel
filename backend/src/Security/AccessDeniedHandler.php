<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): RedirectResponse
    {
        $token = $this->tokenStorage->getToken();
        $roles = $token?->getRoleNames() ?? [];

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }

        if (in_array('ROLE_SUPERVISOR', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('supervisor_dashboard'));
        }

        if (in_array('ROLE_STUDENT', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('student_dashboard'));
        }

        // Not authenticated at all — redirect to login
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}

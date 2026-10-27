<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class DynamicAuthenticator extends AbstractAuthenticator
{
    private string $modeAuth;
    private UserRepository $userRepository;
    private CasUserProvider $casUserProvider;
    private ParameterBagInterface $parameterBag;
    private OidcClient $oidcClient;

    public function __construct(string $modeAuth, UserRepository $userRepository, CasUserProvider $casUserProvider, ParameterBagInterface $parameterBag, OidcClient $oidcClient)
    {
        $this->modeAuth = $modeAuth;
        $this->userRepository = $userRepository;
        $this->casUserProvider = $casUserProvider;
        $this->parameterBag = $parameterBag;
        $this->oidcClient = $oidcClient;
    }

    public function supports(Request $request): ?bool
    {
        // Retour du fournisseur OIDC : le code doit être échangé quelle que
        // soit l'URI de redirection déclarée, y compris /login.
        if ('OIDC' === $this->modeAuth
            && $request->query->has('code')
            && $request->query->has('state')
            && !$request->getSession()->get('_security_main')) {
            return true;
        }

        // Vérifie si l'utilisateur est déjà connecté
        if ($request->getSession()->get('_security_main')) {
            return false; // L'utilisateur est déjà authentifié
        }

        // Exclure les routes de login et logout pour éviter les boucles
        $currentPath = $request->getPathInfo();
        if (in_array($currentPath, ['/login', '/logout'])) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        switch ($this->modeAuth) {
            case 'SQL':
                return $this->authenticateWithSql($request);

            case 'CAS':
                return $this->authenticateWithCas($request);

            case 'OIDC':
                return $this->authenticateWithOidc($request);

            default:
                throw new \InvalidArgumentException('Invalid authentication method');
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // AuthenticatorInterface::authenticate() ne peut pas renvoyer de
        // Response : la redirection initiale vers le fournisseur passe par
        // cette exception.
        if ($exception instanceof OidcRedirectException) {
            return new RedirectResponse($exception->getAuthorizationUrl());
        }

        throw $exception;
    }

    private function authenticateWithSql(Request $request): Passport
    {
        $username = $request->request->get('_username', '');
        $password = $request->request->get('_password', '');

        if (!$username || !$password) {
            throw new AuthenticationException('Username and password are required.');
        }

        $user = $this->userRepository->findOneBy(['username' => $username]);

        if (!$user) {
            throw new AuthenticationException('User not found.');
        }

        return new Passport(
            new UserBadge($username, function ($userIdentifier) {
                return $this->userRepository->findOneBy(['username' => $userIdentifier]);
            }),
            new PasswordCredentials($password)
        );
    }

    private function authenticateWithCas(Request $request): Passport
    {
        // Récupérer l'hôte d'origine derrière le reverse proxy
        $host = $request->headers->get('host');
        $scheme = $request->headers->get('X-Forwarded-Proto') ?? $request->getScheme();

        // Construire l'URL
        $url = $scheme.'://'.$host;

        // \phpCAS::setDebug('/tmp/logcas.log');
        \phpCAS::client(
            CAS_VERSION_2_0,
            $this->parameterBag->get('casHost'),
            (int) $this->parameterBag->get('casPort'),
            $this->parameterBag->get('casPath'),
            $url,
            false);

        \phpCAS::setNoCasServerValidation();
        \phpCAS::forceAuthentication();

        $username = \phpCAS::getUser();
        $attributes = \phpCAS::getAttributes();

        if (!$username) {
            throw new AuthenticationException('CAS authentication failed.');
        }

        $userBadge = new UserBadge($username, function ($userIdentifier) use ($attributes) {
            return $this->casUserProvider->loadUserByIdentifierAndCASAttributes($userIdentifier, $attributes);
        });

        return new SelfValidatingPassport($userBadge);
    }

    private function authenticateWithOidc(Request $request): Passport
    {
        $session = $request->getSession();
        $code = $request->query->get('code');

        // Pas encore de code : on redirige vers le fournisseur d'identité.
        if (!\is_string($code) || '' === $code) {
            throw new OidcRedirectException($this->oidcClient->createAuthorizationUrlSymfonySession($session));
        }

        $sessionState = $session->get(OidcClient::SESSION_STATE_KEY);
        $requestState = $request->query->get('state');

        if (!\is_string($sessionState) || '' === $sessionState
            || !\is_string($requestState) || !\hash_equals($sessionState, $requestState)) {
            throw new AuthenticationException('OIDC authentication failed: invalid state.');
        }

        $session->remove(OidcClient::SESSION_STATE_KEY);

        // Échange le code contre les claims (vérification JWT/JWKS incluse).
        $attributes = $this->oidcClient->exchangeCode($code);

        $usernameAttribute = $this->parameterBag->get('oidcUsernameAttribute');
        $username = $attributes[$usernameAttribute] ?? null;

        if (!\is_string($username) || '' === $username) {
            throw new AuthenticationException(sprintf('OIDC authentication failed: "%s" claim is missing or empty.', $usernameAttribute));
        }

        $userBadge = new UserBadge($username, fn ($userIdentifier) => $this->casUserProvider->loadUserByIdentifierAndOIDCAttributes($userIdentifier, $attributes));

        return new SelfValidatingPassport($userBadge);
    }
}

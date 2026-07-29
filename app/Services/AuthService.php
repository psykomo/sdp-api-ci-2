<?php

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Models\ApiTokenModel;
use App\Models\UserModel;
use App\Models\UserOrganizationRoleModel;
use DomainException;
use InvalidArgumentException;

/**
 * Auth use cases: login and Bearer token validation.
 *
 * Token strategy (opaque hash today) can be swapped later without changing filters.
 */
class AuthService
{
    public function __construct(
        protected UserModel $users = new UserModel(),
        protected ApiTokenModel $tokens = new ApiTokenModel(),
        protected UserOrganizationRoleModel $userOrgRoles = new UserOrganizationRoleModel(),
        protected ?ConnectionResolver $resolver = null,
    ) {
        $this->resolver ??= service('connectionResolver');
    }

    /**
     * Authenticate credentials and issue a Bearer token.
     *
     * In multi topology, `$organizationCode` selects the tenant database first.
     *
     * @return array{
     *     token: string,
     *     expires_at: string|null,
     *     token_type: string,
     *     user: array<string, mixed>,
     *     organization_code?: string
     * }
     *
     * @throws DomainException when multi topology requires organization_code
     * @throws AuthenticationException when credentials are invalid or account inactive
     */
    public function login(string $email, string $password, ?string $organizationCode = null): array
    {
        $email    = strtolower(trim($email));
        $orgCode  = strtoupper(trim((string) $organizationCode));

        if ($email === '' || $password === '') {
            throw new DomainException('Email and password are required.');
        }

        if ($this->resolver->isMulti()) {
            if ($orgCode === '') {
                throw new DomainException('organization_code is required in multi database topology.');
            }

            try {
                $this->resolver->activateForOrgCode($orgCode);
            } catch (InvalidArgumentException $e) {
                throw new DomainException($e->getMessage(), 0, $e);
            }

            // Models must bind to the activated default connection.
            $this->users  = model(UserModel::class, false);
            $this->tokens = model(ApiTokenModel::class, false);
        }

        $user = $this->users->where('email', $email)->first();

        if ($user === null) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $userData = is_object($user) ? $user->toRawArray() : $user;

        if (($userData['status'] ?? '') !== 'active') {
            throw new AuthenticationException('Invalid credentials.');
        }

        $hash = $userData['password_hash'] ?? null;
        if ($hash === null || $hash === '' || ! password_verify($password, $hash)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $token = $this->issueToken((int) $userData['id']);
        unset($userData['password_hash']);

        $payload = [
            'token'      => $token['token'],
            'expires_at' => $token['expires_at'],
            'token_type' => 'Bearer',
            'user'       => $userData,
        ];

        if ($this->resolver->isMulti()) {
            $payload['organization_code'] = $orgCode;
        }

        return $payload;
    }

    /**
     * Validate Bearer token and return auth payload, or null if invalid.
     *
     * @return array{
     *     user: array<string, mixed>,
     *     allowed_org_ids: list<int>,
     *     org_roles: list<array<string, mixed>>
     * }|null
     */
    public function authenticateBearer(?string $authorizationHeader): ?array
    {
        if ($authorizationHeader === null || $authorizationHeader === '') {
            return null;
        }

        if (! str_starts_with($authorizationHeader, 'Bearer ')) {
            return null;
        }

        $plain = trim(substr($authorizationHeader, 7));
        if ($plain === '') {
            return null;
        }

        $tokenHash = hash('sha256', $plain);
        $tokenRow  = $this->tokens->where('token_hash', $tokenHash)
            ->where('revoked_at', null)
            ->first();

        if ($tokenRow === null) {
            return null;
        }

        if (! empty($tokenRow['expires_at']) && strtotime((string) $tokenRow['expires_at']) < time()) {
            return null;
        }

        $user = $this->users->find($tokenRow['user_id']);
        if ($user === null) {
            return null;
        }

        $userData = is_object($user) ? $user->toRawArray() : $user;
        if (($userData['status'] ?? '') !== 'active') {
            return null;
        }

        $orgRoles = $this->userOrgRoles->getAssignmentsForUser((int) $userData['id']);
        $allowed  = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['organization_id'],
            $orgRoles,
        )));

        // Throttle last_used_at writes (at most once per minute).
        $lastUsed = $tokenRow['last_used_at'] ?? null;
        if ($lastUsed === null || strtotime((string) $lastUsed) < (time() - 60)) {
            $this->tokens->update($tokenRow['id'], ['last_used_at' => date('Y-m-d H:i:s')]);
        }

        return [
            'user'            => $userData,
            'allowed_org_ids' => $allowed,
            'org_roles'       => $orgRoles,
        ];
    }

    /**
     * Issue a plain-text API token (store only the hash).
     *
     * @return array{token: string, expires_at: string|null}
     */
    public function issueToken(int $userId, ?string $name = 'api', ?int $ttlHours = 720): array
    {
        $plain     = bin2hex(random_bytes(32));
        $expiresAt = $ttlHours !== null
            ? date('Y-m-d H:i:s', time() + ($ttlHours * 3600))
            : null;

        $this->tokens->insert([
            'user_id'    => $userId,
            'name'       => $name,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token'      => $plain,
            'expires_at' => $expiresAt,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\ApiTokenModel;
use App\Models\UserModel;
use App\Models\UserOrganizationRoleModel;

/**
 * Thin auth facade. Token strategy can be swapped (JWT, Shield, SSO)
 * without changing filters or controllers.
 */
class AuthService
{
    public function __construct(
        protected UserModel $users = new UserModel(),
        protected ApiTokenModel $tokens = new ApiTokenModel(),
        protected UserOrganizationRoleModel $userOrgRoles = new UserOrganizationRoleModel(),
    ) {
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

        $this->tokens->update($tokenRow['id'], ['last_used_at' => date('Y-m-d H:i:s')]);

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

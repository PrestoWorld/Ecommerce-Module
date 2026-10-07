<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\Ecommerce\Storage\Common;

use PrestoWorld\Modules\Ecommerce\Contracts\AuthRepositoryInterface;
use PrestoWorld\Modules\Ecommerce\Storage\AbstractRepository;

final class AuthRepository extends AbstractRepository implements AuthRepositoryInterface
{
    public function findApplication(string $appId): ?array
    {
        $row = $this->db->select('*')->from($this->table('applications'))
            ->where('app_id', $appId)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function findToken(string $appId, string $businessId, string $token): ?array
    {
        $row = $this->db->select('*')->from($this->table('access_tokens'))
            ->where('app_id', $appId)
            ->where('business_id', $businessId)
            ->where('token', $token)
            ->run()->fetch();

        return $row === false ? null : $this->decoded($row);
    }

    public function saveToken(string $appId, string $businessId, string $token, int $expiresAt): void
    {
        $existing = $this->db->select('id')->from($this->table('access_tokens'))
            ->where('app_id', $appId)
            ->where('business_id', $businessId)
            ->run()->fetch();

        if ($existing === false) {
            $this->db->insert($this->table('access_tokens'))->values([
                'app_id' => $appId,
                'business_id' => $businessId,
                'token' => $token,
                'expires_at' => $expiresAt,
                'created_at' => time(),
                'last_used_at' => null,
            ])->run();

            return;
        }

        $existing = (array) $existing;
        $this->db->update($this->table('access_tokens'), [
            'token' => $token,
            'expires_at' => $expiresAt,
            'created_at' => time(),
        ], ['id' => (int) ($existing['id'] ?? 0)])->run();
    }

    public function touchToken(string $appId, string $businessId, int $now): void
    {
        $this->db->update($this->table('access_tokens'), [
            'last_used_at' => $now,
        ], [
            'app_id' => $appId,
            'business_id' => $businessId,
        ])->run();
    }

    public function saveApplication(string $appId, string $secretKey, string $name): void
    {
        $existing = $this->db->select('id')->from($this->table('applications'))
            ->where('app_id', $appId)
            ->run()->fetch();

        if ($existing === false) {
            $this->db->insert($this->table('applications'))->values([
                'app_id' => $appId,
                'secret_key' => $secretKey,
                'name' => $name,
                'status' => 1,
                'created_at' => time(),
            ])->run();

            return;
        }

        $existing = (array) $existing;
        $this->db->update($this->table('applications'), [
            'secret_key' => $secretKey,
            'name' => $name,
            'status' => 1,
        ], ['id' => (int) ($existing['id'] ?? 0)])->run();
    }
}
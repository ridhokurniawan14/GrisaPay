<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencatatan perubahan hak akses: peran milik pengguna, hak akses milik peran, serta
 * pembuatan/penghapusan peran. Yang dicatat hanya NAMA peran/hak akses (tidak pernah password).
 */
final class AccessAudit
{
    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @return array{0: list<string>, 1: list<string>} [ditambah, dicabut]
     */
    public static function diff(array $before, array $after): array
    {
        return [
            array_values(array_diff($after, $before)),
            array_values(array_diff($before, $after)),
        ];
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    public static function describeRoles(array $names): array
    {
        return array_map(fn(string $name): string => RoleName::tryFrom($name)?->label() ?? $name, $names);
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    public static function describePermissions(array $names): array
    {
        return array_map(fn(string $name): string => PermissionName::labelFor($name), $names);
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    public static function userRolesChanged(Model $user, array $before, array $after): void
    {
        [$added, $removed] = self::diff($before, $after);

        if ($added === [] && $removed === []) {
            return;
        }

        self::log('user', $user, 'peran-diubah', array_filter([
            'peran_ditambah' => self::describeRoles($added),
            'peran_dicabut' => self::describeRoles($removed),
        ]));
    }

    public static function passwordChanged(Model $user): void
    {
        self::log('user', $user, 'password-diubah', []);
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    public static function rolePermissionsChanged(Model $role, array $before, array $after): void
    {
        [$added, $removed] = self::diff($before, $after);

        if ($added === [] && $removed === []) {
            return;
        }

        self::log('role', $role, 'hak-akses-diubah', array_filter([
            'peran' => $role->name,
            'hak_ditambah' => self::describePermissions($added),
            'hak_dicabut' => self::describePermissions($removed),
        ]));
    }

    /** $description: "created" atau "deleted" (sama dengan kejadian model lain di log). */
    public static function roleEvent(Model $role, string $description): void
    {
        self::log('role', $role, $description, ['peran' => $role->name]);
    }

    /** @param array<string, mixed> $properties */
    private static function log(string $logName, Model $subject, string $description, array $properties): void
    {
        activity($logName)
            ->performedOn($subject)
            ->causedBy(auth()->user())
            ->withProperties($properties)
            ->log($description);
    }
}

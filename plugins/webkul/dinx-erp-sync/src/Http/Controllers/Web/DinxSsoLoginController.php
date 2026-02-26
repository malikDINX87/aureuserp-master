<?php

namespace Webkul\DinxErpSync\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;
use Webkul\DinxErpSync\Models\DinxSsoUserMapping;
use Webkul\DinxErpSync\Services\DinxSsoTicketVerifier;
use Webkul\Security\Models\Role;
use Webkul\Security\Models\User;

class DinxSsoLoginController extends BaseController
{
    public function __construct(protected DinxSsoTicketVerifier $ticketVerifier)
    {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        if (! $this->ticketVerifier->isEnabled()) {
            abort(404);
        }

        $verification = $this->ticketVerifier->verify($request->query('ticket'));

        if (! ($verification['valid'] ?? false)) {
            abort(401, (string) ($verification['message'] ?? 'Invalid SSO ticket.'));
        }

        $claims = (array) ($verification['claims'] ?? []);

        if (! $this->isAllowedRole($claims)) {
            abort(403, 'Your DINX account does not have ERP access.');
        }

        try {
            $user = $this->upsertUser($claims);
        } catch (Throwable $exception) {
            report($exception);

            abort(500, 'Failed to provision ERP user from SSO ticket.');
        }

        Auth::guard('web')->login($user, true);

        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    protected function isAllowedRole(array $claims): bool
    {
        return ((bool) ($claims['isGlobalAdmin'] ?? false)) || (($claims['crmRole'] ?? null) === 'Admin');
    }

    protected function upsertUser(array $claims): User
    {
        $dinxUserId = trim((string) ($claims['sub'] ?? ''));
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $name = trim((string) ($claims['name'] ?? $email));
        $roleName = config('filament-shield.panel_user.name', 'Admin');

        if ($dinxUserId === '' || $email === '') {
            throw new \InvalidArgumentException('DINX SSO claims are missing sub or email.');
        }

        return DB::transaction(function () use ($dinxUserId, $email, $name, $roleName, $claims) {
            $mapping = DinxSsoUserMapping::query()
                ->where('dinx_user_id', $dinxUserId)
                ->lockForUpdate()
                ->first();

            $user = null;

            if ($mapping?->user_id) {
                $user = User::withTrashed()->find($mapping->user_id);
            }

            if (! $user) {
                $user = User::withTrashed()
                    ->where('email', $email)
                    ->first();
            }

            if (! $user) {
                $user = new User;
                $user->email = $email;
                $user->password = Hash::make(Str::random(48));
                $user->resource_permission = 'global';
                $user->is_active = true;
                $user->email_verified_at = now();
            }

            if (method_exists($user, 'trashed') && $user->trashed()) {
                $user->restore();
            }

            $user->name = $name !== '' ? $name : $email;
            $user->email = $email;
            $user->is_active = true;

            if (! $user->resource_permission) {
                $user->resource_permission = 'global';
            }

            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
            }

            $user->save();

            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            if (! $user->hasRole($roleName)) {
                $user->assignRole($role);
            }

            if (! $mapping) {
                $mapping = new DinxSsoUserMapping;
                $mapping->dinx_user_id = $dinxUserId;
            }

            $mapping->user_id = $user->id;
            $mapping->email = $email;
            $mapping->last_role = ((bool) ($claims['isGlobalAdmin'] ?? false)) ? 'GlobalAdmin' : ((string) ($claims['crmRole'] ?? ''));
            $mapping->last_login_at = now();
            $mapping->save();

            return $user;
        });
    }
}

<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Core\Session;

class ProfileController {
    public function index(Request $request): void {
        $user   = $request->user();
        $tenant = $request->tenant();

        // Activity history for this user (last 20)
        $recentActivity = Database::fetchAll("
            SELECT action, entity_type, entity_id, details, created_at
            FROM audit_logs
            WHERE user_id = :uid
            ORDER BY created_at DESC
            LIMIT 20
        ", ['uid' => $user['id']]);

        View::render('profile/index', [
            'user'           => $user,
            'tenant'         => $tenant,
            'taxProfile'     => $request->taxProfile(),
            'recentActivity' => $recentActivity,
        ]);
    }

    public function update(Request $request): void {
        $user   = $request->user();
        $userId = $user['id'];

        $name        = trim($request->input('name', ''));
        $currentPass = $request->input('current_password', '');
        $newPass     = $request->input('new_password', '');
        $confirmPass = $request->input('confirm_password', '');

        if (empty($name)) {
            flash('error', 'Name cannot be empty.');
            Response::redirect('/app/profile');
            return;
        }

        // Fetch current user from DB
        $dbUser = Database::fetchOne("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
        if (!$dbUser) {
            flash('error', 'User not found.');
            Response::redirect('/app/profile');
            return;
        }

        $changePassword = !empty($newPass);
        if ($changePassword) {
            if (!password_verify($currentPass, $dbUser['password_hash'])) {
                flash('error', 'Current password is incorrect.');
                Response::redirect('/app/profile');
                return;
            }
            if (strlen($newPass) < 6) {
                flash('error', 'New password must be at least 6 characters.');
                Response::redirect('/app/profile');
                return;
            }
            if ($newPass !== $confirmPass) {
                flash('error', 'New passwords do not match.');
                Response::redirect('/app/profile');
                return;
            }
        }

        if ($changePassword) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            Database::connect()->prepare("UPDATE users SET name = ?, password_hash = ? WHERE id = ?")->execute([$name, $hash, $userId]);
        } else {
            Database::connect()->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$name, $userId]);
        }

        // Refresh session user with a column whitelist (never store password_hash)
        $updatedUser = Database::fetchOne(
            "SELECT id, tenant_id, name, email, role, is_active FROM users WHERE id = :id",
            ['id' => $userId]
        );
        if ($updatedUser) {
            $updatedUser['id'] = (int)$updatedUser['id'];
            $updatedUser['tenant_id'] = $updatedUser['tenant_id'] !== null ? (int)$updatedUser['tenant_id'] : null;
            Session::set('user', $updatedUser);
        }

        flash('success', $changePassword ? '✅ Profile and password updated successfully.' : '✅ Profile updated successfully.');
        Response::redirect('/app/profile');
    }
}

<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\Auth; use App\Core\Request; use App\Core\Response; use App\Core\Session; use App\Service\EmailService; use App\Store\UserStore; use App\Util\Csrf; use App\Util\Token; use App\Util\Validator;
final class AuthController
{
    public function __construct(private UserStore $userStore, private Auth $auth, private Session $session, private EmailService $emailService) {}
    public function showRegister(Request $request): Response { return new Response(render('auth/register', ['auth' => $this->auth, 'errors' => []])); }
    public function handleRegister(Request $request): Response
    {
        $data = $request->postData(); put_old($data);
        file_put_contents(DATA_PATH . '/logs/register-debug.log', sprintf("[%s] POST csrf_token=%s session_csrf=%s session_id=%s\n", date('c'), substr((string)($data['csrf_token'] ?? ''), 0, 8) . '...', substr((string)($_SESSION['_csrf'] ?? 'NONE'), 0, 8) . '...', session_id()), FILE_APPEND);
        if (!Csrf::validateToken((string) ($data['csrf_token'] ?? ''))) { return new Response(render('auth/register', ['auth' => $this->auth, 'errors' => ['general' => ['Your session expired. Please try submitting the form again.']]])); }
        $errors = Validator::validate($data, ['username' => ['required', 'regex' => '/^[a-zA-Z0-9_-]{3,30}$/'], 'real_name' => ['required', 'max_length' => 100], 'email' => ['required', 'email'], 'password' => ['required', 'min_length' => 8]]);
        if ($this->userStore->findByUsername((string) ($data['username'] ?? ''))) $errors['username'][] = 'That username is already taken.';
        if ($this->userStore->findByEmail((string) ($data['email'] ?? ''))) $errors['email'][] = 'That email is already registered.';
        if ($errors !== []) return new Response(render('auth/register', ['auth' => $this->auth, 'errors' => $errors]), 422);
        $role = $this->userStore->hasAdmin() ? 'user' : 'admin'; $user = $this->userStore->create(['username' => trim((string) $data['username']), 'real_name' => trim((string) $data['real_name']), 'email' => strtolower(trim((string) $data['email'])), 'password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT), 'role' => $role, 'display_name' => trim((string) $data['username']), 'verify_token' => Token::generate()]);
        $this->emailService->sendVerification($user['email'], $user['verify_token']); clear_old(); flash('success', 'Registration complete. Please check your email.'); return Response::redirect('/login');
    }
    public function showLogin(Request $request): Response { return new Response(render('auth/login', ['auth' => $this->auth, 'errors' => []])); }
    public function handleLogin(Request $request): Response
    {
        $data = $request->postData(); if (!Csrf::validateToken((string) ($data['csrf_token'] ?? ''))) { flash('error', 'Your session expired.'); return Response::redirect('/login'); }
        $user = $this->userStore->findByEmail((string) ($data['email'] ?? '')); if ($user === null || !password_verify((string) ($data['password'] ?? ''), (string) ($user['password_hash'] ?? ''))) { flash('error', 'Invalid credentials.'); return Response::redirect('/login'); }
        if (!$user['verified']) { flash('error', 'Please verify your email before logging in.'); return Response::redirect('/login'); }
        if (!empty($user['deleted_at'])) { flash('error', 'This account is suspended.'); return Response::redirect('/login'); }
        $this->auth->login($user, !empty($data['remember'])); flash('success', 'Welcome back, ' . $user['display_name'] . '!'); return Response::redirect('/');
    }
    public function handleLogout(Request $request): Response { $this->auth->logout(); flash('success', 'You have been logged out.'); return Response::redirect('/'); }
    public function handleVerifyEmail(Request $request): Response { $token = (string) $request->query('token', ''); $user = $this->userStore->findByVerifyToken($token); $success = false; if ($user !== null) { $this->userStore->update($user['id'], ['verified' => true, 'verify_token' => null]); $success = true; } return new Response(render('auth/verify-email', ['auth' => $this->auth, 'success' => $success])); }
    public function showResetRequest(Request $request): Response { return new Response(render('auth/reset-password', ['auth' => $this->auth, 'mode' => 'request', 'errors' => []])); }
    public function handleResetRequest(Request $request): Response { $data = $request->postData(); if (Csrf::validateToken((string) ($data['csrf_token'] ?? ''))) { $user = $this->userStore->findByEmail((string) ($data['email'] ?? '')); if ($user !== null && empty($user['deleted_at'])) { $token = Token::generate(); $this->userStore->update($user['id'], ['reset_token' => $token, 'reset_expires' => time() + 3600]); $this->emailService->sendPasswordReset($user['email'], $token); } } flash('info', 'If that email exists, a password reset link has been sent.'); return Response::redirect('/reset-password'); }
    public function showResetConfirm(Request $request): Response { $token = (string) $request->query('token', ''); $user = $this->userStore->findByResetToken($token); return new Response(render('auth/reset-password', ['auth' => $this->auth, 'mode' => 'confirm', 'token' => $token, 'valid' => $user !== null, 'errors' => []])); }
    public function handleResetConfirm(Request $request): Response
    {
        $data = $request->postData(); $token = (string) ($data['token'] ?? ''); $user = $this->userStore->findByResetToken($token); $errors = [];
        if (!Csrf::validateToken((string) ($data['csrf_token'] ?? ''))) $errors['general'][] = 'Your session expired.';
        if ($user === null) $errors['general'][] = 'This reset link is invalid or expired.';
        if (mb_strlen((string) ($data['password'] ?? '')) < 8) $errors['password'][] = 'Password must be at least 8 characters.';
        if ($errors !== []) return new Response(render('auth/reset-password', ['auth' => $this->auth, 'mode' => 'confirm', 'token' => $token, 'valid' => $user !== null, 'errors' => $errors]), 422);
        $this->userStore->update($user['id'], ['password_hash' => password_hash((string) $data['password'], PASSWORD_BCRYPT), 'reset_token' => null, 'reset_expires' => null]); flash('success', 'Your password has been updated.'); return Response::redirect('/login');
    }
}

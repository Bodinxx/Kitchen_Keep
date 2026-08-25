<?php
declare(strict_types=1);
namespace App\Service;
final class EmailService
{
    public function sendVerification(string $email, string $token): void { $this->send($email, 'Verify your Kitchen Keep account', 'Welcome to ' . site_config('site_name') . '! Verify your email: ' . base_url() . '/verify-email?token=' . urlencode($token)); }
    public function sendPasswordReset(string $email, string $token): void { $this->send($email, 'Reset your Kitchen Keep password', 'Reset your password using this link: ' . base_url() . '/reset-password/confirm?token=' . urlencode($token)); }
    public function sendModerationNotice(string $email, string $subject, string $body): void { $this->send($email, $subject, $body); }
    private function send(string $to, string $subject, string $body): void
    {
        file_put_contents(DATA_PATH . '/logs/email.log', sprintf("[%s] To: %s | %s | %s\n", date('c'), $to, $subject, $body), FILE_APPEND);
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer') && site_config('smtp_host')) {
            try {
                $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = (string) site_config('smtp_host');
                $mailer->Port = (int) site_config('smtp_port', 587);
                $mailer->SMTPAuth = true;
                $mailer->Username = (string) site_config('smtp_user');
                $mailer->Password = (string) site_config('smtp_pass');
                $mailer->setFrom((string) site_config('from_email'), (string) site_config('from_name'));
                $mailer->addAddress($to);
                $mailer->Subject = $subject;
                $mailer->Body = $body;
                $mailer->send();
                return;
            } catch (\Throwable) {
            }
        }
        @mail($to, $subject, $body, 'From: ' . site_config('from_email'));
    }
}

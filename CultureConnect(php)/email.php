<?php
/**
 * Email Helper for CultureConnect
 * PHPMailer SMTP integration with templates
 */

// If Composer dependencies (PHPMailer) are not installed, provide a safe
// fallback Email class so the app continues to run (emails will be no-ops
// and logged). This avoids fatal errors when vendor/autoload.php is missing.
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    // Minimal fallback implementation
    class Email {
        public static function sendResetLink($email, $name, $reset_link, $expires_at) {
            // Log that email sending is disabled in this environment
            if (function_exists('Logger')) {
                // Nothing
            }
            // Use Logger if available
            if (class_exists('Logger')) {
                Logger::log('EMAIL_DISABLED', 'PHPMailer not installed; cannot send reset link', ['email' => $email]);
            }
            return false;
        }

        public static function sendResetConfirmation($email, $name, $ip_address) {
            if (class_exists('Logger')) {
                Logger::log('EMAIL_DISABLED', 'PHPMailer not installed; cannot send confirmation', ['email' => $email]);
            }
            return false;
        }
    }

    // Stop processing the rest of this file; fallback Email is defined.
    return;
}

require_once $vendorAutoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Email {
    
    private static function getMailer() {
        $mail = new PHPMailer(true);
        
        try {
            // SMTP Configuration from environment
            $mail->isSMTP();
            $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = getenv('SMTP_USER');
            $mail->Password = getenv('SMTP_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = getenv('SMTP_PORT') ?: 587;
            
            $mail->setFrom(getenv('MAIL_FROM') ?: 'noreply@cultureconnect.com', 'CultureConnect');
            
            return $mail;
        } catch (Exception $e) {
            Logger::error('MAILER_CONFIG_ERROR', $e->getMessage());
            return null;
        }
    }
    
    /**
     * Send password reset link email
     */
    public static function sendResetLink($email, $name, $reset_link, $expires_at) {
        try {
            $mail = self::getMailer();
            if (!$mail) return false;
            
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = '🔑 Renew Your CultureConnect Key';
            
            // Format expiry time
            $expiry_time = date('g:i A', strtotime($expires_at));
            $expiry_date = date('M d, Y', strtotime($expires_at));
            
            $html_body = <<<HTML
            <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; border-radius: 16px;">
                
                <div style="background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 40px; backdrop-filter: blur(20px);">
                    
                    <h2 style="color: #667eea; margin: 0 0 16px; font-size: 24px;">🔑 Renew Your Soul Key</h2>
                    
                    <p style="color: #1f2937; font-size: 16px; line-height: 1.6; margin-bottom: 24px;">
                        Hello <strong>{$name}</strong>,
                    </p>
                    
                    <p style="color: #1f2937; font-size: 16px; line-height: 1.6; margin-bottom: 32px;">
                        A memory link to renew your CultureConnect key has been requested. This link will expire at <strong>{$expiry_time}</strong> on <strong>{$expiry_date}</strong>.
                    </p>
                    
                    <div style="text-align: center; margin: 32px 0;">
                        <a href="{$reset_link}" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px;">
                            Renew My Soul Key
                        </a>
                    </div>
                    
                    <p style="color: #6b7280; font-size: 14px; margin: 32px 0 16px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
                        Or copy and paste this link in your browser:
                    </p>
                    
                    <p style="background: #f3f4f6; padding: 12px; border-radius: 6px; color: #1f2937; font-size: 12px; word-break: break-all; font-family: monospace; margin-bottom: 24px;">
                        {$reset_link}
                    </p>
                    
                    <p style="color: #6b7280; font-size: 13px; margin-bottom: 16px;">
                        ⏰ <strong>This link expires at {$expiry_time} on {$expiry_date}</strong>
                    </p>
                    
                    <p style="color: #6b7280; font-size: 13px; margin-bottom: 24px;">
                        If you didn't request this, you can safely ignore this email. Your key remains secure.
                    </p>
                    
                    <div style="border-top: 1px solid #e5e7eb; padding-top: 24px; text-align: center; color: #9ca3af; font-size: 12px;">
                        <p style="margin: 0;">🌍 CultureConnect — Where Souls Connect</p>
                        <p style="margin: 8px 0 0;">Sent with care on {$expiry_date}</p>
                    </div>
                </div>
            </div>
            HTML;
            
            $text_body = <<<TEXT
Renew Your CultureConnect Key

Hello {$name},

A memory link to renew your CultureConnect key has been requested. This link will expire at {$expiry_time} on {$expiry_date}.

Reset Link:
{$reset_link}

Or copy the link above and paste it into your browser.

⏰ EXPIRES: {$expiry_time} on {$expiry_date}

If you didn't request this, you can safely ignore this email. Your key remains secure.

---
CultureConnect — Where Souls Connect
TEXT;
            
            $mail->Body = $html_body;
            $mail->AltBody = $text_body;
            
            return $mail->send();
            
        } catch (Exception $e) {
            Logger::error('EMAIL_SEND_ERROR', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send password reset confirmation email
     */
    public static function sendResetConfirmation($email, $name, $ip_address) {
        try {
            $mail = self::getMailer();
            if (!$mail) return false;
            
            $mail->addAddress($email, $name);
            $mail->isHTML(true);
            $mail->Subject = '✓ Your CultureConnect Key Has Been Renewed';
            
            $reset_date = date('M d, Y \a\t g:i A');

            // Build absolute support URL safely
            $support_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? '');

            $html_body = <<<HTML
            <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; border-radius: 16px;">
                
                <div style="background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 40px; backdrop-filter: blur(20px);">
                    
                    <h2 style="color: #10b981; margin: 0 0 16px; font-size: 24px;">✓ Soul Key Renewed</h2>
                    
                    <p style="color: #1f2937; font-size: 16px; line-height: 1.6; margin-bottom: 24px;">
                        Hello <strong>{$name}</strong>,
                    </p>
                    
                    <p style="color: #1f2937; font-size: 16px; line-height: 1.6; margin-bottom: 24px;">
                        Your CultureConnect key has been successfully renewed! Your account is now secure with your new credentials.
                    </p>
                    
                    <div style="background: #f0fdf4; border-left: 4px solid #10b981; padding: 16px; margin: 24px 0; border-radius: 4px;">
                        <p style="color: #15803d; font-size: 14px; margin: 0;">
                            <strong>🔐 Security Note:</strong> This action was completed on {$reset_date} from IP address {$ip_address}.
                        </p>
                    </div>
                    
                    <p style="color: #6b7280; font-size: 14px; margin-bottom: 24px;">
                        If this wasn't you, please secure your account immediately:
                    </p>
                    
                    <div style="text-align: center; margin: 24px 0;">
                        <a href="{$support_url}/contact-support.php" style="display: inline-block; background: #ef4444; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px;">
                            Contact Support
                        </a>
                    </div>
                    
                    <div style="border-top: 1px solid #e5e7eb; padding-top: 24px; text-align: center; color: #9ca3af; font-size: 12px;">
                        <p style="margin: 0;">🌍 CultureConnect — Where Souls Connect</p>
                    </div>
                </div>
            </div>
            HTML;
            
            $text_body = <<<TEXT
Your CultureConnect Key Has Been Renewed

Hello {$name},

Your CultureConnect key has been successfully renewed! Your account is now secure with your new credentials.

SECURITY NOTE:
This action was completed on {$reset_date} from IP address {$ip_address}.

If this wasn't you, please secure your account immediately by contacting our support team.

---
CultureConnect — Where Souls Connect
TEXT;
            
            $mail->Body = $html_body;
            $mail->AltBody = $text_body;
            
            return $mail->send();
            
        } catch (Exception $e) {
            Logger::error('EMAIL_SEND_ERROR', $e->getMessage());
            return false;
        }
    }
}
?>
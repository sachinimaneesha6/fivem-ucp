<?php
class EmailService {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        // Configure your SMTP settings here
        $this->smtp_host = 'smtp.gmail.com'; // Change to your SMTP server
        $this->smtp_port = 587;
        $this->smtp_username = 'your-email@gmail.com'; // Your email
        $this->smtp_password = 'your-app-password'; // Your app password
        $this->from_email = 'noreply@yourserver.com';
        $this->from_name = 'FiveM UCP';
    }
    
    public function sendPasswordReset($to_email, $username, $reset_token) {
        $reset_link = $this->getBaseUrl() . "/reset_password.php?token=" . $reset_token;
        
        $subject = "Password Reset Request - FiveM UCP";
        $message = $this->getPasswordResetTemplate($username, $reset_link);
        
        return $this->sendEmail($to_email, $subject, $message);
    }
    
    private function sendEmail($to_email, $subject, $message) {
        // Using PHP's built-in mail function with headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // For production, you should use PHPMailer or similar
        // This is a basic implementation
        return mail($to_email, $subject, $message, implode("\r\n", $headers));
    }
    
    private function getPasswordResetTemplate($username, $reset_link) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset</title>
        </head>
        <body style='font-family: Arial, sans-serif; background-color: #1f2937; margin: 0; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #374151; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.3);'>
                <div style='background: linear-gradient(135deg, #f39c12, #f1c40f); padding: 30px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 28px; font-weight: bold;'>
                        🎮 FiveM UCP
                    </h1>
                    <p style='color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;'>Password Reset Request</p>
                </div>
                
                <div style='padding: 40px 30px;'>
                    <h2 style='color: #f3f4f6; margin: 0 0 20px 0; font-size: 24px;'>Hello, {$username}!</h2>
                    
                    <p style='color: #d1d5db; line-height: 1.6; margin: 0 0 25px 0; font-size: 16px;'>
                        We received a request to reset your password for your FiveM UCP account. 
                        If you didn't make this request, you can safely ignore this email.
                    </p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$reset_link}' 
                           style='display: inline-block; background: linear-gradient(135deg, #f39c12, #f1c40f); 
                                  color: white; text-decoration: none; padding: 15px 30px; 
                                  border-radius: 8px; font-weight: bold; font-size: 16px;
                                  box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);'>
                            🔐 Reset Your Password
                        </a>
                    </div>
                    
                    <div style='background-color: #4b5563; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                        <p style='color: #f59e0b; margin: 0 0 10px 0; font-weight: bold; font-size: 14px;'>
                            ⚠️ Security Notice:
                        </p>
                        <ul style='color: #d1d5db; margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.5;'>
                            <li>This link will expire in 1 hour</li>
                            <li>Only use this link if you requested the password reset</li>
                            <li>Never share this link with anyone</li>
                        </ul>
                    </div>
                    
                    <p style='color: #9ca3af; font-size: 14px; line-height: 1.6; margin: 25px 0 0 0;'>
                        If the button doesn't work, copy and paste this link into your browser:<br>
                        <span style='color: #60a5fa; word-break: break-all;'>{$reset_link}</span>
                    </p>
                </div>
                
                <div style='background-color: #1f2937; padding: 20px; text-align: center; border-top: 1px solid #4b5563;'>
                    <p style='color: #6b7280; margin: 0; font-size: 12px;'>
                        © 2025 FiveM UCP. This email was sent because a password reset was requested for your account.
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['REQUEST_URI']);
        return $protocol . '://' . $host . $path;
    }
}
?>
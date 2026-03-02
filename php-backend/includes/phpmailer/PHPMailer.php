<?php
/**
 * Minimal PHPMailer-compatible class for JNV School.
 * Supports SMTP with SSL authentication for sending HTML emails.
 * Drop-in replacement using the same API as the full PHPMailer library.
 */
namespace PHPMailer\PHPMailer;

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/SMTP.php';

class PHPMailer
{
    // SMTP settings
    public $Host = '';
    public $Port = 465;
    public $SMTPAuth = true;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = 'ssl';
    public $SMTPDebug = 0;
    public $Timeout = 30;

    // Message settings
    public $CharSet = 'UTF-8';
    public $From = '';
    public $FromName = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $isHTML = true;

    // Recipients
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $replyTo = [];

    // Error info
    public $ErrorInfo = '';

    // Mailer type
    protected $Mailer = 'smtp';

    public function __construct($exceptions = true)
    {
        // Constructor kept for compatibility
    }

    /**
     * Set mailer to use SMTP.
     */
    public function isSMTP(): void
    {
        $this->Mailer = 'smtp';
    }

    /**
     * Set message format to HTML.
     */
    public function isHTML(bool $isHtml = true): void
    {
        $this->isHTML = $isHtml;
    }

    /**
     * Set the From address and name.
     */
    public function setFrom(string $address, string $name = ''): bool
    {
        $this->From = $address;
        $this->FromName = $name;
        return true;
    }

    /**
     * Add a "To" recipient.
     */
    public function addAddress(string $address, string $name = ''): bool
    {
        $this->to[] = ['address' => $address, 'name' => $name];
        return true;
    }

    /**
     * Add a "Reply-To" address.
     */
    public function addReplyTo(string $address, string $name = ''): bool
    {
        $this->replyTo[] = ['address' => $address, 'name' => $name];
        return true;
    }

    /**
     * Add a CC recipient.
     */
    public function addCC(string $address, string $name = ''): bool
    {
        $this->cc[] = ['address' => $address, 'name' => $name];
        return true;
    }

    /**
     * Add a BCC recipient.
     */
    public function addBCC(string $address, string $name = ''): bool
    {
        $this->bcc[] = ['address' => $address, 'name' => $name];
        return true;
    }

    /**
     * Send the email via SMTP.
     */
    public function send(): bool
    {
        if (empty($this->to)) {
            $this->ErrorInfo = 'No recipients specified.';
            return false;
        }

        $smtp = new SMTP();
        $smtp->do_debug = $this->SMTPDebug;

        // Connect
        if (!$smtp->connect($this->Host, $this->Port, $this->Timeout, $this->SMTPSecure)) {
            $err = $smtp->getError();
            $this->ErrorInfo = 'SMTP connect failed: ' . ($err['errstr'] ?? 'Unknown error');
            return false;
        }

        // EHLO
        if (!$smtp->hello(gethostname() ?: 'localhost')) {
            $this->ErrorInfo = 'EHLO failed.';
            $smtp->close();
            return false;
        }

        // Authenticate
        if ($this->SMTPAuth) {
            if (!$smtp->authenticate($this->Username, $this->Password)) {
                $err = $smtp->getError();
                $this->ErrorInfo = 'SMTP authentication failed: ' . ($err['reply'] ?? 'Unknown error');
                $smtp->close();
                return false;
            }
        }

        // MAIL FROM
        if (!$smtp->mail($this->From)) {
            $this->ErrorInfo = 'MAIL FROM failed.';
            $smtp->close();
            return false;
        }

        // RCPT TO (all recipients)
        $allRecipients = array_merge($this->to, $this->cc, $this->bcc);
        foreach ($allRecipients as $recipient) {
            if (!$smtp->recipient($recipient['address'])) {
                $this->ErrorInfo = 'RCPT TO failed for: ' . $recipient['address'];
                $smtp->close();
                return false;
            }
        }

        // Build message
        $message = $this->buildMessage();

        // DATA
        if (!$smtp->data($message)) {
            $this->ErrorInfo = 'DATA command failed.';
            $smtp->close();
            return false;
        }

        // QUIT
        $smtp->quit();

        return true;
    }

    /**
     * Build the email message with headers and body.
     */
    protected function buildMessage(): string
    {
        $eol = "\r\n";
        $headers = '';

        // Date
        $headers .= 'Date: ' . date('r') . $eol;

        // From
        if ($this->FromName) {
            $headers .= 'From: ' . $this->encodeHeader($this->FromName) . ' <' . $this->From . '>' . $eol;
        } else {
            $headers .= 'From: ' . $this->From . $eol;
        }

        // To
        $toAddrs = [];
        foreach ($this->to as $r) {
            $toAddrs[] = $r['name'] ? ($this->encodeHeader($r['name']) . ' <' . $r['address'] . '>') : $r['address'];
        }
        $headers .= 'To: ' . implode(', ', $toAddrs) . $eol;

        // CC
        if (!empty($this->cc)) {
            $ccAddrs = [];
            foreach ($this->cc as $r) {
                $ccAddrs[] = $r['name'] ? ($this->encodeHeader($r['name']) . ' <' . $r['address'] . '>') : $r['address'];
            }
            $headers .= 'Cc: ' . implode(', ', $ccAddrs) . $eol;
        }

        // Reply-To
        if (!empty($this->replyTo)) {
            $r = $this->replyTo[0];
            $headers .= 'Reply-To: ' . ($r['name'] ? ($this->encodeHeader($r['name']) . ' <' . $r['address'] . '>') : $r['address']) . $eol;
        }

        // Subject
        $headers .= 'Subject: ' . $this->encodeHeader($this->Subject) . $eol;

        // MIME
        $headers .= 'MIME-Version: 1.0' . $eol;

        if ($this->isHTML) {
            $headers .= 'Content-Type: text/html; charset=' . $this->CharSet . $eol;
        } else {
            $headers .= 'Content-Type: text/plain; charset=' . $this->CharSet . $eol;
        }

        $headers .= 'Content-Transfer-Encoding: quoted-printable' . $eol;

        // Message ID
        $msgId = sprintf('<%s.%s@%s>', base_convert(microtime(true) * 1000, 10, 36), bin2hex(random_bytes(4)), $this->Host ?: 'localhost');
        $headers .= 'Message-ID: ' . $msgId . $eol;

        // X-Mailer
        $headers .= 'X-Mailer: JNV-School-PHPMailer/1.0' . $eol;

        // Blank line separates headers from body
        $headers .= $eol;

        // Encode body as quoted-printable
        $body = quoted_printable_encode($this->Body);

        return $headers . $body;
    }

    /**
     * Encode a header value for UTF-8 support.
     */
    protected function encodeHeader(string $str): string
    {
        if (!preg_match('/[^\x20-\x7E]/', $str)) {
            return $str; // ASCII only, no encoding needed
        }
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }

    /**
     * Clear all recipients and reset for reuse.
     */
    public function clearAddresses(): void
    {
        $this->to = [];
    }

    public function clearAllRecipients(): void
    {
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
    }
}
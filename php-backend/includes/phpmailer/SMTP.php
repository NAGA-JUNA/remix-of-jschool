<?php
/**
 * Minimal SMTP transport class for JNV School.
 * Handles SSL/TLS SMTP authentication and message sending via PHP sockets.
 * No external dependencies required.
 */
namespace PHPMailer\PHPMailer;

class SMTP
{
    const CRLF = "\r\n";

    protected $smtp_conn = null;
    protected $error = [];
    protected $last_reply = '';

    public $do_debug = 0;

    /**
     * Connect to an SMTP server.
     */
    public function connect(string $host, int $port = 465, int $timeout = 30, string $secure = 'ssl'): bool
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $prefix = ($secure === 'ssl') ? 'ssl://' : '';
        $this->smtp_conn = @stream_socket_client(
            $prefix . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->smtp_conn) {
            $this->error = ['error' => 'Connection failed', 'errno' => $errno, 'errstr' => $errstr];
            return false;
        }

        $this->last_reply = $this->getResponse();
        return (strpos($this->last_reply, '220') === 0);
    }

    /**
     * Send EHLO command.
     */
    public function hello(string $host = 'localhost'): bool
    {
        return $this->sendCommand('EHLO ' . $host, [250]);
    }

    /**
     * Authenticate with LOGIN method.
     */
    public function authenticate(string $username, string $password): bool
    {
        if (!$this->sendCommand('AUTH LOGIN', [334])) {
            return false;
        }
        if (!$this->sendCommand(base64_encode($username), [334])) {
            return false;
        }
        return $this->sendCommand(base64_encode($password), [235]);
    }

    /**
     * Send MAIL FROM command.
     */
    public function mail(string $from): bool
    {
        return $this->sendCommand('MAIL FROM:<' . $from . '>', [250]);
    }

    /**
     * Send RCPT TO command.
     */
    public function recipient(string $to): bool
    {
        return $this->sendCommand('RCPT TO:<' . $to . '>', [250, 251]);
    }

    /**
     * Send DATA command and message body.
     */
    public function data(string $msg_data): bool
    {
        if (!$this->sendCommand('DATA', [354])) {
            return false;
        }
        // Transparency: lines starting with '.' get an extra '.'
        $msg_data = str_replace(self::CRLF . '.', self::CRLF . '..', $msg_data);
        // Send the message data followed by <CRLF>.<CRLF>
        fwrite($this->smtp_conn, $msg_data . self::CRLF . '.' . self::CRLF);
        $this->last_reply = $this->getResponse();
        return (strpos($this->last_reply, '250') === 0);
    }

    /**
     * Send QUIT and close connection.
     */
    public function quit(): bool
    {
        $result = $this->sendCommand('QUIT', [221]);
        $this->close();
        return $result;
    }

    /**
     * Close the socket connection.
     */
    public function close(): void
    {
        if (is_resource($this->smtp_conn) || $this->smtp_conn instanceof \Socket) {
            fclose($this->smtp_conn);
        }
        $this->smtp_conn = null;
    }

    /**
     * Get last error.
     */
    public function getError(): array
    {
        return $this->error;
    }

    /**
     * Send a command and check response code.
     */
    protected function sendCommand(string $command, array $expect): bool
    {
        fwrite($this->smtp_conn, $command . self::CRLF);
        $this->last_reply = $this->getResponse();
        foreach ($expect as $code) {
            if (strpos($this->last_reply, (string)$code) === 0) {
                return true;
            }
        }
        $this->error = ['command' => $command, 'reply' => $this->last_reply, 'expected' => implode(',', $expect)];
        return false;
    }

    /**
     * Read the server response.
     */
    protected function getResponse(): string
    {
        $response = '';
        stream_set_timeout($this->smtp_conn, 10);
        while ($line = @fgets($this->smtp_conn, 515)) {
            $response .= $line;
            // If 4th char is space, this is the last line
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }
}
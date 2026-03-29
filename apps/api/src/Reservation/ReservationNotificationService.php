<?php

namespace App\Reservation;

use App\Entity\Reservation;

final class ReservationNotificationService
{
    public function __construct(
        private readonly string $shareDir,
        private readonly string $fromAddress,
        private readonly bool $smtpEnabled,
        private readonly string $smtpHost,
        private readonly int $smtpPort,
    ) {
    }

    public function sendConfirmedReservationEmail(
        Reservation $reservation,
        string $ticketDownloadUrl,
        string $calendarDownloadUrl,
    ): void {
        $event = $reservation->getEvent();

        $body = implode("\n", [
            'Hello '.$reservation->getAttendeeName().',',
            '',
            'Your reservation is confirmed.',
            'Reservation ID: '.$reservation->getReservationId(),
            'Event: '.($event?->getTitle() ?? 'N/A'),
            'Date: '.($event?->getStartsAt()->format('Y-m-d H:i') ?? 'N/A'),
            'Seats: '.([] === $reservation->getSeatLabels() ? 'N/A' : implode(', ', $reservation->getSeatLabels())),
            '',
            'Ticket PDF: '.$ticketDownloadUrl,
            'Calendar (ICS): '.$calendarDownloadUrl,
            '',
            'Thank you for booking with Tiskerti.',
        ]);

        $this->dispatch(
            $reservation->getAttendeeEmail(),
            'Tiskerti reservation confirmed - '.$reservation->getReservationId(),
            $body,
        );
    }

    public function sendWaitlistEmail(Reservation $reservation, int $waitlistPosition): void
    {
        $event = $reservation->getEvent();

        $body = implode("\n", [
            'Hello '.$reservation->getAttendeeName().',',
            '',
            'You have been added to the waitlist.',
            'Reservation ID: '.$reservation->getReservationId(),
            'Event: '.($event?->getTitle() ?? 'N/A'),
            'Current waitlist position: #'.$waitlistPosition,
            '',
            'We will contact you if seats become available.',
        ]);

        $this->dispatch(
            $reservation->getAttendeeEmail(),
            'Tiskerti waitlist update - '.$reservation->getReservationId(),
            $body,
        );
    }

    private function dispatch(string $recipient, string $subject, string $body): void
    {
        $rawMessage = implode("\r\n", [
            'From: '.$this->fromAddress,
            'To: '.$recipient,
            'Subject: '.$subject,
            'Date: '.gmdate('D, d M Y H:i:s').' +0000',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            $body,
            '',
        ]);

        $this->writeOutboxMessage($recipient, $subject, $rawMessage);

        if (!$this->smtpEnabled) {
            return;
        }

        try {
            $this->sendViaSmtp($recipient, $rawMessage);
        } catch (\Throwable $exception) {
            $this->writeOutboxError($recipient, $subject, $exception->getMessage());
        }
    }

    private function sendViaSmtp(string $recipient, string $rawMessage): void
    {
        $socket = @fsockopen($this->smtpHost, $this->smtpPort, $errorNumber, $errorMessage, 2.5);

        if (!is_resource($socket)) {
            throw new \RuntimeException(sprintf('smtp_connection_failed:%d:%s', $errorNumber, $errorMessage));
        }

        stream_set_timeout($socket, 3);

        try {
            $this->assertSmtpCode($this->readResponse($socket), [220]);
            $this->writeLine($socket, 'EHLO tiskerti.local');
            $this->assertSmtpCode($this->readResponse($socket), [250]);

            $this->writeLine($socket, 'MAIL FROM:<'.$this->fromAddress.'>');
            $this->assertSmtpCode($this->readResponse($socket), [250]);

            $this->writeLine($socket, 'RCPT TO:<'.$recipient.'>');
            $this->assertSmtpCode($this->readResponse($socket), [250, 251]);

            $this->writeLine($socket, 'DATA');
            $this->assertSmtpCode($this->readResponse($socket), [354]);

            fwrite($socket, $rawMessage."\r\n.\r\n");
            $this->assertSmtpCode($this->readResponse($socket), [250]);

            $this->writeLine($socket, 'QUIT');
            $this->readResponse($socket);
        } finally {
            fclose($socket);
        }
    }

    private function readResponse($socket): string
    {
        $response = '';

        while (($line = fgets($socket, 512)) !== false) {
            $response .= $line;

            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        if ('' === $response) {
            throw new \RuntimeException('smtp_empty_response');
        }

        return trim($response);
    }

    /**
     * @param list<int> $acceptedCodes
     */
    private function assertSmtpCode(string $response, array $acceptedCodes): void
    {
        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $acceptedCodes, true)) {
            throw new \RuntimeException('smtp_unexpected_response:'.$response);
        }
    }

    private function writeLine($socket, string $line): void
    {
        fwrite($socket, $line."\r\n");
    }

    private function writeOutboxMessage(string $recipient, string $subject, string $rawMessage): void
    {
        $directory = rtrim($this->shareDir, '/\\').DIRECTORY_SEPARATOR.'mail-outbox';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $safeRecipient = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $recipient) ?? 'recipient';
        $safeSubject = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $subject) ?? 'subject';
        $filename = sprintf('%s_%s_%s.eml', gmdate('Ymd_His'), $safeRecipient, substr($safeSubject, 0, 50));

        @file_put_contents($directory.DIRECTORY_SEPARATOR.$filename, $rawMessage);
    }

    private function writeOutboxError(string $recipient, string $subject, string $error): void
    {
        $directory = rtrim($this->shareDir, '/\\').DIRECTORY_SEPARATOR.'mail-outbox';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $line = sprintf("[%s] to=%s subject=%s error=%s\n", gmdate(DATE_ATOM), $recipient, $subject, $error);
        @file_put_contents($directory.DIRECTORY_SEPARATOR.'smtp-errors.log', $line, FILE_APPEND);
    }
}

<?php

namespace App\Mail\Transport;

use App\Services\HttpNotificationsMailClient;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class HttpNotificationsTransport extends AbstractTransport
{
    public function __construct(
        private readonly HttpNotificationsMailClient $client,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $to = [];
        foreach ($email->getTo() as $address) {
            $to[] = $address->getAddress();
        }
        if ($to === []) {
            throw new \InvalidArgumentException('Email has no recipients.');
        }

        $cc = [];
        foreach ($email->getCc() as $address) {
            $cc[] = $address->getAddress();
        }

        $bcc = [];
        foreach ($email->getBcc() as $address) {
            $bcc[] = $address->getAddress();
        }

        $html = $email->getHtmlBody();
        if ($html === null) {
            $text = $email->getTextBody();
            $html = $text !== null
                ? nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
                : '';
        } elseif (is_resource($html)) {
            $html = stream_get_contents($html) ?: '';
        }

        $this->client->send(
            count($to) === 1 ? $to[0] : $to,
            $email->getSubject() ?? '(no subject)',
            (string) $html,
            $cc,
            $bcc,
        );
    }

    public function __toString(): string
    {
        return 'http';
    }
}

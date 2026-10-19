<?php

namespace App\Mail\Transport;

use App\Services\ExchangeGraphMailClient;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

class ExchangeGraphTransport extends AbstractTransport
{
    public function __construct(
        private readonly ExchangeGraphMailClient $client,
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

        $from = $email->getFrom()[0] ?? null;

        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            if (! $part instanceof DataPart) {
                continue;
            }

            $body = $part->getBody();
            if (is_resource($body)) {
                $body = stream_get_contents($body) ?: '';
            }

            $filename = $part->getName()
                ?: $part->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename')
                ?: 'attachment.bin';

            $attachments[] = [
                'name' => $filename,
                'content' => (string) $body,
                'content_type' => $part->getMediaType().'/'.$part->getMediaSubtype(),
            ];
        }

        $this->client->send(
            count($to) === 1 ? $to[0] : $to,
            $email->getSubject() ?? '(no subject)',
            (string) $html,
            $from?->getAddress(),
            $from?->getName(),
            $cc,
            $bcc,
            $attachments,
        );
    }

    public function __toString(): string
    {
        return 'exchange';
    }
}

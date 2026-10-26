<?php

declare(strict_types=1);

namespace App\Gmail;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Message;

/**
 * Zamienia wiadomość z biblioteki IMAP na proste obiekty do szablonów.
 */
final class MailParser
{
    /**
     * Obrazek osadzony w treści (Content-ID albo „inline”) mniejszy niż ten próg to
     * logotyp lub ikonka ze stopki – nie pokazujemy go na liście załączników.
     */
    public const int EMBEDDED_IMAGE_MAX_BYTES = 100 * 1024;

    /** Obrazki z cid: wstawiamy do treści jako data: tylko do tego rozmiaru. */
    private const int INLINE_DATA_MAX_BYTES = 2 * 1024 * 1024;

    private const array LAYOUT_ATTRIBUTES = ['style', 'class', 'width', 'height', 'align', 'valign', 'bgcolor', 'border', 'cellpadding', 'cellspacing', 'color', 'face', 'size', 'dir'];

    private ?HtmlSanitizer $sanitizer = null;

    /**
     * @param list<string> $contractorAddresses
     */
    public function summary(Message $message, array $contractorAddresses, bool $hasAttachments = false): MailSummary
    {
        $from = $this->addresses($message, 'from')[0] ?? null;
        $date = null;
        if ($message->getDate()->has()) {
            try {
                $date = \DateTimeImmutable::createFromInterface($message->getDate()->toDate())
                    ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            } catch (\Throwable) {
            }
        }

        return new MailSummary(
            uid: (int) $message->getUid(),
            subject: trim((string) $message->getSubject()) ?: '(bez tematu)',
            from: null === $from ? '' : ('' !== $from->personal ? $from->personal : $from->mail),
            fromAddress: null === $from ? '' : mb_strtolower($from->mail),
            to: array_map(self::label(...), $this->addresses($message, 'to')),
            date: $date,
            incoming: null !== $from && \in_array(mb_strtolower($from->mail), $contractorAddresses, true),
            hasAttachments: $hasAttachments,
            participants: array_values(array_unique(array_map(
                static fn (Address $address): string => mb_strtolower($address->mail),
                [...$this->addresses($message, 'from'), ...$this->addresses($message, 'to'), ...$this->addresses($message, 'cc')],
            ))),
        );
    }

    /**
     * Czy w nadawcy lub adresatach jest któryś z adresów kontrahenta.
     *
     * @param list<string> $contractorAddresses
     */
    public function involves(Message $message, array $contractorAddresses): bool
    {
        foreach (['from', 'to', 'cc', 'bcc', 'reply_to'] as $field) {
            foreach ($this->addresses($message, $field) as $address) {
                if (\in_array(mb_strtolower($address->mail), $contractorAddresses, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $contractorAddresses
     */
    public function message(Message $message, array $contractorAddresses): MailMessage
    {
        $attachments = [];
        $cid = [];
        foreach ($message->getAttachments()->values() as $index => $attachment) {
            /** @var Attachment $attachment */
            $content = (string) $attachment->getContent();
            $mime = mb_strtolower((string) ($attachment->getMimeType() ?: $attachment->getContentType() ?: 'application/octet-stream'));
            $mime = trim(explode(';', $mime)[0]);
            $contentId = $this->contentId($attachment);

            if (null !== $contentId && str_starts_with($mime, 'image/') && \strlen($content) <= self::INLINE_DATA_MAX_BYTES) {
                $cid[$contentId] = 'data:'.$mime.';base64,'.base64_encode($content);
            }
            if (self::isEmbeddedImage($mime, \strlen($content), $contentId, $attachment->getDisposition())) {
                continue;
            }

            $attachments[] = new MailAttachment(
                index: (int) $index,
                name: $this->fileName($attachment, (int) $index),
                mimeType: $mime,
                size: \strlen($content),
                content: $content,
            );
        }

        $html = $message->hasHTMLBody() ? $this->sanitize($this->inlineImages($message->getHTMLBody(), $cid)) : null;

        return new MailMessage(
            summary: $this->summary($message, $contractorAddresses, [] !== $attachments),
            cc: array_map(self::label(...), $this->addresses($message, 'cc')),
            html: null === $html || '' === trim(strip_tags($html, '<img>')) ? null : $html,
            text: $message->hasTextBody() ? $message->getTextBody() : '',
            attachments: $attachments,
            messageId: trim((string) $message->getMessageId(), '<> ') ?: null,
        );
    }

    /**
     * Logotypy i ikonki ze stopek: obrazek osadzony w treści i niewielki.
     * Zdjęcia wklejone w treść (duże) zostają na liście załączników.
     */
    public static function isEmbeddedImage(string $mime, int $size, ?string $contentId, ?string $disposition): bool
    {
        if (!str_starts_with($mime, 'image/') || $size >= self::EMBEDDED_IMAGE_MAX_BYTES) {
            return false;
        }

        return null !== $contentId || 'inline' === mb_strtolower((string) $disposition);
    }

    /**
     * @param array<string, string> $cid Content-ID => data: URI
     */
    private function inlineImages(string $html, array $cid): string
    {
        if ([] === $cid) {
            return $html;
        }

        return (string) preg_replace_callback('/cid:([^"\'\s>)]+)/i', static fn (array $m): string => $cid[$m[1]] ?? $cid[urldecode($m[1])] ?? $m[0], $html);
    }

    private function sanitize(string $html): string
    {
        if (null === $this->sanitizer) {
            $config = (new HtmlSanitizerConfig())
                ->allowSafeElements()
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowMediaSchemes(['https', 'http', 'data'])
                ->forceAttribute('a', 'target', '_blank')
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(5_000_000);
            foreach (self::LAYOUT_ATTRIBUTES as $attribute) {
                $config = $config->allowAttribute($attribute, '*');
            }
            $this->sanitizer = new HtmlSanitizer($config);
        }

        return $this->sanitizer->sanitizeFor('body', $html);
    }

    private function contentId(Attachment $attachment): ?string
    {
        $id = (string) $attachment->getId();

        return '' === $id || $id === (string) $attachment->getHash() ? null : trim($id, '<> ');
    }

    private function fileName(Attachment $attachment, int $index): string
    {
        $name = trim((string) $attachment->getName());
        if ('' === $name || 'undefined' === $name) {
            $extension = $attachment->getExtension();
            $name = 'zalacznik-'.($index + 1).($extension ? '.'.$extension : '');
        }

        return $name;
    }

    /**
     * @return list<Address>
     */
    private function addresses(Message $message, string $field): array
    {
        $attribute = $message->get($field);
        if (null === $attribute) {
            return [];
        }

        return array_values(array_filter($attribute->all(), static fn (mixed $a): bool => $a instanceof Address && '' !== $a->mail));
    }

    private static function label(Address $address): string
    {
        return '' !== $address->personal ? \sprintf('%s <%s>', $address->personal, $address->mail) : $address->mail;
    }
}

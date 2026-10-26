<?php

declare(strict_types=1);

namespace App\Tests\Unit\Gmail;

use App\Entity\Contractor;
use App\Gmail\GmailMailbox;
use App\Gmail\MailParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Config;
use Webklex\PHPIMAP\Message;

final class MailParserTest extends TestCase
{
    private const array CONTRACTOR = ['jan@agro-kowalski.pl'];

    public function testParsesHeadersBodyAndAttachments(): void
    {
        $mail = (new MailParser())->message(self::fixture(), self::CONTRACTOR);

        self::assertSame('Faktura za październik', $mail->summary->subject);
        self::assertSame('Jan Kowalski – Agro', $mail->summary->from);
        self::assertSame('jan@agro-kowalski.pl', $mail->summary->fromAddress);
        self::assertSame(['Folwark <folwark@gmail.com>'], $mail->summary->to);
        self::assertSame(['biuro@agro-kowalski.pl'], $mail->cc);
        self::assertTrue($mail->summary->incoming);
        self::assertTrue($mail->summary->hasAttachments);
        self::assertSame('2026-10-20 07:15', $mail->summary->date?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i'));
        self::assertSame('abc123@agro-kowalski.pl', $mail->messageId);
        self::assertStringContainsString('Dzień dobry, w załączniku faktura.', $mail->text);
    }

    public function testSignatureLogoIsInlinedAndNotListedAsAttachment(): void
    {
        $mail = (new MailParser())->message(self::fixture(), self::CONTRACTOR);

        self::assertCount(1, $mail->attachments);
        self::assertSame('FV_10_2026.pdf', $mail->attachments[0]->name);
        self::assertSame('application/pdf', $mail->attachments[0]->mimeType);
        self::assertTrue($mail->attachments[0]->isPreviewable());
        self::assertStringStartsWith('%PDF', $mail->attachments[0]->content);
        self::assertSame($mail->attachments[0], $mail->attachment($mail->attachments[0]->index));

        self::assertNotNull($mail->html);
        self::assertStringContainsString('src="data:image/png;base64,', $mail->html);
        self::assertStringNotContainsString('cid:', $mail->html);
    }

    public function testHtmlIsSanitized(): void
    {
        $html = (string) (new MailParser())->message(self::fixture(), self::CONTRACTOR)->html;

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('alert(1)', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringContainsString('style="margin:0"', $html);
        self::assertStringContainsString('target="_blank"', $html);
    }

    public function testInvolvesChecksAllAddressFields(): void
    {
        $parser = new MailParser();
        $message = self::fixture();

        self::assertTrue($parser->involves($message, ['jan@agro-kowalski.pl']));
        self::assertTrue($parser->involves($message, ['biuro@agro-kowalski.pl']), 'adres z DW');
        self::assertFalse($parser->involves($message, ['ktos@inny.pl']));
        self::assertFalse($parser->summary($message, ['biuro@agro-kowalski.pl'])->incoming, 'kontrahent tylko w DW – wiadomość nie jest „od niego”');
    }

    /**
     * @return iterable<string, array{string, int, ?string, ?string, bool}>
     */
    public static function embeddedImages(): iterable
    {
        yield 'logo w stopce (Content-ID)' => ['image/png', 4_000, 'logo@firma', null, true];
        yield 'ikonka inline bez Content-ID' => ['image/gif', 900, null, 'inline', true];
        yield 'zdjęcie wklejone w treść' => ['image/jpeg', 850_000, 'zdjecie@telefon', 'inline', false];
        yield 'mały obrazek jako zwykły załącznik' => ['image/png', 30_000, null, 'attachment', false];
        yield 'PDF z Content-ID' => ['application/pdf', 4_000, 'faktura@firma', 'inline', false];
    }

    #[DataProvider('embeddedImages')]
    public function testEmbeddedImageDetection(string $mime, int $size, ?string $contentId, ?string $disposition, bool $expected): void
    {
        self::assertSame($expected, MailParser::isEmbeddedImage($mime, $size, $contentId, $disposition));
    }

    public function testGmailFilterSearchesFromToAndCc(): void
    {
        self::assertSame('{from:a@b.pl to:a@b.pl cc:a@b.pl from:c@d.pl to:c@d.pl cc:c@d.pl}', GmailMailbox::gmailFilter(['a@b.pl', 'c@d.pl']));
        self::assertSame('{}', GmailMailbox::gmailFilter(['nie-adres', 'x"}@y.pl']));
    }

    public function testContractorEmailAddresses(): void
    {
        $contractor = (new Contractor())->setEmail(' Biuro@Firma.pl; faktury@firma.pl, <jan@firma.pl> biuro@firma.pl zle@ ');

        self::assertSame(['biuro@firma.pl', 'faktury@firma.pl', 'jan@firma.pl'], $contractor->getEmailAddresses());
        self::assertSame([], (new Contractor())->getEmailAddresses());
    }

    private static function fixture(): Message
    {
        return Message::fromString(
            (string) file_get_contents(__DIR__.'/../../fixtures/gmail/faktura.eml'),
            Config::make(['decoding' => GmailMailbox::DECODING]),
        );
    }
}

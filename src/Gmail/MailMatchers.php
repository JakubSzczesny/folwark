<?php

declare(strict_types=1);

namespace App\Gmail;

/**
 * Po czym mail trafia do kontrahenta: pełny adres („jan@agro.pl”) albo domena zapisana
 * z małpą na początku („@agro.pl”, obejmuje też subdomeny, np. faktury@mail.agro.pl).
 */
final class MailMatchers
{
    /**
     * Domeny skrzynek ogólnodostępnych i końcówki wspólne dla wielu firm – po nich
     * do kontrahenta trafiałyby maile obcych osób.
     */
    private const array PUBLIC_DOMAINS = [
        'gmail.com', 'googlemail.com', 'wp.pl', 'wp.eu', 'o2.pl', 'tlen.pl', 'go2.pl', 'onet.pl', 'onet.eu', 'op.pl', 'vp.pl', 'poczta.onet.pl', 'poczta.onet.eu',
        'spoko.pl', 'autograf.pl', 'buziaczek.pl', 'interia.pl', 'interia.eu', 'poczta.fm', 'gazeta.pl', 'yahoo.com', 'yahoo.pl', 'outlook.com', 'outlook.pl', 'hotmail.com',
        'live.com', 'msn.com', 'icloud.com', 'me.com', 'mac.com', 'proton.me', 'protonmail.com', 'pm.me', 'aol.com', 'gmx.com', 'gmx.de', 'gmx.net', 'mail.com', 'zoho.com',
        'com.pl', 'net.pl', 'org.pl', 'edu.pl', 'gov.pl', 'biz.pl', 'info.pl', 'waw.pl', 'co.uk',
    ];

    /**
     * Domeny z tekstu wpisanego przez użytkownika, małymi literami, bez „@” i bez powtórzeń.
     *
     * @return list<string>
     */
    public static function parseDomains(?string $text): array
    {
        $domains = [];
        foreach (preg_split('/[\s,;]+/', (string) $text, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $domain) {
            $domains[mb_strtolower(ltrim($domain, '@'))] = true;
        }

        return array_values(array_filter(array_map('strval', array_keys($domains)), static fn (string $domain): bool => '' !== $domain));
    }

    public static function isValidDomain(string $domain): bool
    {
        return 1 === preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain);
    }

    public static function isPublicDomain(string $domain): bool
    {
        return \in_array($domain, self::PUBLIC_DOMAINS, true);
    }

    /**
     * Wszystkie wpisy, które pasują do adresu: on sam i jego domena z domenami nadrzędnymi.
     * „jan@mail.agro.pl” → jan@mail.agro.pl, @mail.agro.pl, @agro.pl, @pl
     *
     * @return list<string>
     */
    public static function candidates(string $address): array
    {
        $address = mb_strtolower($address);
        $at = strrpos($address, '@');
        if (false === $at) {
            return [$address];
        }

        $candidates = [$address];
        $domain = substr($address, $at + 1);
        while ('' !== $domain) {
            $candidates[] = '@'.$domain;
            $dot = strpos($domain, '.');
            $domain = false === $dot ? '' : substr($domain, $dot + 1);
        }

        return $candidates;
    }

    /**
     * @param list<string> $matchers
     */
    public static function matches(string $address, array $matchers): bool
    {
        return [] !== array_intersect(self::candidates($address), $matchers);
    }
}

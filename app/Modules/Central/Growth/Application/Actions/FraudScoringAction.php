<?php

declare(strict_types=1);

namespace App\Modules\Central\Growth\Application\Actions;

use App\Modules\Central\Growth\Domain\ValueObjects\FraudSignals;
use Illuminate\Http\Request;

/**
 * Evaluates fraud signals synchronously from the registration request.
 *
 * Score thresholds (additive):
 *  - Disposable email domain  → +60 (hard signal)
 *  - Gmail+ alias trick       → +30 (soft signal)
 *  - Suspicious TLD           → +20
 *  - Single-char local part   → +15
 *  - Known Tor/proxy IP list  → +50 (hard signal, static blocklist)
 *
 * Score ≥ 80 → quarantine.
 *
 * Designed to be synchronous (runs in the Livewire request cycle) and
 * dependency-free (no external HTTP calls). GeoIP/VPN API integration can
 * replace the static IP list in a future iteration.
 */
final readonly class FraudScoringAction
{
    /**
     * Well-known disposable email domains.
     * Extend via config('fraud.disposable_domains') if needed.
     *
     * @var array<int, string>
     */
    private const DISPOSABLE_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', 'trashmail.com', 'yopmail.com',
        'sharklasers.com', 'guerrillamailblock.com', 'grr.la', 'guerrillamail.info',
        'spam4.me', 'tempmail.com', 'throwam.com', 'dispostable.com',
        'maildrop.cc', 'getairmail.com', 'fakeinbox.com', 'spamgourmet.com',
        'mailnull.com', 'spamcero.com', '0-mail.com', 'objectmail.com',
        'ownmail.net', 'peewit.fr', 'safe-mail.net', 'spamfree24.org',
        'tempinbox.com', 'trash-mail.at', 'trashmail.at', 'trashmail.io',
        'trashmail.me', 'trashmail.net', 'trashmail.xyz', 'wegwerfmail.de',
        'wegwerfmail.net', 'wegwerfmail.org', 'discard.email', 'spamgourmet.net',
    ];

    /**
     * Suspicious TLDs that commonly appear in low-quality signups.
     *
     * @var array<int, string>
     */
    private const SUSPICIOUS_TLDS = [
        '.xyz', '.top', '.click', '.work', '.online', '.site', '.website',
        '.space', '.party', '.bid', '.loan', '.review', '.science', '.win',
    ];

    public function evaluate(Request $request, string $email): FraudSignals
    {
        $score = 0;
        $signals = [];
        $ip = $request->ip() ?? '0.0.0.0';

        [$localPart, $domain] = $this->parseEmail($email);

        // Signal: disposable email domain
        if ($this->isDisposableDomain($domain)) {
            $score += 60;
            $signals['disposable_domain'] = $domain;
        }

        // Signal: Gmail+ alias trick (user+anything@gmail.com used to bypass uniqueness)
        if (str_contains($localPart, '+') && in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $score += 30;
            $signals['gmail_alias'] = true;
        }

        // Signal: suspicious TLD
        foreach (self::SUSPICIOUS_TLDS as $tld) {
            if (str_ends_with($domain, $tld)) {
                $score += 20;
                $signals['suspicious_tld'] = $tld;
                break;
            }
        }

        // Signal: very short local part (< 3 chars) — common in auto-generated junk signups
        if (strlen($localPart) < 3) {
            $score += 15;
            $signals['short_local_part'] = $localPart;
        }

        // Signal: IP from extended blocklist (static — replace with GeoIP API for prod)
        $blockedPrefixes = config('fraud.blocked_ip_prefixes', []);
        foreach ($blockedPrefixes as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                $score += 50;
                $signals['blocked_ip_prefix'] = $prefix;
                break;
            }
        }

        return new FraudSignals(
            score: $score,
            signals: $signals,
            ip: $ip,
            email: $email,
        );
    }

    /**
     * @return array{string, string}
     */
    private function parseEmail(string $email): array
    {
        $parts = explode('@', strtolower($email), 2);

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }

    private function isDisposableDomain(string $domain): bool
    {
        $staticList = self::DISPOSABLE_DOMAINS;
        $configList = config('fraud.disposable_domains', []);

        return in_array($domain, array_merge($staticList, $configList), true);
    }
}

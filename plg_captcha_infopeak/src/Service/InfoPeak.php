<?php

/**
 * @package     InfoPeak Captcha for Joomla
 * @copyright   (C) 2026 InfoPeak
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace InfoPeak\Plugin\Captcha\InfoPeak\Service;

use Joomla\CMS\Log\Log;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Talks to the InfoPeak Captcha API and builds the links to InfoPeak.
 *
 * Only two things ever leave the site: the server-side token check when a
 * protected form is sent, and a key check when an administrator opens the
 * plugin settings. Nothing about visitors or the site is added to either.
 */
final class InfoPeak
{
    public const VERSION = '1.0.0';

    public const API = 'https://captcha.infopeak.io';

    public const SCRIPT = self::API . '/infopeak-captcha.js';

    /** Public test keys: work on any domain, never count against a quota, never for production. */
    public const TEST_SITEKEY = 'ipk_test_sitekey';

    /** Results of a token check. */
    public const VALID       = 'valid';
    public const INVALID     = 'invalid';
    public const QUOTA       = 'quota';
    public const UNAVAILABLE = 'unavailable';
    public const BAD_KEYS    = 'bad_keys';

    /** Results of a key check. */
    public const KEYS_OK          = 'ok';
    public const KEYS_MISSING     = 'missing';
    public const UNKNOWN_SITEKEY  = 'unknown_sitekey';
    public const WRONG_SECRET     = 'wrong_secret';

    /** The 16 languages infopeak.io and the widget are available in. */
    private const LANGUAGES = ['en', 'da', 'de', 'fr', 'es', 'it', 'nl', 'pt', 'sv', 'no', 'fi', 'pl', 'cs', 'ro', 'uk', 'et'];

    private Http $http;

    public function __construct(
        private string $sitekey,
        private string $secret,
        ?Http $http = null
    ) {
        $this->http = $http ?? (new HttpFactory())->getHttp(['userAgent' => 'InfoPeakCaptcha-Joomla/' . self::VERSION]);
    }

    public function isConfigured(): bool
    {
        return $this->sitekey !== '' && $this->secret !== '';
    }

    /**
     * Checks a token from the widget. Fails closed: anything but a clear yes is a no.
     */
    public function verify(string $token): string
    {
        if (!$this->isConfigured()) {
            return self::BAD_KEYS;
        }

        if ($token === '') {
            return self::INVALID;
        }

        [$code, $body] = $this->siteverify($token);

        if ($code === 200) {
            if (($body['valid'] ?? null) === true) {
                return self::VALID;
            }

            // A genuine token refused because the monthly quota and its reserve are used up.
            return ($body['quota_exceeded'] ?? null) === true ? self::QUOTA : self::INVALID;
        }

        if ($code === 401 || $code === 404) {
            $this->log('The sitekey or secret was refused by InfoPeak (HTTP ' . $code . '). Check the keys in the plugin settings.');

            return self::BAD_KEYS;
        }

        $this->log('InfoPeak Captcha could not be reached (HTTP ' . $code . '). The form was not accepted.');

        return self::UNAVAILABLE;
    }

    /**
     * Asks InfoPeak whether the sitekey exists and the secret belongs to it, by
     * checking a token that cannot be valid. Uses no quota.
     */
    public function checkKeys(): string
    {
        if (!$this->isConfigured()) {
            return self::KEYS_MISSING;
        }

        [$code] = $this->siteverify('key-check');

        return match ($code) {
            200     => self::KEYS_OK,
            401     => self::WRONG_SECRET,
            404     => self::UNKNOWN_SITEKEY,
            default => self::UNAVAILABLE,
        };
    }

    /**
     * @return array{0: int, 1: array}  HTTP status (0 when unreachable) and the decoded body.
     */
    private function siteverify(string $token): array
    {
        try {
            $response = $this->http->post(
                self::API . '/api/v1/pow/siteverify',
                json_encode(['sitekey' => $this->sitekey, 'secret' => $this->secret, 'token' => $token]),
                ['Content-Type' => 'application/json'],
                8
            );
        } catch (\Throwable $e) {
            return [0, []];
        }

        $body = json_decode((string) $response->getBody(), true);

        return [(int) $response->getStatusCode(), \is_array($body) ? $body : []];
    }

    private function log(string $message): void
    {
        Log::add($message, Log::WARNING, 'plg_captcha_infopeak');
    }

    /**
     * A Joomla language tag (da-DK, nb-NO) as one of InfoPeak's language codes, or '' when there is none.
     */
    public static function language(string $tag): string
    {
        $code = strtolower(substr($tag, 0, 2));

        if ($code === 'nb' || $code === 'nn') {
            $code = 'no';
        }

        return \in_array($code, self::LANGUAGES, true) ? $code : '';
    }

    /**
     * Links from the settings page. They carry the administrator's language and a plain utm_source
     * that says the visit came from this plugin. Nothing about the site or its visitors is added.
     */
    public static function link(string $what, string $lang): string
    {
        $utm = ['utm_source' => 'joomla_plugin', 'utm_medium' => 'plugin'];

        switch ($what) {
            case 'partner':
                return 'https://partner.infopeak.io/' . ($lang !== '' ? $lang : 'en') . '/?'
                    . http_build_query($utm + ['utm_content' => 'partner']);

            case 'console':
                // Someone who is not signed in is asked to sign in and comes back to the console.
                return 'https://infopeak.io/' . ($lang !== '' ? $lang . '/' : '') . 'captcha';

            case 'signup':
            default:
                $args = ['plan' => 'captcha'];

                if ($lang !== '') {
                    $args['lang'] = $lang;
                }

                return 'https://accounts.infopeak.io/signup?' . http_build_query($args + $utm + ['utm_content' => 'settings']);
        }
    }
}

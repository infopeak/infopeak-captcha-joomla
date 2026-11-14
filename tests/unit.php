<?php

/**
 * Checks how the plugin reads every answer the InfoPeak API can give, without calling the API.
 * Runs inside a Joomla 5 or 6 installation:
 *
 *   php tests/unit.php /path/to/joomla
 *
 * Exits with 1 when a check fails. Not part of the installable package.
 */

use InfoPeak\Plugin\Captcha\InfoPeak\Provider\InfoPeakCaptchaProvider;
use InfoPeak\Plugin\Captcha\InfoPeak\Service\InfoPeak;
use Joomla\CMS\Form\Field\CaptchaField;
use Joomla\Http\Http;
use Joomla\Http\Response;

const _JEXEC = 1;

$root = rtrim($argv[1] ?? '', '/');
\define('JPATH_BASE', $root);
require $root . '/includes/defines.php';
require $root . '/includes/framework.php';

$src = \dirname(__DIR__) . '/plg_captcha_infopeak/src';
require $src . '/Service/InfoPeak.php';
require $src . '/Provider/InfoPeakCaptchaProvider.php';

/** Answers every POST with a fixed status and body, or throws to act as an unreachable server. */
final class FakeHttp extends Http
{
    public array $sent = [];

    public function __construct(private int $status, private string $body = '')
    {
    }

    public function post($url, $data, array $headers = [], $timeout = null)
    {
        $this->sent[] = [$url, json_decode($data, true), $headers, $timeout];

        if ($this->status === 0) {
            throw new \RuntimeException('Connection timed out');
        }

        $response = new Response('php://memory', $this->status);
        $response->getBody()->write($this->body);

        return $response;
    }
}

$failed = 0;
$check  = function (string $name, $expected, $actual) use (&$failed): void {
    $ok      = $expected === $actual;
    $failed += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok ? '' : ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')') . "\n";
};

$service = fn (int $status, string $body = '', string $key = 'sk', string $secret = 'sec') => new InfoPeak($key, $secret, new FakeHttp($status, $body));

// Token checks.
$check('valid token', InfoPeak::VALID, $service(200, '{"valid":true,"over_limit":false}')->verify('t'));
$check('token from the reserve is valid', InfoPeak::VALID, $service(200, '{"valid":true,"over_limit":true,"reserve":true}')->verify('t'));
$check('invalid token', InfoPeak::INVALID, $service(200, '{"valid":false,"quota_exceeded":false}')->verify('t'));
$check('quota and reserve used up', InfoPeak::QUOTA, $service(200, '{"valid":false,"quota_exceeded":true}')->verify('t'));
$check('valid must be boolean true', InfoPeak::INVALID, $service(200, '{"valid":"true"}')->verify('t'));
$check('unreadable body', InfoPeak::INVALID, $service(200, 'not json')->verify('t'));
$check('wrong secret', InfoPeak::BAD_KEYS, $service(401, '{"error":"forkert secret"}')->verify('t'));
$check('unknown sitekey', InfoPeak::BAD_KEYS, $service(404, '{"error":"ukendt sitekey"}')->verify('t'));
$check('server error', InfoPeak::UNAVAILABLE, $service(500)->verify('t'));
$check('rate limited', InfoPeak::UNAVAILABLE, $service(429)->verify('t'));
$check('unreachable', InfoPeak::UNAVAILABLE, $service(0)->verify('t'));
$check('empty token is refused without a call', InfoPeak::INVALID, $service(200, '{"valid":true}')->verify(''));
$check('no keys', InfoPeak::BAD_KEYS, $service(200, '{"valid":true}', '', '')->verify('t'));

// Key checks.
$check('keys ok', InfoPeak::KEYS_OK, $service(200, '{"valid":false}')->checkKeys());
$check('keys wrong secret', InfoPeak::WRONG_SECRET, $service(401)->checkKeys());
$check('keys unknown sitekey', InfoPeak::UNKNOWN_SITEKEY, $service(404)->checkKeys());
$check('keys unreachable', InfoPeak::UNAVAILABLE, $service(0)->checkKeys());
$check('keys missing', InfoPeak::KEYS_MISSING, $service(200, '', 'sk', '')->checkKeys());

// What is sent: sitekey, secret and token to the documented endpoint, with an 8 second timeout.
$http = new FakeHttp(200, '{"valid":true}');
(new InfoPeak('sk', 'sec', $http))->verify('tok');
$check('endpoint', 'https://captcha.infopeak.io/api/v1/pow/siteverify', $http->sent[0][0]);
$check('payload', ['sitekey' => 'sk', 'secret' => 'sec', 'token' => 'tok'], $http->sent[0][1]);
$check('timeout', 8, $http->sent[0][3]);

// Languages and links.
$check('da-DK', 'da', InfoPeak::language('da-DK'));
$check('nb-NO', 'no', InfoPeak::language('nb-NO'));
$check('unsupported language', '', InfoPeak::language('ja-JP'));
$check('signup link', 'https://accounts.infopeak.io/signup?plan=captcha&lang=da&utm_source=joomla_plugin&utm_medium=plugin&utm_content=settings', InfoPeak::link('signup', 'da'));
$check('signup link without language', 'https://accounts.infopeak.io/signup?plan=captcha&utm_source=joomla_plugin&utm_medium=plugin&utm_content=settings', InfoPeak::link('signup', ''));
$check('console link', 'https://infopeak.io/da/captcha', InfoPeak::link('console', 'da'));
$check('partner link', 'https://partner.infopeak.io/en/?utm_source=joomla_plugin&utm_medium=plugin&utm_content=partner', InfoPeak::link('partner', ''));

// The provider names the actual reason in the field's validation message.
$reasons = [
    [200, '{"valid":false,"quota_exceeded":true}', 'PLG_CAPTCHA_INFOPEAK_ERROR_QUOTA'],
    [0, '', 'PLG_CAPTCHA_INFOPEAK_ERROR_UNAVAILABLE'],
    [401, '', 'PLG_CAPTCHA_INFOPEAK_ERROR_NOT_CONFIGURED'],
    [200, '{"valid":false}', 'PLG_CAPTCHA_INFOPEAK_ERROR_INVALID'],
];

$field = (new \ReflectionClass(CaptchaField::class))->newInstanceWithoutConstructor();

foreach ($reasons as [$status, $body, $message]) {
    $provider = new InfoPeakCaptchaProvider($service($status, $body), 'sk', null);
    $element  = new \SimpleXMLElement('<field name="captcha" type="captcha" validate="captcha"/>');
    $provider->setupField($field, $element);
    $check('refused (' . $status . ')', false, $provider->checkAnswer('t'));
    $check('message for ' . $message, $message, (string) $element['message']);
}

$provider = new InfoPeakCaptchaProvider($service(200, '{"valid":true}'), 'sk', null);
$check('accepted', true, $provider->checkAnswer('t'));
$check('null code refused', false, (new InfoPeakCaptchaProvider($service(200, '{"valid":true}'), 'sk', null))->checkAnswer(null));
$check('getName matches the plugin element', 'infopeak', $provider->getName());

echo $failed === 0 ? "\nAll checks passed.\n" : "\n$failed check(s) failed.\n";
exit($failed === 0 ? 0 : 1);

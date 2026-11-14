<?php

/**
 * @package     InfoPeak Captcha for Joomla
 * @copyright   (C) 2026 InfoPeak
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace InfoPeak\Plugin\Captcha\InfoPeak\Provider;

use InfoPeak\Plugin\Captcha\InfoPeak\Service\InfoPeak;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Captcha\CaptchaProviderInterface;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Shows the InfoPeak proof-of-work widget in Joomla forms and checks its token on the server.
 *
 * The widget writes its single-use token into the captcha field itself
 * (data-response-field-name), so Joomla hands it straight to checkAnswer().
 * Every form Joomla protects is sent with a full page load, which builds a
 * fresh widget, so no token reset is needed.
 */
final class InfoPeakCaptchaProvider implements CaptchaProviderInterface
{
    /** Validation message keys, one per reason a token is refused. */
    private const MESSAGES = [
        InfoPeak::INVALID     => 'PLG_CAPTCHA_INFOPEAK_ERROR_INVALID',
        InfoPeak::QUOTA       => 'PLG_CAPTCHA_INFOPEAK_ERROR_QUOTA',
        InfoPeak::UNAVAILABLE => 'PLG_CAPTCHA_INFOPEAK_ERROR_UNAVAILABLE',
        InfoPeak::BAD_KEYS    => 'PLG_CAPTCHA_INFOPEAK_ERROR_NOT_CONFIGURED',
    ];

    /** The captcha field being validated, so the message can name the actual reason. */
    private ?\SimpleXMLElement $element = null;

    public function __construct(
        private InfoPeak $service,
        private string $sitekey,
        private ?CMSApplicationInterface $application
    ) {
    }

    public function getName(): string
    {
        return 'infopeak';
    }

    public function display(string $name = '', array $attributes = []): string
    {
        if (!$this->application instanceof CMSWebApplicationInterface) {
            return '';
        }

        // Selected as captcha before the keys were entered: say so instead of showing a form that can never be sent.
        if (!$this->service->isConfigured()) {
            return '<div class="alert alert-warning" role="alert">'
                . htmlspecialchars(Text::_('PLG_CAPTCHA_INFOPEAK_ERROR_NOT_CONFIGURED'), ENT_QUOTES, 'UTF-8')
                . '</div>';
        }

        $this->application->getDocument()->getWebAssetManager()->registerAndUseScript(
            'plg_captcha_infopeak.widget',
            InfoPeak::SCRIPT,
            [],
            ['defer' => true]
        );

        $html = [
            'class'                    => 'infopeak-captcha',
            'id'                       => $attributes['id'] ?? '',
            'data-sitekey'             => $this->sitekey,
            'data-lang'                => InfoPeak::language($this->application->getLanguage()->getTag()),
            'data-response-field-name' => $name,
        ];

        $out = '';

        foreach (array_filter($html, 'strlen') as $key => $value) {
            $out .= ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<div' . $out . '></div>';
    }

    public function checkAnswer(?string $code = null): bool
    {
        $result = $this->service->verify((string) $code);

        if ($result === InfoPeak::VALID) {
            return true;
        }

        if ($this->element !== null) {
            $this->element['message'] = self::MESSAGES[$result];
        }

        return false;
    }

    public function setupField(FormField $field, \SimpleXMLElement $element): void
    {
        $this->element = $element;
    }
}

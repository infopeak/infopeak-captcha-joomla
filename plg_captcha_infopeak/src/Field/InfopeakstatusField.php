<?php

/**
 * @package     InfoPeak Captcha for Joomla
 * @copyright   (C) 2026 InfoPeak
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace InfoPeak\Plugin\Captcha\InfoPeak\Field;

use InfoPeak\Plugin\Captcha\InfoPeak\Service\InfoPeak;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The status panel in the plugin settings, in two parts around the key fields:
 * section="top" says whether the site is protected and what is left to do,
 * section="bottom" lists the forms and the help that applies.
 * It reads the saved settings, so it updates after "Save".
 */
final class InfopeakstatusField extends FormField
{
    protected $type = 'Infopeakstatus';

    /** The Joomla forms that use the captcha, grouped by the component whose options choose it. */
    private const COMPONENTS = [
        'com_users'   => ['PLG_CAPTCHA_INFOPEAK_FORM_REGISTRATION', 'PLG_CAPTCHA_INFOPEAK_FORM_RESET', 'PLG_CAPTCHA_INFOPEAK_FORM_REMIND'],
        'com_contact' => ['PLG_CAPTCHA_INFOPEAK_FORM_CONTACT'],
    ];

    /** Shared by both sections, so the keys are checked with InfoPeak once per page. */
    private static ?array $state = null;

    protected function getLabel()
    {
        return '';
    }

    protected function getInput()
    {
        $state = self::$state ??= $this->state();

        return (string) $this->element['section'] === 'bottom' ? $this->bottom($state) : $this->top($state);
    }

    private function state(): array
    {
        $app     = Factory::getApplication();
        $sitekey = trim((string) $this->form->getValue('sitekey', 'params', ''));
        $global  = (string) $app->get('captcha', '');
        $keys    = (new InfoPeak($sitekey, trim((string) $this->form->getValue('secret', 'params', ''))))->checkKeys();

        // Which captcha each component actually uses: its own choice, or Global Configuration when it has none.
        $forms     = [];
        $protected = 0;

        foreach (self::COMPONENTS as $component => $labels) {
            $own  = (string) ComponentHelper::getParams($component)->get('captcha', '');
            $uses = $own !== '' ? $own : $global;

            foreach ($labels as $label) {
                $forms[] = [$label, $uses, $component];
                $protected += $uses === 'infopeak' ? 1 : 0;
            }
        }

        return [
            'lang'      => InfoPeak::language($app->getLanguage()->getTag()),
            'keys'      => $keys,
            'keysOk'    => $keys === InfoPeak::KEYS_OK,
            'testKeys'  => $sitekey === InfoPeak::TEST_SITEKEY,
            'enabled'   => (int) $this->form->getValue('enabled', null, 0) === 1,
            'default'   => $global === 'infopeak',
            'forms'     => $forms,
            'protected' => $protected,
        ];
    }

    private function top(array $s): string
    {
        $active = $s['keysOk'] && $s['enabled'] && $s['protected'] > 0;
        $html   = [];

        if ($active && !$s['testKeys']) {
            $html[] = $this->alert('success', 'PLG_CAPTCHA_INFOPEAK_STATUS_ACTIVE', Text::plural('PLG_CAPTCHA_INFOPEAK_STATUS_ACTIVE_DESC', $s['protected']));
        } elseif ($active) {
            $html[] = $this->alert('warning', 'PLG_CAPTCHA_INFOPEAK_STATUS_TEST', Text::_('PLG_CAPTCHA_INFOPEAK_STATUS_TEST_DESC'));
        } else {
            $html[] = $this->alert('warning', 'PLG_CAPTCHA_INFOPEAK_STATUS_INACTIVE', Text::_('PLG_CAPTCHA_INFOPEAK_STATUS_INACTIVE_DESC'));
        }

        $keysText = $s['keysOk']
            ? ($s['testKeys'] ? 'PLG_CAPTCHA_INFOPEAK_STEP_KEYS_TEST' : 'PLG_CAPTCHA_INFOPEAK_STEP_KEYS_OK')
            : 'PLG_CAPTCHA_INFOPEAK_STEP_KEYS_' . strtoupper($s['keys']);

        $html[] = '<ol class="list-unstyled mb-4">';
        $html[] = $this->step($s['keysOk'] && !$s['testKeys'], $keysText);
        $html[] = $this->step($s['enabled'], $s['enabled'] ? 'PLG_CAPTCHA_INFOPEAK_STEP_ENABLED' : 'PLG_CAPTCHA_INFOPEAK_STEP_DISABLED');
        $html[] = $this->step(
            $s['default'],
            $s['default'] ? 'PLG_CAPTCHA_INFOPEAK_STEP_DEFAULT' : 'PLG_CAPTCHA_INFOPEAK_STEP_NOT_DEFAULT',
            $s['default'] ? '' : '<a class="btn btn-sm btn-primary ms-2" href="' . $this->e(Route::_('index.php?option=com_config')) . '">'
                . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_OPEN_GLOBAL_CONFIG')) . '</a>'
        );
        $html[] = '</ol>';

        // Where the keys come from, right above the fields they go into.
        if (!$s['keysOk'] || $s['testKeys']) {
            $html[] = '<div class="card mb-2"><div class="card-body">'
                . '<h3 class="h5 card-title">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_ACCOUNT_HEADING')) . '</h3>'
                . '<p class="card-text">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_ACCOUNT_DESC')) . '</p>'
                . '<a class="btn btn-primary me-2" href="' . $this->e(InfoPeak::link('signup', $s['lang'])) . '" target="_blank" rel="noopener">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_CREATE_ACCOUNT')) . '</a>'
                . '<a class="btn btn-outline-secondary" href="' . $this->e(InfoPeak::link('console', $s['lang'])) . '" target="_blank" rel="noopener">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_SIGN_IN')) . '</a>'
                . '<p class="small text-muted mt-3 mb-0">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_TEST_KEYS')) . ' <code>ipk_test_sitekey</code> / <code>ipk_test_secret</code></p>'
                . '</div></div>';
        }

        return implode("\n", $html);
    }

    private function bottom(array $s): string
    {
        $html   = [];
        $html[] = '<h3 class="h5 mt-2">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_FORMS_HEADING')) . '</h3>';
        $html[] = '<table class="table table-sm mb-2"><tbody>';

        foreach ($s['forms'] as [$label, $uses, $component]) {
            if ($uses === 'infopeak') {
                $state = '<span class="badge bg-success">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_FORM_PROTECTED')) . '</span>';
            } elseif ($uses === '' || $uses === '0') {
                $state = '<span class="badge bg-secondary">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_FORM_NONE')) . '</span>';
            } else {
                $state = '<span class="badge bg-warning text-dark">' . $this->e(Text::sprintf('PLG_CAPTCHA_INFOPEAK_FORM_OTHER', $uses)) . '</span>';
            }

            $html[] = '<tr><td>' . $this->e(Text::_($label)) . '</td><td class="text-end">' . $state
                . ' <a class="ms-2" href="' . $this->e(Route::_('index.php?option=com_config&view=component&component=' . $component)) . '">'
                . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_FORM_CHANGE')) . '</a></td></tr>';
        }

        $html[] = '</tbody></table>';
        $html[] = '<p class="small text-muted mb-4">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_FORMS_NOTE')) . '</p>';

        // Domain binding, once the real keys work.
        if ($s['keysOk'] && !$s['testKeys']) {
            $html[] = '<p class="mb-4">' . $this->e(Text::sprintf('PLG_CAPTCHA_INFOPEAK_DOMAIN_HINT', Uri::getInstance(Uri::root())->getHost()))
                . ' <a href="' . $this->e(InfoPeak::link('console', $s['lang'])) . '" target="_blank" rel="noopener">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_OPEN_CONSOLE')) . '</a></p>';
        }

        // Content Security Policy from Joomla's own HTTP Headers plugin.
        if ($this->cspEnabled()) {
            $html[] = '<div class="alert alert-info"><p class="mb-2">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_CSP')) . '</p>'
                . '<code>script-src https://captcha.infopeak.io</code><br><code>connect-src https://captcha.infopeak.io</code><br><code>worker-src blob:</code></div>';
        }

        $html[] = '<p class="small mb-0"><strong>' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_PARTNER_HEADING')) . '</strong> '
            . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_PARTNER_DESC'))
            . ' <a href="' . $this->e(InfoPeak::link('partner', $s['lang'])) . '" target="_blank" rel="noopener">' . $this->e(Text::_('PLG_CAPTCHA_INFOPEAK_PARTNER_LINK')) . '</a></p>';

        return implode("\n", $html);
    }

    private function cspEnabled(): bool
    {
        $plugin = PluginHelper::getPlugin('system', 'httpheaders');

        return \is_object($plugin) && (int) (new Registry($plugin->params ?? ''))->get('contentsecuritypolicy', 0) === 1;
    }

    private function alert(string $type, string $heading, string $text): string
    {
        return '<div class="alert alert-' . $type . '" role="status"><h3 class="alert-heading h5">' . $this->e(Text::_($heading)) . '</h3>'
            . '<p class="mb-0">' . $this->e($text) . '</p></div>';
    }

    private function step(bool $done, string $text, string $extra = ''): string
    {
        $icon = $done ? '<span class="icon-check text-success me-2" aria-hidden="true"></span>' : '<span class="icon-times text-danger me-2" aria-hidden="true"></span>';

        return '<li class="mb-2">' . $icon . $this->e(Text::_($text)) . ($extra !== '' ? ' ' . $extra : '') . '</li>';
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

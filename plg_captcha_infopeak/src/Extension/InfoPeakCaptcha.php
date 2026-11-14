<?php

/**
 * @package     InfoPeak Captcha for Joomla
 * @copyright   (C) 2026 InfoPeak
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace InfoPeak\Plugin\Captcha\InfoPeak\Extension;

use InfoPeak\Plugin\Captcha\InfoPeak\Provider\InfoPeakCaptchaProvider;
use InfoPeak\Plugin\Captcha\InfoPeak\Service\InfoPeak;
use Joomla\CMS\Event\Captcha\CaptchaSetupEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Registers InfoPeak Captcha with Joomla's captcha system (Joomla 5.0 and later).
 * Joomla then offers it as "Default Captcha" in Global Configuration and in the
 * Users and Contacts options.
 */
final class InfoPeakCaptcha extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onCaptchaSetup' => 'onCaptchaSetup'];
    }

    public function onCaptchaSetup(CaptchaSetupEvent $event): void
    {
        $sitekey = trim((string) $this->params->get('sitekey', ''));
        $service = new InfoPeak($sitekey, trim((string) $this->params->get('secret', '')));

        $event->getCaptchaRegistry()->add(new InfoPeakCaptchaProvider($service, $sitekey, $this->getApplication()));
    }
}

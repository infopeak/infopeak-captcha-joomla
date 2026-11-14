<?php

/**
 * @package     InfoPeak Captcha for Joomla
 * @copyright   (C) 2026 InfoPeak
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use InfoPeak\Plugin\Captcha\InfoPeak\Extension\InfoPeakCaptcha;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $config = (array) PluginHelper::getPlugin('captcha', 'infopeak');

                // Joomla 5.0 to 5.3 require the dispatcher as the first argument. Later
                // versions take only the config and deprecate passing the dispatcher.
                $first = (new \ReflectionMethod(CMSPlugin::class, '__construct'))->getParameters()[0];

                $plugin = $first->getName() === 'dispatcher'
                    ? new InfoPeakCaptcha($container->get(DispatcherInterface::class), $config)
                    : new InfoPeakCaptcha($config);

                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};

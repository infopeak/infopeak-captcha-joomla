<?php

/**
 * @package     InfoPeak Captcha for Joomla
 * @copyright   (C) 2026 InfoPeak
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Checks the Joomla and PHP versions, switches the plugin on after the first
 * install, and refuses to uninstall while a form still depends on it.
 */
class PlgCaptchaInfopeakInstallerScript extends InstallerScript
{
    protected $minimumPhp = '8.1.0';

    protected $minimumJoomla = '5.0.0';

    public function preflight($type, $parent)
    {
        if ($type === 'uninstall') {
            return $this->canUninstall();
        }

        return parent::preflight($type, $parent);
    }

    public function postflight($type, $parent)
    {
        if ($type !== 'install') {
            return true;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('captcha'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('infopeak'));
        $db->setQuery($query)->execute();

        $id = (int) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('captcha'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('infopeak'))
        )->loadResult();

        $this->loadLanguage($parent);

        Factory::getApplication()->enqueueMessage(
            Text::sprintf('PLG_CAPTCHA_INFOPEAK_INSTALLED', 'index.php?option=com_plugins&task=plugin.edit&extension_id=' . $id),
            'message'
        );

        return true;
    }

    /**
     * A form whose captcha is uninstalled can no longer be sent. Ask for another captcha first.
     */
    private function canUninstall(): bool
    {
        $inUse = Factory::getApplication()->get('captcha') === 'infopeak';

        foreach (['com_users', 'com_contact'] as $component) {
            $inUse = $inUse || ComponentHelper::getParams($component)->get('captcha') === 'infopeak';
        }

        if (!$inUse) {
            return true;
        }

        $this->loadLanguage(null);
        Factory::getApplication()->enqueueMessage(Text::_('PLG_CAPTCHA_INFOPEAK_UNINSTALL_IN_USE'), 'error');

        return false;
    }

    private function loadLanguage(?InstallerAdapter $parent): void
    {
        $path = $parent ? $parent->getParent()->getPath('source') : JPATH_PLUGINS . '/captcha/infopeak';
        Factory::getApplication()->getLanguage()->load('plg_captcha_infopeak', $path);
    }
}

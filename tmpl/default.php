<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

if (version_compare(JVERSION, 3, '>=')) {
    JHtml::_('jquery.framework');
} else {
    JHtml::_('behavior.framework', true);
}

JFactory::getDocument()->addStyleSheet(JUri::base(true) . '/modules/' . $module->module . '/tmpl/default.css');
JFactory::getDocument()->addScript(JUri::base(true) . '/modules/' . $module->module . '/tmpl/default.js');
?>
<div class="mod_wow_raid_progress_wotlk">
    <?php foreach ($raids as $zoneId => $zone) : ?>
        <ul class="z<?php echo $zoneId; ?>">
            <li class="header">
                <span class="p" style="width:<?php echo $zone['stats']['percent']; ?>%;"></span>
                <?php //echo JHtml::_('link', $zone['link'], JText::_('MOD_WOW_RAID_PROGRESS_WOTLK_ZONE_' . $zoneId), array('target' => '_blank')); ?>
                <?php echo JText::_('MOD_WOW_RAID_PROGRESS_WOTLK_ZONE_' . $zoneId); ?>
                <span class="k" title="<?php echo $zone['stats']['percent']; ?>%"><?php echo JText::sprintf('MOD_WOW_RAID_PROGRESS_WOTLK_MODE_' . strtoupper($zone['stats']['mode']), $zone['stats']['kills'], $zone['stats']['bosses']); ?></span>
            </li>
            <li class="npcs<?php echo ($zone['opened'] == true) ? ' open' : ''; ?>">
                <ul>
                    <?php foreach ($zone['npcs'] as $npc => $data) : ?>
                        <li class="npc">
                            <?php echo JHtml::_('link', $data['link'], JText::_('MOD_WOW_RAID_PROGRESS_WOTLK_NPC_' . $npc), array('target' => '_blank')); ?>
                            <span class="<?php echo ($data['heroic'] === true) ? ' heroic' : (($data['normal'] === true) ? ' normal' : ''); ?>"> </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>
        </ul>
    <?php endforeach; ?>
</div>
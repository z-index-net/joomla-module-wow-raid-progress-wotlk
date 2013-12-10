<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die();

final class mod_wow_raid_progress_wotlk
{

    private $params = null;
    private $raids = array(
        // The Ruby Sanctum
        4987 => array(
            'link' => '',
            'stats' => array('kills' => 0, 'mode' => 'normal'),
            'npcs' => array(
                // Halion
                39863 => array(
                    'link' => '',
                    'normal' => 0,
                    'heroic' => 0
                )
            ),
        )
    );

    public function __construct(JRegistry &$params)
    {
        $this->params = $params;
    }

    public function getRaids()
    {
        if ($this->params->get('mode') == 'auto') {
            $url = 'http://' . $this->params->get('region') . '.battle.net/api/wow/guild/' . $this->params->get('realm') . '/' . $this->params->get('guild') . '?fields=members,achievements';

            $result = $this->remoteContent($url);

            if (!is_object($result)) {
                return $result;
            }

            $this->checkNormal($result->achievements);

            if ($this->params->get('heroic') && $this->params->get('ranks')) {
                $this->checkHeroic($result->members);
            }
        }

        if ($hidden = $this->params->get('hide')) {
            foreach ($hidden as $hide) {
                unset($this->raids[$hide]);
            }
        }

        $this->adjustments();

        // at last replace links and count mode-kills
        foreach ($this->raids as $zoneId => &$zone) {
            $zone['link'] = $this->link($zone['link'], $zoneId);
            $heroic = $normal = 0;
            foreach ($zone['npcs'] as $npcId => &$npc) {
                $npc['link'] = $this->link($npc['link'], $npcId, true);
                if ($npc['heroic'] === true) {
                    $heroic++;
                }
                if ($npc['normal'] === true) {
                    $normal++;
                }
            }

            if ($normal > 0) {
                $zone['stats']['kills'] = $normal;
            }

            if ($heroic > 0) {
                $zone['stats']['kills'] = $heroic;
                $zone['stats']['mode'] = 'heroic';
            }

            $zone['opened'] = in_array($zoneId, (array)$this->params->get('opened'));

            $zone['stats']['bosses'] = count($zone['npcs']);
            $zone['stats']['percent'] = round(($zone['stats']['kills'] / $zone['stats']['bosses']) * 100);
        }

        return $this->raids;
    }

    private function remoteContent($url)
    {
        $cache = JFactory::getCache('wow', 'output');
        $cache->setCaching(1);
        $cache->setLifeTime($this->params->get('cache_time', 24) * 60 + rand(0, 60)); // randomize cache time a little bit for each url

        $key = md5($url);

        if (!$result = $cache->get($key)) {
            try {
                $http = new JHttp(new JRegistry, new JHttpTransportCurl(new JRegistry));
                $http->setOption('userAgent', 'Joomla! ' . JVERSION . '; WoW Raid Progress - WotLK; php/' . phpversion());

                $result = $http->get($url, null, $this->params->get('timeout', 10));
            } catch (Exception $e) {
                return $e->getMessage();
            }

            $cache->store($result, $key);
        }

        if ($result->code != 200) {
            return __CLASS__ . ' HTTP-Status ' . JHtml::_('link', 'http://wikipedia.org/wiki/List_of_HTTP_status_codes#' . $result->code, $result->code, array('target' => '_blank'));
        }

        return json_decode($result->body);
    }

    private function checkNormal(stdClass $achievements)
    {
        foreach ($this->raids as &$zone) {
            foreach ($zone['npcs'] as &$npc) {
                $npc['normal'] = in_array($npc['normal'], $achievements->criteria);
            }
        }
    }

    private function checkHeroic(array &$members)
    {
        $heroicIds = $this->getHeroicIDs();
        foreach ($members as &$member) {
            if (in_array($member->rank, $this->params->get('ranks'))) {
                $member->achievements = $this->loadMember($member->character->name);
                if ($member->achievements) {
                    foreach ($heroicIds as $id => $zoneNpc) {
                        list ($npc, $zone) = explode(':', $zoneNpc, 2);
                        if (in_array($id, $member->achievements->achievementsCompleted)) {
                            $this->raids[$zone]['npcs'][$npc]['heroic']++;
                        }
                    }
                }
            }
        }

        foreach ($this->raids as &$zone) {
            foreach ($zone['npcs'] as &$npc) {
                $npc['heroic'] = (bool)($npc['heroic'] >= $this->params->get('successful', 5));
            }
        }
    }

    private function getHeroicIDs()
    {
        $result = array();
        foreach ($this->raids as $zoneId => &$zone) {
            foreach ($zone['npcs'] as $npc => &$modes) {
                $result[$modes['heroic']] = $npc . ':' . $zoneId;
                $modes['heroic'] = 0;
            }
        }
        return $result;
    }

    private function loadMember($name)
    {
        $url = 'http://' . $this->params->get('region') . '.battle.net/api/wow/character/' . $this->params->get('realm') . '/' . $name . '?fields=achievements';

        $result = $this->remoteContent($url);

        if (!is_object($result) || !isset($result->achievements)) {
            return false;
        }

        return $result->achievements;
    }

    private function adjustments()
    {
        foreach ($this->raids as $zoneId => &$zone) {
            foreach ($zone['npcs'] as $npcId => &$npc) {
                if ($npc['heroic'] === true || $npc['normal'] === true) {
                    continue;
                }
                switch ($this->params->get('adjust_' . $npcId)) {
                    default:
                        continue;
                        break;

                    case 'no':
                        $npc['normal'] = false;
                        $npc['heroic'] = false;
                        break;

                    case 'normal':
                        $npc['normal'] = true;
                        break;

                    case 'heroic':
                        $npc['heroic'] = true;
                        break;
                }
            }
        }
        //$this->generateXML();
        //$this->generateINI();
    }

    private function link($link, $id, $npc = false)
    {
        if ($npc) {
            $sites['battle.net'] = 'http://' . $this->params->get('region') . '.battle.net/wow/' . $this->params->get('lang') . '/' . $link;
            $sites['wowhead.com'] = 'http://' . $this->params->get('lang') . '.wowhead.com/npc=' . $id;
            $sites['wowdb.com'] = 'http://www.wowdb.com/npcs/' . $id;
        } else {
            $sites['battle.net'] = 'http://' . $this->params->get('region') . '.battle.net/wow/' . $this->params->get('lang') . '/' . $link;
            $sites['wowhead.com'] = 'http://' . $this->params->get('lang') . '.wowhead.com/zone=' . $id;
            $sites['wowdb.com'] = 'http://www.wowdb.com/zones/' . $id;
        }

        return $sites[$this->params->get('link')];
    }

    private function generateINI()
    {

        header("Content-type: text/plain; charset=utf-8");

        foreach ($this->raids as $zoneId => &$zone) {

            echo strtoupper(__CLASS__) . '_ZONE_' . $zoneId . ' = ""' . PHP_EOL;

            foreach ($zone['npcs'] as $npcId => &$npc) {
                echo strtoupper(__CLASS__) . '_NPC_' . $npcId . ' = ""' . PHP_EOL;
            }

            echo PHP_EOL;
        }

        exit;
    }

    private function generateXML()
    {
        header("Content-type: text/xml; charset=utf-8");

        $xml = new SimpleXMLElement('<fieldset />');
        $xml->addAttribute('name', 'adjustments');

        $options = array('auto', 'no', 'normal', 'heroic');

        foreach ($this->raids as $zoneId => &$zone) {
            $spacer = $xml->addChild('field');
            $spacer->addAttribute('type', 'spacer');
            $spacer->addAttribute('class', 'label');
            $spacer->addAttribute('label', strtoupper(__CLASS__) . '_ZONE_' . $zoneId);
            foreach ($zone['npcs'] as $npcId => &$npc) {
                $adjust = $xml->addChild('field');
                $adjust->addAttribute('name', 'adjust_' . $npcId);
                $adjust->addAttribute('default', 'auto');
                $adjust->addAttribute('type', 'radio');
                $adjust->addAttribute('class', 'btn-group');
                $adjust->addAttribute('label', strtoupper(__CLASS__) . '_NPC_' . $npcId);
                foreach ($options as $option) {
                    $child = $adjust->addChild('option', strtoupper(__CLASS__ . '_RAID_' . $option));
                    $child->addAttribute('value', $option);
                }
            }
        }

        exit($xml->asXML());
    }
}
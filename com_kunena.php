<?php

/**
 * @author      Guillermo Vargas <guille@vargas.co.cr>
 * @author      Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link        http://www.z-index.net
 * @copyright   (c) 2005 - 2009 Joomla! Vargas. All rights reserved.
 * @copyright   (c) 2015 Branko Wilhelm. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

class xmap_com_kunena
{
    /**
     * @var array
     */
    private static $views = array('home', 'category');

    /**
     * @var bool
     */
    private static $enabled = false;

    public function __construct()
    {
        self::$enabled = JPluginHelper::isEnabled('system', 'kunena') && JComponentHelper::isEnabled('com_kunena');
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     */
    public static function getTree($xmap, stdClass $parent, array &$params)
    {
        $uri = new JUri($parent->link);

        if ($xmap->isNews || !self::$enabled || !in_array($uri->getVar('view'), self::$views))
        {
            return;
        }

        $params['include_topics'] = JArrayHelper::getValue($params, 'include_topics', 1);
        $params['include_topics'] = ($params['include_topics'] == 1 || ($params['include_topics'] == 2 && $xmap->view == 'xml') || ($params['include_topics'] == 3 && $xmap->view == 'html'));

        $params['include_pagination'] = JArrayHelper::getValue($params, 'include_pagination', 0);
        $params['include_pagination'] = ($params['include_pagination'] == 1 || ($params['include_pagination'] == 2 && $xmap->view == 'xml') || ($params['include_pagination'] == 3 && $xmap->view == 'html'));

        $params['cat_priority'] = JArrayHelper::getValue($params, 'cat_priority', $parent->priority);
        $params['cat_changefreq'] = JArrayHelper::getValue($params, 'cat_changefreq', $parent->changefreq);

        if ($params['cat_priority'] == -1)
        {
            $params['cat_changefreq'] = $parent->priority;
        }

        if ($params['cat_changefreq'] == -1)
        {
            $params['cat_changefreq'] = $parent->changefreq;
        }

        $params['topic_priority'] = JArrayHelper::getValue($params, 'topic_priority', $parent->changefreq);
        $params['topic_changefreq'] = JArrayHelper::getValue($params, 'topic_changefreq', $parent->changefreq);

        if ($params['topic_priority'] == -1)
        {
            $params['topic_priority'] = $parent->priority;
        }

        if ($params['topic_changefreq'] == -1)
        {
            $params['topic_changefreq'] = $parent->changefreq;
        }

        if ($params['include_topics'])
        {
            if ((int)$limit = JArrayHelper::getValue($params, 'max_topics', 0))
            {
                $params['limit'] = $limit;
            } else
            {
                $params['limit'] = 0;
            }

            if ((int)$days = JArrayHelper::getValue($params, 'max_age', 0))
            {
                $params['days'] = (JFactory::getDate()->toUnix() - (intval($days) * 86400));
            } else
            {
                $params['days'] = '';
            }
        }

        self::getCategoryTree($xmap, $parent, $params, $uri->getVar('catid', 0));
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     * @param int $catid
     */
    private static function getCategoryTree($xmap, stdClass $parent, array &$params, $catid)
    {
        /** @var KunenaForumCategory[] $categories */
        $categories = KunenaForumCategoryHelper::getChildren($catid);

        $xmap->changeLevel(1);

        foreach ($categories as $cat)
        {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->browserNav = $parent->browserNav;
            $node->uid = $parent->uid . '_c_' . $cat->id;
            $node->name = $cat->name;
            $node->priority = $params['cat_priority'];
            $node->changefreq = $params['cat_changefreq'];
            $node->link = $cat->getUrl();
            $node->secure = $parent->secure;

            if ($xmap->printNode($node))
            {
                self::getTopics($xmap, $parent, $params, $cat->id);
            }
        }

        $xmap->changeLevel(-1);
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     * @param int $catid
     */
    private static function getTopics($xmap, stdClass $parent, array &$params, $catid)
    {
        self::getCategoryTree($xmap, $parent, $params, $catid);

        if (!$params['include_topics'])
        {
            return;
        }

        /** @var KunenaForumTopic[] $topics */
        $topics = KunenaForumTopicHelper::getLatestTopics($catid, 0, $params['limit'], array('nolimit' => true, 'starttime' => $params['days']));
        $topics = $topics[1];

        if (empty($topics))
        {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($topics as $topic)
        {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->browserNav = $parent->browserNav;
            $node->uid = $parent->uid . '_t_' . $topic->id;
            $node->name = $topic->subject;
            $node->priority = $params['topic_priority'];
            $node->changefreq = $params['topic_changefreq'];
            $node->modified = $topic->last_post_time;
            $node->link = 'index.php?option=com_kunena&view=topic&catid=' . $topic->category_id . '&id=' . $topic->id;
            $node->secure = $parent->secure;

            if ($xmap->printNode($node) && $params['include_pagination'])
            {
                $msgPerPage = KunenaFactory::getConfig()->get('messages_per_page');
                $threadPages = ceil($topic->getTotal() / $msgPerPage);

                if ($threadPages > 1)
                {
                    $xmap->changeLevel(1);

                    for ($i = 2; $i <= $threadPages; $i++)
                    {
                        $subnode = new stdclass;
                        $subnode->id = $node->id;
                        $subnode->uid = $node->uid . '_p_' . $i;
                        $subnode->name = '[' . $i . ']';
                        $subnode->link = $node->link . '&limitstart=' . (($i - 1) * $msgPerPage);
                        $subnode->browserNav = $node->browserNav;
                        $subnode->priority = $node->priority;
                        $subnode->changefreq = $node->changefreq;
                        $subnode->modified = $node->modified;
                        $subnode->secure = $node->secure;

                        $xmap->printNode($subnode);
                    }

                    $xmap->changeLevel(-1);
                }
            }
        }

        $xmap->changeLevel(-1);
    }
}

<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget to show a list of profiles
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Public
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Widget to show a list of profiles, good for sidebar
 *
 * @category Public
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ProfileMiniListItem extends ProfileListItem
{
    function show()
    {
        $this->out->elementStart('li', 'h-card');
        if (Event::handle('StartProfileListItemProfileElements', array($this))) {
            if (Event::handle('StartProfileListItemAvatar', array($this))) {
                $aAttrs = $this->linkAttributes();
                $this->out->elementStart('a', $aAttrs);
                $avatarUrl = $this->profile->avatarUrl(AVATAR_MINI_SIZE);
                $this->out->element('img', array('src' => $avatarUrl,
                                                 'width' => AVATAR_MINI_SIZE,
                                                 'height' => AVATAR_MINI_SIZE,
                                                 'class' => 'avatar u-photo',
                                                 'alt' =>  $this->profile->getBestName()));
                $this->out->elementEnd('a');
                Event::handle('EndProfileListItemAvatar', array($this));
            }
            $this->out->elementEnd('li');
        }
    }

    // default; overridden for nofollow lists

    function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();

        $aAttrs['title'] = $this->profile->getBestName();
        $aAttrs['rel']   = 'contact member'; // @todo: member? always?
        $aAttrs['class'] = 'u-url p-name';

        return $aAttrs;
    }
}

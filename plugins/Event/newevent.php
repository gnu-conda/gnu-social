<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Add a new event
 * 
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
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
 * @category  Event
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Add a new event
 *
 * @category  Event
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class NeweventAction extends Action
{
    protected $user        = null;
    protected $error       = null;
    protected $complete    = null;
    protected $title       = null;
    protected $location    = null;
    protected $description = null;
    protected $startTime  = null;
    protected $endTime    = null;

    /**
     * Returns the title of the action
     *
     * @return string Action title
     */

    function title()
    {
        return _('New event');
    }

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */

    function prepare($argarray)
    {
        parent::prepare($argarray);

        $this->user = common_current_user();

        if (empty($this->user)) {
            throw new ClientException(_("Must be logged in to post a event."),
                                      403);
        }

        if ($this->isPost()) {
            $this->checkSessionToken();
        }

        $this->title       = $this->trimmed('title');

        if (empty($this->title)) {
            throw new ClientException(_('Title required.'));
        }

        $this->location    = $this->trimmed('location');
        $this->url         = $this->trimmed('url');
        $this->description = $this->trimmed('description');

        $startDate = $this->trimmed('startdate');

        if (empty($startDate)) {
            throw new ClientException(_('Start date required.'));
        }

        $startTime = $this->trimmed('starttime');

        if (empty($startTime)) {
            $startTime = '00:00';
        }

        $endDate   = $this->trimmed('enddate');

        if (empty($endDate)) {
            throw new ClientException(_('End date required.'));
        }

        $endTime   = $this->trimmed('endtime');

        if (empty($endTime)) {
            $endTime = '00:00';
        }

        $start = $startDate . ' ' . $startTime;

        common_debug("Event start: '$start'");

        $end = $endDate . ' ' . $endTime;

        common_debug("Event start: '$end'");

        $this->startTime = strtotime($start);
        $this->endTime   = strtotime($end);

        if ($this->startTime == 0) {
            throw new Exception(sprintf(_('Could not parse date "%s"'),
                                        $start));
        }


        if ($this->endTime == 0) {
            throw new Exception(sprintf(_('Could not parse date "%s"'),
                                        $end));
        }

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */

    function handle($argarray=null)
    {
        parent::handle($argarray);

        if ($this->isPost()) {
            $this->newEvent();
        } else {
            $this->showPage();
        }

        return;
    }

    /**
     * Add a new event
     *
     * @return void
     */

    function newEvent()
    {
        try {
            if (empty($this->title)) {
                throw new ClientException(_('Event must have a title.'));
            }

            if (empty($this->startTime)) {
                throw new ClientException(_('Event must have a start time.'));
            }

            if (empty($this->endTime)) {
                throw new ClientException(_('Event must have an end time.'));
            }

            $profile = $this->user->getProfile();

            $saved = Happening::saveNew($profile,
                                        $this->startTime,
                                        $this->endTime,
                                        $this->title,
                                        $this->location,
                                        $this->description,
                                        $this->url);

            $event = Happening::fromNotice($saved);

            RSVP::saveNew($profile, $event, RSVP::POSITIVE);

        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showPage();
            return;
        }

        if ($this->boolean('ajax')) {
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            // TRANS: Page title after sending a notice.
            $this->element('title', null, _('Event saved'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->showNotice($saved);
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect($saved->bestUrl(), 303);
        }
    }

    /**
     * Show the event form
     *
     * @return void
     */

    function showContent()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }

        $form = new EventForm($this);

        $form->show();

        return;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */

    function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Output a notice
     *
     * Used to generate the notice code for Ajax results.
     *
     * @param Notice $notice Notice that was saved
     *
     * @return void
     */
    function showNotice($notice)
    {
        $nli = new NoticeListItem($notice, $this);
        $nli->show();
    }
}
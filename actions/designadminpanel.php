<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Design administration panel
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
 * @category  Settings
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Administer design settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class DesignadminpanelAction extends AdminPanelAction
{

    /* The default site design */
    var $design = null;

    /**
     * Returns the page title
     *
     * @return string page title
     */

    function title()
    {
        return _('Design');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */

    function getInstructions()
    {
        return _('Design settings for this StatusNet site.');
    }

    /**
     * Get the default design and show the design admin panel form
     *
     * @return void
     */

    function showForm()
    {
        $this->design = Design::siteDesign();
        $form = new DesignAdminPanelForm($this);
        $form->show();
        return;
    }

    /**
     * Save settings from the form
     *
     * @return void
     */

    function saveSettings()
    {
        if ($this->arg('save')) {
            $this->saveDesignSettings();
        } else if ($this->arg('defaults')) {
            $this->restoreDefaults();
        } else {
            $this->clientError(_('Unexpected form submission.'));
        }
    }

    /**
     * Save the new design settings
     *
     * @return void
     */

    function saveDesignSettings()
    {
        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
        ) {
            $msg = _('The server was unable to handle that much POST ' .
                'data (%s bytes) due to its current configuration.');
            $this->clientException(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
            return;
        }

        // check for an image upload

        $bgimage = $this->saveBackgroundImage();

        static $settings = array(
            'site' => array('theme', 'logo'),
            'theme' => array('server', 'dir', 'path'),
            'avatar' => array('server', 'dir', 'path'),
            'background' => array('server', 'dir', 'path')
        );

        $values = array();

        foreach ($settings as $section => $parts) {
            foreach ($parts as $setting) {
                $values[$section][$setting] = $this->trimmed("$section-$setting");
            }
        }

        $this->validate($values);

        // assert(all values are valid);

        $bgcolor = new WebColor($this->trimmed('design_background'));
        $ccolor  = new WebColor($this->trimmed('design_content'));
        $sbcolor = new WebColor($this->trimmed('design_sidebar'));
        $tcolor  = new WebColor($this->trimmed('design_text'));
        $lcolor  = new WebColor($this->trimmed('design_links'));

        $onoff = $this->arg('design_background-image_onoff');

        $on   = false;
        $off  = false;

        if ($onoff == 'on') {
            $on = true;
        } else {
            $off = true;
        }

        $tile = $this->boolean('design_background-image_repeat');

        $config = new Config();

        $config->query('BEGIN');

        foreach ($settings as $section => $parts) {
            foreach ($parts as $setting) {
                Config::save($section, $setting, $values[$section][$setting]);
            }
        }

        if (isset($bgimage)) {
            Config::save('design', 'backgroundimage', $bgimage);
        }

        Config::save('design', 'backgroundcolor', $bgcolor->intValue());
        Config::save('design', 'contentcolor', $ccolor->intValue());
        Config::save('design', 'sidebarcolor', $sbcolor->intValue());
        Config::save('design', 'textcolor', $tcolor->intValue());
        Config::save('design', 'linkcolor', $lcolor->intValue());

        // Hack to use Design's bit setter
        $scratch = new Design();
        $scratch->setDisposition($on, $off, $tile);

        Config::save('design', 'disposition', $scratch->disposition);

        $config->query('COMMIT');

        return;
    }

    /**
      * Restore the default design
      *
      * @return void
      */

    function restoreDefaults()
    {
        $this->deleteSetting('site', 'logo');
        $this->deleteSetting('site', 'theme');

        $settings = array(
            'theme', 'backgroundimage', 'backgroundcolor', 'contentcolor',
            'sidebarcolor', 'textcolor', 'linkcolor', 'disposition'
        );

        foreach ($settings as $setting) {
            $this->deleteSetting('design', $setting);
        }

        // XXX: Should we restore the default dir settings, etc.? --Z
    }

    /**
     * Save the background image if the user uploaded one
     *
     * @return string $filename the filename of the image
     */

    function saveBackgroundImage()
    {
        $filename = null;

        if ($_FILES['design_background-image_file']['error'] ==
            UPLOAD_ERR_OK) {

            $filepath = null;

            try {
                $imagefile =
                    ImageFile::fromUpload('design_background-image_file');
            } catch (Exception $e) {
                $this->clientError('Unable to save background image.');
                return;
            }

            // Note: site design background image has a special filename

            $filename = Design::filename('site-design-background',
                image_type_to_extension($imagefile->type),
                    common_timestamp());

            $filepath = Design::path($filename);

            move_uploaded_file($imagefile->filepath, $filepath);

            // delete any old backround img laying around

            if (isset($this->design->backgroundimage)) {
                @unlink(Design::path($design->backgroundimage));
            }

            return $filename;
        }
    }

    /**
     * Attempt to validate setting values
     *
     * @return void
     */

    function validate(&$values)
    {

        if (!empty($values['site']['logo']) &&
            !Validate::uri($values['site']['logo'], array('allowed_schemes' => array('http', 'https')))) {
            $this->clientError(_("Invalid logo URL."));
        }

        if (!in_array($values['site']['theme'], Theme::listAvailable())) {
            $this->clientError(sprintf(_("Theme not available: %s"), $values['site']['theme']));
        }

        // Make sure the directories are there

        if (!empty($values['theme']['dir']) && !is_readable($values['theme']['dir'])) {
            $this->clientError(sprintf(_("Theme directory not readable: %s"), $values['theme']['dir']));
        }

        if (empty($values['avatar']['dir']) || !is_writable($values['avatar']['dir'])) {
            $this->clientError(sprintf(_("Avatar directory not writable: %s"), $values['avatar']['dir']));
        }

        if (empty($values['background']['dir']) || !is_writable($values['background']['dir'])) {
            $this->clientError(sprintf(_("Background directory not writable: %s"), $values['background']['dir']));
        }

        // Do we need to do anything else but validate the
        // other fields for length?  Design settings are
        // validated elsewhere --Z

        static $settings = array(
            'theme' => array('server', 'path'),
            'avatar' => array('server', 'path'),
            'background' => array('server', 'path')
        );

        foreach ($settings as $section => $parts) {
            foreach ($parts as $setting) {
                if (mb_strlen($values[$section][$setting]) > 255) {
                    $this->clientError(sprintf(_("Max length for %s %s is 255 characters."),
                        $section, $setting));
                        return;
                }
            }
        }
    }

    /**
     * Add the Farbtastic stylesheet
     *
     * @return void
     */

    function showStylesheets()
    {
        parent::showStylesheets();
        $this->cssLink('css/farbtastic.css','base','screen, projection, tv');
    }

    /**
     * Add the Farbtastic scripts
     *
     * @return void
     */

    function showScripts()
    {
        parent::showScripts();

        $this->script('js/farbtastic/farbtastic.js');
        $this->script('js/userdesign.go.js');

        $this->autofocus('design_background-image_file');
    }

}

class DesignAdminPanelForm extends AdminForm
{

    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'form_design_admin_panel';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_settings';
    }

    /**
     * HTTP method used to submit the form
     *
     * For image data we need to send multipart/form-data
     * so we set that here too
     *
     * @return string the method to use for submitting
     */

    function method()
    {
        $this->enctype = 'multipart/form-data';

        return 'post';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('designadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {

        $this->out->elementStart('fieldset', array('id' => 'settings_logo'));
        $this->out->element('legend', null, _('Change logo'));

        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input('logo', _('Site logo'), 'Logo for the site (full URL)', 'site');
        $this->unli();

        $this->out->elementEnd('ul');

        $this->out->elementEnd('fieldset');
        $this->out->elementStart('fieldset', array('id' => 'settings_theme'));
        $this->out->element('legend', null, _('Change theme'));

        $this->out->elementStart('ul', 'form_data');

        $themes = Theme::listAvailable();

        // XXX: listAvailable() can return an empty list if you
        // screw up your settings, so just in case:

        if (empty($themes)) {
            $themes = array('default', 'default');
        }

        asort($themes);
        $themes = array_combine($themes, $themes);

        $this->li();
        $this->out->dropdown('site-theme', _('Site theme'),
                             $themes, _('Theme for the site.'),
                             false, $this->value('theme', 'site'));
        $this->unli();

        $this->li();
        $this->input('server', _('Theme server'), 'Server for themes', 'theme');
        $this->unli();

        $this->li();
        $this->input('path', _('Theme path'), 'Web path to themes', 'theme');
        $this->unli();

        $this->li();
        $this->input('dir', _('Theme directory'), 'Directory where themes are located', 'theme');
        $this->unli();

        $this->out->elementEnd('ul');

        $this->out->elementEnd('fieldset');
        $this->out->elementStart('fieldset', array('id' => 'settings_avatar'));
        $this->out->element('legend', null, _('Avatar Settings'));

        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input('server', _('Avatar server'), 'Server for avatars', 'avatar');
        $this->unli();

        $this->li();
        $this->input('path', _('Avatar path'), 'Web path to avatars', 'avatar');
        $this->unli();

        $this->li();
        $this->input('dir', _('Avatar directory'), 'Directory where avatars are located', 'avatar');
        $this->unli();

        $this->out->elementEnd('ul');

        $this->out->elementEnd('fieldset');

        $design = $this->out->design;

        $this->out->elementStart('fieldset', array('id' =>
            'settings_design_background-image'));
        $this->out->element('legend', null, _('Change background image'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->out->element('label', array('for' => 'design_background-image_file'),
                                _('Background'));
        $this->out->element('input', array('name' => 'design_background-image_file',
                                     'type' => 'file',
                                     'id' => 'design_background-image_file'));
        $this->out->element('p', 'form_guide',
            sprintf(_('You can upload a background image for the site. ' .
              'The maximum file size is %1$s.'), ImageFile::maxFileSize()));
        $this->out->element('input', array('name' => 'MAX_FILE_SIZE',
                                          'type' => 'hidden',
                                          'id' => 'MAX_FILE_SIZE',
                                          'value' => ImageFile::maxFileSizeInt()));
        $this->unli();

        if (!empty($design->backgroundimage)) {

            $this->out->elementStart('li', array('id' =>
                'design_background-image_onoff'));

            $this->out->element('img', array('src' =>
                Design::url($design->backgroundimage)));

            $attrs = array('name' => 'design_background-image_onoff',
                           'type' => 'radio',
                           'id' => 'design_background-image_on',
                           'class' => 'radio',
                           'value' => 'on');

            if ($design->disposition & BACKGROUND_ON) {
                $attrs['checked'] = 'checked';
            }

            $this->out->element('input', $attrs);

            $this->out->element('label', array('for' => 'design_background-image_on',
                                          'class' => 'radio'),
                                          _('On'));

            $attrs = array('name' => 'design_background-image_onoff',
                           'type' => 'radio',
                           'id' => 'design_background-image_off',
                           'class' => 'radio',
                           'value' => 'off');

            if ($design->disposition & BACKGROUND_OFF) {
                $attrs['checked'] = 'checked';
            }

            $this->out->element('input', $attrs);

            $this->out->element('label', array('for' => 'design_background-image_off',
                                          'class' => 'radio'),
                                          _('Off'));
            $this->out->element('p', 'form_guide', _('Turn background image on or off.'));
            $this->unli();

            $this->li();
            $this->out->checkbox('design_background-image_repeat',
                            _('Tile background image'),
                            ($design->disposition & BACKGROUND_TILE) ? true : false);
            $this->unli();
        }

        $this->li();
        $this->input('server', _('Background server'), 'Server for backgrounds', 'background');
        $this->unli();

        $this->li();
        $this->input('path', _('Background path'), 'Web path to backgrounds', 'background');
        $this->unli();

        $this->li();
        $this->input('dir', _('Background directory'), 'Directory where backgrounds are located', 'background');
        $this->unli();

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');

        $this->out->elementStart('fieldset', array('id' => 'settings_design_color'));
        $this->out->element('legend', null, _('Change colours'));

        $this->out->elementStart('ul', 'form_data');

        try {

            $bgcolor = new WebColor($design->backgroundcolor);

            $this->li();
            $this->out->element('label', array('for' => 'swatch-1'), _('Background'));
            $this->out->element('input', array('name' => 'design_background',
                                          'type' => 'text',
                                          'id' => 'swatch-1',
                                          'class' => 'swatch',
                                          'maxlength' => '7',
                                          'size' => '7',
                                          'value' => ''));
            $this->unli();

            $ccolor = new WebColor($design->contentcolor);

            $this->li();
            $this->out->element('label', array('for' => 'swatch-2'), _('Content'));
            $this->out->element('input', array('name' => 'design_content',
                                          'type' => 'text',
                                          'id' => 'swatch-2',
                                          'class' => 'swatch',
                                          'maxlength' => '7',
                                          'size' => '7',
                                          'value' => ''));
            $this->unli();

            $sbcolor = new WebColor($design->sidebarcolor);

            $this->li();
            $this->out->element('label', array('for' => 'swatch-3'), _('Sidebar'));
            $this->out->element('input', array('name' => 'design_sidebar',
                                        'type' => 'text',
                                        'id' => 'swatch-3',
                                        'class' => 'swatch',
                                        'maxlength' => '7',
                                        'size' => '7',
                                        'value' => ''));
            $this->unli();

            $tcolor = new WebColor($design->textcolor);

            $this->li();
            $this->out->element('label', array('for' => 'swatch-4'), _('Text'));
            $this->out->element('input', array('name' => 'design_text',
                                        'type' => 'text',
                                        'id' => 'swatch-4',
                                        'class' => 'swatch',
                                        'maxlength' => '7',
                                        'size' => '7',
                                        'value' => ''));
            $this->unli();

            $lcolor = new WebColor($design->linkcolor);

            $this->li();
            $this->out->element('label', array('for' => 'swatch-5'), _('Links'));
            $this->out->element('input', array('name' => 'design_links',
                                         'type' => 'text',
                                         'id' => 'swatch-5',
                                         'class' => 'swatch',
                                         'maxlength' => '7',
                                         'size' => '7',
                                         'value' => ''));
            $this->unli();

        } catch (WebColorException $e) {
            common_log(LOG_ERR, 'Bad color values in site design: ' .
                $e->getMessage());
        }

        $this->out->elementEnd('fieldset');

        $this->out->elementEnd('ul');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('defaults', _('Use defaults'), 'submit form_action-default',
                'defaults', _('Restore default designs'));

        $this->out->element('input', array('id' => 'settings_design_reset',
                                         'type' => 'reset',
                                         'value' => 'Reset',
                                         'class' => 'submit form_action-primary',
                                         'title' => _('Reset back to default')));

        $this->out->submit('save', _('Save'), 'submit form_action-secondary',
                'save', _('Save design'));
    }


    /**
     * Utility to simplify some of the duplicated code around
     * params and settings. Overriding the input() in the base class
     * to handle a whole bunch of cases of settings with the same
     * name under different sections.
     *
     * @param string $setting      Name of the setting
     * @param string $title        Title to use for the input
     * @param string $instructions Instructions for this field
     * @param string $section      config section, default = 'site'
     *
     * @return void
     */

    function input($setting, $title, $instructions, $section='site')
    {
        $this->out->input("$section-$setting", $title, $this->value($setting, $section), $instructions);
    }

}

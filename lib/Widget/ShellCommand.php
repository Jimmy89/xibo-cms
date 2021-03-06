<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-15 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Widget;

use InvalidArgumentException;
use Xibo\Factory\CommandFactory;
use Xibo\Helper\Sanitize;

class ShellCommand extends ModuleWidget
{
    public function validate()
    {
        if ($this->getOption('windowsCommand') == '' && $this->getOption('linuxCommand') == '' && $this->getOption('commandCode') == '')
            throw new InvalidArgumentException(__('You must enter a command'));
    }

    /**
     * Add Media
     */
    public function add()
    {
        // Any Options (we need to encode shell commands, as they sit on the options rather than the raw
        $this->setOption('name', Sanitize::getString('name'));
        $this->setDuration(1);

        // Commands
        $windows = Sanitize::getString('windowsCommand');
        $linux = Sanitize::getString('linuxCommand');

        $this->setOption('commandCode', Sanitize::getString('commandCode'));
        $this->setOption('windowsCommand', urlencode($windows));
        $this->setOption('linuxCommand', urlencode($linux));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        // Any Options (we need to encode shell commands, as they sit on the options rather than the raw
        $this->setDuration(1);
        $this->setOption('name', Sanitize::getString('name', $this->getOption('name')));

        // Commands
        $windows = Sanitize::getString('windowsCommand');
        $linux = Sanitize::getString('linuxCommand');

        $this->setOption('commandCode', Sanitize::getString('commandCode'));
        $this->setOption('windowsCommand', urlencode($windows));
        $this->setOption('linuxCommand', urlencode($linux));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    public function preview($width, $height, $scaleOverride = 0)
    {
        if ($this->module->previewEnabled == 0)
            return parent::Preview($width, $height);

        $windows = $this->getOption('windowsCommand');
        $linux = $this->getOption('linuxCommand');

        if ($windows == '' && $linux == '') {
            return __('Stored Command: %s', $this->getOption('commandCode'));
        }
        else {

            $preview  = '<p>' . __('Windows Command') . ': ' . urldecode($windows) . '</p>';
            $preview .= '<p>' . __('Linux Command') . ': ' . urldecode($linux) . '</p>';

            return $preview;
        }
    }

    public function hoverPreview()
    {
        return $this->Preview(0, 0);
    }

    public function isValid()
    {
        // Client dependant
        return 2;
    }

    public function setTemplateData($data)
    {
        $data['commands'] = CommandFactory::query();
        return $data;
    }
}

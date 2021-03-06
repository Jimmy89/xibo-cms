<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Tag.php) is part of Xibo.
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


namespace Xibo\Entity;


use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

/**
 * Class Tag
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Tag implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Tag ID")
     * @var int
     */
    public $tagId;

    /**
     * @SWG\Property(description="The Tag Name")
     * @var string
     */
    public $tag;

    /**
     * @SWG\Property(description="An array of layoutIDs with this Tag")
     * @var int[]
     */
    public $layoutIds = [];

    /**
     * @SWG\Property(description="An array of mediaIds with this Tag")
     * @var int[]
     */
    public $mediaIds = [];

    private $originalLayoutIds = [];
    private $originalMediaIds = [];

    public function __clone()
    {
        $this->tagId = null;
    }

    /**
     * Assign Layout
     * @param int $layoutId
     */
    public function assignLayout($layoutId)
    {
        $this->load();

        if (!in_array($layoutId, $this->layoutIds))
            $this->layoutIds[] = $layoutId;
    }

    /**
     * Unassign Layout
     * @param int $layoutId
     */
    public function unassignLayout($layoutId)
    {
        $this->load();

        $this->layoutIds = array_diff($this->layoutIds, [$layoutId]);
    }

    /**
     * Assign Media
     * @param int $mediaId
     */
    public function assignMedia($mediaId)
    {
        $this->load();

        if (!in_array($mediaId, $this->mediaIds))
            $this->mediaIds[] = $mediaId;
    }

    /**
     * Unassign Media
     * @param int $mediaId
     */
    public function unassignMedia($mediaId)
    {
        $this->load();

        $this->mediaIds = array_diff($this->mediaIds, [$mediaId]);
    }

    /**
     * Load
     */
    public function load()
    {
        if ($this->tagId == null || $this->loaded)
            return;

        $this->layoutIds = [];
        foreach (PDOConnect::select('SELECT layoutId FROM `lktaglayout` WHERE tagId = :tagId', ['tagId' => $this->tagId]) as $row) {
            $this->layoutIds[] = $row['layoutId'];
        }

        $this->mediaIds = [];
        foreach (PDOConnect::select('SELECT mediaId FROM `lktagmedia` WHERE tagId = :tagId', ['tagId' => $this->tagId]) as $row) {
            $this->mediaIds[] = $row['mediaId'];
        }

        // Set the originals
        $this->originalLayoutIds = $this->layoutIds;
        $this->originalMediaIds = $this->mediaIds;

        $this->loaded = true;
    }

    /**
     * Save
     */
    public function save()
    {
        // If the tag doesn't exist already - save it
        if ($this->tagId == null || $this->tagId == 0)
            $this->add();

        // Manage the links to layouts and media
        $this->linkLayouts();
        $this->linkMedia();
        $this->removeAssignments();
    }

    /**
     * Remove Assignments
     */
    public function removeAssignments()
    {
        $this->unlinkLayouts();
        $this->unlinkMedia();
    }

    /**
     * Add a tag
     * @throws \PDOException
     */
    private function add()
    {
        $this->tagId = PDOConnect::insert('INSERT INTO `tag` (tag) VALUES (:tag) ON DUPLICATE KEY UPDATE tag = tag', array('tag' => $this->tag));
    }

    /**
     * Link all assigned layouts
     */
    private function linkLayouts()
    {
        $layoutsToLink = array_diff($this->layoutIds, $this->originalLayoutIds);

        Log::debug('Linking %d layouts to Tag %s', count($layoutsToLink), $this->tag);

        // Layouts that are in layoutIds but not in originalLayoutIds
        foreach ($layoutsToLink as $layoutId) {
            PDOConnect::update('INSERT INTO `lktaglayout` (tagId, layoutId) VALUES (:tagId, :layoutId) ON DUPLICATE KEY UPDATE layoutId = layoutId', array(
                'tagId' => $this->tagId,
                'layoutId' => $layoutId
            ));
        }
    }

    /**
     * Unlink all assigned Layouts
     */
    private function unlinkLayouts()
    {
        // Layouts that are in the originalLayoutIds but not in the current layoutIds
        $layoutsToUnlink = array_diff($this->originalLayoutIds, $this->layoutIds);

        Log::debug('Unlinking %d layouts from Tag %s', count($layoutsToUnlink), $this->tag);

        if (count($layoutsToUnlink) <= 0)
            return;

        // Unlink any layouts that are NOT in the collection
        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktaglayout` WHERE tagId = :tagId AND layoutId IN (0';

        $i = 0;
        foreach ($layoutsToUnlink as $layoutId) {
            $i++;
            $sql .= ',:layoutId' . $i;
            $params['layoutId' . $i] = $layoutId;
        }

        $sql .= ')';

        Log::sql($sql, $params);

        PDOConnect::update($sql, $params);
    }

    /**
     * Link all assigned media
     */
    private function linkMedia()
    {
        $mediaToLink = array_diff($this->mediaIds, $this->originalMediaIds);

        Log::debug('Linking %d media to Tag %s', count($mediaToLink), $this->tag);

        foreach ($mediaToLink as $mediaId) {
            PDOConnect::update('INSERT INTO `lktagmedia` (tagId, mediaId) VALUES (:tagId, :mediaId) ON DUPLICATE KEY UPDATE mediaId = mediaId', array(
                'tagId' => $this->tagId,
                'mediaId' => $mediaId
            ));
        }
    }

    /**
     * Unlink all assigned media
     */
    private function unlinkMedia()
    {
        $mediaToUnlink = array_diff($this->originalMediaIds, $this->mediaIds);

        Log::debug('Unlinking %d media from Tag %s', count($mediaToUnlink), $this->tag);

        // Unlink any layouts that are NOT in the collection
        if (count($mediaToUnlink) <= 0)
            return;

        $params = ['tagId' => $this->tagId];

        $sql = 'DELETE FROM `lktagmedia` WHERE tagId = :tagId AND mediaId NOT IN (0';

        $i = 0;
        foreach ($mediaToUnlink as $mediaId) {
            $i++;
            $sql .= ',:mediaId' . $i;
            $params['mediaId' . $i] = $mediaId;
        }

        $sql .= ')';

        Log::sql($sql, $params);

        PDOConnect::update($sql, $params);
    }
}
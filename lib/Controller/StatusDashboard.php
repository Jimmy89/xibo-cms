<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file (StatusDashboard.php) is part of Xibo.
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
 * along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Controller;
use Exception;
use PicoFeed\PicoFeedException;
use PicoFeed\Reader\Reader;
use Xibo\Factory\BaseFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\Cache;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;
use Xibo\Storage\PDOConnect;

class StatusDashboard extends Base
{
    function displayPage()
    {
        $data = [];

        // Set up some suffixes
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');

        try {
            // Displays this user has access to
            $displays = DisplayFactory::query(['display']);
            $displayIds = array_map(function($element) {
                return $element->displayId;
            }, $displays);
            $displayIds[] = -1;

            // Get some data for a bandwidth chart
            $dbh = PDOConnect::init();

            $sql = '
              SELECT MAX(FROM_UNIXTIME(month)) AS month,
                  IFNULL(SUM(Size), 0) AS size
                FROM `bandwidth`
               WHERE month > :month AND displayId IN (' . implode(',', $displayIds) . ')
              GROUP BY MONTH(FROM_UNIXTIME(month)) ORDER BY MIN(month);
              ';
            $params = array('month' => time() - (86400 * 365));

            Log::sql($sql, $params);
            $results = PDOConnect::select($sql, $params);

            // Monthly bandwidth - optionally tested against limits
            $xmdsLimit = Config::GetSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB');

            $maxSize = 0;
            foreach ($results as $row) {
                $maxSize = ($row['size'] > $maxSize) ? $row['size'] : $maxSize;
            }

            // Decide what our units are going to be, based on the size
            $base = ($maxSize == 0) ? 0 : floor(log($maxSize) / log(1024));

            if ($xmdsLimit > 0) {
                // Convert to appropriate size (xmds limit is in KB)
                $xmdsLimit = ($xmdsLimit * 1024) / (pow(1024, $base));
                $data['xmdsLimit'] = $xmdsLimit . ' ' . $suffixes[$base];
            }

            $output = array();

            foreach ($results as $row) {
                $size = ((double)$row['size']) / (pow(1024, $base));
                $remaining = $xmdsLimit - $size;
                $output[] = array(
                    'label' => Date::getLocalDate(Sanitize::getDate('month', $row), 'F'),
                    'value' => round($size, 2),
                    'limit' => round($remaining, 2)
                );
            }

            // What if we are empty?
            if (count($output) == 0) {
                $output[] = array(
                    'label' => Date::getLocalDate(null, 'F'),
                    'value' => 0,
                    'limit' => 0
                );
            }

            // Set the data
            $data['xmdsLimitSet'] = ($xmdsLimit > 0);
            $data['bandwidthSuffix'] = $suffixes[$base];
            $data['bandwidthWidget'] = json_encode($output);

            // We would also like a library usage pie chart!
            if ($this->getUser()->libraryQuota != 0) {
                $libraryLimit = $this->getUser()->libraryQuota * 1024;
            }
            else {
                $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;
            }

            // Library Size in Bytes
            $params = [];
            $sql = 'SELECT IFNULL(SUM(FileSize), 0) AS SumSize, type FROM `media` WHERE 1 = 1 ';
            BaseFactory::viewPermissionSql('Xibo\Entity\Media', $sql, $params, '`media`.mediaId', '`media`.userId');
            $sql .= ' GROUP BY type ';

            Log::sql($sql, $params);

            $sth = $dbh->prepare($sql);
            $sth->execute($params);

            $results = $sth->fetchAll();

            // Do we base the units on the maximum size or the library limit
            $maxSize = 0;
            if ($libraryLimit > 0) {
                $maxSize = $libraryLimit;
            } else {
                // Find the maximum sized chunk of the items in the library
                foreach ($results as $library) {
                    $maxSize = ($library['SumSize'] > $maxSize) ? $library['SumSize'] : $maxSize;
                }
            }

            // Decide what our units are going to be, based on the size
            $base = ($maxSize == 0) ? 0 : floor(log($maxSize) / log(1024));

            $output = array();
            $totalSize = 0;
            foreach ($results as $library) {
                $output[] = array(
                    'value' => round((double)$library['SumSize'] / (pow(1024, $base)), 2),
                    'label' => ucfirst($library['type'])
                );
                $totalSize = $totalSize + $library['SumSize'];
            }

            // Do we need to add the library remaining?
            if ($libraryLimit > 0) {
                $remaining = round(($libraryLimit - $totalSize) / (pow(1024, $base)), 2);
                $output[] = array(
                    'value' => $remaining,
                    'label' => __('Free')
                );
            }

            // What if we are empty?
            if (count($output) == 0) {
                $output[] = array(
                    'label' => __('Empty'),
                    'value' => 0
                );
            }

            $data['libraryLimitSet'] = $libraryLimit;
            $data['libraryLimit'] = (round((double)$libraryLimit / (pow(1024, $base)), 2)) . ' ' . $suffixes[$base];
            $data['librarySize'] = ByteFormatter::format($totalSize, 1);
            $data['librarySuffix'] = $suffixes[$base];
            $data['libraryWidget'] = json_encode($output);

            // Also a display widget
            $data['displays'] = $displays;

            // Get a count of users
            $data['countUsers'] = count(UserFactory::query());

            // Get a count of active layouts, only for display groups we have permission for
            $displayGroups = DisplayGroupFactory::query(null, ['isDisplaySpecific' => -1]);
            $displayGroupIds = array_map(function($element) {
                return $element->displayGroupId;
            }, $displayGroups);
            // Add an empty one
            $displayGroupIds[] = -1;

            $sql = 'SELECT IFNULL(COUNT(*), 0) AS count_scheduled FROM `schedule_detail` WHERE :now BETWEEN FromDT AND ToDT AND eventId IN (SELECT eventId FROM `lkscheduledisplaygroup` WHERE displayGroupId IN (' . implode(',', $displayGroupIds) . '))';
            $params = array('now' => time());

            Log::sql($sql, $params);

            $sth = $dbh->prepare($sql);
            $sth->execute($params);

            $data['nowShowing'] = $sth->fetchColumn(0);

            // Latest news
            if (Config::GetSetting('DASHBOARD_LATEST_NEWS_ENABLED') == 1) {
                // Make sure we have the cache location configured
                Library::ensureLibraryExists();

                try {
                    $feedUrl = Theme::getConfig('latest_news_url');
                    $key = md5($feedUrl);

                    // Check the cache
                    if (!Cache::has($key)) {

                        // Get the feed
                        $reader = new Reader(Config::getPicoFeedProxy($feedUrl));
                        $resource = $reader->download($feedUrl);

                        // Get the feed parser
                        $parser = $reader->getParser($resource->getUrl(), $resource->getContent(), $resource->getEncoding());

                        // Get a feed object
                        $feed = $parser->execute();

                        // Parse the items in the feed
                        $latestNews = [];

                        foreach ($feed->getItems() as $item) {
                            /* @var \PicoFeed\Parser\Item $item */
                            $latestNews[] = array(
                                'title' => $item->getTitle(),
                                'description' => $item->getContent(),
                                'link' => $item->getUrl()
                            );
                        }

                        // Store in the cache for 1 day
                        Cache::put($key, $latestNews, 86400);

                    } else {
                        $latestNews = Cache::get($key);
                    }

                    $data['latestNews'] = $latestNews;
                }
                catch (PicoFeedException $e) {
                    Log::error('Unable to get feed: %s', $e->getMessage());
                    Log::debug($e->getTraceAsString());

                    $data['latestNews'] = array(array('title' => __('Latest news not available.'), 'description' => '', 'link' => ''));
                }
            }
            else {
                $data['latestNews'] = array(array('title' => __('Latest news not enabled.'), 'description' => '', 'link' => ''));
            }
        }
        catch (Exception $e) {

            Log::error($e->getMessage());

            // Show the error in place of the bandwidth chart
            $data['widget-error'] = 'Unable to get widget details';
        }

        // Do we have an embedded widget?
        $data['embedded-widget'] = html_entity_decode(Config::GetSetting('EMBEDDED_STATUS_WIDGET'));

        // Render the Theme and output
        $this->getState()->template = 'dashboard-status-page';
        $this->getState()->setData($data);
    }
}

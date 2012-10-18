<?php

/**
 * Database report base class.
 *
 * @category   Apps
 * @package    Reports_Database
 * @subpackage Libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/reports_database/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\reports_database;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('reports_database');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Classes
//--------

use \clearos\apps\base\Configuration_File as Configuration_File;
use \clearos\apps\base\Engine as Engine;
use \clearos\apps\base\File as File;
use \clearos\apps\reports\Report_Engine as Report_Engine;

clearos_load_library('base/Configuration_File');
clearos_load_library('base/Engine');
clearos_load_library('base/File');
clearos_load_library('reports/Report_Engine');

// Exceptions
//-----------

use \clearos\apps\base\Engine_Exception as Engine_Exception;

clearos_load_library('base/Engine_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Database report base class.
 *
 * @category   Apps
 * @package    Reports_Database
 * @subpackage Libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2012 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/reports_database/
 */

class Database_Report extends Report_Engine
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const DB_HOST = '127.0.0.1';
    const DB_PORT = '3308';
    const DB_USER = 'reports';
    const DB_NAME = 'reports';

    const CACHE_TIME = 1800; // seconds // FIXME
    const FILE_CONFIG_DB = '/var/clearos/system_database/reports';
    const PATH_CACHE = '/var/clearos/reports_database/cache';

    ///////////////////////////////////////////////////////////////////////////////
    // V A R I A B L E S
    ///////////////////////////////////////////////////////////////////////////////

    protected $db_config = NULL;

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Database report constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Runs database query.
     *
     * @return array table rows
     */

    protected function _run_query($sql, $range, $timespan, $records = 200)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Add data range SQL
        //-------------------

        if ($range === Report_Engine::RANGE_TODAY) {
            $date = date('Y-m-d');
            $range = " AND date(timestamp) = '$date'";
        } else if ($range === Report_Engine::RANGE_YESTERDAY) {
            $date = date("Y-m-d", time() - 86400);
            $range = " AND date(timestamp) = '$date'";
        } else if ($range === Report_Engine::RANGE_LAST_7_DAYS) {
            $date = date("Y-m-d", time() - (7*86400));
            $range = " AND date(timestamp) >= '$date'";
        } else if ($range === Report_Engine::RANGE_LAST_30_DAYS) {
            $date = date("Y-m-d", time() - (30*86400));
            $range = " AND date(timestamp) >= '$date'";
        } else {
            $range = '';
        }

        // Add timespan handling
        //----------------------

        if ($timespan === Report_Engine::INTERVAL_DAILY) {
            $timespan = 'date(timestamp) AS timespan, ';
        } else if ($timespan === Report_Engine::INTERVAL_HOURLY) {
            $timespan = 'hour(timestamp) AS timespan, ';
        } else {
            $timespan = '';
        }

        // Add record limit SQL
        //---------------------

        $limit = (empty($records)) ? '' : " LIMIT $records";

        // Generate SQL statement
        //-----------------------

        $select = 'SELECT ' . $timespan . ' ' . $sql['select'] . ' FROM ' . $sql['from'];
        $where = 'WHERE ' .  $sql['where'] . ' ' . $range;
        $group_by = 'GROUP BY ' . $sql['group_by'];
        $order_by = 'ORDER BY ' . $sql['order_by'];

        $full_sql =
            $select . ' ' .
            $where . ' ' .
            $group_by . ' ' . 
            $order_by . ' ' .
            $limit;

        // Check cache
        //------------

        clearstatcache();

        $cache_filename = self::PATH_CACHE . '/' . md5($full_sql);

        $cache = new File($cache_filename);

        if ($cache->exists()) {
            $stat = stat($cache_filename);

            if ((time() - $stat['ctime']) <= self::CACHE_TIME) {
                return unserialize($cache->get_contents());
            } else {
                $cache->delete();
            }
        }

        // Load configuration
        //-------------------

        if (is_null($this->db_config)) {
            $file = new Configuration_File(self::FILE_CONFIG_DB, 'explode', '=', 2);
            $this->db_config = $file->load();
        }

        // Run query
        //----------

        try {
            $dbh = new \PDO(
                'mysql:host=' . self::DB_HOST . ';port=' . self::DB_PORT . ';dbname=' . self::DB_NAME,
                self::DB_USER,
                $this->db_config['password']
            );

            $dbs = $dbh->prepare($full_sql);
            $dbs->execute();
            $rows = array();

            while ($row = $dbs->fetch())
                $rows[] = $row;
        } catch(\PDOException $e) {  
            throw new Engine_Exception($e->getMessage());
        }

        $dbh = NULL;

        // Handle cache
        //-------------

        if (! $cache->exists()) {
            $cache->create('root', 'root', '0600');
            $cache->add_lines(serialize($rows));
        }

        return $rows;
    }
}

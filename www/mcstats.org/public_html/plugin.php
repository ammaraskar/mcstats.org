<?php

/// Todo fully remove other deprecated methods
/// Todo for the old way of generating graphs :D

define('ROOT', './');
session_start();

require_once ROOT . 'config.php';
require_once ROOT . 'includes/database.php';
require_once ROOT . 'includes/func.php';

// Cache until the next interval
header('Cache-Control: public, s-maxage=' . (timeUntilNextGraph() - time()));

if (!isset($_GET['plugin']))
{
    exit('ERR No plugin provided.');
}

// Load the plugin
$plugin = loadPlugin($_GET['plugin']);

// Doesn't exist
if ($plugin === NULL)
{
    exit('ERR Invalid plugin.');
}

// Get the plugin name
$pluginName = htmlentities($plugin->getName());
$encodedName = urlencode($pluginName); // encoded name, for use in signature url

/// Template hook
$page_title = 'MCStats :: ' . $pluginName;

/// Templating
send_header();

echo '
            <script>
                // Plugin-specific bindings
                var pluginName = "' . $pluginName . '";
            </script>

            <div id="row-fluid" style="width: 100%">

                <div class="span4" id="plugin-table">
                    <h3>Plugin information</h3>
';

// get last updated
$timelast = getTimeLast();

// display the time since the last graph update
if($timelast > 0) {
    $lastUpdate = floor($timelast / 60);
    $nextUpdate = $config['graph']['interval'] - $lastUpdate;
    if ($nextUpdate < 0)
    {
        $nextUpdate = 0;
    }

    echo '
                    <p> Last update: ' . $lastUpdate . ' minutes ago <br/>
                        Next update in: ' . $nextUpdate . ' minutes </p>
';
}

$output = $cache->get('plugin/' . $plugin->getName());

if (!$output)
{
    ob_start();

    $authors = htmlentities($plugin->getAuthors());

    // set a default author if none is set
    if ($authors == '')
    {
        $authors = 'unknown :-(';
    }

    // check for spaces or commas (and if they exist, throw is (s) after Author
    $author_prepend = '';
    if (strstr($authors, ' ') !== FALSE || strstr($authors, ',') !== FALSE)
    {
        $author_prepend = '(s)';
    }

    // Ranking
    $rank = $plugin->getRank();

    // unknown rank
    if ($rank == '')
    {
        $rank = '<i>Unknown</i>';
    }

    // bolden the rank if they're in the top-10
    if (is_numeric($rank) && $rank <= 10)
    {
        $rank = '<b>' . $rank . '</b>';
    }

    // increase
    if ($plugin->getRank() < $plugin->getLastRank())
    {
        $rank .= ' <i class="fam-arrow-up" title="Increased from ' . $plugin->getLastRank() . ' (+' . ($plugin->getLastRank() - $plugin->getRank()) . ')"></i>';
    }

    // decrease
    elseif ($plugin->getRank() > $plugin->getLastRank())
    {
        $rank .= ' <i class="fam-arrow-down" title="Decreased from ' . $plugin->getLastRank() . ' (-' . ($plugin->getRank() - $plugin->getLastRank()) . ')"></i>';
    }

    // no change
    else
    {
        $rank .= ' <i class="fam-bullet-blue" title="No change"></i>';
    }

    if (!is_numeric($plugin->getLastRankChange()))
    {
        $rankHeldFor = '<i>No rank</i>';
    } else
    {
        $rankHeldFor = epochToHumanString(time() - $plugin->getLastRankChange());
    }

    echo '
                    <table class="table table-striped">
                        <tbody>
                            <tr> <td> Name </td> <td> ' . $pluginName . ' </td> </tr>
                            <tr> <td> Rank </td> <td> ' . $rank . ' </td> </tr>
                            <tr> <td> Rank held for </td> <td> ' . $rankHeldFor . ' </td> </tr>
                            <tr> <td> Author' . $author_prepend . ' </td> <td> ' . $authors . ' </td> </tr>
                            <tr> <td> Date added </td> <td> ' . date('F d, Y', $plugin->getCreated()) . ' </td> </tr>
                            <tr> <td> Global starts </td> <td> ' . number_format($plugin->getGlobalHits()) . ' </td> </tr>
                            <tr> <td> </td>
                                <td>
                                    <ul style="list-style: none;">
                                        <li> <a class="btn btn-mini" href="/signature/' . strtolower($encodedName) . '.png" target="_blank" style="margin-bottom: 5px;"><i class="icon-tasks"></i> Signature image</a> </li>
                                        <li> <a class="btn btn-mini" href="/plugin-preview/' . strtolower($encodedName) . '.png" target="_blank"><i class="icon-tasks"></i> Textless preview</a> </li>
                                    </ul>
                                </td> </tr>
                        </tbody>
                    </table>

                    <h3>Servers using ' . $pluginName . '</h3>
                    <table class="table table-striped">
                        <tbody>
                            <tr> <td> All-time </td> <td> ' . number_format($plugin->countServers()) . ' </td> </tr>
                            <tr> <td> Last hour </td> <td> ' . number_format($plugin->countServersLastUpdated(normalizeTime() - SECONDS_IN_HOUR)) . ' </td> </tr>
                            <tr> <td> Last 12 hrs </td> <td> ' . number_format($plugin->countServersLastUpdated(normalizeTime() - SECONDS_IN_HALFDAY)) . ' </td> </tr>
                            <tr> <td> Last 24 hrs </td> <td> ' . number_format($plugin->countServersLastUpdated(normalizeTime() - SECONDS_IN_DAY)) . ' </td> </tr>
                            <tr> <td> Last 7 days </td> <td> ' . number_format($plugin->countServersLastUpdated(normalizeTime() - SECONDS_IN_WEEK)) . ' </td> </tr>
                            <tr> <td> This month </td> <td> ' . number_format($plugin->countServersLastUpdated(strtotime(date('m') . '/01/' . date('Y') . ' 00:00:00'))) . ' </td> </tr>
                        </tbody>
                    </table>';

    echo '
                </div>

                <div id="graph-content">
';

    outputGraphs($plugin);

    echo '
                </div>
                <div class="clear"></div>';
    $output = ob_get_contents();
    ob_end_clean();
    $cache->set('plugin/' . $plugin->getName(), $output, CACHE_UNTIL_NEXT_GRAPH);
}

echo $output;

/// Templating
send_footer();
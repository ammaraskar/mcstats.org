<?php

define('ROOT', '../public_html/');

require_once ROOT . 'config.php';
require_once ROOT . 'includes/database.php';
require_once ROOT . 'includes/func.php';

$statement = get_slave_db_handle()->prepare('SELECT Plugin.ID, Parent, Name, Author, Hidden, GlobalHits, Created, count(ServerPlugin.Server) AS ServerCount, LastUpdated, ServerCount30, Rank, LastRank, LastRankChange FROM Plugin LEFT JOIN ServerPlugin ON Plugin.ID = ServerPlugin.Plugin WHERE ServerPlugin.Updated >= ? AND Plugin.Parent = -1 GROUP BY Plugin.ID ORDER BY ServerCount DESC');
$statement->execute(array(normalizeTime() - SECONDS_IN_DAY));

$rank = 0;
$lastChange = normalizeTime();
while ($row = $statement->fetch())
{
    $plugin = resolvePlugin($row);

    $newRank = ++$rank;

    // did their rank change ?
    if ($newRank != $plugin->getRank())
    {
        $plugin->setLastRankChange($lastChange);
    }

    $plugin->setServerCount($plugin->countServersLastUpdated(normalizeTime() - SECONDS_IN_DAY));
    $plugin->setLastRank($plugin->getRank());
    $plugin->setRank($newRank);
    $plugin->save();

    echo sprintf ('%d: %s%s', $rank, $plugin->getName(), PHP_EOL);
}
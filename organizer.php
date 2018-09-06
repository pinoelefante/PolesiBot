<?php
    require_once("database.php");
    require_once("telegram.php");

    define("GAME_PENDING", 1);
    define("GAME_ENDED", 2);
    define("GAME_CANCEL", 3);

    function GetLastGame($chatId, $withPlayers = false)
    {
        $query = "SELECT * FROM my_match WHERE id IN (SELECT MAX(id) FROM my_match WHERE chatId = ? AND status=?)";
        $match = dbSelect($query, "ii", array($chatId, GAME_PENDING), true);
        return $match;
    }
    function GetLastGameNG($chatId)
    {
        $query = "SELECT * FROM my_match WHERE id IN (SELECT MAX(id) FROM my_match WHERE chatId = ?)";
        $match = dbSelect($query, "i", array($chatId), true);
        return $match;
    }
    function CreateNewGame($chatId, $userId)
    {
        $field = "";
        $players = 10;
        $startTimestamp = GetTimestampFromString("domani");
        $date = getdate($startTimestamp);
        $startDay = sprintf("%02d-%02d-%02d", $date["year"], $date["mon"], $date["mday"]);
        $startHour = sprintf("%02d:%02d:00", $date["hours"], $date["minutes"]);
        
        $lastGame = GetLastGameNG($chatId);
        if($lastGame != NULL)
        {
            EndGame($chatId);
            $field = $lastGame["field"];
            $players = $lastGame["players"];
            $startHour = $lastGame["startHour"];
        }

        $query = "INSERT INTO my_match (chatId,field,startBy,players,startDay,startHour) VALUES (?,?,?,?,?,?)";
        return dbUpdate($query, "isiiss", array($chatId, $field, $userId, $players, $startDay, $startHour));
    }
    function EndGame($chatId)
    {
        $game = GetLastGame($chatId);
        if($game == null)
            return true;
        $query = "UPDATE my_match SET status = ".GAME_ENDED." WHERE id = ?";
        return dbUpdate($query, "i", array($game["id"]));
    }
    function ChangeExternalPlayerStatus($chatId)
    {
        $game = GetLastGame($chatId);
        if($game == null)
            return NULL;
        $newStatus = !$game["external"];
        $query = "UPDATE my_match SET external = ? WHERE chatId = ?";
        if(dbUpdate($query, "ii", array($newStatus, $chatId)))
            return $newStatus;
        return NULL;
    }
    function ReservePlayer($chatId, $firstName, $lastName, $nickname, $places = 1)
    {
        $game = GetLastGame($chatId);
        if($game == null)
            return false;
        $userId = GetNextReservedPlayerId($game["id"]);
        $query = "INSERT INTO my_match_player (userId,matchId,nickname,firstname,lastname) VALUES (?,?,?,?,?)";
        return dbUpdate($query, "iisss", array($userId, $game["id"], $nickname, $firstName, $lastName));
    }
    function GetNextReservedPlayerId($matchId)
    {
        $query = "SELECT MIN(userId) as lastFriendId FROM my_match_player WHERE matchId = ? AND userId < 0";
        $result = dbSelect($query, "i", array($matchId), true);
        if($result === NULL)
            return -1;
        return $result["lastFriendId"]-1;
    }
    function SetDay($chatId, $day)
    {
        $game = GetLastGame($chatId);
        if($game == null)
            return false;
        
        $startTimestamp = GetTimestampFromString($day);
        $date = getdate($startTimestamp);
        $startDay = sprintf("%02d-%02d-%02d", $date["year"], $date["mon"], $date["mday"]);
        // $startHour = sprintf("%02d:%02d:00", $date["hours"], $date["minutes"]);
        $query = "UPDATE my_match SET startDay=? WHERE id = ?";
        return dbUpdate($query, "si", array($startDay, $game["id"]));
    }
    function GetTimestampFromString($day)
    {
        switch($day)
        {
            case "oggi":
                return strtotime("now")+3600;
            case "domani":
                return strtotime("+1 day");
            case "dopodomani":
                return strtotime("+2 day");
            case "lunedi":
                return strtotime("next Monday");
            case "martedi":
                return strtotime("next Tuesday");
            case "mercoledi":
                return strtotime("next Wednesday");
            case "giovedi":
                return strtotime("next Thursday");
            case "venerdi":
                return strtotime("next Friday");
            case "sabato":
                return strtotime("next Saturday");
            case "domenica":
                return strtotime("next Sunday");
            default:
                return GetTimestampFromString("oggi");
        }
    }
    function SetHour($chatId, $hour)
    {
        $game = GetLastGame($chatId);
        if($game == null)
            return false;
        $hour.=":00";
        $query = "UPDATE my_match SET startHour=? WHERE id = ?";
        return dbUpdate($query, "si", array($hour, $game["id"]));
    }
    function SetField($chatId, $name)
    {
        $game = GetLastGame($chatId);
        if($game == null)
            return false;
        $query = "UPDATE my_match SET field=? WHERE id = ?";
        return dbUpdate($query, "si", array($name, $game["id"]));
    }
    function SetPlayers($chatId, $numPlayers)
    {
        $game = GetLastGame($chatId);
        if($game == null)
            return false;
        $query = "UPDATE my_match SET players=? WHERE id = ?";
        return dbUpdate($query, "ii", array($numPlayers, $game["id"]));
    }
    function AddPlayer($chatId, $userId, $firstName, $lastName, $nickName, $gameId=NULL)
    {
        if($gameId == NULL)
        {
            $game = GetLastGame($chatId);
            if($game == null)
                return false;
            $gameId = $game["id"];
        }
        $query = "INSERT INTO my_match_player (userId,matchId,nickname,firstname,lastname) VALUES (?,?,?,?,?)";
        return dbUpdate($query, "iisss",array($userId,$gameId,$nickName,$firstName,$lastName));
    }
    function RemovePlayer($chatId, $userId, $gameId=NULL)
    {
        if($gameId == NULL)
        {
            $game = GetLastGame($chatId);
            if($game == null)
                return false;
            $gameId = $game["id"];
        }
        $query = "DELETE FROM my_match_player WHERE userId = ? AND matchId = ?";
        return dbUpdate($query,"ii", array($userId,$gameId));
    }
    function GetPlayers($chatId, $gameId=NULL)
    {
        if($gameId == NULL)
        {
            $game = GetLastGame($chatId);
            if($game == null)
                return array();
            $gameId = $game["id"];
        }
        $query = "SELECT * FROM my_match_player WHERE matchId = ?";
        return dbSelect($query, "i", array($gameId));
    }
?>
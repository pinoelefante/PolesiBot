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

            $startTimestamp = GetTimestampFromString(GetDayFromStartDate($lastGame["startDay"]));
            $date = getdate($startTimestamp);
            $startDay = sprintf("%02d-%02d-%02d", $date["year"], $date["mon"], $date["mday"]);
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
    function ReservePlayer($insertBy, $chatId, $externalName, $userName, $nickname, $places = 1)
    {
        $game = GetLastGame($chatId);
        if($game == null)
            return false;
        $insertCount = 0;
        for($i=0;$i<$places;$i++)
        {
            $userId = GetNextReservedPlayerId($game["id"]);
            $query = "INSERT INTO my_match_player (userId,matchId,nickname,firstname,lastname,insertBy) VALUES (?,?,?,?,?,?)";
            if(dbUpdate($query, "iisssi", array($userId, $game["id"], $nickname, $externalName, $userName, $insertBy)))
                $insertCount++;
        }
        return $insertCount;
    }
    function FreeSpotByName($chatId, $insertBy, $externalName)
    {
        $query = "DELETE FROM my_match_player WHERE userId < 0 AND insertBy = ? AND firstname LIKE ?";
        return dbUpdate($query, "is", array($insertBy, $externalName), DatabaseReturns::RETURN_AFFECTED_ROWS);
    }
    function FreeSpot($chatId, $insertBy, $spots=1)
    {
        $query = "DELETE FROM my_match_player WHERE userId < 0 AND insertBy = ? AND ISNULL(firstname) LIMIT $spots";
        return dbUpdate($query, "i", array($insertBy), DatabaseReturns::RETURN_AFFECTED_ROWS);
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
        $day = strtolower($day);
        switch($day)
        {
            case "today":
            case "oggi":
                return strtotime("now")+3600;
            case "tomorrow":
            case "domani":
                return strtotime("+1 day");
            case "dopodomani":
                return strtotime("+2 day");
            case "monday":
            case "lunedi":
            case "lunedì":
                return strtotime("next Monday");
            case "tuesday":
            case "martedi":
            case "martedì":
                return strtotime("next Tuesday");
            case "wednesday":
            case "mercoledi":
            case "mercoledì":
                return strtotime("next Wednesday");
            case "thursday":
            case "giovedi":
            case "giovedì":
                return strtotime("next Thursday");
            case "friday":
            case "venerdi":
            case "venerdì":
                return strtotime("next Friday");
            case "saturday":
            case "sabato":
                return strtotime("next Saturday");
            case "sunday":
            case "domenica":
                return strtotime("next Sunday");
            default:
                return GetTimestampFromString("oggi");
        }
    }
    function GetDayFromStartDate($data)
    {
        $date_item = DateTime::createFromFormat("Y-m-d", $data);
        $timestamp = $date_item->getTimestamp();
        
        $day = date("l", $timestamp);
        return $day;
    }
    function GetItalianDay($dayEng)
    {
        $dayEng = strtolower($dayEng);
        switch($dayEng)
        {
            case "sunday":
                return "Domenica";
            case "monday":
                return "Lunedì";
            case "tuesday":
                return "Martedì";
            case "wednesday":
                return "Mercoledì";
            case "thursday":
                return "Giovedì";
            case "friday":
                return "Venerdì";
            case "saturday":
                return "Sabato";
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
        $query = "INSERT INTO my_match_player (userId,matchId,nickname,firstname,lastname,insertBy) VALUES (?,?,?,?,?,?)";
        return dbUpdate($query, "iisssi",array($userId,$gameId,$nickName,$firstName,$lastName,$userId));
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
        $query = "SELECT * FROM my_match_player WHERE matchId = ? ORDER BY insertTime ASC";
        return dbSelect($query, "i", array($gameId));
    }
    function ChangeGroupId($from, $to)
    {
        $query = "UPDATE my_match SET chatId = ? WHERE chatId = ?";
        return dbUpdate($query, "ii", array($to, $from));
    }
?>
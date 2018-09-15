<?php
    require_once("config.php");

    function IsGroup($content)
    {
        if(!array_key_exists("type", $content["message"]["chat"]))
            return false;
        return $content["message"]["chat"]["type"] == "group";
    }
    function IsSupergroup($content)
    {
        if(!array_key_exists("type", $content["message"]["chat"]))
            return false;
        return $content["message"]["chat"]["type"] == "supergroup";
    }
    function SendMessage($chatId, $message, $markdown = false)
    {
        $url = WEB_ENDPOINT."/sendMessage?chat_id=".$chatId.($markdown ? "&parse_mode=Markdown" : "")."&text=".urlencode($message);
        $res = file_get_contents($url);
    }
    function SendKeyboard($chatId,$message,$perLine=2,...$buttons)
    {
        $keyButtons = array();
        $keyRow = array();
        foreach ($buttons as $buttonText) {
            if(array_push($keyRow, urlencode($buttonText)) == $perLine)
            {
                array_push($keyButtons, $keyRow);
                $keyRow = array();
            }
        }
        if(count($keyRow) > 0)
            array_push($keyButtons, $keyRow);
        $key_array = array("keyboard" => $keyButtons, "one_time_keyboard" => true);
        $keyboard = json_encode($key_array);
        $url = WEB_ENDPOINT."/sendMessage?chat_id=".$chatId."&text=".urlencode($message)."&reply_markup=".$keyboard;
        file_get_contents($url);
    }
    function SendCloseKeyboard($chatId,$message,$nick=NULL)
    {

    }
    function GetUpdate()
    {
        $content = file_get_contents("php://input");
        if(DEBUG_MODE)
            file_put_contents("last_request.txt", $content, FILE_APPEND);
        return json_decode($content, true);
    }
    function GetTextMessage($content)
    {
        if(array_key_exists("text", $content["message"]))
            return $content["message"]["text"];
        return "";
    }
    function GetChatId($content)
    {
        return $content["message"]["chat"]["id"];
    }
    function GetMessageTimestamp($content)
    {
        return $content["message"]["date"];
    }
    function IsBot($message)
    {
        return $content["message"]["from"]["is_bot"];
    }
    function GetUserId($content)
    {
        return $content["message"]["from"]["id"];
    }
    function GetFirstName($content)
    {
        if(!array_key_exists("first_name", $content["message"]["from"]))
            return NULL;
        return utf8_decode($content["message"]["from"]["first_name"]);
    }
    function GetLastName($content)
    {
        if(!array_key_exists("last_name", $content["message"]["from"]))
            return NULL;
        return utf8_decode($content["message"]["from"]["last_name"]);
    }
    function GetNickName($content)
    {
        if(!array_key_exists("username", $content["message"]["from"]))
            return NULL;
        return $content["message"]["from"]["username"];
    }
    function IsAllAdmin($content)
    {
        if(array_key_exists("all_members_are_administrators", $content["message"]["chat"]))
            return $content["message"]["chat"]["all_members_are_administrators"];
        return false;
    }
    function IsAdmin($message)
    {
        if(IsAllAdmin($message))
            return TRUE;
        $userId = GetUserId($message);
        $admin_resp = GetAdministrators(GetChatId($message));
        if(!$admin_resp["ok"])
            return false;
        foreach ($admin_resp["result"] as $admin) {
            if($admin["user"]["id"] == $userId)
                return TRUE;
        }
        return FALSE;
    }
    function IsBotCommand($content)
    {
        if(!array_key_exists("entities", $content["message"]))
            return false;
        return $content["message"]["entities"][0]["type"] == "bot_command";
    }
    function GetAdministrators($chatId)
    {
        $url = WEB_ENDPOINT."/getChatAdministrators?chat_id=".$chatId;
        $resp = file_get_contents($url);
        return json_decode($resp, true);
    }
    function CheckChangeChatId($json)
    {
        if(array_key_exists("migrate_to_chat_id", $json["message"]))
            return $json["message"]["migrate_to_chat_id"];
        return NULL;
    }
?>
<?php
    require_once("config.php");
    require_once("logger.php");
    require_once("telegram.php");
    require_once("organizer.php");

    set_error_handler("app_error_logger");

    $json = GetUpdate();
    CheckChatSupported($json);

    $message = GetTextMessage($json);
    $chatId = GetChatId($json);
    $userId = GetUserId($json);
    
    $isBotCommand = IsBotCommand($json);
    if($isBotCommand)
    {
        if(strpos($message, " ")!==false)
        {
            $parameters = explode(" ", $message);
            $lowmsg = $parameters[0];
        }
        else
            $lowmsg = $message;
    }
    else
        $lowmsg = strtolower($message);
    switch($lowmsg)
    {
        case "/nuova":
        case "/nuova@PolesiBot":
            if(!IsAdmin($json))
                break;
            if(CreateNewGame($chatId, $userId))
            {
                SendMessage($chatId, "Partita creata");
                SendInfoGame($chatId);
            }
            else
                SendMessage($chatId, "Non è stato possibile creare una nuova partita");
            break;
        case "/termina":
        case "/termina@PolesiBot":
            if(!IsAdmin($json))
                break;
            if(EndGame($chatId))
                SendMessage($chatId, "Partita terminata con *SUCCESSO*", true);
            else
                SendMessage($chatId, "Non sono riuscito a terminare la partita");
            break;
        case "/info":
        case "/info@PolesiBot":
            SendInfoGame($chatId);
            break;
        case "ci sono":
        case "/ci_sono":
        case "/ci_sono@PolesiBot":
            $userId = GetUserId($json);
            $firstname = GetFirstName($json);
            $lastName = GetLastName($json);
            $nickname = GetNickName($json);
            $game = GetLastGame($chatId);
            if($game == null)
                return;
            $players = GetPlayers($chatId, $game["id"]);
            if(count($players) >= $game["players"])
            {
                SendMessage($chatId, "Mi dispiace $firstname, ma la partita è già al completo");
                break;
            }
            if(AddPlayer($chatId, $userId, $firstname, $lastName, $nickname))
            {
                SendMessage($chatId, "Si è unito anche $firstname");
                SendInfoGame($chatId);
            }
            break;
        case "non ci sono":
        case "non ci sono più":
        case "non ci sono piu":
        case "/non_ci_sono":
        case "/non_ci_sono@PolesiBot":
            $userId = GetUserId($json);
            if(RemovePlayer($chatId, $userId))
            {
                $firstname = GetFirstName($json);
                SendMessage($chatId, "$firstname non verrà alla partita");
                SendInfoGame($chatId);
            }
            break;
        case "/info_esterni":
        case "/info_esterni@PolesiBot":
            $game = GetLastGame($chatId);
            if($game != null)
                SendMessage($chatId, ($game["external"] ? "Giocatori esterni al gruppo *ABILITATO*" : "Giocatori esterni al gruppo *NON ABILITATO*"), true);
            break;
        case "/cambia_esterni":
        case "/cambia_esterni@PolesiBot":
            if(!IsAdmin($json))
                return;
            if(ChangeExternalPlayerStatus($chatId))
            {
                $game = GetLastGame($chatId);
                if($game != null)
                    SendMessage($chatId, $game["external"] ? 
                            "Giocatori esterni al gruppo *ABILITATO*" : 
                            "Giocatori esterni al gruppo *NON ABILITATO*", true);
            }
            break;
        case "/riserva_posto":
        case "/riserva_posto@PolesiBot":
            $game = GetLastGame($chatId);
            if($game==null || !$game["external"])
                return;
            $firstname = GetFirstName($json);
            $lastName = GetLastName($json);
            $spots = 1;
            $players = GetPlayers($chatId, $game["id"]);
            $freeSpots = $game["players"]-count($players);
            if($spots > $freeSpots)
            {
                SendMessage($chatId, "Posti non riservati. Sono disponibili $freeSpots posti");
                break;
            }
            if(ReservePlayer($chatId, $firstname, $lastName, GetNickname($json), $spots))
            {
                SendMessage($chatId,"Riservato/i $spots posto/i da *$firstname $lastName*" ,true);
                SendInfoGame($chatId);
            }
            break;
        case "/imposta_giorno":
        case "/imposta_giorno@PolesiBot":
            if(!IsAdmin($json))
                return;
            if(isset($parameters) && count($parameters) > 1){
                $newDay = $parameters[1];
                if(SetDay($chatId, $newDay))
                    SendInfoGame($chatId);
                else
                    SendMessage($chatId, "Giorno non cambiato");
            }
            else
                SendMessage($chatId, "Modalità d'uso: /imposta_giorno giorno\ngiorno può assumere i seguenti valori: oggi,domani,dopodomani,lunedi,martedi,mercoledi,giovedi,venerdi,sabato,domenica");
            break;
        case "/imposta_ora":
        case "/imposta_ora@PolesiBot":
            if(!IsAdmin($json))
                return;
            if(isset($parameters) && count($parameters) > 1){
                $hour = $parameters[1]; //verificare il formato dell'ora
                if(SetHour($chatId, $hour))
                    SendInfoGame($chatId);
                else
                    SendMessage($chatId, "Ora non cambiata");
            }
            else
                SendMessage($chatId, "Modalità d'uso: /imposta_ora HH:MM");
            break;
        case "/imposta_giocatori":
        case "/imposta_giocatori@PolesiBot":
            if(!IsAdmin($json))
                return;
            if(isset($parameters) && count($parameters) > 1 && is_numeric($parameters[1]))
            {
                $numPlayers = intval($parameters[1]);
                if(SetPlayers($chatId, $numPlayers))
                    SendInfoGame($chatId);
                else
                    SendMessage($chatId, "Si è verificato un errore");
            }
            else
                SendMessage($chatId, "Modalità d'uso: /imposta_giocatori N");
            break;
        case "/imposta_campo":
        case "/imposta_campo@PolesiBot":
            if(!IsAdmin($json))
                return;
            if(isset($parameters))
            {
                unset($parameters[0]);
                $fieldName = implode(" ", $parameters);
                if(SetField($chatId, $fieldName))
                    SendInfoGame($chatId);
                else
                    SendMessage($chatId, "Si è verificato un errore");
            }
            else
                SendMessage($chatId, "Modalità d'uso: /imposta_campo NomeCampo");
            break;

        case "/history":
            break;
        default:
            ManageMessage($chatId, $json);
            break;
    }

    function SendInfoGame($chatId)
    {
        $game = GetLastGame($chatId);
        if($game == NULL)
        {
            SendMessage($chatId, "Al momento non ci sono partite in programma");
            return;
        }
        $players = GetPlayers($chatId);
        $elenco = "";
        $indexPlayer = 1;
        foreach ($players as $player) {
            $playerName = GetPlayerNameForList($player);
            $elenco.=$indexPlayer." - ".($player["userId"]<0 ? "Amico di " : "").$playerName."\n";
            $indexPlayer++;
        }
        //if(count($players) > 0)
            //$elenco.="\n";

        $date_item = DateTime::createFromFormat("Y-m-d", $game["startDay"]);
        $formattedData = date("d-m-Y", $date_item->getTimestamp());
        $italianDay = GetItalianDay(date("l", $date_item->getTimestamp()));

        $hour_split = explode(":", $game["startHour"]);
        unset($hour_split[2]);
        $formattedTime = implode(":", $hour_split);
        
        SendMessage($chatId,"La prossima partita si giocherà *$italianDay*, *$formattedData* alle ore *".$formattedTime."*"
        .(!empty($game["field"]) ? " al campo *".$game["field"]."*\n" : "\n")
        .(count($players) > 0 ? "*\nElenco partecipanti:*\n$elenco\n" : "\n")
        .(count($players) == $game["players"] ? "*Siamo al completo*" : "Mancano *".($game["players"]-count($players)."* giocatori")), true);
    }
    function GetPlayerNameForList($user)
    {
        $name = "";
        if(empty($user["firstname"]) && empty($user["lastname"]))
        {
            if(!empty($user["nickname"]))
                $name = $user["nickname"];
            else
                $name = "Utente senza nome";
        }
        else
        {
            $name = ($user["firstname"]!=NULL ? $user["firstname"] : "")." ".($user["lastname"]!=NULL ? $user["lastname"] : "");
        }
        return trim($name);
    }
    function CheckChatSupported($messageJson)
    {
        $chatId = GetChatId($messageJson);
        if(!IsGroup($messageJson) && !IsSupergroup($messageJson))
        {
            SendMessage($chatId, "Questo bot supporta soltanto i gruppi e i supergruppi");
            exit;
        }
    }
    function ManageMessage($chatId, $json)
    {
        $newChatId = CheckChangeChatId($json);
        if($newChatId !== NULL && $chatId != $newChatId)
        {
            if(!ChangeGroupId($chatId, $newChatId))
                file_put_contents("errore_cambio_id.txt", "origine: $chatId destinazione: $newChatId");
        }
    }
?>
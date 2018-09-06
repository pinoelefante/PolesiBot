<?php
    require_once("config.php");
    require_once("logger.php");
    require_once("telegram.php");
    require_once("organizer.php");

    set_error_handler("app_error_logger");

    $json = GetUpdate();
    // print_r($json);
    $message = GetTextMessage($json);
    //file_put_contents("last_message.txt", $message);
    $chatId = GetChatId($json);
    $userId = GetUserId($json);
    //file_put_contents("last_chat.txt", $chatId);

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
                SendMessage("Mi dispiace $firstname, ma la partita è già al completo");
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
                SendMessage("Posti non riservati. Sono disponibili $freeSpots posti");
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
            if(isset($parameters)){
                $newDay = $parameters[1];
                if(SetDay($chatId, $newDay))
                    SendInfoGame($chatId);
                else
                    SendMessage($chatId, "Giorno non cambiato");
            }
            else
                SendMessage($chatId, "Modalità d'uso: /imposta_giorno giorno\ngiorno può assumere i seguenti valori:*oggi,domani,dopodomani,lunedi,martedi,mercoledi,giovedi,venerdi,sabato,domenica*", true);
            break;
        case "/imposta_ora":
        case "/imposta_ora@PolesiBot":
            if(!IsAdmin($json))
                return;
            if(isset($parameters)){
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
            if(isset($parameters) && is_numeric($parameters[1]))
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
            //SendMessage($chatId, "Comando non valido: $lowmsg");
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
            $elenco.=$indexPlayer." - ".($player["userId"]<0 ? "Amico di " : "").$player["firstname"]." ".$player["lastname"]."\n";
            $indexPlayer++;
        }
        $elenco.="\n";
        
        SendMessage($chatId,"La prossima partita si giocherà il *".$game["startDay"]."* alle ore *".$game["startHour"]."*\n"
        .(!empty($game["field"]) ? "al campo *".$game["field"]."*": "")
        .(count($players) > 0 ? "*\nElenco partecipanti:*\n$elenco\n": "\n")
        .(count($players) == $game["players"] ? "*Siamo al completo*" : "Mancano *".($game["players"]-count($players)."* giocatori")), true);
        
    }
?>
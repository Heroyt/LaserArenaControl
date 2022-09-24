#include <MsgBoxConstants.au3>
#include <StringConstants.au3>

#include "TCPServer.au3"
#include "functions.au3"
#include "load.au3"
#include "start.au3"
#include "end.au3"

_TCPServer_OnReceive("received")
_TCPServer_DebugMode(True)
_TCPServer_SetMaxClients(1)

_TCPServer_Start(8081)

Func received($iSocket, $sIP, $sData, $sPar)
	$data = StringRegExp($sData, "([a-zA-Z]*?):(.*?)", $STR_REGEXPARRAYMATCH)
	If @error Then Return
	$action = $data[0]
	$parameter = $data[1]
    MsgBox(0, "Data received from " & $sIP, $sData & @CRLF & "Parameter: " & $sPar)
    Switch $action
    	Case "load"
    		load($parameter)
    		_TCPServer_Send($iSocket, "done")
    	Case "start"
    		start()
    		_TCPServer_Send($iSocket, "done")
    	Case "end"
    		end()
    		_TCPServer_Send($iSocket, "done")
    	Case "status"
    		_TCPServer_Send($iSocket, GetGameStatus())
EndFunc   ;==>received
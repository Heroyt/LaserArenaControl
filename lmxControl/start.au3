#include <MsgBoxConstants.au3>

#include "functions.au3"

Func start()
	$activeWin = WinGetHandle("[ACTIVE]")
	$win = WinActivate("LaserMaxx EVO-5 Console")
	Sleep(200)
	$status = GetGameStatus()
	If $status = "ARMED" Then
		ControlClick($win, "", 367) ;Click - Start Game
	 EndIf
	Sleep(1000)
	WinActivate($activeWin)
EndFunc

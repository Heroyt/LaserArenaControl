#include "functions.au3"
#include "logFunctions.au3"

Func start()
	; Save currently active window handle for later
	Local $activeWin = WinGetHandle("[ACTIVE]")

	; Activate the main window
	Local $win = WinWaitActivate("LaserMaxx EVO-5 Console")
	If $win = 0 Then
		ErrorLog("Cannot activate the main window for load", @ScriptName, @ScriptLineNumber)
		Return False
	EndIf

	Sleep(200)

	Local $status = GetGameStatus()
	If $status = "ARMED" Then
		ClickButton($win, 367) ;Click - Start Game
	EndIf

	Sleep(1000)

	; Return to the previously active window
    ; Doesn't matter if it fails
	WinActivate($activeWin)

	Return True
EndFunc

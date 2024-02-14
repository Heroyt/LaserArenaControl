#include-once
#include "logFunctions.au3"

Func GetGameStatus()
	Local $downloadWin = WinExists("Downloading Scores from Packs")
	If $downloadWin Then
		Return "DOWNLOAD"
	EndIf
	Local $win = WinGetHandle("LaserMaxx EVO-5 Console")
	Local $music = ControlGetText($win, "", 349)
	If $music = "EVO-5 LaserMixx Standard Standby.mp3" Or $music = "EVO-5 LaserMixx Standard Gameover.mp3" Then
		Return "STANDBY"
	ElseIf $music = "EVO-5 LaserMixx Standard Armed.mp3" Then
		Return "ARMED"
	EndIf
	Return "PLAYING"
EndFunc

Func WinWaitActivate($title, $timeout = 5)
	If WinExists($title) = 0 Then
		;MsgBox($MB_ICONERROR, "Error", "Window does not exist " & $title)
		ErrorLog("Window does not exist " & $title, @ScriptName, @ScriptLineNumber)
        Return 0
	EndIf
	Local $hWin = WinGetHandle($title)
	If WinActive($hWin) Then
		Return $hWin
	EndIf
	If WinActivate($hWin) = 0 Then
		; Retry
		Sleep(200)
		If WinActivate($hWin) = 0 Then
			;MsgBox($MB_ICONERROR, "Error", "Cannot activate window " & $title)
			ErrorLog("Cannot activate window " & $title, @ScriptName, @ScriptLineNumber)
			Return $hWin
		EndIf
		If WinWaitActive($hWin, "", $timeout) = 0 Then
			;MsgBox($MB_ICONERROR, "Error", "Cannot activate window " & $title)
			ErrorLog("Win wait active window " & $title, @ScriptName, @ScriptLineNumber)
			Return $hWin
		EndIf
	EndIf
	Return $hWin
EndFunc

Func ClickButton($win, $ID)
	Local $hCtrl = ControlGetHandle($win, "", $ID)
	If $hCtrl = 0 Then
		; Retry
		Sleep(100)
		$hCtrl = ControlGetHandle($win, "", $ID)
		If $hCtrl = 0 Then
			;MsgBox($MB_ICONERROR, "Error", "Cannot find button")
			ErrorLog("Cannot find button " & $ID, @ScriptName, @ScriptLineNumber)
			Return False
		EndIf
	EndIf
	If ControlCommand($win, "", $hCtrl, "IsEnabled", "") = 0 Then
		; Retry
		Sleep(100)
		If ControlCommand($win, "", $hCtrl, "IsEnabled", "") = 0 Then
			;MsgBox($MB_ICONERROR, "Error", "Cannot click button")
			ErrorLog("Button is not enabled " & $ID, @ScriptName, @ScriptLineNumber)
			Return False
		EndIf
    EndIf
    If ControlClick($win, "", $hCtrl) = 0 Then
    	; Retry
    	Sleep(100)
    	If ControlClick($win, "", $hCtrl) = 0 Then
			;MsgBox($MB_ICONERROR, "Error", "Click failed")
			ErrorLog("Button click failed " & $ID, @ScriptName, @ScriptLineNumber)
			Return False
		EndIf
    EndIf
    Return True
EndFunc
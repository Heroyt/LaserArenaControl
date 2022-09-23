Func GetGameStatus()
	$win = WinGetHandle("LaserMaxx EVO-5 Console")
	$music = ControlGetText($win, "", 349)
	If $music = "EVO-5 LaserMixx Standard Standby.mp3" Or $music = "EVO-5 LaserMixx Standard Gameover.mp3" Then
		Return "STANDBY"
	ElseIf $music = "EVO-5 LaserMixx Standard Armed.mp3" Then
		Return "ARMED"
	EndIf
	Return "PLAYING"
EndFunc
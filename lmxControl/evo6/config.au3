#include-once

Global $g_tcpPort = IniRead("C:\LaserMaxx\shared\lmxControl.ini", "TCP", "Port", 8081)

Global $g_WinName = "LMXconsole"
Global $g_LoadGroupWinName = "Load group file"
Global $g_ScoresDownloadSelector = "[ID:323]"

Global $g_LoadGroupBtnId = "[ID:360]"
Global $g_LoadGameBtnId = "[ID:348]"
Global $g_StartGameBtnId = "[ID:347]"
Global $g_CancelGameBtnId = "[ID:333]"
Global $g_StopGameBtnId = "[ID:347]"
Global $g_RedownloadScoresBtnId = "[ID:326]"
Global $g_CancelScoresDownloadBtnId = "[ID:325]"

Global $g_ModeSelectBox = "[CLASS:ListView20WndClass; INSTANCE:4]"

Global $g_MusicPlayerId = "[ID:345]"

Global $g_LoadGameShortcut = "F2"
Global $g_StartGameShortcut = "F3"
Global $g_StopGameShortcut = "F4"

#include <StringConstants.au3>
#include <FileConstants.au3>
#include <NamedPipes.au3>
#include <WinAPI.au3>
#include <WindowsConstants.au3>
#include <GuiConstantsEx.au3>

#include "functions.au3"
#include "logFunctions.au3"
#include "load.au3"
#include "start.au3"
#include "end.au3"

Opt("TrayAutoPause",0)
Opt("TrayIconDebug",1)


Global Const $DEBUGGING = True
Global Const $BUFSIZE = 4096
Global Const $PIPE_NAME = "\\.\pipe\lmxControlPipe"
Global Const $TIMEOUT = 5000
Global Const $WAIT_TIMEOUT = 258
Global Const $ERROR_IO_PENDING = 997
Global Const $ERROR_PIPE_CONNECTED = 535

Global $g_hEvent, $g_idMemo, $g_pOverlap, $g_tOverlap, $g_hPipe, $g_hReadPipe, $g_iState, $g_iToWrite, $g_msg

; ===============================================================================================================================
; Main
; ===============================================================================================================================

InitPipe()
MsgLoop()

; ===============================================================================================================================
; This function creates an instance of a named pipe
; ===============================================================================================================================
Func InitPipe()
	; Create an event object for the instance
	$g_tOverlap = DllStructCreate($tagOVERLAPPED)
	$g_pOverlap = DllStructGetPtr($g_tOverlap)
	$g_hEvent = _WinAPI_CreateEvent()
	If $g_hEvent = 0 Then
		ErrorLog("InitPipe ..........: API_CreateEvent failed", @ScriptName, @ScriptLineNumber)
		Return
	EndIf
	DllStructSetData($g_tOverlap, "hEvent", $g_hEvent)

	; Create a named pipe
	$g_hPipe = _NamedPipes_CreateNamedPipe($PIPE_NAME, _ ; Pipe name
			2, _ ; The pipe is bi-directional
			2, _ ; Overlapped mode is enabled
			0, _ ; No security ACL flags
			1, _ ; Data is written to the pipe as a stream of messages
			1, _ ; Data is read from the pipe as a stream of messages
			0, _ ; Blocking mode is enabled
			1, _ ; Maximum number of instances
			$BUFSIZE, _ ; Output buffer size
			$BUFSIZE, _ ; Input buffer size
			$TIMEOUT, _ ; Client time out
			0) ; Default security attributes
	If $g_hPipe = -1 Then
		ErrorLog("InitPipe ..........: _NamedPipes_CreateNamedPipe failed", @ScriptName, @ScriptLineNumber)
	Else
		; Connect pipe instance to client
		ConnectClient()
	EndIf
EndFunc   ;==>InitPipe

Func MsgLoop()
    While True
		$iEvent = _WinAPI_WaitForSingleObject($g_hEvent, 0)
		If $iEvent < 0 Then
			ErrorLog("MsgLoop ...........: _WinAPI_WaitForSingleObject failed", @ScriptName, @ScriptLineNumber)
			Exit
		EndIf
		If $iEvent = $WAIT_TIMEOUT Then ContinueLoop
		DebugLog($g_logFile, "MsgLoop ...........: Instance signaled", @ScriptName, @ScriptLineNumber)

        Switch $g_iState
            Case 0
                CheckConnect()
            Case 1
                ReadRequest()
			Case 2
				CheckPending()
            Case 3
                RelayOutput()
        EndSwitch
    WEnd
EndFunc

; ===============================================================================================================================
; This function reads a request message from the client
; ===============================================================================================================================
Func ReadRequest()
	Local $pBuffer, $tBuffer, $iRead, $bSuccess

	$tBuffer = DllStructCreate("char Text[" & $BUFSIZE & "]")
	$pBuffer = DllStructGetPtr($tBuffer)
	$bSuccess = _WinAPI_ReadFile($g_hPipe, $pBuffer, $BUFSIZE, $iRead, $g_pOverlap)

	If $bSuccess And ($iRead <> 0) Then
		; The read operation completed successfully
		DebugLog($g_logFile, "ReadRequest .......: Read success", @ScriptName, @ScriptLineNumber)
	Else
		; Wait for read Buffer to complete
		If Not _WinAPI_GetOverlappedResult($g_hPipe, $g_pOverlap, $iRead, True) Then
			ErrorLog("ReadRequest .......: _WinAPI_GetOverlappedResult failed", @ScriptName, @ScriptLineNumber)
			ReconnectClient()
			Return
		Else
			; Read the command from the pipe
			$bSuccess = _WinAPI_ReadFile($g_hPipe, $pBuffer, $BUFSIZE, $iRead, $g_pOverlap)
			If Not $bSuccess Or ($iRead = 0) Then
                DebugLog($g_logFile, "ReadRequest .......: _WinAPI_ReadFile failed", @ScriptName, @ScriptLineNumber)
				ErrorLog("ReadRequest .......: _WinAPI_ReadFile failed", @ScriptName, @ScriptLineNumber)
				ReconnectClient()
				Return
			EndIf
		EndIf
	EndIf

	; Execute the console command
	Local $sData = DllStructGetData($tBuffer, "Text")
	Local $data = StringRegExp($sData, "([a-zA-Z]*?):(.*)", $STR_REGEXPARRAYFULLMATCH)
	If @error Then
        ErrorLog("Cannot parse regex data", @ScriptName, @ScriptLineNumber)
		ReconnectClient()
        Return
    EndIf

    Local $action = $data[1]
    $parameter = $data[2]
    For $i = 0 To UBound($data) - 1
        DebugLog($g_logFile, "Received data: " & " > [" & $i & "] " & $data[$i], @ScriptName, @ScriptLineNumber)
    Next
    Local $status = GetGameStatus()

    If $status = "DOWNLOAD" Then
        DebugLog($g_logFile, "Downloading scores", @ScriptName, @ScriptLineNumber)
        Local $winTitle = "Downloading Scores from Packs"
        Switch $action
            Case "retryDownload"
                If WinExists($winTitle) = 1 Then
                    Local $win = WinGetHandle($winTitle)
                    ClickButton($win, "[CLASS:ThunderRT6CommandButton; INSTANCE:2]")
                EndIf
                SendMsg("ok")
            Case "cancelDownload"
                If WinExists($winTitle) = 1 Then
                    $win = WinGetHandle($winTitle)
                    ClickButton($win, "[CLASS:ThunderRT6CommandButton; INSTANCE:3]")
                EndIf
                SendMsg("ok")
            Case Else
                SendMsg("DOWNLOAD")
        EndSwitch
    Else
        Switch $action
            Case "load"
                DebugLog($g_logFile, "Load game", @ScriptName, @ScriptLineNumber)
                If load($parameter) Then
                    SendMsg("ok")
                Else
                    SendMsg("error")
                EndIf
            Case "start"
                DebugLog($g_logFile, "Start game", @ScriptName, @ScriptLineNumber)
                If start() Then
                    SendMsg("ok")
                Else
                    SendMsg("error")
                EndIf
            Case "loadStart"
                DebugLog($g_logFile, "Load Start game", @ScriptName, @ScriptLineNumber)
                If load($parameter) Then
                    If start() Then
                        SendMsg("ok")
                    Else
                        SendMsg("error")
                    EndIf
                Else
                    SendMsg("error")
                EndIf
            Case "end"
                DebugLog($g_logFile, "End game", @ScriptName, @ScriptLineNumber)
                If end() Then
                    SendMsg("ok")
                Else
                    SendMsg("error")
                EndIf
            Case "status"
                DebugLog($g_logFile, "Get status: "&$status, @ScriptName, @ScriptLineNumber)
                SendMsg($status)
            Case Else
                SendMsg("invalid command")
        EndSwitch
    EndIf
EndFunc   ;==>ReadRequest

; ===============================================================================================================================
; Checks to see if the pending client connection has finished
; ===============================================================================================================================
Func CheckConnect()
	Local $iBytes

	; Was the operation successful?
	If Not _WinAPI_GetOverlappedResult($g_hPipe, $g_pOverlap, $iBytes, False) Then
		ErrorLog("CheckConnect ......: Connection failed", @ScriptName, @ScriptLineNumber)
		ReconnectClient()
	Else
		DebugLog($g_logFile, "CheckConnect ......: Connected", @ScriptName, @ScriptLineNumber)
		$g_iState = 1
	EndIf
EndFunc   ;==>CheckConnect

; ===============================================================================================================================
; This function is called to start an overlapped connection operation
; ===============================================================================================================================
Func ConnectClient()
	$g_iState = 0
	; Start an overlapped connection
	If _NamedPipes_ConnectNamedPipe($g_hPipe, $g_pOverlap) Then
		ErrorLog("ConnectClient .....: ConnectNamedPipe 1 failed", @ScriptName, @ScriptLineNumber)
	Else
		Switch _WinAPI_GetLastError()
			; The overlapped connection is in progress
			Case $ERROR_IO_PENDING
				DebugLog($g_logFile, "ConnectClient .....: Pending", @ScriptName, @ScriptLineNumber)
				; Client is already connected, so signal an event
			Case $ERROR_PIPE_CONNECTED
				DebugLog($g_logFile, "ConnectClient .....: Connected", @ScriptName, @ScriptLineNumber)
				$g_iState = 1
				If Not _WinAPI_SetEvent(DllStructGetData($g_tOverlap, "hEvent")) Then
					ErrorLog("ConnectClient .....: SetEvent failed", @ScriptName, @ScriptLineNumber)
				EndIf
				; Error occurred during the connection event
			Case Else
				ErrorLog("ConnectClient .....: ConnectNamedPipe 2 failed (" & @error & ")", @ScriptName, @ScriptLineNumber)
		EndSwitch
	EndIf
EndFunc   ;==>ConnectClient


; ===============================================================================================================================
; This function is called when an error occurs or when the client closes its handle to the pipe
; ===============================================================================================================================
Func ReconnectClient()
	; Disconnect the pipe instance
	If Not _NamedPipes_DisconnectNamedPipe($g_hPipe) Then
		ErrorLog("ReconnectClient ...: DisonnectNamedPipe failed", @ScriptName, @ScriptLineNumber)
		Return
	EndIf

	; Connect to a new client
	ConnectClient()
EndFunc   ;==>ReconnectClient

; ===============================================================================================================================
; This function relays the console output back to the client
; ===============================================================================================================================
Func CheckPending()
	Local $bSuccess, $iWritten

	$bSuccess = _WinAPI_GetOverlappedResult($g_hPipe, $g_pOverlap, $iWritten, False)
	If Not $bSuccess Or ($iWritten <> $g_iToWrite) Then
		ReconnectClient()
	Else
		$g_iState = 3
	EndIf
EndFunc   ;==>CheckPending

func SendMsg($msg)
    $g_msg = $msg & @CR
    $g_iState = 3
EndFunc

; ===============================================================================================================================
; This function relays the console output back to the client
; ===============================================================================================================================
Func RelayOutput()
	Local $pBuffer, $tBuffer, $sLine, $iRead, $bSuccess, $iWritten

    $iBuffer = StringLen($g_msg) + 1
	$tBuffer = DllStructCreate("char Text[" & $iBuffer & "]")
	$pBuffer = DllStructGetPtr($tBuffer)

	; Get the data and strip out the extra carriage returns
	$sLine = StringLeft($g_msg, $iBuffer)
	$sLine = StringReplace($sLine, @CR & @CR, @CR)
	$g_iToWrite = StringLen($sLine)
	DllStructSetData($tBuffer, "Text", $sLine)
	; Relay the data back to the client
	$bSuccess = _WinAPI_WriteFile($g_hPipe, $pBuffer, $g_iToWrite, $iWritten, $g_pOverlap)
	If $bSuccess And ($iWritten = $g_iToWrite) Then
		DebugLog($g_logFile, "RelayOutput .......: Write success", @ScriptName, @ScriptLineNumber)

		DebugLog($g_logFile, ("RelayOutput .......: Write done" & $g_msg, @ScriptName, @ScriptLineNumber)
		_WinAPI_CloseHandle($g_hReadPipe)
		_WinAPI_FlushFileBuffers($g_hPipe)
		$g_msg = ""
		ReconnectClient()
	Else
		If Not $bSuccess And (_WinAPI_GetLastError() = $ERROR_IO_PENDING) Then
			DebugLog($g_logFile, "RelayOutput .......: Write pending", @ScriptName, @ScriptLineNumber)
			$g_iState = 2
		Else
			; An error occurred, disconnect from the client
			ErrorLog("RelayOutput .......: Write failed", @ScriptName, @ScriptLineNumber)
			ReconnectClient()
		EndIf
	EndIf
EndFunc   ;==>RelayOutput
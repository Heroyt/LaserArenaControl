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


Global Const $BUFSIZE = 4096
Global Const $PIPE_NAME = "\\.\pipe\lmxControlPipe"
Global Const $TIMEOUT = 5000
Global Const $WAIT_TIMEOUT = 258
Global Const $ERROR_IO_PENDING = 997
Global Const $ERROR_PIPE_CONNECTED = 535

Global $g_hEvent, $g_idMemo, $g_pOverlap, $g_tOverlap, $g_hReadPipe, $g_iState, $g_iToWrite

Global $pipeHandle = _NamedPipes_CreateNamedPipe($PIPE_NAME, _ ; Pipe name
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

If $pipeHandle = -1 Then
	ErrorLog("InitPipe ..........: _NamedPipes_CreateNamedPipe failed", @ScriptName, @ScriptLineNumber)
Else
	; Connect pipe instance to client
	ConnectClient()
EndIf

Func MsgLoop()
    While True
		$iEvent = _WinAPI_WaitForSingleObject($g_hEvent, 0)
		If $iEvent < 0 Then
			ErrorLog("MsgLoop ...........: _WinAPI_WaitForSingleObject failed", @ScriptName, @ScriptLineNumber)
			Exit
		EndIf
		If $iEvent = $WAIT_TIMEOUT Then ContinueLoop
		DebugLog("MsgLoop ...........: Instance signaled", @ScriptName, @ScriptLineNumber)

        Switch $g_iState
            Case 0
                CheckConnect()
            Case 1
                ReadRequest()
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
	$bSuccess = _WinAPI_ReadFile($pipeHandle, $pBuffer, $BUFSIZE, $iRead, $g_pOverlap)

	If $bSuccess And ($iRead <> 0) Then
		; The read operation completed successfully
		DebugLog("ReadRequest .......: Read success", @ScriptName, @ScriptLineNumber)
	Else
		; Wait for read Buffer to complete
		If Not _WinAPI_GetOverlappedResult($pipeHandle, $g_pOverlap, $iRead, True) Then
			ErrorLog("ReadRequest .......: _WinAPI_GetOverlappedResult failed", @ScriptName, @ScriptLineNumber)
			ReconnectClient()
			Return
		Else
			; Read the command from the pipe
			$bSuccess = _WinAPI_ReadFile($pipeHandle, $pBuffer, $BUFSIZE, $iRead, $g_pOverlap)
			If Not $bSuccess Or ($iRead = 0) Then
				ErrorLog("ReadRequest .......: _WinAPI_ReadFile failed", @ScriptName, @ScriptLineNumber)
				ReconnectClient()
				Return
			EndIf
		EndIf
	EndIf

	; Execute the console command
	Local $data = StringRegExp(DllStructGetData($tBuffer, "Text"), "([a-zA-Z]*?):(.*)", $STR_REGEXPARRAYFULLMATCH)
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
                RelayOutput("ok")
            Case "cancelDownload"
                If WinExists($winTitle) = 1 Then
                    $win = WinGetHandle($winTitle)
                    ClickButton($win, "[CLASS:ThunderRT6CommandButton; INSTANCE:3]")
                EndIf
                RelayOutput("ok")
            Case Else
                RelayOutput("DOWNLOAD")
        EndSwitch
    Else
        Switch $action
            Case "load"
                DebugLog($g_logFile, "Load game", @ScriptName, @ScriptLineNumber)
                If load($parameter) Then
                    RelayOutput("ok")
                Else
                    RelayOutput("error")
                EndIf
            Case "start"
                DebugLog($g_logFile, "Start game", @ScriptName, @ScriptLineNumber)
                If start() Then
                    RelayOutput("ok")
                Else
                    RelayOutput("error")
                EndIf
            Case "loadStart"
                DebugLog($g_logFile, "Load Start game", @ScriptName, @ScriptLineNumber)
                If load($parameter) Then
                    If start() Then
                        RelayOutput("ok")
                    Else
                        RelayOutput("error")
                    EndIf
                Else
                    RelayOutput("error")
                EndIf
            Case "end"
                DebugLog($g_logFile, "End game", @ScriptName, @ScriptLineNumber)
                If end() Then
                    RelayOutput("ok")
                Else
                    RelayOutput("error")
                EndIf
            Case "status"
                DebugLog($g_logFile, "Get status", @ScriptName, @ScriptLineNumber)
                RelayOutput($status)
            Case Else
                RelayOutput("invalid command")
        EndSwitch
    EndIf
EndFunc   ;==>ReadRequest

; ===============================================================================================================================
; Checks to see if the pending client connection has finished
; ===============================================================================================================================
Func CheckConnect()
	Local $iBytes

	; Was the operation successful?
	If Not _WinAPI_GetOverlappedResult($pipeHandle, $g_pOverlap, $iBytes, False) Then
		ErrorLog("CheckConnect ......: Connection failed", @ScriptName, @ScriptLineNumber)
		ReconnectClient()
	Else
		DebugLog("CheckConnect ......: Connected", @ScriptName, @ScriptLineNumber)
		$g_iState = 1
	EndIf
EndFunc   ;==>CheckConnect

; ===============================================================================================================================
; This function is called to start an overlapped connection operation
; ===============================================================================================================================
Func ConnectClient()
	$g_iState = 0
	; Start an overlapped connection
	If _NamedPipes_ConnectNamedPipe($pipeHandle, $g_pOverlap) Then
		ErrorLog("ConnectClient .....: ConnectNamedPipe 1 failed", @ScriptName, @ScriptLineNumber)
	Else
		Switch @error
			; The overlapped connection is in progress
			Case $ERROR_IO_PENDING
				DebugLog("ConnectClient .....: Pending", @ScriptName, @ScriptLineNumber)
				; Client is already connected, so signal an event
			Case $ERROR_PIPE_CONNECTED
				DebugLog("ConnectClient .....: Connected", @ScriptName, @ScriptLineNumber)
				$g_iState = 1
				If Not _WinAPI_SetEvent(DllStructGetData($g_tOverlap, "hEvent")) Then
					ErrorLog("ConnectClient .....: SetEvent failed", @ScriptName, @ScriptLineNumber)
				EndIf
				; Error occurred during the connection event
			Case Else
				ErrorLog("ConnectClient .....: ConnectNamedPipe 2 failed", @ScriptName, @ScriptLineNumber)
		EndSwitch
	EndIf
EndFunc   ;==>ConnectClient


; ===============================================================================================================================
; This function is called when an error occurs or when the client closes its handle to the pipe
; ===============================================================================================================================
Func ReconnectClient()
	; Disconnect the pipe instance
	If Not _NamedPipes_DisconnectNamedPipe($pipeHandle) Then
		ErrorLog("ReconnectClient ...: DisonnectNamedPipe failed", @ScriptName, @ScriptLineNumber)
		Return
	EndIf

	; Connect to a new client
	ConnectClient()
EndFunc   ;==>ReconnectClient



; ===============================================================================================================================
; This function relays the console output back to the client
; ===============================================================================================================================
Func RelayOutput($msg)
	Local $pBuffer, $tBuffer, $sLine, $bSuccess, $iWritten

    $iBuffer = StringLen($sMessage) + 1
	$tBuffer = DllStructCreate("char Text[" & $iBuffer & "]")
	$pBuffer = DllStructGetPtr($tBuffer)

	; Get the data and strip out the extra carriage returns
	$sLine = StringLeft(DllStructGetData($msg, "Text"), $iBuffer)
	$sLine = StringReplace($sLine, @CR & @CR, @CR)
	$g_iToWrite = StringLen($sLine)
	DllStructSetData($tBuffer, "Text", $sLine)
	; Relay the data back to the client
	$bSuccess = _WinAPI_WriteFile($pipeHandle, $pBuffer, $g_iToWrite, $iWritten, $g_pOverlap)
	If $bSuccess And ($iWritten = $g_iToWrite) Then
		DebugLog("RelayOutput .......: Write success", @ScriptName, @ScriptLineNumber)
	Else
		If Not $bSuccess And (_WinAPI_GetLastError() = $ERROR_IO_PENDING) Then
			DebugLog("RelayOutput .......: Write pending", @ScriptName, @ScriptLineNumber)
			$g_iState = 0
		Else
			; An error occurred, disconnect from the client
			ErrorLog("RelayOutput .......: Write failed", @ScriptName, @ScriptLineNumber)
			ReconnectClient()
		EndIf
	EndIf
EndFunc   ;==>RelayOutput
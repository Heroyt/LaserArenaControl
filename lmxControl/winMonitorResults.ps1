# Check parameter definition 
if ($args.count < 3)
{
    write-host "Missing arguments. Expected 2 arguments: results directory, valid server URI, server result path"
    return 1
}

# SET FOLDER TO WATCH + FILES TO WATCH + SUBFOLDERS YES/NO
$Path = $args[0]
$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $Path
$watcher.Filter = "*.*"
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $true

# DEFINE ACTIONS AFTER AN EVENT IS DETECTED
$action = {
    $Form = @{
        dir = $args[2]
    }
    write-host "Sending a change request"
    try
    {
        $response = nvoke-WebRequest -URI $args[1] -Method Post -Form $Form
        $StatusCode = $Response.StatusCode
        $Message = $Response.Content
    }
    catch
    {
        $StatusCode = $_.Exception.Response.StatusCode.value__
        $Message = ""
    }
    write-host "Server responded with status code: $StatusCode"
    write-host "Body: $Message"
}

# DECIDE WHICH EVENTS SHOULD BE WATCHED
Register-ObjectEvent $watcher "Created" -Action $action
Register-ObjectEvent $watcher "Changed" -Action $action
Register-ObjectEvent $watcher "Deleted" -Action $action
Register-ObjectEvent $watcher "Renamed" -Action $action

write-host "Starting to watch for changes in $Path"
while ($true)
{
    sleep 5
}
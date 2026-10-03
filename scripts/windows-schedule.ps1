# Registers the analyzer's two runs in Windows Task Scheduler (ADR-0002: Windows owns the clock,
# because WSL stops when the host sleeps). Run once from PowerShell as the owner:
#
#   powershell -ExecutionPolicy Bypass -File \\wsl$\<distro>\home\agent\development\trend-analyzer\scripts\windows-schedule.ps1 -Distro <distro>
#
# StartWhenAvailable runs a missed trigger on wake; both runs heal missed days and weeks themselves.
param(
    [Parameter(Mandatory = $true)][string]$Distro,
    [string]$AppPath = '/home/agent/development/trend-analyzer'
)

function Register-TrendRun([string]$Name, [string]$Command, $Trigger) {
    $log = $Command -replace ':', '-'
    $bash = "cd $AppPath && . ~/.config/php-env.sh && php artisan $Command >> storage/logs/$log.log 2>&1"
    $action = New-ScheduledTaskAction -Execute 'wsl.exe' -Argument "-d $Distro -- bash -lc `"$bash`""
    $settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopIfGoingOnBatteries -AllowStartIfOnBatteries
    Register-ScheduledTask -TaskName $Name -Action $action -Trigger $Trigger -Settings $settings -Force | Out-Null
    Write-Host "Registered $Name"
}

Register-TrendRun 'trend-analyzer daily' 'trends:daily' (New-ScheduledTaskTrigger -Daily -At 06:00)
Register-TrendRun 'trend-analyzer weekly' 'trends:weekly' (New-ScheduledTaskTrigger -Weekly -DaysOfWeek Monday -At 07:00)

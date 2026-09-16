# Auto-commit + push Rezera changes to GitHub.
# - stop: always sync when agent finishes (if there are changes)
# - edit: debounced sync after file edits (default 3 minutes)

param(
    [ValidateSet('stop', 'edit', 'manual')]
    [string]$Reason = 'manual'
)

$ErrorActionPreference = 'Continue'

# Consume hook JSON from stdin (required by Cursor hooks protocol).
try { [void][Console]::In.ReadToEnd() } catch {}

function Write-HookOk {
    Write-Output '{}'
    exit 0
}

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $repoRoot

$git = Get-Command git -ErrorAction SilentlyContinue
if (-not $git) { Write-HookOk }

# Ensure we are inside this repo and origin exists.
$inside = & git rev-parse --is-inside-work-tree 2>$null
if ($inside -ne 'true') { Write-HookOk }

$remote = & git remote get-url origin 2>$null
if (-not $remote) { Write-HookOk }

$debounceSeconds = 180
$stampFile = Join-Path $PSScriptRoot '.last-sync'
$lockFile = Join-Path $PSScriptRoot '.sync.lock'

if ($Reason -eq 'edit' -and (Test-Path $stampFile)) {
    try {
        $last = Get-Item $stampFile
        $age = (Get-Date) - $last.LastWriteTime
        if ($age.TotalSeconds -lt $debounceSeconds) {
            Write-HookOk
        }
    } catch {}
}

# Simple lock to avoid overlapping syncs.
if (Test-Path $lockFile) {
    try {
        $lockAge = (Get-Date) - (Get-Item $lockFile).LastWriteTime
        if ($lockAge.TotalSeconds -lt 120) { Write-HookOk }
    } catch {}
}
Set-Content -Path $lockFile -Value (Get-Date).ToString('o') -Encoding utf8

try {
    & git add -A 2>$null

    # Never commit secrets even if somehow staged.
    $blocked = @(
        '\.env$',
        '\.env\.backup$',
        '\.env\.production$',
        'firebase-credentials\.json$',
        'fcm-service-account\.json$',
        'auth\.json$',
        'credentials\.json$'
    )
    $staged = & git diff --cached --name-only 2>$null
    foreach ($f in $staged) {
        foreach ($pat in $blocked) {
            if ($f -match $pat) {
                & git reset HEAD -- $f 2>$null | Out-Null
            }
        }
    }

    $pending = & git status --porcelain 2>$null
    $ahead = 0
    try {
        $counts = & git rev-list --left-right --count 'origin/master...HEAD' 2>$null
        if ($counts) {
            $parts = ($counts -split '\s+')
            if ($parts.Count -ge 2) { $ahead = [int]$parts[1] }
        }
    } catch {}

    if (-not $pending -and $ahead -le 0) {
        Write-HookOk
    }

    if ($pending) {
        $stamp = Get-Date -Format 'yyyy-MM-dd HH:mm'
        $msg = "chore: auto-sync ($Reason) $stamp"
        & git commit -m $msg 2>$null | Out-Null
    }

    # Push if we have commits ahead of origin (or just committed).
    & git push origin HEAD 2>$null | Out-Null
    Set-Content -Path $stampFile -Value (Get-Date).ToString('o') -Encoding utf8
}
finally {
    Remove-Item $lockFile -Force -ErrorAction SilentlyContinue
}

Write-HookOk

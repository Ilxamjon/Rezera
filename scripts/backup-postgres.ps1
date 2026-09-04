# Daily PostgreSQL backup for Rezera (Windows / local ops).
# Example:
#   $env:PGPASSWORD='secret'
#   .\scripts\backup-postgres.ps1

param(
    [string]$DbHost = $(if ($env:DB_HOST) { $env:DB_HOST } else { '127.0.0.1' }),
    [string]$DbPort = $(if ($env:DB_PORT) { $env:DB_PORT } else { '5432' }),
    [string]$DbName = $(if ($env:DB_NAME) { $env:DB_NAME } else { 'rezera' }),
    [string]$DbUser = $(if ($env:DB_USER) { $env:DB_USER } else { 'rezera' }),
    [string]$BackupDir = $(if ($env:BACKUP_DIR) { $env:BACKUP_DIR } else { '.\storage\backups' }),
    [int]$RetentionDays = $(if ($env:RETENTION_DAYS) { [int]$env:RETENTION_DAYS } else { 14 })
)

New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
$stamp = (Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssZ')
$file = Join-Path $BackupDir "${DbName}_${stamp}.dump"

& pg_dump -h $DbHost -p $DbPort -U $DbUser -Fc -f $file $DbName
Write-Host "Wrote $file"

Get-ChildItem -Path $BackupDir -Filter "${DbName}_*.dump" |
    Where-Object { $_.LastWriteTimeUtc -lt (Get-Date).ToUniversalTime().AddDays(-$RetentionDays) } |
    Remove-Item -Force
Write-Host "Retention: removed dumps older than $RetentionDays days"
